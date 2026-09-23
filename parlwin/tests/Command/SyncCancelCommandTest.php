<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\SyncCancelCommand;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCancelCommandTest extends TestCase {
    private string $lockFile;

    protected function setUp(): void {
        parent::setUp();
        $this->lockFile = '/tmp/parlwin-sync-cancel-command-test-' . uniqid('', true) . '.lock';
    }

    protected function tearDown(): void {
        @unlink($this->lockFile);
        @unlink($this->lockFile . '.abbruch');
        @unlink($this->lockFile . '.pid');
        parent::tearDown();
    }

    /** Eine Konfiguration, die nur im Speicher dieses Aufrufs steht. */
    private function config(array &$values): IConfig {
        return new class($values) implements IConfig {
            /** @var array<string, string> */
            private array $values;

            /** @param array<string, string> $values */
            public function __construct(array &$values) {
                $this->values =& $values;
            }

            public function getAppValue(string $app, string $key, string $default = ''): string {
                return $this->values[$key] ?? $default;
            }

            public function setAppValue(string $app, string $key, string $value): void {
                $this->values[$key] = $value;
            }

            public function getSystemValue(string $key, $default = '') {
                return $this->values[$key] ?? $default;
            }
        };
    }

    /**
     * Der Abbruch über die Kommandozeile muss den LAUFENDEN Sync erreichen, und der
     * ist ein eigener Aufruf: Eine geänderte App-Konfiguration sieht er nie, weil
     * Nextcloud deren Werte pro Aufruf im Speicher hält. Das Signal gehört deshalb
     * in die Datei des Sperrdienstes — wie beim Abbruch aus der Verwaltung.
     */
    public function testSignalErreichtDenLaufendenSyncUeberDieSperrdatei(): void {
        $holderLock = new SyncLockService($this->lockFile);
        self::assertTrue($holderLock->acquire());
        $beobachter = new SyncLockService($this->lockFile);

        $values = [
            SyncCommand::SYNC_WORKER_PID_KEY => '4242',
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
        ];
        $signalBeimStoppen = null;
        $syncProcessService = $this->createStub(SyncProcessService::class);
        $syncProcessService->method('ensureStopped')->willReturnCallback(
            static function () use ($beobachter, &$signalBeimStoppen): array {
                $signalBeimStoppen = $beobachter->abbruchAngefordert();
                return ['stopped' => false, 'forced' => false, 'signalled' => false];
            }
        );

        $command = new SyncCancelCommand(
            $this->config($values),
            new SyncLockService($this->lockFile),
            $this->createStub(RealtimePublisherService::class),
            $syncProcessService,
        );

        $output = $this->createStub(OutputInterface::class);
        $result = (new \ReflectionMethod($command, 'execute'))
            ->invoke($command, $this->createStub(InputInterface::class), $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue(
            (bool) $signalBeimStoppen,
            'Der laufende Sync sieht das Abbruch-Signal der Kommandozeile nicht und läuft zu Ende'
        );
        $holderLock->release();
    }

    /**
     * Der Abbruch im STARTFENSTER: Der Prozess läuft schon, hat die Sperre aber
     * noch nicht gegriffen. «Keine laufende Synchronisation» wäre falsch — das
     * Signal muss stehen bleiben, bis der startende Lauf es liest.
     */
    public function testAbbruchImStartfensterSetztDasSignalUndLaesstEsStehen(): void {
        $lock = new SyncLockService($this->lockFile);
        $lock->pidSetzen(getmypid());

        $values = [
            SyncCommand::SYNC_WORKER_PID_KEY => (string) getmypid(),
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
        ];
        $syncProcessService = $this->createStub(SyncProcessService::class);
        $syncProcessService->method('ensureStopped')
            ->willReturn(['stopped' => true, 'forced' => false, 'signalled' => false]);

        $command = new SyncCancelCommand(
            $this->config($values),
            new SyncLockService($this->lockFile),
            $this->createStub(RealtimePublisherService::class),
            $syncProcessService,
        );

        $output = $this->createStub(OutputInterface::class);
        $result = (new \ReflectionMethod($command, 'execute'))
            ->invoke($command, $this->createStub(InputInterface::class), $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue(
            $lock->abbruchAngefordert(),
            'Das Signal ist weg, bevor der startende Lauf es lesen konnte'
        );
    }

    public function testSetztCancelFlagWennSyncLaeuft(): void {
        $holderLock = new SyncLockService($this->lockFile);
        self::assertTrue($holderLock->acquire());

        $values = [
            SyncCommand::SYNC_WORKER_PID_KEY => '4242',
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
        ];
        $config = new class($values) implements IConfig {
            /** @var array<string, string> */
            private array $values;

            /**
             * @param array<string, string> $values
             */
            public function __construct(array &$values) {
                $this->values =& $values;
            }

            public function getAppValue(string $app, string $key, string $default = ''): string {
                return $this->values[$key] ?? $default;
            }

            public function setAppValue(string $app, string $key, string $value): void {
                $this->values[$key] = $value;
            }

            public function getSystemValue(string $key, $default = '') {
                return $this->values[$key] ?? $default;
            }
        };

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::exactly(2))
            ->method('publish')
            ->with(
                self::logicalOr(
                    self::equalTo('sync.cancel.requested'),
                    self::equalTo('sync.cancelled')
                ),
                self::isArray()
            );

        $syncProcessService = $this->createMock(SyncProcessService::class);
        $syncProcessService->expects(self::once())
            ->method('ensureStopped')
            ->with(
                self::equalTo(4242),
                self::isCallable(),
                self::equalTo(900),
                self::equalTo(250),
                self::equalTo(450),
                self::equalTo(50),
            )
            ->willReturn(['stopped' => true, 'forced' => false, 'signalled' => true]);

        $command = new SyncCancelCommand(
            $config,
            new SyncLockService($this->lockFile),
            $realtimePublisher,
            $syncProcessService,
        );

        $input = $this->createStub(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains('wurde beendet'));

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame('0', $values[SyncCommand::SYNC_CANCEL_REQUESTED_KEY] ?? null);
        self::assertSame('', $values[SyncCommand::SYNC_WORKER_PID_KEY] ?? null);
        $holderLock->release();
    }

    public function testKeinLaufenderSyncSetztCancelZurueck(): void {
        $values = [
            SyncCommand::SYNC_WORKER_PID_KEY => '1111',
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '1',
        ];
        $config = new class($values) implements IConfig {
            /** @var array<string, string> */
            private array $values;

            /**
             * @param array<string, string> $values
             */
            public function __construct(array &$values) {
                $this->values =& $values;
            }

            public function getAppValue(string $app, string $key, string $default = ''): string {
                return $this->values[$key] ?? $default;
            }

            public function setAppValue(string $app, string $key, string $value): void {
                $this->values[$key] = $value;
            }

            public function getSystemValue(string $key, $default = '') {
                return $this->values[$key] ?? $default;
            }
        };

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::never())->method('publish');

        $syncProcessService = $this->createMock(SyncProcessService::class);
        $syncProcessService->expects(self::never())->method('ensureStopped');

        $command = new SyncCancelCommand(
            $config,
            new SyncLockService($this->lockFile),
            $realtimePublisher,
            $syncProcessService,
        );

        $input = $this->createStub(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains('Keine laufende Synchronisation'));

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame('0', $values[SyncCommand::SYNC_CANCEL_REQUESTED_KEY] ?? null);
        self::assertSame('', $values[SyncCommand::SYNC_WORKER_PID_KEY] ?? null);
    }
}
