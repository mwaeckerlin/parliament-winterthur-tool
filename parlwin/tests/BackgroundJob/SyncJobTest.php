<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\BackgroundJob;

use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncJobTest extends TestCase {
    private function makeJob(int $stunde, SyncCommand $syncCommand, LoggerInterface $logger): SyncJob {
        $timeFactory = $this->createStub(ITimeFactory::class);
        $fraktionsraumService = $this->createStub(FraktionsraumService::class);
        return new class($timeFactory, $syncCommand, $logger, $fraktionsraumService, $stunde) extends SyncJob {
            public function __construct(
                ITimeFactory $time,
                SyncCommand $syncCommand,
                LoggerInterface $logger,
                FraktionsraumService $fraktionsraumService,
                private readonly int $fakeStunde,
            ) {
                parent::__construct($time, $syncCommand, $logger, $fraktionsraumService);
            }

            protected function aktuelleStunde(): int {
                return $this->fakeStunde;
            }
        };
    }

    public function testSyncLaeuftUm3Uhr(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::once())
            ->method('run')
            ->with(
                self::callback(static function (InputInterface $input): bool {
                    return $input->getOption('source') === 'background-job'
                        && $input->getOption('update-progress') === true;
                }),
                self::isInstanceOf(OutputInterface::class),
            )
            ->willReturn(0);

        $job = $this->makeJob(3, $syncCommand, $logger);
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }

    public function testSyncLaeuftUm15Uhr(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::once())->method('run')->willReturn(0);

        $job = $this->makeJob(15, $syncCommand, $logger);
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }

    public function testSyncUebersprochtenAusserhalbSyncStunden(): void {
        $logger = $this->createStub(LoggerInterface::class);

        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::never())->method('run');

        foreach ([0, 6, 12, 14, 16, 23] as $stunde) {
            $job = $this->makeJob($stunde, $syncCommand, $logger);
            (new \ReflectionMethod($job, 'run'))->invoke($job, null);
        }
    }
}
