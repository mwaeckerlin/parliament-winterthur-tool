<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\SyncZeitplan;
use OCA\ParliamentWinterthur\Service\VorstossImportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Automatische Synchronisation der Parlamentsdaten nach Zeitplan.
 *
 * Es gilt der konfigurierbare Zeitplan `sync_zeitplan` (Einträge mit Wochentagen
 * und Uhrzeit, siehe SyncZeitplan). Ist keiner konfiguriert, gilt der Standard:
 * zwei Läufe an allen Wochentagen um 10:00 und 18:00 Uhr (SyncZeitplan::standard).
 */
class SyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly SyncCommand $syncCommand,
        private readonly LoggerInterface $logger,
        private readonly FraktionsraumService $fraktionsraumService,
        private readonly IConfig $config,
        private readonly VorstossImportService $vorstossImport,
        private readonly BudgetImportService $budgetImport,
    ) {
        parent::__construct($time);
        // Kurzes Intervall: die Fälligkeit entscheidet der Zeitplan, nicht das
        // Job-Intervall — so sind auch minutengenaue Zeitplan-Punkte möglich.
        $this->setInterval(300);
        $this->setTimeSensitivity(self::TIME_SENSITIVE);
    }

    /** Aktuelle Zeit als Timestamp — für Tests überschreibbar. */
    protected function jetztTs(): int {
        return time();
    }

    /**
     * Entscheidet, ob JETZT ein automatischer Lauf fällig ist, und vermerkt den
     * Prüfzeitpunkt (verhindert Doppelläufe und holt verpasste Zeitplan-Punkte
     * nach einem Ausfall nach). Ohne konfigurierten Zeitplan gilt der Standard.
     */
    protected function istFaellig(): bool {
        $jetzt = $this->jetztTs();
        $plan = SyncZeitplan::mitStandard(
            (string) $this->config->getAppValue(Application::APP_ID, 'sync_zeitplan', '[]')
        );
        $letzter = (int) $this->config->getAppValue(Application::APP_ID, 'sync_zeitplan_letzter_check', '0');
        $this->config->setAppValue(Application::APP_ID, 'sync_zeitplan_letzter_check', (string) $jetzt);
        // Erste Prüfung nach Aktivierung: nur initialisieren, nichts nachholen.
        if ($letzter === 0) {
            return false;
        }
        return SyncZeitplan::faellig($plan, $letzter, $jetzt);
    }

    protected function run(mixed $argument): void {
        if (!$this->istFaellig()) {
            return;
        }

        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->fraktionsraumService->sicherstellen();

        $importiert = $this->vorstossImport->importiere();
        if ($importiert > 0) {
            $this->logger->info('Parlament Winterthur: ' . $importiert . ' Vorstösse aus 40_Vorstösse übernommen');
        }

        // Budget: neues Budgetjahr automatisch einlesen, sobald die Weisung
        // vorliegt, und einen vorhandenen Novemberbrief nachziehen (F89).
        try {
            $budget = $this->budgetImport->automatischerImport();
            if ($budget !== null && ($budget['importiert'] || $budget['novemberbrief'])) {
                $this->logger->info(
                    'Parlament Winterthur: Budgetjahr ' . $budget['jahr'] . ' automatisch eingelesen'
                    . ($budget['novemberbrief'] ? ' (inkl. Novemberbrief)' : '')
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler beim automatischen Budget-Import: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        $this->logger->info('Parlament Winterthur: Starte Datensynchronisation (BackgroundJob)');

        try {
            $input = new ArrayInput([
                '--update-progress' => true,
                '--source' => 'background-job',
            ]);
            $input->setInteractive(false);
            $exitCode = $this->syncCommand->run($input, new NullOutput());
            if ($exitCode !== 0) {
                throw new \RuntimeException('SyncCommand lieferte Exit-Code ' . $exitCode);
            }

            $this->logger->info('Parlament Winterthur: Datensynchronisation (BackgroundJob) erfolgreich abgeschlossen');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler bei der Datensynchronisation (BackgroundJob): ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
