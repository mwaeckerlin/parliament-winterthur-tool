<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\SettingsController;
use OCA\ParliamentWinterthur\Db\Fraktion;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
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
    public function testRunHaengtAnLaufendeSynchronisationAnWennLockAktiv(): void {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $store = [
            'sync_progress' => json_encode([
                'running' => false,
                'phase' => 'idle',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            }
        );

        $syncLock = $this->createMock(SyncLockService::class);
        $syncLock->method('isLocked')->willReturn(true);

        $syncProcess = $this->createMock(SyncProcessService::class);
        $publisher = $this->createMock(RealtimePublisherService::class);
        $fraktionMapper = $this->createMock(FraktionMapper::class);
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
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturn(null);
        $request->method('offsetExists')->willReturn(false);

        $store = [
            'sync_progress' => json_encode([
                'running' => false,
                'phase' => 'idle',
                'source' => 'admin-ui',
            ], JSON_UNESCAPED_UNICODE),
        ];
        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $_app, string $key, string $default = ''): string => $store[$key] ?? $default
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $_app, string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            }
        );

        $syncLock = $this->createMock(SyncLockService::class);
        $syncLock->method('isLocked')->willReturn(true);

        $syncProcess = $this->createMock(SyncProcessService::class);
        $publisher = $this->createMock(RealtimePublisherService::class);
        $fraktionMapper = $this->createMock(FraktionMapper::class);
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

    public function testSetLehntUnbekanntenKalenderBenutzerAbOhneTeilwrites(): void {
        [$controller, $config, $publisher] = $this->buildController(
            [
                'fraktion' => 'SP/Grüne',
                'kalender_nutzer' => 'nicht-vorhanden',
                'nextcloud_gruppe' => 'Fraktion-SP-Gruene',
            ],
            ['SP/Grüne'],
            []
        );

        $config->expects(self::never())->method('setAppValue');
        $publisher->expects(self::never())->method('publish');

        $response = $controller->set();
        self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
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
        $bekannterUser = $this->createMock(IUser::class);
        $bekannterUser->method('getUID')->willReturn('admin');
        $bekannterUser->method('getDisplayName')->willReturn('Admin');
        $bekannterUser->method('isEnabled')->willReturn(true);

        [$controller, $config, $publisher] = $this->buildController(
            [
                'fraktion' => 'SP/Grüne',
                'kalender_nutzer' => 'admin',
                'nextcloud_gruppe' => 'Fraktion-SP-Gruene',
                'absender_email' => 'noreply@example.com',
                'absender_name' => 'Parliament',
            ],
            ['SP/Grüne', 'FDP'],
            ['admin' => $bekannterUser]
        );

        $writes = [];
        $config->expects(self::exactly(5))
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
        self::assertSame('admin', $writes['kalender_nutzer'] ?? null);
        self::assertSame('Fraktion-SP-Gruene', $writes['nextcloud_gruppe'] ?? null);
        self::assertSame(['FDP', 'SP/Grüne'], $data['optionen']['fraktionen'] ?? []);
    }

    public function testFraktionMitgliederLiefertUsernameUndLokaleGruppen(): void {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => [
            'fraktion' => 'SP/Grüne',
        ][$key] ?? $default);

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturn('');

        $fraktionMapper = $this->createMock(FraktionMapper::class);
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

        $localUser = $this->createMock(IUser::class);
        $localUser->method('getUID')->willReturn('max-muster');
        $localUser->method('getDisplayName')->willReturn('Max Muster');
        $localUser->method('getEMailAddress')->willReturn('max@example.org');
        $localUser->method('isEnabled')->willReturn(true);

        $userManager = $this->createMock(IUserManager::class);
        $userManager->method('get')->with('max-muster')->willReturn($localUser);

        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('getUserGroupIds')->with($localUser)->willReturn(['Fraktion-SP-Gruene', 'users']);

        $publisher = $this->createMock(RealtimePublisherService::class);

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

    public function testProvisionFraktionMitgliederLegtUserAnUndFuegtGruppeHinzu(): void {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => [
            'fraktion' => 'SP/Grüne',
            'nextcloud_gruppe' => 'Fraktion-SP-Gruene',
            'mitglied_ids' => [7],
            'mappings' => [
                ['mitgliedId' => 7, 'username' => 'Max Muster'],
            ],
        ][$key] ?? $default);

        $config = $this->createMock(IConfig::class);
        $config->method('getAppValue')->willReturn('');

        $fraktionMapper = $this->createMock(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn([]);

        $mitglied = new Mitglied();
        $mitglied->setId(7);
        $mitglied->setExternId('777');
        $mitglied->setVorname('Max');
        $mitglied->setName('Muster');
        $mitglied->setFraktion('SP/Grüne');
        $mitglied->setEmail('max@example.org');

        $mitgliedService = $this->createMock(MitgliedService::class);
        $mitgliedService->method('eins')->with(7)->willReturn($mitglied);
        $mitgliedService->method('setzeNextcloudUid')->with(7, 'max-muster')->willReturn($mitglied);
        $mitgliedService->method('aktiveDerFraktion')->with('SP/Grüne')->willReturn([$mitglied]);

        $localUser = $this->createMock(IUser::class);
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
            ->with('max-muster', self::isType('string'))
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
            self::isType('array')
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
        );

        $response = $controller->provisionFraktionMitglieder();
        self::assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        self::assertIsArray($data);
        self::assertSame(1, (int) ($data['provision']['angelegt'] ?? 0));
        self::assertSame(1, (int) ($data['provision']['zurGruppeHinzugefuegt'] ?? 0));
    }

    /**
     * @param array<string, string> $requestParams
     * @param array<int, string|array{name: string, aktiv?: bool}> $fraktionen
     * @param array<string, IUser> $benutzer
     * @return array{0: SettingsController, 1: MockObject&IConfig, 2: MockObject&RealtimePublisherService}
     */
    private function buildController(array $requestParams, array $fraktionen, array $benutzer): array {
        $request = $this->createMock(IRequest::class);
        $request->method('offsetExists')->willReturnCallback(static fn (string $key): bool => array_key_exists($key, $requestParams));
        $request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => $requestParams[$key] ?? $default);

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
        $fraktionMapper = $this->createMock(FraktionMapper::class);
        $fraktionMapper->method('findAll')->willReturn($fraktionEntities);

        $userManager = $this->createMock(IUserManager::class);
        $userManager->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $benutzer[$uid] ?? null);
        $userManager->method('search')->willReturn([]);
        $userManager->method('getByEmail')->willReturn([]);
        $userManager->method('createUser')->willReturn(null);

        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);
        $groupManager->method('get')->willReturn(null);
        $groupManager->method('createGroup')->willReturn(null);
        $groupManager->method('search')->willReturn([]);
        $groupManager->method('getUserGroups')->willReturn([]);
        $groupManager->method('getUserGroupIds')->willReturn([]);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
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
        );

        return [$controller, $config, $publisher];
    }
}
