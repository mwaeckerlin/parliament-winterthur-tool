<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\SettingsController;
use OCA\ParliamentWinterthur\Db\Fraktion;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCP\AppFramework\Http;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
    /**
     * Der laufende Sync und der Abbruch sind zwei getrennte Aufrufe. Über die
     * App-Konfiguration erreichte das Signal den laufenden Sync nie: Nextcloud
     * hält deren Werte pro Aufruf im Speicher, und er las bis zu seinem Ende den
     * Stand von seinem Beginn — der Abbruch-Knopf blieb wirkungslos.
     *
     * Geprüft wird die ganze Kette: Der abbrechende Aufruf schreibt das Signal,
     * ein zweiter Controller mit EIGENEM Konfigurations-Zwischenspeicher (wie ihn
     * der laufende Sync hat) sieht es trotzdem.
     */
    public function testAbbruchErreichtDenLaufendenSyncTrotzKonfigurationsCache(): void {
        $sperrdatei = '/tmp/parlwin-sync-abbruch-test-' . uniqid('', true) . '.lock';
        $lock = new SyncLockService($sperrdatei);
        $lesen = new \ReflectionMethod(SettingsController::class, 'isCancelRequested');
        $setzen = new \ReflectionMethod(SettingsController::class, 'setCancelRequested');

        try {
            // Der laufende Sync: seine Konfiguration steht auf dem Stand von seinem
            // Beginn und ändert sich nicht mehr.
            $laufend = $this->minimalController(['sync_cancel_requested' => '0'], $lock);
            self::assertFalse($lesen->invoke($laufend), 'ohne Anforderung kein Abbruch');

            // Der abbrechende Aufruf, mit eigener Konfiguration.
            $abbrechend = $this->minimalController([], $lock);
            $setzen->invoke($abbrechend, true);

            self::assertTrue(
                $lesen->invoke($laufend),
                'Der laufende Sync sieht den Abbruch nicht und läuft weiter'
            );

            $setzen->invoke($abbrechend, false);
            self::assertFalse($lesen->invoke($laufend), 'Der aufgehobene Abbruch wirkt weiter');
        } finally {
            @unlink($sperrdatei);
            @unlink($sperrdatei . '.abbruch');
        }
    }

    /**
     * Der harte Stopp braucht die Prozessnummer des laufenden Syncs, und er läuft in
     * einem anderen Aufruf als der Sync selbst. Über die App-Konfiguration kam sie
     * dort nicht zuverlässig an: Nextcloud hält deren Werte pro Aufruf im Speicher.
     * Ohne die Nummer traf der Stopp niemanden, und der Sync lief weiter, bis er von
     * selbst fertig war — der Abbruch-Knopf war wirkungslos.
     */
    public function testDieProzessnummerErreichtDenAbbrechendenAufruf(): void {
        $sperrdatei = '/tmp/parlwin-sync-pid-test-' . uniqid('', true) . '.lock';
        $lock = new SyncLockService($sperrdatei);
        $setzen = new \ReflectionMethod(SettingsController::class, 'setCurrentWorkerPid');
        $lesen = new \ReflectionMethod(SettingsController::class, 'getCurrentWorkerPid');

        try {
            $laufenderSync = $this->minimalController([], $lock);
            $setzen->invoke($laufenderSync, 4242);

            // Der abbrechende Aufruf mit EIGENEM Konfigurationsstand: leer.
            $abbrechend = $this->minimalController([], $lock);
            self::assertSame(
                4242,
                $lesen->invoke($abbrechend),
                'Der abbrechende Aufruf kennt die Prozessnummer des laufenden Syncs nicht'
            );

            $setzen->invoke($laufenderSync, null);
            self::assertNull($lesen->invoke($abbrechend), 'Die Prozessnummer bleibt nach dem Ende stehen');
        } finally {
            @unlink($sperrdatei);
            @unlink($sperrdatei . '.abbruch');
            @unlink($sperrdatei . '.pid');
        }
    }

    /**
     * Ein Status, der «läuft» behauptet, während weder eine Sperre gehalten wird
     * noch ein Worker lebt: Der Sync ist beendet, sein Fortschritt sagt es nur
     * nicht. Der Abbruch räumte bisher nur das Signal und die Prozessnummer auf
     * und liess den Fortschritt stehen — die Oberfläche zeigte danach für immer
     * eine laufende Synchronisation, und kein weiterer Abbruch half.
     */
    public function testAbbruchRaeumtEinenStehengebliebenenStatusAuf(): void {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $stand = [
            'sync_progress' => json_encode([
                'running' => true,
                'phase' => 'geschaefte',
                'phaseLabel' => 'Geschäfte',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $stand[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$stand): void {
                $stand[$key] = $value;
            }
        );

        $sperrdatei = '/tmp/parlwin-sync-status-test-' . uniqid('', true) . '.lock';
        try {
            $controller = new SettingsController(
                $request,
                $config,
                $this->createStub(GeschaeftService::class),
                $this->createStub(SitzungService::class),
                $this->createStub(MitgliedService::class),
                $this->createStub(ScraperService::class),
                $this->createStub(KalenderService::class),
                $this->createStub(FraktionsarbeitService::class),
                $this->createStub(RealtimePublisherService::class),
                new SyncLockService($sperrdatei),
                $this->createStub(SyncProcessService::class),
                $this->createStub(FraktionMapper::class),
                $this->createStub(IGroupManager::class),
                $this->createStub(IUserManager::class),
                $this->createStub(FraktionsraumService::class),
                $this->createStub(EreignisService::class),
            );

            $antwort = $controller->cancelSync();
            self::assertSame(Http::STATUS_OK, $antwort->getStatus());
            $daten = $antwort->getData();
            self::assertIsArray($daten);
            self::assertTrue((bool) ($daten['bereits_beendet'] ?? false), 'der Sync gilt nicht als beendet');

            $status = json_decode((string) ($stand['sync_progress'] ?? '{}'), true);
            self::assertIsArray($status);
            self::assertFalse(
                $status['running'] ?? true,
                'Der Fortschritt behauptet weiter, die Synchronisation laufe'
            );
        } finally {
            @unlink($sperrdatei);
            @unlink($sperrdatei . '.abbruch');
        }
    }

    /**
     * Der Abbruch im STARTFENSTER: Der Sync-Prozess läuft schon (seine
     * Prozessnummer steht), hat seine Sperre aber noch nicht gegriffen — dazwischen
     * liegen ein bis zwei Sekunden, in denen die Oberfläche «gestartet» meldet und
     * den Abbruch zulässt. Der Stopp meldet in diesem Moment «beendet», weil keine
     * Sperre gehalten wird.
     *
     * Das Abbruch-Signal muss dann STEHEN BLEIBEN: Der Prozess liest es, sobald er
     * die Sperre greift. Wer es hier aufräumt, lässt die Synchronisation zu Ende
     * laufen, während die Oberfläche «abgebrochen» meldet.
     */
    public function testAbbruchImStartfensterBleibtStehenSolangeDerWorkerLebt(): void {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $stand = [
            'sync_progress' => json_encode([
                'running' => true,
                'phase' => 'queued',
                'phaseLabel' => 'Synchronisation gestartet',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $stand[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$stand): void {
                $stand[$key] = $value;
            }
        );

        // Der Stopp findet keine Sperre und meldet «beendet» — genau das tut der
        // echte Dienst, solange der Prozess seine Sperre noch nicht gegriffen hat.
        $prozesse = $this->createStub(SyncProcessService::class);
        $prozesse->method('ensureStopped')->willReturn(['stopped' => true, 'forced' => false, 'signalled' => false]);

        $sperrdatei = '/tmp/parlwin-sync-startfenster-test-' . uniqid('', true) . '.lock';
        $lock = new SyncLockService($sperrdatei);
        try {
            $controller = new SettingsController(
                $request,
                $config,
                $this->createStub(GeschaeftService::class),
                $this->createStub(SitzungService::class),
                $this->createStub(MitgliedService::class),
                $this->createStub(ScraperService::class),
                $this->createStub(KalenderService::class),
                $this->createStub(FraktionsarbeitService::class),
                $this->createStub(RealtimePublisherService::class),
                $lock,
                $prozesse,
                $this->createStub(FraktionMapper::class),
                $this->createStub(IGroupManager::class),
                $this->createStub(IUserManager::class),
                $this->createStub(FraktionsraumService::class),
                $this->createStub(EreignisService::class),
            );

            // Der Worker lebt: Als seine Nummer dient die des Testprozesses.
            (new \ReflectionMethod(SettingsController::class, 'setCurrentWorkerPid'))
                ->invoke($controller, getmypid());

            $antwort = $controller->cancelSync();
            self::assertSame(Http::STATUS_OK, $antwort->getStatus());

            self::assertTrue(
                $lock->abbruchAngefordert(),
                'Das Abbruch-Signal ist weg, bevor der startende Lauf es lesen konnte'
            );
            self::assertNotNull(
                $lock->pidLesen(),
                'Die Prozessnummer des noch laufenden Workers ist weg'
            );
        } finally {
            @unlink($sperrdatei);
            @unlink($sperrdatei . '.abbruch');
            @unlink($sperrdatei . '.pid');
        }
    }

    /**
     * Der harte Stopp dagegen räumt auf: Wer den Lauf mit einem Signal beendet
     * hat, hinterlässt weder Abbruch-Signal noch Prozessnummer — nach einem KILL
     * läuft das Aufräumen des Laufs nicht mehr, und eine stehengebliebene
     * Prozessnummer meldet für immer «läuft».
     */
    public function testHarterStoppRaeumtSignalUndProzessnummerWeg(): void {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $stand = [
            'sync_progress' => json_encode([
                'running' => true,
                'phase' => 'geschaefte',
                'phaseLabel' => 'Geschäfte',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $stand[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$stand): void {
                $stand[$key] = $value;
            }
        );

        $prozesse = $this->createStub(SyncProcessService::class);
        $prozesse->method('ensureStopped')->willReturn(['stopped' => true, 'forced' => true, 'signalled' => true]);

        $sperrdatei = '/tmp/parlwin-sync-hartstopp-test-' . uniqid('', true) . '.lock';
        $lock = new SyncLockService($sperrdatei);
        try {
            $controller = new SettingsController(
                $request,
                $config,
                $this->createStub(GeschaeftService::class),
                $this->createStub(SitzungService::class),
                $this->createStub(MitgliedService::class),
                $this->createStub(ScraperService::class),
                $this->createStub(KalenderService::class),
                $this->createStub(FraktionsarbeitService::class),
                $this->createStub(RealtimePublisherService::class),
                $lock,
                $prozesse,
                $this->createStub(FraktionMapper::class),
                $this->createStub(IGroupManager::class),
                $this->createStub(IUserManager::class),
                $this->createStub(FraktionsraumService::class),
                $this->createStub(EreignisService::class),
            );

            (new \ReflectionMethod(SettingsController::class, 'setCurrentWorkerPid'))
                ->invoke($controller, getmypid());

            $controller->cancelSync();

            self::assertFalse($lock->abbruchAngefordert(), 'Das Abbruch-Signal bleibt nach dem harten Stopp stehen');
            self::assertNull($lock->pidLesen(), 'Die Prozessnummer bleibt nach dem harten Stopp stehen');
        } finally {
            @unlink($sperrdatei);
            @unlink($sperrdatei . '.abbruch');
            @unlink($sperrdatei . '.pid');
        }
    }

    /** Ein Controller mit festem Konfigurationsstand und echtem Sperrdienst. */
    private function minimalController(array $stand, SyncLockService $lock): SettingsController {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $stand[$key] ?? $default
        );

        return new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $lock,
            $this->createStub(SyncProcessService::class),
            $this->createStub(FraktionMapper::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );
    }

    public function testRunHaengtAnLaufendeSynchronisationAnWennLockAktiv(): void {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $store = [
            'sync_progress' => json_encode([
                'running' => false,
                'phase' => 'idle',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            }
        );

        $syncLock = $this->createStub(SyncLockService::class);
        $syncLock->method('isLocked')->willReturn(true);

        $syncProcess = $this->createStub(SyncProcessService::class);
        $publisher = $this->createStub(RealtimePublisherService::class);
        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $controller = new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $publisher,
            $syncLock,
            $syncProcess,
            $fraktionMapper,
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $response = $controller->run();
        self::assertSame(Http::STATUS_ACCEPTED, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertTrue((bool) ($data['erfolg'] ?? false));
        self::assertTrue((bool) ($data['bereits_laufend'] ?? false));
        self::assertTrue((bool) ($data['asynchron'] ?? false));
    }

    public function testSyncStatusZeigtLaufendWennLockAktivAuchBeiIdleStatus(): void {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $store = [
            'sync_progress' => json_encode([
                'running' => false,
                'phase' => 'idle',
                'source' => 'admin-ui',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            }
        );

        $syncLock = $this->createStub(SyncLockService::class);
        $syncLock->method('isLocked')->willReturn(true);

        $syncProcess = $this->createStub(SyncProcessService::class);
        $publisher = $this->createStub(RealtimePublisherService::class);
        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $controller = new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $publisher,
            $syncLock,
            $syncProcess,
            $fraktionMapper,
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $response = $controller->syncStatus();
        self::assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertTrue((bool) ($data['running'] ?? false));
        self::assertSame('running', (string) ($data['phase'] ?? ''));
        self::assertSame('Synchronisation läuft', (string) ($data['phaseLabel'] ?? ''));
        self::assertSame('Synchronisation läuft', (string) (($data['current']['label'] ?? '')));
    }

    public function testSetLehntUnbekannteFraktionAb(): void {
        [$controller, $config, $publisher] = $this->buildController(
            ['fraktion' => 'Unbekannte Fraktion'],
            ['SP/Grüne'],
            []
        );

        $config->expects(self::never())->method('setAppValue');
        $publisher->expects(self::never())->method('publish');

        $response = $controller->set();
        self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertStringContainsString('Fraktion', (string) ($data['fehler'] ?? ''));
    }

    public function testSetLehntInaktiveFraktionAb(): void {
        [$controller, $config, $publisher] = $this->buildController(
            ['fraktion' => 'SVP-Fraktion'],
            [
                ['name' => 'SVP-Fraktion', 'aktiv' => false],
                ['name' => 'Schweizerische Volkspartei-Fraktion (SVP)', 'aktiv' => true],
            ],
            []
        );

        $config->expects(self::never())->method('setAppValue');
        $publisher->expects(self::never())->method('publish');

        $response = $controller->set();
        self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertStringContainsString('Fraktion', (string) ($data['fehler'] ?? ''));
    }

    public function testSetSpeichertGueltigeWerteUndLiefertOptionen(): void {
        $bekannterUser = $this->createStub(IUser::class);
        $bekannterUser->method('getUID')->willReturn('admin');
        $bekannterUser->method('getDisplayName')->willReturn('Admin');
        $bekannterUser->method('isEnabled')->willReturn(true);

        [$controller, $config, $publisher] = $this->buildController(
            [
                'fraktion' => 'SP/Grüne',
                'nextcloud_gruppe' => 'Fraktion-SP-Gruene',
                'absender_email' => 'noreply@example.com',
                'absender_name' => 'Parliament',
            ],
            ['SP/Grüne', 'FDP'],
            ['admin' => $bekannterUser]
        );

        // Kein kalender_nutzer mehr: fraktion, nextcloud_gruppe, absender_email,
        // absender_name → 4 Writes.
        $writes = [];
        $config->expects(self::exactly(4))
            ->method('setAppValue')
            ->willReturnCallback(static function (string $_app, string $key, string $value) use (&$writes): void {
                $writes[$key] = $value;
            });
        $publisher->expects(self::once())->method('publish')->with('settings.updated');

        $response = $controller->set();
        self::assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertSame('SP/Grüne', $writes['fraktion'] ?? null);
        self::assertArrayNotHasKey('kalender_nutzer', $writes);
        self::assertSame('Fraktion-SP-Gruene', $writes['nextcloud_gruppe'] ?? null);
        self::assertSame(['FDP', 'SP/Grüne'], $data['optionen']['fraktionen'] ?? []);
    }

    public function testFraktionMitgliederLiefertUsernameUndLokaleGruppen(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => [
            'fraktion' => 'SP/Grüne',
        ][$key] ?? $default);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturn('');

        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $mitglied = new Mitglied();
        $mitglied->setId(1);
        $mitglied->setExternId('123');
        $mitglied->setVorname('Max');
        $mitglied->setName('Muster');
        $mitglied->setFraktion('SP/Grüne');
        $mitglied->setEmail('max@example.org');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->expects(self::once())
            ->method('aktiveDerFraktion')
            ->with('SP/Grüne')
            ->willReturn([$mitglied]);

        $localUser = $this->createStub(IUser::class);
        $localUser->method('getUID')->willReturn('max-muster');
        $localUser->method('getDisplayName')->willReturn('Max Muster');
        $localUser->method('getEMailAddress')->willReturn('max@example.org');
        $localUser->method('isEnabled')->willReturn(true);

        $userManager = $this->createStub(IUserManager::class);
        $userManager->method('get')->with('max-muster')->willReturn($localUser);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('getUserGroupIds')->with($localUser)->willReturn(['Fraktion-SP-Gruene', 'users']);

        $publisher = $this->createStub(RealtimePublisherService::class);

        $controller = new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $publisher,
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $fraktionMapper,
            $groupManager,
            $userManager,
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $response = $controller->fraktionMitglieder();
        self::assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertSame('SP/Grüne', $data['fraktion'] ?? '');
        self::assertCount(1, $data['mitglieder'] ?? []);
        self::assertSame('max-muster', $data['mitglieder'][0]['username'] ?? null);
        self::assertTrue((bool) ($data['mitglieder'][0]['lokalerUserExistiert'] ?? false));
        self::assertSame(['Fraktion-SP-Gruene', 'users'], $data['mitglieder'][0]['lokaleGruppen'] ?? []);
    }

    public function testProvisionFraktionMitgliederLegtUserAnUndFuegtGruppeHinzu(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => [
            'fraktion' => 'SP/Grüne',
            'nextcloud_gruppe' => 'Fraktion-SP-Gruene',
            'mitglied_ids' => [7],
            'mappings' => [
                ['mitgliedId' => 7, 'username' => 'Max Muster'],
            ],
        ][$key] ?? $default);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturn('');

        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $mitglied = new Mitglied();
        $mitglied->setId(7);
        $mitglied->setExternId('777');
        $mitglied->setVorname('Max');
        $mitglied->setName('Muster');
        $mitglied->setFraktion('SP/Grüne');
        $mitglied->setEmail('max@example.org');

        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('eins')->with(7)->willReturn($mitglied);
        $mitgliedService->method('setzeNextcloudUid')->with(7, 'max-muster')->willReturn($mitglied);
        $mitgliedService->method('aktiveDerFraktion')->with('SP/Grüne')->willReturn([$mitglied]);
        $mitgliedService->method('gehoertZurFraktion')->willReturn(true);

        $localUser = $this->createStub(IUser::class);
        $localUser->method('getUID')->willReturn('max-muster');
        $localUser->method('getDisplayName')->willReturn('Max Muster');
        $localUser->method('getEMailAddress')->willReturn('max@example.org');
        $localUser->method('isEnabled')->willReturn(true);

        $userManager = $this->createMock(IUserManager::class);
        $userManager->expects(self::exactly(2))
            ->method('get')
            ->with('max-muster')
            ->willReturnOnConsecutiveCalls(null, $localUser);
        $userManager->expects(self::once())
            ->method('createUser')
            ->with('max-muster', self::isString())
            ->willReturn($localUser);

        $group = $this->createMock(IGroup::class);
        $group->method('inGroup')->with($localUser)->willReturn(false);
        $group->expects(self::once())->method('addUser')->with($localUser);

        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('groupExists')->with('Fraktion-SP-Gruene')->willReturn(false);
        $groupManager->expects(self::once())->method('createGroup')->with('Fraktion-SP-Gruene')->willReturn($group);
        $groupManager->method('get')->with('Fraktion-SP-Gruene')->willReturn($group);
        $groupManager->method('getUserGroupIds')->with($localUser)->willReturn(['Fraktion-SP-Gruene']);

        $publisher = $this->createMock(RealtimePublisherService::class);
        $publisher->expects(self::once())->method('publish')->with(
            'settings.members.provisioned',
            self::isArray()
        );

        $controller = new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $publisher,
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $fraktionMapper,
            $groupManager,
            $userManager,
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $response = $controller->provisionFraktionMitglieder();
        self::assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertSame(1, (int) ($data['provision']['angelegt'] ?? 0));
        self::assertSame(1, (int) ($data['provision']['zurGruppeHinzugefuegt'] ?? 0));
    }

    private function makeMitglied(string $vorname, string $name, string $email = '', string $ncUid = ''): Mitglied
    {
        $m = new Mitglied();
        $m->setVorname($vorname);
        $m->setName($name);
        $m->setEmail($email);
        if ($ncUid !== '') {
            $m->setNextcloudUid($ncUid);
        }
        return $m;
    }

    private function makeNCUser(string $uid, string $displayName = '', string $email = '', bool $aktiv = true): IUser
    {
        $u = $this->createStub(IUser::class);
        $u->method('getUID')->willReturn($uid);
        $u->method('getDisplayName')->willReturn($displayName ?: $uid);
        $u->method('getEMailAddress')->willReturn($email);
        $u->method('isEnabled')->willReturn($aktiv);
        return $u;
    }

    /**
     * Anforderung: Alle Benutzer der Nextcloud-Gruppe werden in «Fraktionsmitglieder ↔ Nextcloud-Benutzer» angezeigt.
     * Wer NICHT in der Fraktion ist, erscheint als «verwaist» (wird im Frontend durchgestrichen).
     */
    public function testFraktionMitgliederZeigtVerwaisteNCGruppenUserOhneParlamentseintrag(): void
    {
        // Parlament-Mitglied mit zugeordnetem NC-User
        $mitglied = $this->makeMitglied('Marc', 'Muster', 'marc@example.com', 'marc-muster');
        $marcUser = $this->makeNCUser('marc-muster', 'Marc Muster', 'marc@example.com');

        // Erster Ersatz: in NC-Gruppe, aber KEIN Parlamentseintrag
        $erstErsatz = $this->makeNCUser('erster-ersatz', 'Ersatz Person', 'ersatz@example.com');

        $gruppe = $this->createStub(IGroup::class);
        $gruppe->method('getUsers')->willReturn([$marcUser, $erstErsatz]);

        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => match ($key) {
                'fraktion' => 'SP',
                default => $default,
            }
        );

        $store = ['nextcloud_gruppe' => 'sp-gruppe', 'fraktion' => 'SP'];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('get')->willReturn($gruppe);

        $userManager = $this->createStub(IUserManager::class);
        $userManager->method('get')->willReturnCallback(
            static fn(string $uid): ?IUser => $uid === 'marc-muster' ? $marcUser : null
        );
        $userManager->method('getByEmail')->willReturn([]);

        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('aktiveDerFraktion')->willReturn([$mitglied]);

        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $controller = new SettingsController(
            $request, $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $fraktionMapper,
            $groupManager,
            $userManager,
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $data = $controller->fraktionMitglieder()->getData();

        self::assertIsArray($data['verwaiste'], 'verwaiste muss im Response enthalten sein');
        self::assertCount(1, $data['verwaiste'], 'Genau ein verwaister User (Erster Ersatz)');
        self::assertSame('erster-ersatz', $data['verwaiste'][0]['uid']);
        self::assertSame('Ersatz Person', $data['verwaiste'][0]['displayName']);
        // Parlament-Mitglied darf NICHT als verwaist erscheinen
        $verwaistUids = array_column($data['verwaiste'], 'uid');
        self::assertNotContains('marc-muster', $verwaistUids);
    }

    /**
     * Requirement: NUR wenn ein verwaister User SELEKTIERT ist bei «Ausgewählte abgleichen»,
     * wird er verarbeitet (aus Gruppe entfernt). Nicht selektierte bleiben unberührt.
     * (setEnabled ist nicht Teil von IUser-Interface — Deaktivierung nur in Integration testbar)
     */
    public function testProvisionVerarbeitetNurSelektierteOrphans(): void
    {
        $gruppe = $this->createStub(IGroup::class);
        $gruppe->method('getUsers')->willReturn([]);

        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => match ($key) {
                'fraktion' => 'SP',
                'nextcloud_gruppe' => 'sp-gruppe',
                'mitglied_ids' => [],
                'orphan_uids' => ['zu-loeschen'],
                'mappings' => [],
                default => $default,
            }
        );
        $request->method('offsetExists')->willReturnCallback(
            static fn(string $key): bool => in_array($key, ['fraktion', 'nextcloud_gruppe', 'mitglied_ids', 'orphan_uids', 'mappings'], true)
        );

        $store = ['nextcloud_gruppe' => 'sp-gruppe', 'fraktion' => 'SP'];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('get')->willReturn($gruppe);
        $groupManager->method('groupExists')->willReturn(true);

        // Tracking: welche UIDs werden für Orphan-Verarbeitung aufgerufen?
        $lookedUpForOrphan = [];
        $zuLoeschenUser = $this->makeNCUser('zu-loeschen');
        $userManager = $this->createStub(IUserManager::class);
        $userManager->method('get')->willReturnCallback(
            function (string $uid) use ($zuLoeschenUser, &$lookedUpForOrphan): ?IUser {
                $lookedUpForOrphan[] = $uid;
                return $uid === 'zu-loeschen' ? $zuLoeschenUser : null;
            }
        );
        $userManager->method('getByEmail')->willReturn([]);

        $mitgliedService = $this->createStub(MitgliedService::class);
        $mitgliedService->method('aktiveDerFraktion')->willReturn([]);

        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $controller = new SettingsController(
            $request, $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $mitgliedService,
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $fraktionMapper,
            $groupManager,
            $userManager,
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        $data = $controller->provisionFraktionMitglieder()->getData();

        // Nur 'zu-loeschen' darf in der Orphan-Verarbeitungsschleife geladen worden sein
        self::assertContains('zu-loeschen', $lookedUpForOrphan, 'Selektierter Orphan muss verarbeitet werden');
        self::assertNotContains('erster-ersatz', $lookedUpForOrphan, 'Nicht selektierter Orphan darf nicht verarbeitet werden');
        self::assertEmpty($data['provision']['warnungen']);
    }

    /**
     * @param array<string, string> $requestParams
     * @param array<int, string|array{name: string, aktiv?: bool}> $fraktionen
     * @param array<string, IUser> $benutzer
     * @return array{0: SettingsController, 1: MockObject&IConfig, 2: MockObject&RealtimePublisherService}
     */
    public function testStatusKuerzelRoundtripListenformat(): void
    {
        $store = [];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static function (string $_a, string $k, string $d = '') use (&$store): string {
                return $store[$k] ?? $d;
            }
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_a, string $k, string $v) use (&$store): void {
                $store[$k] = $v;
            }
        );
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => $key === 'status_kuerzel'
                ? [
                    ['suche' => 'Kommission Bildung', 'kuerzel' => 'BSKK'],
                    ['suche' => '', 'kuerzel' => 'ignoriert'],
                ]
                : $default
        );
        $controller = $this->buildStatusController($request, $config);

        // Speichern verwirft ungültige Einträge und behält die Listenform.
        $resp = $controller->setStatusKuerzel();
        self::assertSame(Http::STATUS_OK, $resp->getStatus());
        self::assertSame([['suche' => 'Kommission Bildung', 'kuerzel' => 'BSKK']], $resp->getData());
        self::assertSame('[{"suche":"Kommission Bildung","kuerzel":"BSKK"}]', $store['status_kuerzel']);

        // Laden liefert exakt dieselbe Liste – kein {object Object}.
        $get = $controller->getStatusKuerzel();
        self::assertSame([['suche' => 'Kommission Bildung', 'kuerzel' => 'BSKK']], $get->getData());
    }

    public function testGetStatusKuerzelMigriertAlteMapForm(): void
    {
        $store = ['status_kuerzel' => json_encode(['Bei der Kommission pendent' => 'Pendent'])];
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $_a, string $k, string $d = ''): string => $store[$k] ?? $d
        );
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => $default
        );
        $controller = $this->buildStatusController($request, $config);

        $resp = $controller->getStatusKuerzel();
        self::assertSame(
            [['suche' => 'Bei der Kommission pendent', 'kuerzel' => 'Pendent']],
            $resp->getData()
        );
    }

    private function buildStatusController(IRequest $request, IConfig $config): SettingsController
    {
        return new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $this->createStub(FraktionMapper::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );
    }

    private function buildController(array $requestParams, array $fraktionen, array $benutzer): array
    {
        $request = $this->createStub(IRequest::class);
        $request->method('offsetExists')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $requestParams));
        $request->method('getParam')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $requestParams[$key] ?? $default);

        $store = [
            'fraktion' => '',
            'nextcloud_gruppe' => '',
            'kalender_nutzer' => '',
            'absender_email' => '',
            'absender_name' => 'Parlament Winterthur Tool',
            'letzte_synchronisation' => '',
        ];

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')
            ->willReturnCallback(static function (string $_app, string $key, string $default = '') use (&$store): string {
                return $store[$key] ?? $default;
            });

        $fraktionEntities = array_map(static function (string|array $eintrag): Fraktion {
            $fraktion = new Fraktion();
            if (is_array($eintrag)) {
                $fraktion->setName((string) ($eintrag['name'] ?? ''));
                $fraktion->setAktiv((bool) ($eintrag['aktiv'] ?? true));
            } else {
                $fraktion->setName($eintrag);
                $fraktion->setAktiv(true);
            }
            return $fraktion;
        }, $fraktionen);
        $fraktionMapper = $this->createStub(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn($fraktionEntities);

        $userManager = $this->createStub(IUserManager::class);
        $userManager->method('get')->willReturnCallback(static fn(string $uid): ?IUser => $benutzer[$uid] ?? null);
        $userManager->method('search')->willReturn([]);
        $userManager->method('getByEmail')->willReturn([]);
        $userManager->method('createUser')->willReturn(null);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);
        $groupManager->method('get')->willReturn(null);
        $groupManager->method('createGroup')->willReturn(null);
        $groupManager->method('search')->willReturn([]);
        $groupManager->method('getUserGroups')->willReturn([]);
        $groupManager->method('getUserGroupIds')->willReturn([]);

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('fraktionssitzungKontext')->willReturn(['modusAktiv' => false]);

        $publisher = $this->createMock(RealtimePublisherService::class);

        $controller = new SettingsController(
            $request,
            $config,
            $this->createStub(GeschaeftService::class),
            $this->createStub(SitzungService::class),
            $this->createStub(MitgliedService::class),
            $this->createStub(ScraperService::class),
            $this->createStub(KalenderService::class),
            $fraktionsarbeit,
            $publisher,
            $this->createStub(SyncLockService::class),
            $this->createStub(SyncProcessService::class),
            $fraktionMapper,
            $groupManager,
            $userManager,
            $this->createStub(FraktionsraumService::class),
            $this->createStub(EreignisService::class),
        );

        return [$controller, $config, $publisher];
    }
}
