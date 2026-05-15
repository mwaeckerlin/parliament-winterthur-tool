<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
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

    public function testExtrahiereLinkAusHtml(): void {
        $result = ScraperService::extrahiereLinkAusHtml(
            '<a href="/_rte/information/1388420">Titel mit &uuml;mlaut</a>'
        );

        $this->assertSame('Titel mit ümlaut', $result['titel']);
        $this->assertSame('https://parlament.winterthur.ch/_rte/information/1388420', $result['url']);
        $this->assertSame('1388420', $result['externId']);
    }

    public function testExtrahiereGeschaeftDetailsAusHtml(): void {
        $html = <<<HTML
        <div class="icms-desclist-container">
            <dl class="row">
                <dt>Nummer</dt><dd>2026.42</dd>
                <dt>Geschäftsart</dt><dd>Interpellation</dd>
                <dt>Status</dt><dd>Erledigt</dd>
                <dt>Eingangsdatum</dt><dd>4. Oktober 2021</dd>
            </dl>
        </div>
        HTML;

        $details = $this->service->extrahiereGeschaeftDetailsAusHtml($html);
        $this->assertSame('2026.42', $details['number']);
        $this->assertSame('Interpellation', $details['type']);
        $this->assertSame('Erledigt', $details['status']);
        $this->assertSame('2021-10-04', $details['date']);
        $this->assertArrayHasKey('detailPairs', $details);
        $this->assertArrayHasKey('detailFields', $details);
        $this->assertArrayHasKey('events', $details);
        $this->assertSame('Nummer', $details['detailPairs'][0]['label']);
        $this->assertSame('nummer', $details['events'][0]['type']);
    }

    public function testLadeGeschaefteAusDataWrapperUndDetailseite(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'emptyColumns' => [],
            'data' => [[
                'title' => '<a href="/_rte/information/1388420">Beispiel Geschäft</a>',
                '_nummer' => '2026.1',
                '_geschaeftsdatum-sort' => '2026-01-15',
                '_kategorieId' => 'Motion',
            ]],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $detailHtml = <<<HTML
        <div class="icms-desclist-container">
            <dl class="row">
                <dt>Status</dt><dd>Erledigt</dd>
            </dl>
        </div>
        HTML;

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);
        $responseDetail = $this->createMock(IResponse::class);
        $responseDetail->method('getBody')->willReturn($detailHtml);

        $clientService->expects($this->exactly(2))
            ->method('newClient')
            ->willReturn($client);

        $aufgerufeneUrls = [];
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use (&$aufgerufeneUrls, $responseListe, $responseDetail) {
                $aufgerufeneUrls[] = $url;
                if (count($aufgerufeneUrls) === 1) {
                    return $responseListe;
                }
                return $responseDetail;
            });

        $geschaefte = $service->ladeGeschaefte();

        $this->assertCount(1, $geschaefte);
        $this->assertSame('1388420', $geschaefte[0]['id']);
        $this->assertSame('Beispiel Geschäft', $geschaefte[0]['title']);
        $this->assertSame('https://parlament.winterthur.ch/_rte/information/1388420', $geschaefte[0]['url']);
        $this->assertSame('2026.1', $geschaefte[0]['number']);
        $this->assertSame('Motion', $geschaefte[0]['type']);
        $this->assertSame('2026-01-15', $geschaefte[0]['date']);
        $this->assertSame('Erledigt', $geschaefte[0]['status']);

        $this->assertSame('https://parlament.winterthur.ch/politbusiness', $aufgerufeneUrls[0]);
        $this->assertSame('https://parlament.winterthur.ch/_rte/information/1388420', $aufgerufeneUrls[1]);
    }

    public function testLadeSitzungenNormalisiertNameLinkUndDatum(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'emptyColumns' => [],
            'data' => [[
                'name' => '<a href="/_rte/anlass/7524445">2./3. Sitzungen</a>',
                '_datum' => '<span class="text-nowrap">01.06.2026, 16.15 Uhr - 22.00 Uhr </span>',
            ]],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);
        $client->expects($this->once())
            ->method('get')
            ->with($this->identicalTo('https://parlament.winterthur.ch/sitzung'))
            ->willReturn($responseListe);

        $sitzungen = $service->ladeSitzungen();

        $this->assertCount(1, $sitzungen);
        $this->assertSame('7524445', $sitzungen[0]['id']);
        $this->assertSame('2./3. Sitzungen', $sitzungen[0]['title']);
        $this->assertSame('https://parlament.winterthur.ch/_rte/anlass/7524445', $sitzungen[0]['url']);
        $this->assertSame('2026-06-01', $sitzungen[0]['date']);
        $this->assertSame('16:15', $sitzungen[0]['startTime']);
        $this->assertSame('22:00', $sitzungen[0]['endTime']);
    }

    public function testLadeFraktionenLeitetAktivAusDatumBisAb(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'data' => [
                [
                    'name' => '<a href="/_rte/behoerde/28072">CVP-Fraktion</a>',
                    'datumBis' => '01.01.2012',
                    'kategorieId' => 'Fraktion',
                ],
                [
                    'name' => '<a href="/_rte/behoerde/43405">Die Mitte (Die Mitte)</a>',
                    'datumBis' => '',
                    'kategorieId' => 'Fraktion',
                ],
            ],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);

        $responseLeer = $this->createMock(IResponse::class);
        $responseLeer->method('getBody')->willReturn('<html></html>');

        $clientService->method('newClient')->willReturn($client);
        $client->method('get')
            ->willReturnCallback(function (string $url) use ($responseListe, $responseLeer): IResponse {
                if ($url === 'https://parlament.winterthur.ch/fraktionen') {
                    return $responseListe;
                }
                return $responseLeer;
            });

        $fraktionen = $service->ladeFraktionen();

        $this->assertCount(2, $fraktionen);
        $this->assertSame('2012-01-01', $fraktionen[0]['datumBis']);
        $this->assertFalse((bool) $fraktionen[0]['aktiv']);
        $this->assertSame('', $fraktionen[1]['datumBis']);
        $this->assertTrue((bool) $fraktionen[1]['aktiv']);
    }

    public function testPrefetchTopLevelListenVerwendetCacheBeimNachfolgendenLaden(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'data' => [[
                'name' => '<a href="/_rte/anlass/7524445">2./3. Sitzungen</a>',
                '_datum' => '<span class="text-nowrap">01.06.2026, 16.15 Uhr - 22.00 Uhr </span>',
            ]],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('get')
            ->with('https://parlament.winterthur.ch/sitzung', $this->isType('array'))
            ->willReturn($responseListe);

        $service->prefetchTopLevelListen(['sitzungen']);
        $sitzungen = $service->ladeSitzungen();

        $this->assertCount(1, $sitzungen);
        $this->assertSame('7524445', $sitzungen[0]['id']);
    }

    public function testVorabTotalsFuerSyncVerdoppeltGeschaefteFuerZweiphasenFortschritt(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'data' => [[
                'title' => '<a href="/_rte/information/1388420">Beispiel Geschäft</a>',
                '_nummer' => '2026.1',
                '_geschaeftsdatum-sort' => '2026-01-15',
                '_kategorieId' => 'Motion',
            ]],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('get')
            ->with('https://parlament.winterthur.ch/politbusiness', $this->isType('array'))
            ->willReturn($responseListe);

        $service->prefetchTopLevelListen(['geschaefte']);
        $totals = $service->vorabTotalsFuerSync();

        $this->assertSame(2, $totals['geschaefte'] ?? 0);
    }

    public function testLadeGeschaefteMeldetFortschrittImSequenziellenDetailFallbackProElement(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $listenRohdaten = [
            'data' => [
                [
                    'title' => '<a href="/_rte/information/1001">Geschäft A</a>',
                    '_nummer' => '2026.100',
                    '_geschaeftsdatum-sort' => '2026-01-10',
                    '_kategorieId' => 'Motion',
                ],
                [
                    'title' => '<a href="/_rte/information/1002">Geschäft B</a>',
                    '_nummer' => '2026.101',
                    '_geschaeftsdatum-sort' => '2026-01-11',
                    '_kategorieId' => 'Postulat',
                ],
            ],
        ];
        $listenJson = htmlspecialchars((string) json_encode($listenRohdaten), ENT_QUOTES);
        $listenHtml = "<div data-entities=\"{$listenJson}\"></div>";

        $responseListe = $this->createMock(IResponse::class);
        $responseListe->method('getBody')->willReturn($listenHtml);
        $responseDetailA = $this->createMock(IResponse::class);
        $responseDetailA->method('getBody')->willReturn('<dt>Status</dt><dd>Offen</dd>');
        $responseDetailB = $this->createMock(IResponse::class);
        $responseDetailB->method('getBody')->willReturn('<dt>Status</dt><dd>Erledigt</dd>');

        $clientService->expects($this->exactly(3))
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(static function (string $url) use ($responseListe, $responseDetailA, $responseDetailB): IResponse {
                return match ($url) {
                    'https://parlament.winterthur.ch/politbusiness' => $responseListe,
                    'https://parlament.winterthur.ch/_rte/information/1001' => $responseDetailA,
                    'https://parlament.winterthur.ch/_rte/information/1002' => $responseDetailB,
                    default => throw new \RuntimeException('Unerwartete URL: ' . $url),
                };
            });

        $events = [];
        $geschaefte = $service->ladeGeschaefte(static function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertCount(2, $geschaefte);
        $this->assertNotEmpty($events);

        $totals = array_values(array_unique(array_map(static fn (array $e): int => (int) ($e['total'] ?? -1), $events)));
        $this->assertSame([2], $totals);

        $processedList = array_map(static fn (array $e): int => (int) ($e['processed'] ?? -1), $events);
        $this->assertContains(0, $processedList);
        $this->assertContains(1, $processedList);
        $this->assertContains(2, $processedList);
        $this->assertSame(2, max($processedList));
    }

    public function testLadeMitgliederFiltertNichtPersonenUndExtrahiertBasisfelder(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $mitgliederBlock = htmlspecialchars((string) json_encode([
            'emptyColumns' => [],
            'data' => [
                [
                    '_nameVorname' => '<a href="/_rte/person/285922">Albanese Franco</a>',
                    '_partei' => '<a href="/_rte/partei/5759">Schweizerische Volkspartei (SVP)</a>',
                    '_taetigInAlle' => '<a href="/_rte/behoerde/28072">CVP-Fraktion</a> (Mitglied)',
                    '_kontakt' => '<a href="mailto:franco@example.org">franco@example.org</a>',
                    '_thumbnail' => '/media/franco.jpg',
                    '_funktionInaktiv' => 'Mitglied',
                    '_mandatPersonDatumBis' => '04.09.2016',
                ],
                [
                    '_nameVorname' => '<a href="/_rte/person/403309">Bachmann Miguel Pedro</a>',
                    '_partei' => '<a href="/_rte/partei/5780">Alternative Linke (AL)</a>',
                    '_taetigInAlle' => '<a href="/_rte/behoerde/27464">Grüne/AL-Fraktion</a> (Mitglied)',
                    '_kontakt' => '<a href="mailto:miguel@example.org">miguel@example.org</a>',
                    '_funktionAktiv' => 'Mitglied',
                    '_mandatPersonDatumVon' => '11.05.2026',
                ],
            ],
        ]), ENT_QUOTES);

        $sitzungenBlock = htmlspecialchars((string) json_encode([
            'emptyColumns' => [],
            'data' => [[
                'name' => '<a href="/_rte/anlass/7524445">2./3. Sitzungen</a>',
                '_datum' => '01.06.2026',
            ]],
        ]), ENT_QUOTES);

        $html = "<div data-entities=\"{$mitgliederBlock}\"></div><div data-entities=\"{$sitzungenBlock}\"></div>";

        $response = $this->createMock(IResponse::class);
        $response->method('getBody')->willReturn($html);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);
        $client->expects($this->once())
            ->method('get')
            ->with($this->identicalTo('https://parlament.winterthur.ch/stadtparlament/27428'))
            ->willReturn($response);

        $mitglieder = $service->ladeMitglieder();

        $this->assertCount(2, $mitglieder);
        $this->assertSame('285922', $mitglieder[0]['id']);
        $this->assertSame('Albanese', $mitglieder[0]['name']);
        $this->assertSame('Franco', $mitglieder[0]['firstName']);
        $this->assertSame('Schweizerische Volkspartei (SVP)', $mitglieder[0]['party']);
        $this->assertSame('CVP-Fraktion', $mitglieder[0]['faction']);
        $this->assertSame('franco@example.org', $mitglieder[0]['email']);
        $this->assertSame('https://parlament.winterthur.ch/media/franco.jpg', $mitglieder[0]['photoUrl']);
        $this->assertFalse((bool) $mitglieder[0]['aktiv']);

        $this->assertSame('403309', $mitglieder[1]['id']);
        $this->assertTrue((bool) $mitglieder[1]['aktiv']);
    }

    public function testLadeTraktandenAusSitzungsHtmlTabelle(): void {
        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new ScraperService($clientService, $logger);

        $html = <<<HTML
        <table>
            <tbody>
                <tr id="traktanden_88649">
                    <td>1</td>
                    <td><div class="icms-wysiwyg">Wahl von zwei Mitgliedern</div></td>
                    <td>Wahlen</td>
                    <td><a href="/_rte/information/1388420">2021.82</a></td>
                </tr>
            </tbody>
        </table>
        HTML;

        $response = $this->createMock(IResponse::class);
        $response->method('getBody')->willReturn($html);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);
        $client->expects($this->once())
            ->method('get')
            ->with($this->identicalTo('https://parlament.winterthur.ch/_rte/anlass/4855958'))
            ->willReturn($response);

        $traktanden = $service->ladeTraktanden('https://parlament.winterthur.ch/_rte/anlass/4855958');

        $this->assertCount(1, $traktanden);
        $this->assertSame('88649', $traktanden[0]['id']);
        $this->assertSame(1, $traktanden[0]['number']);
        $this->assertSame('Wahl von zwei Mitgliedern', $traktanden[0]['title']);
        $this->assertSame('Wahlen', $traktanden[0]['type']);
        $this->assertSame('1388420', $traktanden[0]['businessId']);
        $this->assertSame('https://parlament.winterthur.ch/_rte/information/1388420', $traktanden[0]['url']);
    }
}
