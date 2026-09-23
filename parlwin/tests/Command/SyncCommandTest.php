<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommandTest extends TestCase {
    private string $lockFile;

    protected function setUp(): void {
        parent::setUp();
        $this->lockFile = '/tmp/parlwin-sync-command-test-' . uniqid('', true) . '.lock';
    }

    protected function tearDown(): void {
        @unlink($this->lockFile);
        @unlink($this->lockFile . '.abbruch');
        @unlink($this->lockFile . '.pid');
        parent::tearDown();
    }

    public function testZweiterStartWirdAnLaufendenSyncAngehaengt(): void {
        $holderLock = new SyncLockService($this->lockFile);
        self::assertTrue($holderLock->acquire());

        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');

        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::never())->method('synchronisieren');

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::never())->method('publish');

        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::never())->method('prefetchTopLevelListen');

        $config = $this->createMock(IConfig::class);
        $config->expects(self::never())->method('setAppValue');

        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen', 'nur-mitglieder' => false,
                'update-progress' => true,
                'source' => 'occ',
                default => null,
            };
        });

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains('läuft bereits'));

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);

        $holderLock->release();
    }

    public function testSyncPubliziertFortschrittsevents(): void {
        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');

        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())
            ->method('synchronisieren')
            ->willReturnCallback(static function (callable $fortschritt): array {
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => 1,
                    'total' => 2,
                    'cursor' => 'm-1',
                    'final' => false,
                ]);
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => 2,
                    'total' => 2,
                    'cursor' => 'm-2',
                    'final' => true,
                ]);
                return [
                    'mitglieder' => ['neu' => 1, 'aktualisiert' => 1, 'inaktiv' => 0],
                    'fraktionen' => ['neu' => 0, 'aktualisiert' => 0],
                    'kommissionen' => ['neu' => 0, 'aktualisiert' => 0],
                ];
            });

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');

        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::once())
            ->method('prefetchTopLevelListen')
            ->with(self::equalTo(['mitglieder', 'kommissionen', 'fraktionen']));
        $scraperService->expects(self::once())
            ->method('vorabTotalsFuerSync')
            ->willReturn([
                'mitglieder' => 2,
                'kommissionen' => 0,
                'fraktionen' => 0,
            ]);

        $config = $this->createMock(IConfig::class);
        $config->expects(self::atLeast(1))
            ->method('setAppValue');

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::atLeast(2))
            ->method('publish')
            ->with(
                self::logicalOr(
                    self::equalTo('sync.progress'),
                    self::equalTo('sync.completed')
                ),
                self::isArray()
            );

        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen' => false,
                'nur-mitglieder' => true,
                'update-progress' => true,
                'source' => 'admin-ui',
                default => null,
            };
        });

        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln');

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testSyncWirdBeiAbbruchsignalSauberBeendet(): void {
        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');

        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');

        $values = [
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
        ];

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())
            ->method('synchronisieren')
            ->willReturnCallback(static function (callable $fortschritt) use (&$values): array {
                $values[SyncCommand::SYNC_CANCEL_REQUESTED_KEY] = '1';
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => 1,
                    'total' => 10,
                    'cursor' => 'm-1',
                    'final' => false,
                ]);
                self::fail('Synchronisierung hätte im Fortschritt-Callback abgebrochen werden müssen');
            });

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');

        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::once())
            ->method('prefetchTopLevelListen')
            ->with(self::equalTo(['mitglieder', 'kommissionen', 'fraktionen']));
        $scraperService->expects(self::once())
            ->method('vorabTotalsFuerSync')
            ->willReturn([
                'mitglieder' => 10,
                'kommissionen' => 0,
                'fraktionen' => 0,
            ]);

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

        $realtimeEvents = [];
        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::atLeast(2))
            ->method('publish')
            ->willReturnCallback(static function (string $event, array $payload) use (&$realtimeEvents): void {
                $realtimeEvents[] = $event;
            });

        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen' => false,
                'nur-mitglieder' => true,
                'update-progress' => true,
                'source' => 'admin-ui',
                default => null,
            };
        });

        $output = new class implements OutputInterface {
            /** @var list<string> */
            public array $messages = [];
            public function writeln(string|array $messages, int $options = 0): void {
                if (is_array($messages)) {
                    $messages = implode("\n", $messages);
                }
                $this->messages[] = $messages;
            }
        };

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        self::assertContains('sync.cancelled', $realtimeEvents);
        self::assertSame('0', $values[SyncCommand::SYNC_CANCEL_REQUESTED_KEY] ?? null);
        $progress = json_decode($values['sync_progress'] ?? '{}', true);
        self::assertIsArray($progress);
        self::assertSame('abgebrochen', $progress['phase'] ?? null);
        self::assertFalse((bool) ($progress['running'] ?? true));
    }

    /**
     * Der Abbruch aus dem STARTFENSTER: Zwischen dem Start des Sync-Prozesses und
     * dem Greifen seiner Sperre vergehen ein bis zwei Sekunden. Die Oberfläche
     * zeigt in dieser Zeit bereits «Synchronisation gestartet» und lässt den
     * Abbruch zu — und dieser Abbruch meint diesen Lauf.
     *
     * Bis dahin räumte der Lauf jedes Signal weg, das er beim Start vorfand: Er
     * lief die ganze Synchronisation durch, während die Verwaltung «abgebrochen»
     * meldete und die Oberfläche minutenlang eine laufende Synchronisation zeigte.
     */
    public function testAbbruchAusDemStartfensterGiltFuerDiesenLauf(): void {
        // Das Signal steht schon da, wenn der Lauf die Sperre greift.
        (new SyncLockService($this->lockFile))->abbruchAnfordern();

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::never())->method('synchronisieren');
        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');
        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');
        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');
        // Der Abbruch wirkt VOR dem Vorabladen der Listen: Ein abgebrochener Lauf
        // lädt die Parlamentswebseite nicht mehr ab.
        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::never())->method('prefetchTopLevelListen');

        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0'];
        $config = new class($values) implements IConfig {
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

        $realtimeEvents = [];
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')
            ->willReturnCallback(static function (string $event, array $payload) use (&$realtimeEvents): void {
                $realtimeEvents[] = $event;
            });

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $this->createStub(FraktionsarbeitService::class),
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen', 'nur-mitglieder' => false,
                'update-progress' => true,
                'source' => 'admin-ui',
                default => null,
            };
        });

        $output = new class implements OutputInterface {
            /** @var list<string> */
            public array $messages = [];
            public function writeln(string|array $messages, int $options = 0): void {
                if (is_array($messages)) {
                    $messages = implode("\n", $messages);
                }
                $this->messages[] = $messages;
            }
        };

        $result = (new \ReflectionMethod($command, 'execute'))->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        self::assertContains('sync.cancelled', $realtimeEvents, 'Der Abbruch aus dem Startfenster wurde verschluckt');
        $progress = json_decode($values['sync_progress'] ?? '{}', true);
        self::assertIsArray($progress);
        self::assertSame('abgebrochen', $progress['phase'] ?? null);
        self::assertFalse((bool) ($progress['running'] ?? true));
    }

    /**
     * Ein Signal, das schon vor dem Start dieses Prozesses dastand, ist das
     * Überbleibsel eines hart beendeten Laufs: Es räumt sich weg, und der neue
     * Lauf synchronisiert.
     */
    public function testAeltererAbbruchAusEinemFruererenLaufWirdWeggeraeumt(): void {
        $lock = new SyncLockService($this->lockFile);
        $lock->abbruchAnfordern();
        // So alt wie ein Signal aus einem Lauf, der vor diesem Prozess lief.
        file_put_contents($this->lockFile . '.abbruch', '1');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())->method('synchronisieren')->willReturn([
            'mitglieder' => ['neu' => 0, 'aktualisiert' => 0, 'inaktiv' => 0],
        ]);
        $scraperService = $this->createStub(ScraperService::class);
        $scraperService->method('vorabTotalsFuerSync')->willReturn([]);
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0'];
        $config = new class($values) implements IConfig {
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

        $command = new SyncCommand(
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->createStub(KalenderService::class),
            $this->createStub(RealtimePublisherService::class),
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen' => false,
                'nur-mitglieder' => true,
                'update-progress' => true,
                'source' => 'admin-ui',
                default => null,
            };
        });

        $output = new class implements OutputInterface {
            /** @var list<string> */
            public array $messages = [];
            public function writeln(string|array $messages, int $options = 0): void {
                if (is_array($messages)) {
                    $messages = implode("\n", $messages);
                }
                $this->messages[] = $messages;
            }
        };

        $result = (new \ReflectionMethod($command, 'execute'))->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        $progress = json_decode($values['sync_progress'] ?? '{}', true);
        self::assertIsArray($progress);
        self::assertSame('abgeschlossen', $progress['phase'] ?? null);
    }

    public function testSyncSetztUndLoeschtWorkerPid(): void {
        $values = [
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
            SyncCommand::SYNC_WORKER_PID_KEY => '',
        ];
        $workerPidWaerendSync = '';

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

        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');

        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())
            ->method('synchronisieren')
            ->willReturnCallback(static function (callable $fortschritt) use (&$values, &$workerPidWaerendSync): array {
                $workerPidWaerendSync = (string) ($values[SyncCommand::SYNC_WORKER_PID_KEY] ?? '');
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => 1,
                    'total' => 1,
                    'cursor' => 'm-1',
                    'final' => true,
                ]);
                return [
                    'mitglieder' => ['neu' => 1, 'aktualisiert' => 0, 'inaktiv' => 0],
                    'fraktionen' => ['neu' => 0, 'aktualisiert' => 0],
                    'kommissionen' => ['neu' => 0, 'aktualisiert' => 0],
                ];
            });

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');

        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::once())
            ->method('prefetchTopLevelListen')
            ->with(self::equalTo(['mitglieder', 'kommissionen', 'fraktionen']));
        $scraperService->expects(self::never())->method('vorabTotalsFuerSync');

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::once())
            ->method('publish')
            ->with(self::equalTo('sync.completed'), self::isArray());

        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen' => false,
                'nur-mitglieder' => true,
                'update-progress' => false,
                'source' => 'occ',
                default => null,
            };
        });

        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln');

        $ref = new \ReflectionMethod($command, 'execute');

        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertNotSame('', $workerPidWaerendSync);
        self::assertSame((string) getmypid(), $workerPidWaerendSync);
        self::assertSame('', $values[SyncCommand::SYNC_WORKER_PID_KEY] ?? '');
    }

    /**
     * Die Prozessnummer steht an ZWEI Stellen: in der App-Konfiguration und in der
     * Datei neben der Sperre. Der Controller schreibt beide, bevor er den Prozess
     * startet; der Status liest bevorzugt die Datei. Räumt der Lauf am Ende nur die
     * Konfiguration auf, bleibt die Nummer in der Datei stehen — und sobald der
     * Container sie neu vergibt, meldet der Status für immer «läuft».
     */
    public function testSyncLoeschtDieProzessnummerAuchInDerDatei(): void {
        $values = [
            SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0',
            SyncCommand::SYNC_WORKER_PID_KEY => '',
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

        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');

        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');

        $sperre = new SyncLockService($this->lockFile);
        // So macht es der Controller, bevor er den occ-Prozess startet.
        $sperre->pidSetzen(4242);

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())
            ->method('synchronisieren')
            ->willReturnCallback(static function (callable $fortschritt): array {
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => 1,
                    'total' => 1,
                    'cursor' => 'm-1',
                    'final' => true,
                ]);
                return [
                    'mitglieder' => ['neu' => 1, 'aktualisiert' => 0, 'inaktiv' => 0],
                    'fraktionen' => ['neu' => 0, 'aktualisiert' => 0],
                    'kommissionen' => ['neu' => 0, 'aktualisiert' => 0],
                ];
            });

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');

        $scraperService = $this->createMock(ScraperService::class);
        $scraperService->expects(self::once())
            ->method('prefetchTopLevelListen')
            ->with(self::equalTo(['mitglieder', 'kommissionen', 'fraktionen']));
        $scraperService->expects(self::never())->method('vorabTotalsFuerSync');

        $realtimePublisher = $this->createMock(RealtimePublisherService::class);
        $realtimePublisher->expects(self::once())
            ->method('publish')
            ->with(self::equalTo('sync.completed'), self::isArray());

        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
        );

        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static function (string $name): mixed {
            return match ($name) {
                'nur-geschaefte', 'nur-sitzungen' => false,
                'nur-mitglieder' => true,
                'update-progress' => false,
                'source' => 'occ',
                default => null,
            };
        });

        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln');

        $ref = new \ReflectionMethod($command, 'execute');
        $result = $ref->invoke($command, $input, $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame('', $values[SyncCommand::SYNC_WORKER_PID_KEY] ?? '');
        self::assertNull(
            $sperre->pidLesen(),
            'Die Prozessnummer steht nach dem Lauf noch in der Datei; der Status meldet damit weiter «läuft»'
        );
    }
}
