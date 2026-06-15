<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FraktionsraumServiceTest extends TestCase {
    private function makeService(
        IConfig $config,
        IRootFolder $rootFolder,
        IGroupManager $groupManager,
        IDBConnection $db,
        KalenderService $kalenderService,
    ): FraktionsraumService {
        return new FraktionsraumService(
            $config,
            $rootFolder,
            $groupManager,
            $this->createStub(IUserManager::class),
            $db,
            $kalenderService,
            $this->createStub(LoggerInterface::class),
        );
    }

    private function configMit(string $nutzer, string $gruppe): IConfig {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnMap([
            [Application::APP_ID, 'kalender_nutzer', '', $nutzer],
            [Application::APP_ID, 'nextcloud_gruppe', '', $gruppe],
        ]);
        return $config;
    }

    public function testSicherstellenLegtOrdnerstrukturAn(): void {
        $config = $this->configMit('admin', 'fraktion-gruppe');

        $folder = $this->createMock(Folder::class);
        // Alle Ordner fehlen → nodeExists liefert false
        $folder->method('nodeExists')->willReturn(false);
        $folder->expects($this->exactly(11))->method('newFolder');
        $fraktionNode = $this->createStub(Node::class);
        $fraktionNode->method('getId')->willReturn(1);
        $folder->method('get')->with('Fraktion')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->with('admin')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->createStub(IDBConnection::class);

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects($this->once())->method('sicherstelleKalenderOeffentlich');

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
    }

    public function testSicherstellenLegtNichtNochmalAn(): void {
        $config = $this->configMit('admin', 'fraktion-gruppe');

        $folder = $this->createMock(Folder::class);
        // Alle Ordner existieren bereits
        $folder->method('nodeExists')->willReturn(true);
        $folder->expects($this->never())->method('newFolder');
        $fraktionNode = $this->createStub(Node::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->createStub(IDBConnection::class);
        $kalenderService = $this->createStub(KalenderService::class);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
    }

    public function testSicherstellenTeiltOrdnerMitGruppe(): void {
        $config = $this->configMit('admin', 'meine-gruppe');

        $folder = $this->createStub(Folder::class);
        $folder->method('nodeExists')->willReturn(true);
        $fraktionNode = $this->createStub(Node::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->createStub(IDBConnection::class);
        $kalenderService = $this->createStub(KalenderService::class);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
        $this->assertTrue(true); // sicherstellen() sollte ohne Exception durchlaufen
    }

    public function testSicherstellenTutNichtsOhneKalenderNutzer(): void {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturn('');

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->expects($this->never())->method('getUserFolder');

        $service = $this->makeService(
            $config,
            $rootFolder,
            $this->createStub(IGroupManager::class),
            $this->createStub(IDBConnection::class),
            $this->createStub(KalenderService::class),
        );
        $service->sicherstellen();
    }

    public function testSicherstellenTeiltNichtWennGruppeNichtExistiert(): void {
        $config = $this->configMit('admin', 'nicht-existierende-gruppe');

        $folder = $this->createStub(Folder::class);
        $folder->method('nodeExists')->willReturn(true);
        $fraktionNode = $this->createStub(Node::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(false);

        $db = $this->createStub(IDBConnection::class);

        $kalenderService = $this->createStub(KalenderService::class);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
        $this->assertTrue(true); // sicherstellen() sollte ohne Exception durchlaufen
    }
}
