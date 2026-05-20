<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OCC-Befehl für die manuelle Synchronisation.
 *
 * Verwendung:
 *   php occ parlwin:sync
 *   php occ parlwin:sync --nur-geschaefte
 *   php occ parlwin:sync --nur-sitzungen
 *   php occ parlwin:sync --nur-mitglieder
 */
class SyncCommand extends Command
{
    private const APP_ID = 'parlwin';
    protected static $defaultName = 'parlwin:sync';
    private const SYNC_PROGRESS_KEY = 'sync_progress';
    public const SYNC_CANCEL_REQUESTED_KEY = 'sync_cancel_requested';
    public const SYNC_WORKER_PID_KEY = 'sync_worker_pid';
    private const SECTION_META = [
        'mitglieder' => ['label' => 'Mitglieder', 'db' => 'pw_mitglieder'],
        'fraktionen' => ['label' => 'Fraktionen', 'db' => 'pw_fraktionen'],
        'kommissionen' => ['label' => 'Kommissionen', 'db' => 'pw_kommissionen'],
        'geschaefte' => ['label' => 'Geschäfte', 'db' => 'pw_geschaefte (+ pw_geschaeft_ereignisse)'],
        'sitzungen' => ['label' => 'Sitzungen', 'db' => 'pw_sitzungen (+ pw_traktanden)'],
    ];

