<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\StadtratsbeschlussMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCP\Http\Client\IClient;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $this->createStub(FraktionsarbeitService::class),
            $config,
            $this->createStub(EreignisService::class),
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
            $this->beschluesse(),
            $this->createStub(KalenderService::class),
            $this->createStub(RealtimePublisherService::class),
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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
     * F150: `sync.completed` nennt die Geschäfte, die der Abgleich angelegt oder
     * geändert hat, und die, denen er Zuständige gegeben hat, je einmal. Die
     * Oberfläche lädt nur sie nach.
     */
    public function testSyncCompletedNenntDieGeaendertenGeschaefte(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = ''): string => $values[$key] ?? $default
        );

        $geschaeftService = $this->createStub(GeschaeftService::class);
        $geschaeftService->method('synchronisieren')->willReturn(['neu' => 1, 'aktualisiert' => 1, 'geloescht' => 0]);
        $geschaeftService->method('zuletztGeaenderteIds')->willReturn([5, 7]);
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 2, 'zugewiesen' => 2, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);
        $fraktionsarbeitService->method('zuletztZugewieseneIds')->willReturn([7, 9]);

        $abgeschlossen = null;
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')->willReturnCallback(
            static function (string $typ, array $nutzlast) use (&$abgeschlossen): void {
                if ($typ === 'sync.completed') {
                    $abgeschlossen = $nutzlast;
                }
            }
        );

        $command = new SyncCommand(
            $geschaeftService,
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->beschluesse(),
            $this->createStub(KalenderService::class),
            $realtimePublisher,
            $this->createStub(ScraperService::class),
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
        );
        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(static fn (string $name): mixed => match ($name) {
            'nur-geschaefte' => true,
            'nur-sitzungen', 'nur-mitglieder', 'update-progress' => false,
            'source' => 'occ',
            default => null,
        });

        $ref = new \ReflectionMethod($command, 'execute');
        $result = $ref->invoke($command, $input, $this->createStub(OutputInterface::class));

        self::assertSame(Command::SUCCESS, $result);
        self::assertIsArray($abgeschlossen, 'kein sync.completed');
        self::assertSame([5, 7, 9], $abgeschlossen['geschaeftIds']);
    }

    /**
     * F150: Bricht ein Lauf mitten im Abgleich der Geschäfte ab, nennt
     * `sync.cancelled` die Geschäfte, die er bis dahin angelegt hat. Ein
     * fortgesetzter Lauf beginnt hinter ihnen und nennt sie nicht mehr.
     */
    public function testSyncCancelledNenntDieGeschaefteBisZumAbbruch(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $angelegt = 0;
        $geschaeftService = $this->abgleichDerGeschaefte(static function (Geschaeft $geschaeft) use (&$values, &$angelegt): Geschaeft {
            if (++$angelegt === 2) {
                $values[SyncCommand::SYNC_CANCEL_REQUESTED_KEY] = '1';
            }
            return $geschaeft;
        });

        $ereignisse = $this->laufNurGeschaefte($geschaeftService, $values);

        self::assertArrayHasKey('sync.cancelled', $ereignisse, 'der Lauf brach nicht ab');
        self::assertArrayNotHasKey('sync.completed', $ereignisse);
        self::assertSame(2, $angelegt, 'der Lauf schrieb nach dem Abbruch weiter');
        self::assertSame([2777401, 2777402], $ereignisse['sync.cancelled']['geschaeftIds'] ?? 'kein Feld geschaeftIds');
    }

    /**
     * F150: Scheitert ein Lauf mitten im Abgleich der Geschäfte, nennt
     * `sync.failed` die Geschäfte, die er bis zum Fehler angelegt hat.
     */
    public function testSyncFailedNenntDieGeschaefteBisZumFehler(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $angelegt = 0;
        $geschaeftService = $this->abgleichDerGeschaefte(static function (Geschaeft $geschaeft) use (&$angelegt): Geschaeft {
            if (++$angelegt === 3) {
                throw new \RuntimeException('Die Datenbank nimmt nichts mehr an');
            }
            return $geschaeft;
        });

        $ereignisse = $this->laufNurGeschaefte($geschaeftService, $values);

        self::assertSame('Die Datenbank nimmt nichts mehr an', $ereignisse['sync.failed']['fehler'] ?? null);
        self::assertSame([2777401, 2777402], $ereignisse['sync.failed']['geschaeftIds'] ?? 'kein Feld geschaeftIds');
    }

    /**
     * F150: Hat der Abgleich der Geschäfte Geschäfte als gelöscht markiert und
     * scheitert der Lauf danach bei den Sitzungen, steht in `sync.failed`
     * `null`, und die Oberfläche lädt die ganze Liste.
     */
    public function testSyncFailedNachEinerLoeschungNenntKeineEinzelnenGeschaefte(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $geschaeftService = $this->createStub(GeschaeftService::class);
        $geschaeftService->method('synchronisieren')->willReturn(['neu' => 0, 'aktualisiert' => 1, 'geloescht' => 2]);
        $geschaeftService->method('zuletztGeaenderteIds')->willReturn([5]);
        $sitzungService = $this->createStub(SitzungService::class);
        $sitzungService->method('synchronisieren')->willThrowException(new \RuntimeException('Die Sitzungsliste fehlt'));

        $ereignisse = $this->laufNurGeschaefte($geschaeftService, $values, $sitzungService);

        self::assertSame('Die Sitzungsliste fehlt', $ereignisse['sync.failed']['fehler'] ?? null);
        self::assertArrayHasKey('geschaeftIds', $ereignisse['sync.failed']);
        self::assertNull($ereignisse['sync.failed']['geschaeftIds']);
    }

    /**
     * Der echte Abgleich der Geschäfte über drei neue Geschäfte der Quelle.
     * `$anlegen` steht für das Schreiben in die Datenbank.
     *
     * @param callable(Geschaeft): Geschaeft $anlegen
     */
    private function abgleichDerGeschaefte(callable $anlegen): GeschaeftService
    {
        $quelle = [];
        foreach ([1, 2, 3] as $n) {
            $quelle[] = [
                'id' => (string) (2777400 + $n),
                'number' => '2026.' . $n,
                'title' => 'Geschäft ' . $n,
                'type' => 'Schriftliche Anfrage',
                'status' => 'In Kommission',
                'date' => '2026-03-0' . $n,
                'url' => 'https://parlament.winterthur.ch/_rte/information/' . (2777400 + $n),
            ];
        }
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findByExternId')->willThrowException(new DoesNotExistException('neu'));
        $mapper->method('insert')->willReturnCallback($anlegen);
        $scraper = $this->createStub(ScraperService::class);
        $scraper->method('ladeGeschaefte')->willReturn($quelle);
        return new GeschaeftService(
            $mapper,
            $this->createStub(VorstossEntwurfMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $scraper,
            $this->createStub(LoggerInterface::class),
        );
    }

    /**
     * Ein Lauf mit `--nur-geschaefte`, mit `$sitzungen` auch mit
     * `--nur-sitzungen`. Zurück kommt die Nutzlast jedes Ereignisses nach Typ.
     *
     * @param array<string, string> $values
     * @return array<string, array<string, mixed>>
     */
    private function laufNurGeschaefte(GeschaeftService $geschaeftService, array &$values, ?SitzungService $sitzungen = null): array
    {
        $ereignisse = [];
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')->willReturnCallback(
            static function (string $typ, array $nutzlast) use (&$ereignisse): void {
                $ereignisse[$typ] = $nutzlast;
            }
        );
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);
        $fraktionsarbeitService->method('zuletztZugewieseneIds')->willReturn([]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungen ?? $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->beschluesse(),
            $this->createStub(KalenderService::class),
            $realtimePublisher,
            $this->createStub(ScraperService::class),
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $this->konfiguration($values),
            $this->createStub(EreignisService::class),
        );
        (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['nur-geschaefte' => true, 'nur-sitzungen' => $sitzungen !== null]),
            $this->ausgabe()
        );
        return $ereignisse;
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
            $this->beschluesse(),
            $kalenderService,
            $realtimePublisher,
            $scraperService,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
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

    /**
     * F146: Antwortet die Webseite der Stadt nicht, endet nur der Bereich der
     * Stadtratsbeschlüsse. Die Synchronisation läuft zu Ende: Sie setzt die
     * Zuständigkeit, schreibt den Zeitpunkt und meldet `sync.completed`, und
     * die Meldung mit der Adresse steht in der Statistik und im Stand.
     */
    public function testEineUnerreichbareStadtquelleBeendetDieSynchronisationNicht(): void {
        putenv('PARLWIN_STADT_BASE_URL');
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $config = $this->konfiguration($values);

        $client = $this->createStub(IClient::class);
        $client->method('get')->willThrowException(new \RuntimeException('Connection refused'));
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $stadt = new ScraperService($clientService, $this->createStub(LoggerInterface::class));
        $beschluesse = new StadtratsbeschlussService(
            $this->createStub(StadtratsbeschlussMapper::class),
            $stadt,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );

        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('synchronisieren')->willReturn([
            'mitglieder' => ['neu' => 0, 'aktualisiert' => 0, 'inaktiv' => 0],
        ]);
        $geschaeftService = $this->createStub(GeschaeftService::class);
        $geschaeftService->method('synchronisieren')->willReturn(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0]);
        $sitzungService = $this->createStub(SitzungService::class);
        $sitzungService->method('synchronisieren')->willReturn(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0]);
        $parlament = $this->createStub(ScraperService::class);
        $parlament->method('vorabTotalsFuerSync')->willReturn([]);

        $fraktionsarbeitService = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeitService->expects(self::once())->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 1, 'zugewiesen' => 1, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);
        $fraktionsarbeitService->method('zuletztZugewieseneIds')->willReturn([4]);

        $abgeschlossen = null;
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')->willReturnCallback(
            static function (string $typ, array $nutzlast) use (&$abgeschlossen): void {
                if ($typ === 'sync.completed') {
                    $abgeschlossen = $nutzlast;
                }
            }
        );

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $beschluesse,
            $this->createStub(KalenderService::class),
            $realtimePublisher,
            $parlament,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $this->createStub(EreignisService::class),
        );
        $output = $this->ausgabe();
        $result = (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['update-progress' => true, 'source' => 'background-job']),
            $output
        );

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        self::assertIsArray($abgeschlossen, 'kein sync.completed: die Synchronisation brach am Bereich der Stadtratsbeschlüsse ab');
        self::assertSame([4], $abgeschlossen['geschaeftIds']);
        $statistik = $abgeschlossen['statistik']['stadtratsbeschluesse'];
        self::assertTrue($statistik['ausgefallen']);
        self::assertStringContainsString('https://stadt.winterthur.ch/stadtratsbeschluesse', $statistik['fehlermeldung']);
        self::assertStringContainsString('Connection refused', $statistik['fehlermeldung']);
        self::assertNotSame('', $values['letzte_synchronisation'] ?? '');
        $stand = json_decode($values['sync_progress'] ?? '{}', true);
        self::assertSame('abgeschlossen', $stand['phase'] ?? null);
        self::assertStringContainsString(
            'https://stadt.winterthur.ch/stadtratsbeschluesse',
            (string) ($stand['sections']['stadtratsbeschluesse']['fehler'] ?? '')
        );
    }

    /**
     * F146: Ein Beschluss, den die Datenbank ablehnt, zählt in `fehler`. Die
     * Statistik erreicht `sync.completed`, den Stand, die Ausgabe und das
     * Protokoll, und `nurStadtratsbeschluesse` steht im Ereignis.
     */
    public function testNichtGespeicherteBeschluesseErreichenEreignisStandAusgabeUndProtokoll(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $config = $this->konfiguration($values);

        $mapper = $this->createStub(StadtratsbeschlussMapper::class);
        $mapper->method('findByExternId')->willReturn(null);
        $mapper->method('insert')->willThrowException(new \RuntimeException('Spalte zu kurz'));
        $quelle = $this->createStub(ScraperService::class);
        $quelle->method('ladeStadtratsbeschluesse')->willReturn([[
            'externId' => '20261279',
            'nummer' => '2026/1279',
            'titel' => 'Festlegung Legislaturschwerpunkte',
            'datum' => '2026-10-21',
            'url' => 'https://stadt.winterthur.ch/stadtratsbeschluesse/2026-10-21/20261279',
            'detailseiteGelesen' => true,
        ]]);
        $beschluesse = new StadtratsbeschlussService(
            $mapper,
            $quelle,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );

        $abgeschlossen = null;
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')->willReturnCallback(
            static function (string $typ, array $nutzlast) use (&$abgeschlossen): void {
                if ($typ === 'sync.completed') {
                    $abgeschlossen = $nutzlast;
                }
            }
        );
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);
        $protokoll = [];
        $ereignisse = $this->protokoll($protokoll);

        $command = new SyncCommand(
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $beschluesse,
            $this->createStub(KalenderService::class),
            $realtimePublisher,
            $this->createStub(ScraperService::class),
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $config,
            $ereignisse,
        );
        $output = $this->ausgabe();
        $result = (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['nur-stadtratsbeschluesse' => true, 'update-progress' => true, 'source' => 'admin-ui']),
            $output
        );

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        self::assertIsArray($abgeschlossen, 'kein sync.completed');
        self::assertTrue($abgeschlossen['nurStadtratsbeschluesse'] ?? null, 'nurStadtratsbeschluesse fehlt im Ereignis');
        self::assertSame(1, $abgeschlossen['statistik']['stadtratsbeschluesse']['fehler'] ?? null, 'die Statistik der Stadtratsbeschlüsse fehlt im Ereignis');
        $stand = json_decode($values['sync_progress'] ?? '{}', true);
        self::assertSame(1, $stand['statistik']['stadtratsbeschluesse']['fehler'] ?? null, 'die Statistik der Stadtratsbeschlüsse fehlt im Stand');
        self::assertStringContainsString('Spalte zu kurz', (string) ($stand['sections']['stadtratsbeschluesse']['fehler'] ?? ''));
        $ausgabe = implode("\n", $output->messages);
        self::assertStringContainsString('1 nicht gespeichert', $ausgabe);
        self::assertStringContainsString('20261279: Spalte zu kurz', $ausgabe);
        self::assertCount(1, $protokoll, 'der Lauf steht nicht im Protokoll');
        [$art, $bereich, $erfolg, $titel, $meldung, $ausgeloestVon] = $protokoll[0];
        self::assertSame(['sync', '', false, 'Synchronisation abgeschlossen', ''], [$art, $bereich, $erfolg, $titel, $ausgeloestVon]);
        self::assertStringContainsString('1 nicht gespeichert (20261279: Spalte zu kurz)', $meldung);
    }

    /**
     * Die Option `--nur-stadtratsbeschluesse` liest nur diesen Bereich: Kein
     * anderer Bereich und kein Kalender wird angefasst.
     */
    public function testNurStadtratsbeschluesseLiestNurDiesenBereich(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::never())->method('synchronisieren');
        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects(self::never())->method('synchronisieren');
        $sitzungService = $this->createMock(SitzungService::class);
        $sitzungService->expects(self::never())->method('synchronisieren');
        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects(self::never())->method('sitzungenAktualisieren');
        $beschluesse = $this->createMock(StadtratsbeschlussService::class);
        $beschluesse->expects(self::once())->method('synchronisierenMitFehlerInStatistik')->willReturn(
            ['neu' => 2, 'aktualisiert' => 0, 'gelesen' => 2, 'fehler' => 0, 'fehlermeldung' => '']
        );
        $parlament = $this->createMock(ScraperService::class);
        $parlament->expects(self::once())->method('prefetchTopLevelListen')->with([]);

        $abgeschlossen = null;
        $realtimePublisher = $this->createStub(RealtimePublisherService::class);
        $realtimePublisher->method('publish')->willReturnCallback(
            static function (string $typ, array $nutzlast) use (&$abgeschlossen): void {
                if ($typ === 'sync.completed') {
                    $abgeschlossen = $nutzlast;
                }
            }
        );
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);

        $command = new SyncCommand(
            $geschaeftService,
            $sitzungService,
            $mitgliedService,
            $beschluesse,
            $kalenderService,
            $realtimePublisher,
            $parlament,
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $this->konfiguration($values),
            $this->createStub(EreignisService::class),
        );
        $output = $this->ausgabe();
        $result = (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['nur-stadtratsbeschluesse' => true]),
            $output
        );

        self::assertSame(Command::SUCCESS, $result, implode("\n", $output->messages));
        self::assertTrue($abgeschlossen['nurStadtratsbeschluesse'] ?? null);
        self::assertSame(2, $abgeschlossen['statistik']['stadtratsbeschluesse']['neu'] ?? null);
    }

    /** Ein Lauf, der an einem Fehler scheitert, steht mit der Meldung im Protokoll. */
    public function testEinFehlgeschlagenerLaufStehtImProtokoll(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('synchronisieren')->willThrowException(new \RuntimeException('Die Mitgliederliste fehlt'));
        $protokoll = [];
        $ereignisse = $this->protokoll($protokoll);

        $command = new SyncCommand(
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->beschluesse(),
            $this->createStub(KalenderService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(ScraperService::class),
            new SyncLockService($this->lockFile),
            $this->createStub(FraktionsarbeitService::class),
            $this->konfiguration($values),
            $ereignisse,
        );
        $result = (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['nur-mitglieder' => true, 'source' => 'background-job']),
            $this->ausgabe()
        );

        self::assertSame(Command::FAILURE, $result);
        self::assertSame(
            [['fehler', '', false, 'Automatische Synchronisation fehlgeschlagen', 'Die Mitgliederliste fehlt', 'auto']],
            $protokoll
        );
    }

    /**
     * F108: Startet ein Administrator die Synchronisation in der Verwaltung,
     * steht seine Benutzer-ID als Auslöser im Protokoll. Der Lauf ist ein
     * eigener Prozess ohne Anmeldung; die ID kommt über `--ausgeloest-von`.
     */
    public function testDerAusloeserAusDerVerwaltungStehtImProtokoll(): void {
        $values = [SyncCommand::SYNC_CANCEL_REQUESTED_KEY => '0', SyncCommand::SYNC_WORKER_PID_KEY => ''];
        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('synchronisieren')->willReturn([
            'mitglieder' => ['neu' => 1, 'aktualisiert' => 2, 'inaktiv' => 0],
        ]);
        $fraktionsarbeitService = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeitService->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);
        $protokoll = [];

        $command = new SyncCommand(
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->beschluesse(),
            $this->createStub(KalenderService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(ScraperService::class),
            new SyncLockService($this->lockFile),
            $fraktionsarbeitService,
            $this->konfiguration($values),
            $this->protokoll($protokoll),
        );
        $result = (new \ReflectionMethod($command, 'execute'))->invoke(
            $command,
            $this->optionen(['nur-mitglieder' => true, 'source' => 'admin-ui', 'ausgeloest-von' => 'anna']),
            $this->ausgabe()
        );

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame(
            [['sync', '', true, 'Synchronisation abgeschlossen', '1 neu, 2 geändert', 'anna']],
            $protokoll
        );
    }

    /**
     * Ein Ereignis-Protokoll, das jeden Eintrag mit seinen Argumenten in
     * `$eintraege` ablegt.
     */
    private function protokoll(array &$eintraege): EreignisService
    {
        $ereignisse = $this->createStub(EreignisService::class);
        $ereignisse->method('protokolliere')->willReturnCallback(
            function (...$argumente) use (&$eintraege): \OCA\ParliamentWinterthur\Db\Ereignis {
                $eintraege[] = $argumente;
                return new \OCA\ParliamentWinterthur\Db\Ereignis();
            }
        );
        return $ereignisse;
    }

    /**
     * Die Optionen eines Aufrufs von `occ parlwin:sync`; was fehlt, ist nicht gesetzt.
     *
     * @param array<string, mixed> $gesetzt
     */
    private function optionen(array $gesetzt): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(
            static fn (string $name): mixed => $gesetzt[$name] ?? ($name === 'source' ? 'occ' : false)
        );
        return $input;
    }

    /** Eine Konfiguration, die in `$values` liest und schreibt. */
    private function konfiguration(array &$values): IConfig
    {
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

    /** Eine Ausgabe, die jede Zeile behält. */
    private function ausgabe(): OutputInterface
    {
        return new class implements OutputInterface {
            /** @var list<string> */
            public array $messages = [];
            public function writeln(string|array $messages, int $options = 0): void {
                if (is_array($messages)) {
                    $messages = implode("\n", $messages);
                }
                $this->messages[] = $messages;
            }
        };
    }

    /** Der Abgleich der Stadtratsbeschlüsse, ohne Quelle: er liest nichts und schreibt nichts. */
    private function beschluesse(): StadtratsbeschlussService
    {
        $leer = ['neu' => 0, 'aktualisiert' => 0, 'gelesen' => 0, 'fehler' => 0, 'fehlermeldung' => ''];
        $dienst = $this->createStub(StadtratsbeschlussService::class);
        $dienst->method('synchronisieren')->willReturn($leer);
        $dienst->method('synchronisierenMitFehlerInStatistik')->willReturn($leer);
        return $dienst;
    }
}
