<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Täglicher Hintergrund-Job zur Synchronisation der Parlamentsdaten.
 *
 * Dieser Job wird täglich ausgeführt und synchronisiert alle relevanten
 * Daten von https://parlament.winterthur.ch/ in die lokale Datenbank.
 */
class SyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly KalenderService $kalenderService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        // Täglich ausführen (86400 Sekunden = 24 Stunden)
        $this->setInterval(86400);
        // Zufälligen Offset von bis zu 1 Stunde, um Server-Last zu verteilen
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    /**
     * Führt die vollständige Synchronisation aller Parlamentsdaten durch.
     */
    protected function run(mixed $argument): void {
        $this->logger->info('Parliament Winterthur: Starte Datensynchronisation');

        try {
            // 1. Mitglieder, Fraktionen und Kommissionen synchronisieren
            $this->logger->info('Synchronisiere Mitglieder, Fraktionen und Kommissionen...');
            $this->mitgliedService->synchronisieren();

            // 2. Geschäfte synchronisieren
            $this->logger->info('Synchronisiere Geschäfte...');
            $this->geschaeftService->synchronisieren();

            // 3. Sitzungen und Traktanden synchronisieren
            $this->logger->info('Synchronisiere Sitzungen und Traktanden...');
            $this->sitzungService->synchronisieren();

            // 4. Kalendereinträge aktualisieren
            $this->logger->info('Aktualisiere Kalendereinträge...');
            $this->kalenderService->sitzungenAktualisieren(
                $this->sitzungService->alleAktiven()
            );

            $this->logger->info('Parliament Winterthur: Synchronisation erfolgreich abgeschlossen');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parliament Winterthur: Fehler bei der Synchronisation: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