    public function __construct(
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly KalenderService $kalenderService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly ScraperService $scraperService,
        private readonly SyncLockService $syncLockService,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly IConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronisiert Daten vom Parlament Winterthur')
            ->addOption('nur-geschaefte', null, InputOption::VALUE_NONE, 'Nur Geschäfte synchronisieren')
            ->addOption('nur-sitzungen', null, InputOption::VALUE_NONE, 'Nur Sitzungen synchronisieren')
            ->addOption('nur-mitglieder', null, InputOption::VALUE_NONE, 'Nur Mitglieder/Fraktionen/Kommissionen synchronisieren')
            ->addOption('update-progress', null, InputOption::VALUE_NONE, 'Schreibt Live-Fortschritt für den Admin-Dialog')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Quelle des Sync-Starts (occ/admin-ui/background-job)', 'occ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        $nurGeschaefte = $input->getOption('nur-geschaefte');
        $nurSitzungen = $input->getOption('nur-sitzungen');
        $nurMitglieder = $input->getOption('nur-mitglieder');
        $updateProgress = (bool) $input->getOption('update-progress');
        $source = trim((string) $input->getOption('source'));
        if ($source === '') {
            $source = 'occ';
        }

        if (!$this->syncLockService->acquire()) {
            $output->writeln('<comment>Synchronisation läuft bereits, zweiter Start wird angehängt.</comment>');
            return Command::SUCCESS;
        }

        try {
            $this->setCurrentWorkerPid(getmypid() ?: null);
            $this->setCancelRequested(false);
            $alles = !$nurGeschaefte && !$nurSitzungen && !$nurMitglieder;
            $startZeitpunkt = new \DateTimeImmutable();
            $aktiveScopes = [];
            if ($alles || $nurMitglieder) {
                $aktiveScopes = array_merge($aktiveScopes, ['mitglieder', 'fraktionen', 'kommissionen']);
            }
            if ($alles || $nurGeschaefte) {
                $aktiveScopes[] = 'geschaefte';
            }
            if ($alles || $nurSitzungen) {
                $aktiveScopes[] = 'sitzungen';
            }
            $aktiveScopes = array_values(array_unique($aktiveScopes));
            $status = $this->initialisiereProgressStatus($startZeitpunkt, $aktiveScopes, $source);
            if ($updateProgress) {
                $this->setProgressStatus($status);
            }

            $output->writeln('<info>Parlament Winterthur: Starte Synchronisation...</info>');
            $mitgliederStatistik = null;
            $geschaefteStatistik = null;
            $sitzungenStatistik = null;

            try {
                $prefetchBereiche = [];
                if ($alles || $nurGeschaefte) {
                    $prefetchBereiche[] = 'geschaefte';
                }
                if ($alles || $nurSitzungen) {
                    $prefetchBereiche[] = 'sitzungen';
                }
                if ($alles || $nurMitglieder) {
                    $prefetchBereiche = array_merge($prefetchBereiche, ['mitglieder', 'kommissionen', 'fraktionen']);
                }
                $this->scraperService->prefetchTopLevelListen(array_values(array_unique($prefetchBereiche)));
                if ($updateProgress) {
                    $vorabTotals = $this->scraperService->vorabTotalsFuerSync();
                    foreach ($vorabTotals as $scope => $total) {
                        if (!isset($status['sections'][$scope]) || !is_array($status['sections'][$scope])) {
                            continue;
                        }
                        $status['sections'][$scope]['total'] = max(0, (int) $total);
                    }
                    $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                    $this->setProgressStatus($status);
                }

                $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);

                $fortschritt = function (array $event) use (&$status, $updateProgress, $startZeitpunkt, $source): void {
                    if ($updateProgress) {
                        $this->aktualisiereProgressStatus($status, $event);
                        $this->setProgressStatus($status);
                    }
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                };

                if ($alles || $nurMitglieder) {
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                    $output->writeln('  Synchronisiere Mitglieder, Fraktionen und Kommissionen...');
                    $mitgliederStatistik = $this->mitgliedService->synchronisieren($fortschritt);
                    $output->writeln(sprintf(
                        '  Mitglieder: %d neu, %d aktualisiert, %d inaktiv',
                        $mitgliederStatistik['mitglieder']['neu'],
                        $mitgliederStatistik['mitglieder']['aktualisiert'],
                        $mitgliederStatistik['mitglieder']['inaktiv'],
                    ));
                }

                if ($alles || $nurGeschaefte) {
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                    $output->writeln('  Synchronisiere Geschäfte...');
                    $geschaefteStatistik = $this->geschaeftService->synchronisieren($fortschritt);
                    $output->writeln(sprintf(
                        '  Geschäfte: %d neu, %d aktualisiert, %d als gelöscht markiert',
                        $geschaefteStatistik['neu'],
                        $geschaefteStatistik['aktualisiert'],
                        $geschaefteStatistik['geloescht'],
                    ));
                }

                if ($alles || $nurSitzungen) {
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                    $output->writeln('  Synchronisiere Sitzungen und Traktanden...');
                    $sitzungenStatistik = $this->sitzungService->synchronisieren($fortschritt);
                    $output->writeln(sprintf(
                        '  Sitzungen: %d neu, %d aktualisiert, %d als gelöscht markiert',
                        $sitzungenStatistik['neu'],
                        $sitzungenStatistik['aktualisiert'],
                        $sitzungenStatistik['geloescht'],
                    ));

                    $output->writeln('  Aktualisiere Kalendereinträge...');
                    $this->kalenderService->sitzungenAktualisieren(
                        $this->sitzungService->alleAktiven()
                    );
                }

                $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
                $this->config->setAppValue(self::APP_ID, 'letzte_synchronisation', $jetzt);

                // Automatische Zuständigkeit setzen: Geschäfte ohne aktive
                // Zuweisung, die laut Status bei einer bekannten Kommission
                // hängig sind, bekommen die Kommissionsmitglieder der
                // eigenen Fraktion zugewiesen.  Identisch zum Admin-UI-Pfad
                // in SettingsController::starteSyncProzess().
                $autoZustaendigkeit = null;
                try {
                    $autoZustaendigkeit = $this->fraktionsarbeitService->autoZuweisenKommissionsmitglieder();
                    $output->writeln(sprintf(
                        '  Auto-Zuständigkeit: %d geprüft, %d zugewiesen, %d übersprungen, %d ohne Kommission, %d ohne passendes Mitglied',
                        $autoZustaendigkeit['gepruet'],
                        $autoZustaendigkeit['zugewiesen'],
                        $autoZustaendigkeit['uebersprungen'],
                        $autoZustaendigkeit['ohne_kommission'],
                        $autoZustaendigkeit['ohne_passendes_mitglied']
                    ));
                } catch (\Throwable $e) {
                    $autoZustaendigkeit = ['fehler' => $e->getMessage()];
                    $output->writeln('<comment>Auto-Zuständigkeit fehlgeschlagen: ' . $e->getMessage() . '</comment>');
                }

                $statistik = [
                    'mitglieder' => $mitgliederStatistik,
                    'geschaefte' => $geschaefteStatistik,
                    'sitzungen' => $sitzungenStatistik,
                    'auto_zustaendigkeit' => $autoZustaendigkeit,
                ];
                $this->realtimePublisher->publish('sync.completed', [
                    'quelle' => $source,
                    'zeitpunkt' => $jetzt,
                    'nurGeschaefte' => $nurGeschaefte,
                    'nurSitzungen' => $nurSitzungen,
                    'nurMitglieder' => $nurMitglieder,
                    'statistik' => $statistik,
                ]);

                if ($updateProgress) {
                    $status['running'] = false;
                    $status['phase'] = 'abgeschlossen';
                    $status['phaseLabel'] = 'Synchronisation abgeschlossen';
                    $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                    $status['updatedAt'] = $status['finishedAt'];
                    $status['elapsed'] = self::formatiereDauer(
                        max(0, time() - $startZeitpunkt->getTimestamp())
                    );
                    $status['eta'] = '00:00:00';
                    $status['error'] = null;
                    $status['current'] = [
                        'scope' => 'done',
                        'label' => 'Synchronisation abgeschlossen',
                        'db' => '-',
                        'processed' => 0,
                        'total' => 0,
                        'cursor' => '',
                    ];
                    $status['statistik'] = $statistik;
                    $this->setProgressStatus($status);
                }

                $output->writeln("<info>Synchronisation abgeschlossen um {$jetzt}</info>");
                return Command::SUCCESS;
            } catch (SyncAbortedException) {
                $output->writeln('<comment>Synchronisation wurde auf Anfrage abgebrochen.</comment>');
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                if ($updateProgress) {
                    $status['running'] = false;
                    $status['phase'] = 'fehler';
                    $status['phaseLabel'] = 'Synchronisation fehlgeschlagen';
                    $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                    $status['updatedAt'] = $status['finishedAt'];
                    $status['elapsed'] = self::formatiereDauer(
                        max(0, time() - $startZeitpunkt->getTimestamp())
                    );
                    $status['eta'] = '--:--:--';
                    $status['error'] = $e->getMessage();
                    $status['current'] = [
                        'scope' => 'error',
                        'label' => 'Fehler',
                        'db' => '-',
                        'processed' => 0,
                        'total' => 0,
                        'cursor' => '',
                    ];
                    $this->setProgressStatus($status);
                }
                $output->writeln('<error>Synchronisation fehlgeschlagen: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        } finally {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            $this->syncLockService->release();
        }
    }

    /**
     * @param array<string, mixed> $status
     */
    private function throwIfCancelRequested(array &$status, \DateTimeImmutable $startZeitpunkt, string $source, bool $updateProgress): void
    {
        if (!$this->isCancelRequested()) {
            return;
        }

        if ($updateProgress) {
            $status['running'] = false;
            $status['phase'] = 'abgebrochen';
            $status['phaseLabel'] = 'Synchronisation abgebrochen';
            $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $status['updatedAt'] = $status['finishedAt'];
            $status['elapsed'] = self::formatiereDauer(
                max(0, time() - $startZeitpunkt->getTimestamp())
            );
            $status['eta'] = '--:--:--';
            $status['error'] = 'Synchronisation wurde manuell abgebrochen';
            $status['current'] = [
                'scope' => 'abgebrochen',
                'label' => 'Abgebrochen',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
            $this->setProgressStatus($status);
        }

        $this->realtimePublisher->publish('sync.cancelled', [
            'quelle' => $source,
            'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
        throw new SyncAbortedException('Synchronisation wurde manuell abgebrochen');
    }

    /**
     * @param array<int, string> $aktiveScopes
     * @return array<string, mixed>
     */
    private function initialisiereProgressStatus(\DateTimeImmutable $startZeitpunkt, array $aktiveScopes, string $source): array
    {
        $sections = [];
        foreach (self::SECTION_META as $scope => $meta) {
            $sections[$scope] = [
                'label' => $meta['label'],
                'db' => $meta['db'],
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
                'aktiv' => in_array($scope, $aktiveScopes, true),
            ];
        }

        return [
            'running' => true,
            'phase' => 'running',
            'phaseLabel' => 'Synchronisation läuft',
            'startedAt' => $startZeitpunkt->format(DATE_ATOM),
            'finishedAt' => null,
            'updatedAt' => $startZeitpunkt->format(DATE_ATOM),
            'elapsed' => '00:00:00',
            'eta' => '--:--:--',
            'error' => null,
            'source' => $source,
            'current' => [
                'scope' => 'queued',
                'label' => 'Synchronisation initialisiert',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @param array<string, mixed> $status
     * @param array<string, mixed> $event
     */
    private function aktualisiereProgressStatus(array &$status, array $event): void
    {
        $scope = (string) ($event['scope'] ?? '');
        if ($scope === '' || !isset(self::SECTION_META[$scope])) {
            return;
        }
        if (!isset($status['sections'][$scope]) || !is_array($status['sections'][$scope])) {
            return;
        }

        $processed = max(0, (int) ($event['processed'] ?? 0));
        $total = max(0, (int) ($event['total'] ?? 0));
        $cursor = trim((string) ($event['cursor'] ?? ''));
        if ($total > 0) {
            $processed = min($processed, $total);
        }

        $status['sections'][$scope]['processed'] = $processed;
        $status['sections'][$scope]['total'] = $total;
        if ($cursor !== '') {
            $status['sections'][$scope]['cursor'] = $cursor;
        }
        $status['sections'][$scope]['aktiv'] = true;
        $status['current'] = [
            'scope' => $scope,
            'label' => self::SECTION_META[$scope]['label'],
            'db' => self::SECTION_META[$scope]['db'],
            'processed' => $processed,
            'total' => $total,
            'cursor' => $cursor,
        ];
        $status['phase'] = 'running';
        $status['phaseLabel'] = 'Synchronisiere ' . self::SECTION_META[$scope]['label'];
        $status['error'] = null;

        $startedAt = \DateTimeImmutable::createFromFormat(DATE_ATOM, (string) ($status['startedAt'] ?? ''));
        $elapsedSekunden = max(0, time() - (($startedAt ?: new \DateTimeImmutable())->getTimestamp()));
        $status['elapsed'] = self::formatiereDauer($elapsedSekunden);

        [$globalProcessed, $globalTotal] = $this->berechneGlobalenFortschritt($status);
        $etaSekunden = null;
        if ($globalTotal > 0 && $globalProcessed > 0 && $globalProcessed < $globalTotal && $elapsedSekunden >= 5 && $globalProcessed >= 5) {
            $etaSekunden = (int) round(($elapsedSekunden / $globalProcessed) * ($globalTotal - $globalProcessed));
        } elseif ($total > 0 && $processed > 0 && $processed < $total && $elapsedSekunden >= 5) {
            $etaSekunden = (int) round(($elapsedSekunden / $processed) * ($total - $processed));
        }

        if ($globalTotal > 0 && $globalProcessed >= $globalTotal) {
            $status['eta'] = '00:00:00';
        } elseif ($etaSekunden !== null && $etaSekunden >= 0) {
            $status['eta'] = self::formatiereDauer($etaSekunden);
        } else {
            $status['eta'] = '--:--:--';
        }

        $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
    }

    /**
     * @param array<string, mixed> $status
     * @return array{0: int, 1: int}
     */
    private function berechneGlobalenFortschritt(array $status): array
    {
        $processed = 0;
        $total = 0;
        $sections = $status['sections'] ?? [];
        if (!is_array($sections)) {
            return [0, 0];
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (($section['aktiv'] ?? true) !== true) {
                continue;
            }

            $sectionProcessed = max(0, (int) ($section['processed'] ?? 0));
            $sectionTotal = max(0, (int) ($section['total'] ?? 0));
            if ($sectionTotal <= 0) {
                continue;
            }

            $processed += min($sectionProcessed, $sectionTotal);
            $total += $sectionTotal;
        }

        return [$processed, $total];
    }

    /**
     * @param array<string, mixed> $status
     */
    private function setProgressStatus(array $status): void
    {
        $json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $this->config->setAppValue(self::APP_ID, self::SYNC_PROGRESS_KEY, $json);
        $this->realtimePublisher->publish('sync.progress', $status);
    }

    private function isCancelRequested(): bool
    {
        return trim($this->config->getAppValue(self::APP_ID, self::SYNC_CANCEL_REQUESTED_KEY, '0')) === '1';
    }

    private function setCancelRequested(bool $requested): void
    {
        $this->config->setAppValue(self::APP_ID, self::SYNC_CANCEL_REQUESTED_KEY, $requested ? '1' : '0');
    }

    private function setCurrentWorkerPid(?int $pid): void
    {
        $this->config->setAppValue(
            self::APP_ID,
            self::SYNC_WORKER_PID_KEY,
            ($pid !== null && $pid > 1) ? (string) $pid : ''
        );
    }

    private static function formatiereDauer(int $sekunden): string
    {
        $sekunden = max(0, $sekunden);
        $h = intdiv($sekunden, 3600);
        $m = intdiv($sekunden % 3600, 60);
        $s = $sekunden % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

class SyncAbortedException extends \RuntimeException
{
}
