<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\VorstossImportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Zweimal täglich (03:00 und 15:00 Uhr) Synchronisation der Parlamentsdaten.
 */
class SyncJob extends TimedJob {
    private const SYNC_HOURS_DEFAULT = '3,15';
    private const TIMEZONE   = 'Europe/Zurich';

    public function __construct(
        ITimeFactory $time,
        private readonly SyncCommand $syncCommand,
        private readonly LoggerInterface $logger,
        private readonly FraktionsraumService $fraktionsraumService,
        private readonly IConfig $config,
        private readonly VorstossImportService $vorstossImport,
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

    /**
     * Stunden (0–23, Europe/Zurich), zu denen synchronisiert wird. Konfigurierbar
     * über die App-Einstellung `sync_stunden` (kommagetrennt), Default 3 und 15 Uhr.
     *
     * @return int[]
     */
    protected function syncStunden(): array {
        $roh = (string) $this->config->getAppValue(Application::APP_ID, 'sync_stunden', self::SYNC_HOURS_DEFAULT);
        $stunden = [];
        foreach (explode(',', $roh) as $teil) {
            $teil = trim($teil);
            if ($teil === '' || !is_numeric($teil)) {
                continue;
            }
            $h = (int) $teil;
            if ($h >= 0 && $h <= 23) {
                $stunden[] = $h;
            }
        }
        return $stunden !== [] ? $stunden : [3, 15];
    }

    protected function run(mixed $argument): void {
        if (!in_array($this->aktuelleStunde(), $this->syncStunden(), true)) {
            return;
        }

        @ignore_user_abort(true);
        @set_time_limit(0);

        $this->fraktionsraumService->sicherstellen();

        $importiert = $this->vorstossImport->importiere();
        if ($importiert > 0) {
            $this->logger->info('Parlament Winterthur: ' . $importiert . ' Vorstösse aus 40_Vorstösse übernommen');
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
