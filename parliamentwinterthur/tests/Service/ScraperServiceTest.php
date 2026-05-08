<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit-Tests für ScraperService.
 *
 * Testet das Parsen von data-entities-Attributen aus HTML-Seiten.
 */
class ScraperServiceTest extends TestCase {
    private ScraperService $service;

    protected function setUp(): void {
        $this->service = new ScraperService(
            $this->createMock(IClientService::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * Testet die Extraktion eines einzelnen data-entities-Attributs.
     */
    public function testExtrahiereEntitaetenEinfach(): void {
        $html = <<<HTML
        <html>
        <body>
          <div class="list-view" data-entities="[{&quot;id&quot;:&quot;123&quot;,&quot;title&quot;:&quot;Test Geschäft&quot;}]">
          </div>
        </body>
        </html>
        HTML;

        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(1, $entitaeten);
        $this->assertSame('123', $entitaeten[0]['id']);
        $this->assertSame('Test Geschäft', $entitaeten[0]['title']);
    }

    /**
     * Testet die Extraktion mehrerer Entitäten aus einem data-entities-Attribut.
     */
    public function testExtrahiereEntitaetenMehrere(): void {
        $json = htmlspecialchars(json_encode([
            ['id' => '1', 'title' => 'Geschäft A', 'status' => 'offen'],
            ['id' => '2', 'title' => 'Geschäft B', 'status' => 'erledigt'],
            ['id' => '3', 'title' => 'Geschäft C', 'status' => 'pendent'],
        ]), ENT_QUOTES);

        $html = "<div data-entities=\"{$json}\"></div>";

        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(3, $entitaeten);
        $this->assertSame('Geschäft A', $entitaeten[0]['title']);
        $this->assertSame('Geschäft B', $entitaeten[1]['title']);
        $this->assertSame('Geschäft C', $entitaeten[2]['title']);
    }

    /**
     * Testet die Extraktion aus mehreren data-entities-Attributen auf einer Seite.
     */
    public function testExtrahiereEntitaetenMehrereBloecke(): void {
        $json1 = htmlspecialchars(json_encode([['id' => 'a1', 'title' => 'Block 1 Eintrag 1']]), ENT_QUOTES);
        $json2 = htmlspecialchars(json_encode([['id' => 'a2', 'title' => 'Block 2 Eintrag 1'], ['id' => 'a3', 'title' => 'Block 2 Eintrag 2']]), ENT_QUOTES);

        $html = "<div data-entities=\"{$json1}\"></div><div data-entities=\"{$json2}\"></div>";

        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(3, $entitaeten);
    }

    /**
     * Testet das Verhalten bei leerem HTML.
     */
    public function testExtrahiereEntitaetenLeeresHtml(): void {
        $entitaeten = $this->service->extrahiereEntitaeten('', 'Test');
        $this->assertCount(0, $entitaeten);
    }

    /**
     * Testet das Verhalten bei HTML ohne data-entities.
     */
    public function testExtrahiereEntitaetenOhneAttribut(): void {
        $html = '<html><body><div class="test">Kein data-entities hier</div></body></html>';
        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(0, $entitaeten);
    }

    /**
     * Testet das Verhalten bei ungültigem JSON im Attribut.
     */
    public function testExtrahiereEntitaetenUngueltigesJson(): void {
        $html = '<div data-entities="[ungültig json]"></div>';
        // Soll keine Exception werfen, sondern leeres Array zurückgeben
        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(0, $entitaeten);
    }

    /**
     * Testet die Hilfsfunktion wert() mit verschiedenen Schlüsseln.
     */
    public function testWertMitErstemVorhandenenSchluessel(): void {
        $daten = ['Id' => '456', 'title' => 'Test'];

        // Erster Schlüssel 'id' nicht vorhanden, zweiter 'Id' vorhanden
        $this->assertSame('456', ScraperService::wert($daten, ['id', 'Id', 'ID']));
    }

    /**
     * Testet die Hilfsfunktion wert() mit Standardwert.
     */
    public function testWertMitStandardwert(): void {
        $daten = ['status' => 'offen'];
        $this->assertSame('', ScraperService::wert($daten, ['datum', 'date']));
        $this->assertSame('unbekannt', ScraperService::wert($daten, ['datum', 'date'], 'unbekannt'));
    }

    /**
     * Testet die Hilfsfunktion wert() ignoriert leere Strings.
     */
    public function testWertIgnoriertLeereStrings(): void {
        $daten = ['titel' => '', 'title' => 'Voller Titel'];
        $this->assertSame('Voller Titel', ScraperService::wert($daten, ['titel', 'title']));
    }

    /**
     * Testet Extraktion einer einzelnen Entität (kein Array).
     */
    public function testExtrahiereEinzelneEntitaet(): void {
        $json = htmlspecialchars(json_encode(['id' => 'xyz', 'name' => 'Einzeleintrag']), ENT_QUOTES);
        $html = "<div data-entities=\"{$json}\"></div>";
        $entitaeten = $this->service->extrahiereEntitaeten($html, 'Test');
        $this->assertCount(1, $entitaeten);
        $this->assertSame('Einzeleintrag', $entitaeten[0]['name']);
    }
}
