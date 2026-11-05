<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCP\IConfig;
use Symfony\Component\Console\Attribute\AsCommand;
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
 *   php occ parlwin:sync --nur-stadtratsbeschluesse
 */
#[AsCommand(name: 'parlwin:sync')]
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
        'stadtratsbeschluesse' => ['label' => 'Stadtratsbeschlüsse', 'db' => 'pw_stadtratsbeschluesse'],
    ];

    /** @var array<string, mixed>|null die Statistik der Geschäfte dieses Laufs, `null`, bis ihr Abgleich fertig ist */
    private ?array $geschaefteStatistik = null;

    public function __construct(
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly StadtratsbeschlussService $stadtratsbeschlussService,
        private readonly KalenderService $kalenderService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly ScraperService $scraperService,
        private readonly SyncLockService $syncLockService,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly IConfig $config,
        private readonly EreignisService $ereignisse,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronisiert Daten vom Parlament Winterthur')
            ->addOption('nur-geschaefte', null, InputOption::VALUE_NONE, 'Nur Geschäfte synchronisieren')
            ->addOption('nur-sitzungen', null, InputOption::VALUE_NONE, 'Nur Sitzungen synchronisieren')
            ->addOption('nur-mitglieder', null, InputOption::VALUE_NONE, 'Nur Mitglieder/Fraktionen/Kommissionen synchronisieren')
            ->addOption('nur-stadtratsbeschluesse', null, InputOption::VALUE_NONE, 'Nur Stadtratsbeschlüsse synchronisieren')
            ->addOption('update-progress', null, InputOption::VALUE_NONE, 'Schreibt Live-Fortschritt für den Admin-Dialog')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Quelle des Sync-Starts (occ/admin-ui/background-job)', 'occ')
            ->addOption('ausgeloest-von', null, InputOption::VALUE_REQUIRED, 'Benutzer-ID, die den Lauf ausgelöst hat, für das Protokoll', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        $nurGeschaefte = $input->getOption('nur-geschaefte');
        $nurSitzungen = $input->getOption('nur-sitzungen');
        $nurMitglieder = $input->getOption('nur-mitglieder');
        $nurBeschluesse = $input->getOption('nur-stadtratsbeschluesse');
        $updateProgress = (bool) $input->getOption('update-progress');
        $source = trim((string) $input->getOption('source'));
        if ($source === '') {
            $source = 'occ';
        }
        $ausgeloestVon = mb_substr(trim((string) $input->getOption('ausgeloest-von')), 0, 64);

        if (!$this->syncLockService->acquire()) {
            $output->writeln('<comment>Synchronisation läuft bereits, zweiter Start wird angehängt.</comment>');
            return Command::SUCCESS;
        }

        try {
            $this->setCurrentWorkerPid(getmypid() ?: null);
            // Ein Abbruch-Signal, das NACH dem Start dieses Prozesses geschrieben
            // wurde, gilt diesem Lauf: Die Oberfläche meldet «gestartet», sobald
            // der Prozess gestartet ist, und lässt den Abbruch zu — bis die Sperre
            // hier greift, vergehen ein bis zwei Sekunden. Nur ein älteres Signal
            // ist das Überbleibsel eines hart beendeten Laufs und wird weggeräumt.
            $signalZeit = $this->syncLockService->abbruchZeitpunkt();
            if ($signalZeit === null || $signalZeit < self::prozessStart()) {
                $this->setCancelRequested(false);
            }
            $alles = !$nurGeschaefte && !$nurSitzungen && !$nurMitglieder && !$nurBeschluesse;
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
            if ($alles || $nurBeschluesse) {
                $aktiveScopes[] = 'stadtratsbeschluesse';
            }
            $aktiveScopes = array_values(array_unique($aktiveScopes));
            $fortsetzen = $updateProgress ? $this->fortsetzungspunkte() : [];
            $status = $this->initialisiereProgressStatus($startZeitpunkt, $aktiveScopes, $source, $fortsetzen);
            if ($updateProgress) {
                $this->setProgressStatus($status);
            }

            $output->writeln('<info>Parlament Winterthur: Starte Synchronisation...</info>');
            $mitgliederStatistik = null;
            $this->geschaefteStatistik = null;
            $sitzungenStatistik = null;
            $beschluesseStatistik = null;

            try {
                // Vor der ersten Netzarbeit: Ein Lauf, der schon beim Start
                // abgebrochen wurde, lädt die Parlamentswebseite nicht mehr ab.
                $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);

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
                    $mitgliederStatistik = $this->mitgliedService->synchronisieren($fortschritt, [
                        'mitglieder' => ['resume_cursor' => $fortsetzen['mitglieder'] ?? ''],
                        'fraktionen' => ['resume_cursor' => $fortsetzen['fraktionen'] ?? ''],
                        'kommissionen' => ['resume_cursor' => $fortsetzen['kommissionen'] ?? ''],
                    ]);
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
                    $this->geschaefteStatistik = $this->geschaeftService->synchronisieren($fortschritt, ['resume_cursor' => $fortsetzen['geschaefte'] ?? '']);
                    $output->writeln(sprintf(
                        '  Geschäfte: %d neu, %d aktualisiert, %d als gelöscht markiert',
                        $this->geschaefteStatistik['neu'],
                        $this->geschaefteStatistik['aktualisiert'],
                        $this->geschaefteStatistik['geloescht'],
                    ));
                }

                if ($alles || $nurSitzungen) {
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                    $output->writeln('  Synchronisiere Sitzungen und Traktanden...');
                    $sitzungenStatistik = $this->sitzungService->synchronisieren($fortschritt, ['resume_cursor' => $fortsetzen['sitzungen'] ?? '']);
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

                if ($alles || $nurBeschluesse) {
                    $this->throwIfCancelRequested($status, $startZeitpunkt, $source, $updateProgress);
                    $output->writeln('  Synchronisiere Stadtratsbeschlüsse...');
                    $beschluesseStatistik = $this->stadtratsbeschlussService->synchronisierenMitFehlerInStatistik($fortschritt);
                    $output->writeln(sprintf(
                        '  Stadtratsbeschlüsse: %d gelesen, %d neu, %d aktualisiert, %d nicht gespeichert',
                        $beschluesseStatistik['gelesen'],
                        $beschluesseStatistik['neu'],
                        $beschluesseStatistik['aktualisiert'],
                        $beschluesseStatistik['fehler'],
                    ));
                    $beschluesseFehler = trim((string) ($beschluesseStatistik['fehlermeldung'] ?? ''));
                    if ($beschluesseFehler !== '') {
                        $output->writeln('<comment>  Stadtratsbeschlüsse: ' . $beschluesseFehler . '</comment>');
                        $status['sections']['stadtratsbeschluesse']['fehler'] = $beschluesseFehler;
                        if ($updateProgress) {
                            $this->setProgressStatus($status);
                        }
                    }
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
                    'geschaefte' => $this->geschaefteStatistik,
                    'sitzungen' => $sitzungenStatistik,
                    'stadtratsbeschluesse' => $beschluesseStatistik,
                    'auto_zustaendigkeit' => $autoZustaendigkeit,
                ];
                $this->realtimePublisher->publish('sync.completed', [
                    'quelle' => $source,
                    'zeitpunkt' => $jetzt,
                    'nurGeschaefte' => $nurGeschaefte,
                    'nurSitzungen' => $nurSitzungen,
                    'nurMitglieder' => $nurMitglieder,
                    'nurStadtratsbeschluesse' => $nurBeschluesse,
                    'statistik' => $statistik,
                    'geschaeftIds' => $this->geaenderteGeschaefte(),
                ]);
                $this->protokolliereLauf($source, $ausgeloestVon, true, self::syncFehlerzahl($statistik) === 0, self::syncZusammenfassung($statistik), $output);

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
                $this->protokolliereLauf($source, $ausgeloestVon, false, false, $e->getMessage(), $output);
                $this->realtimePublisher->publish('sync.failed', ['quelle' => $source, 'fehler' => $e->getMessage(), 'geschaeftIds' => $this->geaenderteGeschaefte()]);
                return Command::FAILURE;
            }
        } finally {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            $this->syncLockService->release();
        }
    }

    /**
     * Schreibt den Lauf ins Ereignis-Protokoll. Scheitert der Eintrag, endet der
     * Lauf trotzdem mit seinem eigenen Ergebnis. Ohne `$ausgeloestVon` setzt das
     * Protokoll den angemeldeten Benutzer oder «auto».
     */
    private function protokolliereLauf(string $source, string $ausgeloestVon, bool $abgeschlossen, bool $erfolg, string $meldung, OutputInterface $output): void
    {
        $automatisch = $source === 'background-job';
        $titel = ($automatisch ? 'Automatische Synchronisation ' : 'Synchronisation ')
            . ($abgeschlossen ? 'abgeschlossen' : 'fehlgeschlagen');
        if ($ausgeloestVon === '' && $automatisch) {
            $ausgeloestVon = 'auto';
        }
        try {
            $this->ereignisse->protokolliere($abgeschlossen ? 'sync' : 'fehler', '', $erfolg, $titel, $meldung, $ausgeloestVon);
        } catch (\Throwable $e) {
            $output->writeln('<comment>Eintrag im Protokoll fehlgeschlagen: ' . $e->getMessage() . '</comment>');
        }
    }

    /**
     * Die Zusammenfassung einer Synchronisation für das Protokoll: die Zähler
     * «neu» und «aktualisiert» über alle Bereiche, dazu was nicht gespeichert
     * und was nicht gelesen wurde.
     *
     * @param array<string, mixed> $statistik
     */
    public static function syncZusammenfassung(array $statistik): string
    {
        $summe = self::zaehleStatistik($statistik);
        $text = $summe['neu'] . ' neu, ' . $summe['geaendert'] . ' geändert';
        if ($summe['fehler'] > 0) {
            $text .= ', ' . $summe['fehler'] . ' nicht gespeichert';
            if ($summe['meldungen'] !== []) {
                $text .= ' (' . $summe['meldungen'][0] . ')';
            }
        }
        foreach ($summe['ausgefallen'] as $meldung) {
            $text .= ', nicht gelesen: ' . $meldung;
        }
        return $text;
    }

    /**
     * Wie viele Einträge nicht gespeichert und wie viele Bereiche nicht gelesen
     * wurden.
     *
     * @param array<string, mixed> $statistik
     */
    public static function syncFehlerzahl(array $statistik): int
    {
        $summe = self::zaehleStatistik($statistik);
        return $summe['fehler'] + count($summe['ausgefallen']);
    }

    /**
     * Summiert die Zähler über alle Bereiche. Eine Ebene mit eigenen Zählern
     * ist ein Blatt; ihre Unterebenen zählen nicht noch einmal.
     *
     * @param array<string, mixed> $statistik
     * @return array{neu: int, geaendert: int, fehler: int, meldungen: list<string>, ausgefallen: list<string>}
     */
    private static function zaehleStatistik(array $statistik): array
    {
        $summe = ['neu' => 0, 'geaendert' => 0, 'fehler' => 0, 'meldungen' => [], 'ausgefallen' => []];
        $sammle = static function ($wert) use (&$sammle, &$summe): void {
            if (!is_array($wert)) {
                return;
            }
            if (array_key_exists('neu', $wert) || array_key_exists('aktualisiert', $wert)) {
                $summe['neu'] += (int) ($wert['neu'] ?? 0);
                $summe['geaendert'] += (int) ($wert['aktualisiert'] ?? 0);
                $summe['fehler'] += (int) ($wert['fehler'] ?? 0);
                $meldung = trim((string) ($wert['fehlermeldung'] ?? ''));
                if ($meldung !== '' && ($wert['ausgefallen'] ?? false) === true) {
                    $summe['ausgefallen'][] = $meldung;
                } elseif ($meldung !== '') {
                    $summe['meldungen'][] = $meldung;
                }
                return;
            }
            foreach ($wert as $unter) {
                $sammle($unter);
            }
        };
        $sammle($statistik);
        return $summe;
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
            'geschaeftIds' => $this->geaenderteGeschaefte(),
        ]);
        throw new SyncAbortedException('Synchronisation wurde manuell abgebrochen');
    }

    /**
     * Die Geschäfte, die dieser Lauf bis jetzt angelegt, geändert oder
     * zugewiesen hat (F150), für `sync.completed`, `sync.cancelled` und
     * `sync.failed`; `null`, wenn er Geschäfte als gelöscht markiert hat.
     *
     * @return list<int>|null
     */
    private function geaenderteGeschaefte(): ?array
    {
        return RealtimePublisherService::geaenderteGeschaefte(
            $this->geschaefteStatistik,
            $this->geschaeftService->zuletztGeaenderteIds(),
            $this->fraktionsarbeitService->zuletztZugewieseneIds(),
        );
    }

    /**
     * Die Punkte, an denen ein fortgesetzter Lauf je Bereich weitermacht.
     * `SettingsController::run` legt sie im wartenden Stand (`queued`) ab, wenn
     * der vorige Lauf abgebrochen oder gescheitert ist.
     *
     * @return array<string, string>
     */
    private function fortsetzungspunkte(): array
    {
        $stand = json_decode($this->config->getAppValue(self::APP_ID, self::SYNC_PROGRESS_KEY, ''), true);
        if (!is_array($stand) || ($stand['phase'] ?? '') !== 'queued' || !is_array($stand['sections'] ?? null)) {
            return [];
        }
        $punkte = [];
        foreach (array_keys(self::SECTION_META) as $scope) {
            $cursor = trim((string) ($stand['sections'][$scope]['cursor'] ?? ''));
            if ($cursor !== '') {
                $punkte[$scope] = $cursor;
            }
        }
        return $punkte;
    }

    /**
     * @param array<int, string> $aktiveScopes
     * @param array<string, string> $fortsetzen
     * @return array<string, mixed>
     */
    private function initialisiereProgressStatus(\DateTimeImmutable $startZeitpunkt, array $aktiveScopes, string $source, array $fortsetzen = []): array
    {
        $sections = [];
        foreach (self::SECTION_META as $scope => $meta) {
            $sections[$scope] = [
                'label' => $meta['label'],
                'db' => $meta['db'],
                'processed' => 0,
                'total' => 0,
                'cursor' => $fortsetzen[$scope] ?? '',
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

    /**
     * Gelesen wird die Datei des Sperrdienstes, nicht die App-Konfiguration:
     * Nextcloud hält deren Werte pro Aufruf im Speicher, und der Sync ist EIN
     * Aufruf, während der Abbruch aus einem zweiten kommt.
     */
    private function isCancelRequested(): bool
    {
        return $this->syncLockService->abbruchAngefordert()
            || trim($this->config->getAppValue(self::APP_ID, self::SYNC_CANCEL_REQUESTED_KEY, '0')) === '1';
    }

    private function setCancelRequested(bool $requested): void
    {
        if ($requested) {
            $this->syncLockService->abbruchAnfordern();
        } else {
            $this->syncLockService->abbruchAufheben();
        }
        $this->config->setAppValue(self::APP_ID, self::SYNC_CANCEL_REQUESTED_KEY, $requested ? '1' : '0');
    }

    /**
     * Die Prozessnummer steht an ZWEI Stellen, und beide gehören zusammen: in der
     * App-Konfiguration und in der Datei neben der Sperre, aus der der Status sie
     * bevorzugt liest. Wer nur die Konfiguration aufräumt, lässt die Nummer in der
     * Datei stehen; vergibt der Container sie neu, meldet der Status für immer
     * «läuft», und der nächste Lauf hängt sich an eine Synchronisation an, die
     * längst fertig ist.
     */
    private function setCurrentWorkerPid(?int $pid): void
    {
        $this->syncLockService->pidSetzen($pid);
        $this->config->setAppValue(
            self::APP_ID,
            self::SYNC_WORKER_PID_KEY,
            ($pid !== null && $pid > 1) ? (string) $pid : ''
        );
    }

    /** Der Startzeitpunkt dieses Prozesses, so genau wie PHP ihn kennt. */
    private static function prozessStart(): float
    {
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        return is_numeric($start) ? (float) $start : microtime(true);
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
