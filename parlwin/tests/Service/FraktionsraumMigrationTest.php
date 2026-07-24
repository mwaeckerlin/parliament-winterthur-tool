<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\DeckService;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Migration der Fraktions-Ordnerstruktur auf den aktuellen Stand.
 *
 * Ausgangslage in der produktiven Instanz ist die alte Struktur (40_Wahlkampf,
 * 50_Medien). Sie muss vollständig auf die neue Struktur wandern — die
 * Umbenennungen zuerst, damit die neue Nummer 40_Vorstösse nicht mit dem alten
 * 40_Wahlkampf kollidiert. Die Nummerierung läuft lückenlos in 10er-Schritten:
 * 40_Vorstösse, 50_Wahlkampf, 60_Medien.
 */
class FraktionsraumMigrationTest extends TestCase
{
    /** Ordner-Attrappe mit echtem Zustand: nodeExists/newFolder/move wirken zusammen. */
    private function adminOrdner(array $vorhanden, array &$angelegt, array &$verschoben, array &$geloescht): Folder
    {
        $set = array_fill_keys($vorhanden, true);
        $folder = $this->createStub(Folder::class);

        $folder->method('nodeExists')->willReturnCallback(
            function (string $pfad) use (&$set): bool {
                return isset($set[$pfad]);
            }
        );
        $folder->method('getFullPath')->willReturnCallback(
            static fn(string $pfad): string => $pfad
        );
        $folder->method('newFolder')->willReturnCallback(
            function (string $pfad) use (&$set, &$angelegt): Folder {
                $set[$pfad] = true;
                $angelegt[] = $pfad;
                return $this->createStub(Folder::class);
            }
        );
        $folder->method('get')->willReturnCallback(
            function (string $pfad) use (&$set, &$verschoben, &$geloescht): Folder {
                $node = $this->createStub(Folder::class);
                $node->method('move')->willReturnCallback(
                    function (string $ziel) use ($pfad, &$set, &$verschoben): Folder {
                        unset($set[$pfad]);
                        $set[$ziel] = true;
                        $verschoben[] = $pfad . ' → ' . $ziel;
                        return $this->createStub(Folder::class);
                    }
                );
                $node->method('delete')->willReturnCallback(
                    function () use ($pfad, &$set, &$geloescht): void {
                        unset($set[$pfad]);
                        $geloescht[] = $pfad;
                    }
                );
                // Für die Zusammenführung: alter Ordner ist leer, sofern nichts anderes gesetzt.
                $node->method('getDirectoryListing')->willReturn([]);
                return $node;
            }
        );

        return $folder;
    }

    private function makeService(IRootFolder $rootFolder): FraktionsraumService
    {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnMap([
            [Application::APP_ID, 'nextcloud_gruppe', '', 'fraktion-gruppe'],
            [Application::APP_ID, 'kalender_nutzer', '', 'dienst-nutzer'],
        ]);

        $groupManager = $this->createStub(IGroupManager::class);
        $groupManager->method('groupExists')->willReturn(true);

        return new FraktionsraumService(
            $config,
            $rootFolder,
            $groupManager,
            $this->createStub(IUserManager::class),
            $this->createStub(IDBConnection::class),
            $this->createStub(KalenderService::class),
            $this->createStub(IShareManager::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(DeckService::class),
        );
    }

    /** Die produktive Ausgangslage: alte Struktur, sieben Ordner. */
    private const LIVE_STRUKTUR = [
        'Fraktion',
        'Fraktion/00_Allgemein',
        'Fraktion/10_Sitzungen',
        'Fraktion/10_Sitzungen/2026',
        'Fraktion/20_Geschäfte',
        'Fraktion/30_Kommissionen',
        'Fraktion/30_Kommissionen/Aufsichtskommission',
        'Fraktion/30_Kommissionen/Sachkommission Bildung Sport Kultur',
        'Fraktion/40_Wahlkampf',
        'Fraktion/50_Medien',
        'Fraktion/90_Archiv',
    ];

    public function testLiveStrukturWirdVollstaendigMigriert(): void
    {
        $angelegt = [];
        $verschoben = [];
        $geloescht = [];
        $adminOrdner = $this->adminOrdner(self::LIVE_STRUKTUR, $angelegt, $verschoben, $geloescht);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($adminOrdner);

        $service = $this->makeService($rootFolder);
        $service->sicherstellen();

        // Die alten Namen werden umbenannt — Inhalte bleiben erhalten.
        self::assertContains(
            'Fraktion/40_Wahlkampf → Fraktion/50_Wahlkampf',
            $verschoben,
            'Bericht: ' . json_encode($service->getBericht(), JSON_UNESCAPED_UNICODE)
        );
        self::assertContains('Fraktion/50_Medien → Fraktion/60_Medien', $verschoben);

        // Nur die wirklich fehlenden Ordner werden neu angelegt.
        self::assertContains('Fraktion/40_Vorstösse', $angelegt);
        self::assertContains('Fraktion/40_Vorstösse/10_Eigene', $angelegt);
        self::assertContains('Fraktion/40_Vorstösse/20_Fremde', $angelegt);

        // «50_Finanzen» wurde nie gebraucht und ist keine Vorgabe — er darf
        // nicht angelegt werden. Die 50 gehört jetzt dem Wahlkampf.
        self::assertNotContains('Fraktion/50_Finanzen', $angelegt);

        // Die umbenannten Ordner dürfen NICHT zusätzlich leer neu angelegt werden.
        self::assertNotContains('Fraktion/50_Wahlkampf', $angelegt);
        self::assertNotContains('Fraktion/60_Medien', $angelegt);

        // Die früheren Zwischennummern (nie ausgeliefert) dürfen nicht entstehen.
        self::assertNotContains('Fraktion/60_Wahlkampf', $angelegt);
        self::assertNotContains('Fraktion/70_Medien', $angelegt);
    }

    public function testAbgebrocheneMigrationHinterlaesstKeineAltlast(): void
    {
        // Teilmigration: der alte UND der neue Ordner existieren nebeneinander.
        // Der alte muss zusammengeführt und entfernt werden — sonst bleiben
        // doppelte Nummern (40_Wahlkampf neben 50_Wahlkampf) liegen.
        $vorhanden = array_merge(self::LIVE_STRUKTUR, [
            'Fraktion/50_Wahlkampf',
            'Fraktion/60_Medien',
        ]);

        $angelegt = [];
        $verschoben = [];
        $geloescht = [];
        $adminOrdner = $this->adminOrdner($vorhanden, $angelegt, $verschoben, $geloescht);

        $rootFolder = $this->createStub(IRootFolder::class);
        $rootFolder->method('getUserFolder')->willReturn($adminOrdner);

        $this->makeService($rootFolder)->sicherstellen();

        self::assertContains(
            'Fraktion/40_Wahlkampf',
            $geloescht,
            'Der alte Ordner muss nach der Zusammenführung verschwinden'
        );
        self::assertContains(
            'Fraktion/50_Medien',
            $geloescht,
            'Der alte Ordner muss nach der Zusammenführung verschwinden'
        );
    }
}
