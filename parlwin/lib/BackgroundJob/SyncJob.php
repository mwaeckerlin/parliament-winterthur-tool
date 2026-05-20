<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Täglicher Hintergrund-Job zur Synchronisation der Parlamentsdaten.
 *
 * Dieser Job wird täglich ausgeführt und synchronisiert alle relevanten
 * Daten von https://parlament.winterthur.ch/ in die lokale Datenbank.
 */
class SyncJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly SyncCommand $syncCommand,
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
        @ignore_user_abort(true);
        @set_time_limit(0);

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
