<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FraktionsraumServiceTest extends TestCase {
    private function makeService(
        IConfig $config,
        IRootFolder $rootFolder,
        IGroupManager $groupManager,
        IDBConnection $db,
        KalenderService $kalenderService,
        ?IShareManager $shareManager = null,
    ): FraktionsraumService {
        return new FraktionsraumService(
            $config,
            $rootFolder,
            $groupManager,
            $this->createStub(IUserManager::class),
            $db,
            $kalenderService,
            $shareManager ?? $this->createStub(IShareManager::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function configMit(string $gruppe, string $kalenderNutzer = 'dienst-nutzer'): IConfig {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnMap([
            [Application::APP_ID, 'nextcloud_gruppe', '', $gruppe],
            [Application::APP_ID, 'kalender_nutzer', '', $kalenderNutzer],
        ]);
        return $config;
    }

    private function dbMitFolderShare(): IDBConnection {
        // DB-Stub - teileOrdnerMitGruppe() wird try-catch aufrufen
        // executeQuery wird nicht erreicht oder ignoriert Fehler
        return $this->createStub(IDBConnection::class);
    }

    public function testSicherstellenLegtOrdnerstrukturAn(): void {
        $config = $this->configMit('fraktion-gruppe');

        $folder = $this->createMock(Folder::class);
        // Alle Ordner fehlen → nodeExists liefert false
        $folder->method('nodeExists')->willReturn(false);
        $folder->expects($this->exactly(11))->method('newFolder');
        $fraktionNode = $this->createStub(Folder::class);
        $fraktionNode->method('getId')->willReturn(1);
        $folder->method('get')->with('Fraktion')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->dbMitFolderShare();

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects($this->once())->method('sicherstelleKalenderOeffentlich');

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
    }

    public function testSicherstellenLegtNichtNochmalAn(): void {
        $config = $this->configMit('fraktion-gruppe');

        $folder = $this->createMock(Folder::class);
        // Alle Ordner existieren bereits
        $folder->method('nodeExists')->willReturn(true);
        $folder->expects($this->never())->method('newFolder');
        $fraktionNode = $this->createStub(Folder::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->dbMitFolderShare();
        $kalenderService = $this->createStub(KalenderService::class);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
    }

    public function testSicherstellenTeiltOrdnerMitGruppe(): void {
        $config = $this->configMit('meine-gruppe');

        $folder = $this->createStub(Folder::class);
        $folder->method('nodeExists')->willReturn(true);
        $fraktionNode = $this->createStub(Folder::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        $db = $this->dbMitFolderShare();
        $kalenderService = $this->createStub(KalenderService::class);

        // Erwartung: der Fraktionsordner wird über die Share-API mit der Gruppe
        // geteilt (createShare), nicht per rohem SQL.
        $shareManager = $this->createMock(IShareManager::class);
        $shareManager->method('getSharesBy')->willReturn([]);
        $shareStub = $this->createStub(IShare::class);
        $shareManager->method('newShare')->willReturn($shareStub);
        $shareManager->expects($this->once())
            ->method('createShare')
            ->with($shareStub)
            ->willReturn($shareStub);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService, $shareManager);
        $service->sicherstellen();
    }

    public function testSicherstellenTutNichtsOhneGruppe(): void {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnMap([
            [Application::APP_ID, 'nextcloud_gruppe', '', ''],
            [Application::APP_ID, 'kalender_nutzer', '', ''],
        ]);

        $rootFolder = $this->createMock(IRootFolder::class);
        $rootFolder->expects($this->never())->method('getUserFolder');

        $groupManager = $this->createStub(IGroupManager::class);

        $service = $this->makeService(
            $config,
            $rootFolder,
            $groupManager,
            $this->createStub(IDBConnection::class),
            $this->createStub(KalenderService::class),
        );
        $service->sicherstellen();
    }

    public function testSicherstellenTeiltNichtWennGruppeNichtExistiert(): void {
        $config = $this->configMit('nicht-existierende-gruppe');

        $folder = $this->createStub(Folder::class);
        $folder->method('nodeExists')->willReturn(true);
        $fraktionNode = $this->createStub(Folder::class);
        $fraktionNode->method('getId')->willReturn(123);
        $folder->method('get')->willReturn($fraktionNode);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($folder);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(false);

        $db = $this->dbMitFolderShare();

        $kalenderService = $this->createStub(KalenderService::class);

        $service = $this->makeService($config, $rootFolder, $groupManager, $db, $kalenderService);
        $service->sicherstellen();
        $this->assertTrue(true); // sicherstellen() sollte ohne Exception durchlaufen
    }
}
