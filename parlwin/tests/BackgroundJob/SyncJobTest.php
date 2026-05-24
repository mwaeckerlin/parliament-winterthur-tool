<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\BackgroundJob;

use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncJobTest extends TestCase {
    public function testBackgroundJobNutztSyncCommandMitSourceUndProgress(): void {
        $timeFactory = $this->createStub(ITimeFactory::class);
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

        $job = new SyncJob($timeFactory, $syncCommand, $logger);
        $ref = new \ReflectionMethod($job, 'run');
        $ref->invoke($job, null);
    }
}

