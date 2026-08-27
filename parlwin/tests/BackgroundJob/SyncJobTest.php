<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\BackgroundJob;

use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Der automatische Sync läuft nach dem konfigurierbaren Zeitplan; ohne Zeitplan
 * gilt der Standard (alle Wochentage 10:00 und 18:00 Uhr). Fällig ist ein Lauf,
 * wenn zwischen zwei Prüfzeitpunkten ein Zeitplan-Punkt liegt (verpasste Punkte
 * werden nachgeholt, ein Doppellauf wird verhindert).
 */
class SyncJobTest extends TestCase {
    /** Einfacher Config-Fake mit Zustand: getAppValue/setAppValue arbeiten auf einer Map. */
    private array $appConfig = [];

    private function makeConfig(array $werte): IConfig {
        $this->appConfig = $werte;
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            fn (string $app, string $key, string $default = '') => $this->appConfig[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            function (string $app, string $key, string $wert): void {
                $this->appConfig[$key] = $wert;
            }
        );
        return $config;
    }

    private function makeJob(
        SyncCommand $syncCommand,
        LoggerInterface $logger,
        IConfig $config,
        int $jetztTs,
    ): SyncJob {
        $timeFactory = $this->createStub(ITimeFactory::class);
        $fraktionsraumService = $this->createStub(FraktionsraumService::class);
        $vorstossImport = $this->createStub(\OCA\ParliamentWinterthur\Service\VorstossImportService::class);
        $budgetImport = $this->createStub(\OCA\ParliamentWinterthur\Service\BudgetImportService::class);
        $budgetService = $this->createStub(\OCA\ParliamentWinterthur\Service\BudgetService::class);
        $ereignisse = $this->createStub(\OCA\ParliamentWinterthur\Service\EreignisService::class);
        return new class($timeFactory, $syncCommand, $logger, $fraktionsraumService, $config, $vorstossImport, $budgetImport, $budgetService, $ereignisse, $jetztTs) extends SyncJob {
            public function __construct(
                ITimeFactory $time,
                SyncCommand $syncCommand,
                LoggerInterface $logger,
                FraktionsraumService $fraktionsraumService,
                IConfig $config,
                \OCA\ParliamentWinterthur\Service\VorstossImportService $vorstossImport,
                \OCA\ParliamentWinterthur\Service\BudgetImportService $budgetImport,
                \OCA\ParliamentWinterthur\Service\BudgetService $budgetService,
                \OCA\ParliamentWinterthur\Service\EreignisService $ereignisse,
                private readonly int $fakeJetzt,
            ) {
                parent::__construct($time, $syncCommand, $logger, $fraktionsraumService, $config, $vorstossImport, $budgetImport, $budgetService, $ereignisse);
            }

            protected function jetztTs(): int {
                return $this->fakeJetzt;
            }
        };
    }

    private static function ts(string $wann): int {
        return (new \DateTimeImmutable($wann, new \DateTimeZone('Europe/Zurich')))->getTimestamp();
    }

    /** Ist ein Zeitplan konfiguriert, entscheidet ER — der Punkt im Prüf-Fenster löst den Lauf aus. */
    public function testZeitplanPfadLaeuftWennPunktImFensterLiegt(): void {
        $logger = $this->createStub(LoggerInterface::class);
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

        // 2026-07-20 ist ein Montag; letzter Check 06:25, jetzt 06:31 → Punkt Mo 06:30 liegt dazwischen.
        $config = $this->makeConfig([
            'sync_zeitplan' => (string) json_encode([['tage' => [1], 'zeit' => '06:30']]),
            'sync_zeitplan_letzter_check' => (string) self::ts('2026-07-20 06:25'),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 06:31'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);

        self::assertSame((string) self::ts('2026-07-20 06:31'), $this->appConfig['sync_zeitplan_letzter_check']);
    }

    public function testZeitplanPfadLaeuftNichtOhnePunktImFenster(): void {
        $logger = $this->createStub(LoggerInterface::class);
        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::never())->method('run');

        $config = $this->makeConfig([
            'sync_zeitplan' => (string) json_encode([['tage' => [1], 'zeit' => '06:30']]),
            'sync_zeitplan_letzter_check' => (string) self::ts('2026-07-20 06:31'),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 06:40'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }

    /** Erste Prüfung nach Aktivierung initialisiert nur — kein Nachhol-Lauf. */
    public function testZeitplanErstePruefungInitialisiertOhneLauf(): void {
        $logger = $this->createStub(LoggerInterface::class);
        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::never())->method('run');

        $config = $this->makeConfig([
            'sync_zeitplan' => (string) json_encode([['tage' => [1], 'zeit' => '06:30']]),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 06:31'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);

        self::assertSame((string) self::ts('2026-07-20 06:31'), $this->appConfig['sync_zeitplan_letzter_check']);
    }

    /** Ohne konfigurierten Zeitplan gilt der Standard: 10:00 und 18:00 Uhr an allen Tagen. */
    public function testOhneZeitplanGiltDerStandardUm1000(): void {
        $logger = $this->createStub(LoggerInterface::class);
        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::once())->method('run')->willReturn(0);

        // Kein sync_zeitplan gesetzt; letzter Check 09:58, jetzt 10:02 → Standard-Punkt 10:00 liegt dazwischen.
        $config = $this->makeConfig([
            'sync_zeitplan_letzter_check' => (string) self::ts('2026-07-20 09:58'),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 10:02'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }

    public function testOhneZeitplanKeinLaufAusserhalbDerStandardzeiten(): void {
        $logger = $this->createStub(LoggerInterface::class);
        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::never())->method('run');

        // Zwischen 11:00 und 11:05 liegt weder 10:00 noch 18:00.
        $config = $this->makeConfig([
            'sync_zeitplan_letzter_check' => (string) self::ts('2026-07-20 11:00'),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 11:05'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }

    public function testOhneZeitplanGiltDerStandardUm1800(): void {
        $logger = $this->createStub(LoggerInterface::class);
        $syncCommand = $this->createMock(SyncCommand::class);
        $syncCommand->expects(self::once())->method('run')->willReturn(0);

        $config = $this->makeConfig([
            'sync_zeitplan_letzter_check' => (string) self::ts('2026-07-20 17:59'),
        ]);
        $job = $this->makeJob($syncCommand, $logger, $config, self::ts('2026-07-20 18:01'));
        (new \ReflectionMethod($job, 'run'))->invoke($job, null);
    }
}
