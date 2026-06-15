<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Zweimal täglich (03:00 und 15:00 Uhr) Synchronisation der Parlamentsdaten.
 */
class SyncJob extends TimedJob {
    private const SYNC_HOURS = [3, 15];
    private const TIMEZONE   = 'Europe/Zurich';

    public function __construct(
        ITimeFactory $time,
        private readonly SyncCommand $syncCommand,
        private readonly LoggerInterface $logger,
        private readonly FraktionsraumService $fraktionsraumService,
    ) {
        parent::__construct($time);
        // Mindestabstand 11 Stunden — verhindert Doppelläufe im selben Zeitfenster
        $this->setInterval(11 * 3600);
        $this->setTimeSensitivity(self::TIME_SENSITIVE);
    }

    /**
     * Führt die vollständige Synchronisation aller Parlamentsdaten durch.
     */
    protected function aktuelleStunde(): int {
        return (int) (new \DateTime('now', new \DateTimeZone(self::TIMEZONE)))->format('G');
    }

    protected function run(mixed $argument): void {
        if (!in_array($this->aktuelleStunde(), self::SYNC_HOURS, true)) {
            return;
        }

        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->fraktionsraumService->sicherstellen();
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
