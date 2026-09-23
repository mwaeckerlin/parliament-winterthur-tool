<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCancelCommand extends Command {
    private const APP_ID = 'parlwin';
    protected static $defaultName = 'parlwin:sync:cancel';

    public function __construct(
        private readonly IConfig $config,
        private readonly SyncLockService $syncLockService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly SyncProcessService $syncProcessService,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setDescription('Fordert den Abbruch einer laufenden Synchronisation an');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Ein Lauf läuft auch dann, wenn seine Sperre noch nicht greift: Zwischen
        // dem Start seines Prozesses und dem Greifen der Sperre vergehen ein bis
        // zwei Sekunden, und ein Abbruch aus diesem Fenster meint ihn.
        if (!$this->syncLockService->isLocked() && !$this->istWorkerProzessLebendig()) {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            $output->writeln('<comment>Keine laufende Synchronisation gefunden.</comment>');
            return Command::SUCCESS;
        }

        $this->setCancelRequested(true);
        $zeitpunkt = (new \DateTime())->format('Y-m-d H:i:s');
        $this->realtimePublisher->publish('sync.cancel.requested', [
            'quelle' => 'occ',
            'zeitpunkt' => $zeitpunkt,
        ]);

        $stopResult = $this->syncProcessService->ensureStopped(
            $this->getCurrentWorkerPid(),
            fn (): bool => $this->syncLockService->isLocked(),
            900,
            250,
            450,
            50,
        );

        if (($stopResult['stopped'] ?? false) === true) {
            // Solange der Prozess lebt und nicht mit einem Signal beendet wurde,
            // bleiben Abbruch-Signal und Prozessnummer stehen: Der Lauf liest das
            // Signal, sobald er die Sperre greift, und räumt danach selbst auf.
            if ((bool) ($stopResult['signalled'] ?? false) || !$this->istWorkerProzessLebendig()) {
                $this->setCancelRequested(false);
                $this->setCurrentWorkerPid(null);
            }
            $this->realtimePublisher->publish('sync.cancelled', [
                'quelle' => 'occ',
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'forced' => (bool) ($stopResult['forced'] ?? false),
                'signalled' => (bool) ($stopResult['signalled'] ?? false),
            ]);
            if (($stopResult['forced'] ?? false) === true) {
                $output->writeln('<info>Abbruchsignal gesetzt, Sync-Prozess wurde hart beendet.</info>');
            } else {
                $output->writeln('<info>Abbruchsignal gesetzt, Sync-Prozess wurde beendet.</info>');
            }
            return Command::SUCCESS;
        }

        $output->writeln('<info>Abbruchsignal wurde gesetzt.</info>');
        return Command::SUCCESS;
    }

    /**
     * Das Signal geht über die Datei des Sperrdienstes, nicht über die
     * App-Konfiguration: Der laufende Sync ist ein eigener Aufruf, und Nextcloud
     * hält die Konfigurationswerte pro Aufruf im Speicher — er sähe bis zu seinem
     * Ende den Stand von seinem Beginn. Der Wert in der Konfiguration steht daneben
     * für alles, was den Stand nur nachliest.
     */
    private function setCancelRequested(bool $requested): void {
        if ($requested) {
            $this->syncLockService->abbruchAnfordern();
        } else {
            $this->syncLockService->abbruchAufheben();
        }
        $this->config->setAppValue(
            self::APP_ID,
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY,
            $requested ? '1' : '0'
        );
    }

    private function getCurrentWorkerPid(): ?int {
        $ausDatei = $this->syncLockService->pidLesen();
        if ($ausDatei !== null) {
            return $ausDatei;
        }
        $raw = trim((string) $this->config->getAppValue(self::APP_ID, SyncCommand::SYNC_WORKER_PID_KEY, ''));
        if ($raw === '') {
            return null;
        }
        $pid = (int) $raw;
        return $pid > 1 ? $pid : null;
    }

    private function setCurrentWorkerPid(?int $pid): void {
        $this->syncLockService->pidSetzen($pid);
        $this->config->setAppValue(
            self::APP_ID,
            SyncCommand::SYNC_WORKER_PID_KEY,
            ($pid !== null && $pid > 1) ? (string) $pid : ''
        );
    }

    /** Ob der Sync-Prozess noch lebt, auch wenn seine Sperre noch nicht greift. */
    private function istWorkerProzessLebendig(): bool {
        $pid = $this->getCurrentWorkerPid();
        if ($pid === null) {
            return false;
        }
        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 0)) {
                return true;
            }
            // EPERM: Der Prozess lebt, gehört nur jemand anderem.
            if (function_exists('posix_get_last_error') && posix_get_last_error() === 1) {
                return true;
            }
        }
        return @is_dir('/proc/' . $pid);
    }
}
