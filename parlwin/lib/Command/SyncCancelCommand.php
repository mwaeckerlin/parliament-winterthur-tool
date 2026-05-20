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
        $lockAktiv = $this->syncLockService->isLocked();
        if (!$lockAktiv) {
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
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
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

    private function setCancelRequested(bool $requested): void {
        $this->config->setAppValue(
            self::APP_ID,
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY,
            $requested ? '1' : '0'
        );
    }

    private function getCurrentWorkerPid(): ?int {
        $raw = trim((string) $this->config->getAppValue(self::APP_ID, SyncCommand::SYNC_WORKER_PID_KEY, ''));
        if ($raw === '') {
            return null;
        }
        $pid = (int) $raw;
        return $pid > 1 ? $pid : null;
    }

    private function setCurrentWorkerPid(?int $pid): void {
        $this->config->setAppValue(
            self::APP_ID,
            SyncCommand::SYNC_WORKER_PID_KEY,
            ($pid !== null && $pid > 1) ? (string) $pid : ''
        );
    }
}
