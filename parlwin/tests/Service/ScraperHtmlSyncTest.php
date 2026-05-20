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
 * Tests fuer HTML-basierten Sync ohne DB-Schreibzugriffe.
 *
 * Fokus:
 * - echte, lokal gespeicherte HTML-Fixtures verwenden
 * - Parser/Importer auf reale Endpunkt-Strukturen pruefen
 * - Fehlerfall bei HTTP-Problemen pruefen
 */
class ScraperHtmlSyncTest extends TestCase {
    private IClientService $clientService;
    private IClient $client;
    private LoggerInterface $logger;
    private ScraperService $service;

    protected function setUp(): void {
        $this->clientService = $this->createMock(IClientService::class);
        $this->client = $this->createMock(IClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ScraperService(
            $this->clientService,
            $this->logger,
        );
    }

    public function testLadeGeschaefteAusLokalerFixture(): void {
        $listHtml = $this->ladeFixture('politbusiness-list.html');
        $detailHtml = $this->ladeFixture('geschaeft-detail-1388420.html');

        $this->erwarteHttpAntwortenMitResolver(static function (string $url) use ($listHtml, $detailHtml): string {
            if ($url === 'https://parlament.winterthur.ch/politbusiness') {
                return $listHtml;
            }
            if (str_contains($url, '/_rte/information/')) {
                return $detailHtml;
            }
            throw new \RuntimeException("Unerwartete URL: {$url}");
        });

        $entitaeten = $this->service->ladeGeschaefte();

        $this->assertNotEmpty($entitaeten);
        $this->assertSame('1388420', ScraperService::wert($entitaeten[0], ['id']));
        $this->assertStringContainsString('Wahl von zwei Mitgliedern', ScraperService::wert($entitaeten[0], ['title']));
        $this->assertSame('2021.82', ScraperService::wert($entitaeten[0], ['number']));
        $this->assertSame('Wahlen', ScraperService::wert($entitaeten[0], ['type']));
        $this->assertSame('Erledigt', ScraperService::wert($entitaeten[0], ['status']));
        $this->assertSame('2021-10-04', ScraperService::wert($entitaeten[0], ['date']));
        $this->assertSame('https://parlament.winterthur.ch/_rte/information/1388420', ScraperService::wert($entitaeten[0], ['url']));
    }

    public function testLadeSitzungenAusLokalerFixture(): void {
        $html = $this->ladeFixture('sitzung-list.html');
        $this->erwarteHttpAntwort('https://parlament.winterthur.ch/sitzung', $html);

        $entitaeten = $this->service->ladeSitzungen();

        $this->assertNotEmpty($entitaeten);
        $this->assertSame('7524445', ScraperService::wert($entitaeten[0], ['id']));
        $this->assertSame('2./3. Sitzungen', ScraperService::wert($entitaeten[0], ['title']));
        $this->assertSame('2026-06-01', ScraperService::wert($entitaeten[0], ['date']));
        $this->assertSame('16:15', ScraperService::wert($entitaeten[0], ['startTime']));
        $this->assertSame('22:00', ScraperService::wert($entitaeten[0], ['endTime']));
    }

    public function testLadeTraktandenAusLokalerDetailFixture(): void {
        $detailUrl = 'https://parlament.winterthur.ch/_rte/anlass/4855958';
        $html = $this->ladeFixture('sitzung-detail-4855958.html');

        $this->erwarteHttpAntwort($detailUrl, $html);

        $entitaeten = $this->service->ladeTraktanden($detailUrl);

        $this->assertNotEmpty($entitaeten);
        $this->assertSame(1, ScraperService::wert($entitaeten[0], ['number']));
        $this->assertStringContainsString('Wahl von zwei Mitgliedern', ScraperService::wert($entitaeten[0], ['title']));
        $this->assertSame('Wahlen', ScraperService::wert($entitaeten[0], ['type']));
        $this->assertSame('1388420', ScraperService::wert($entitaeten[0], ['businessId']));
    }

    public function testLadeMitgliederAusLokalerFixture(): void {
        $html = $this->ladeFixture('mitglieder-list.html');
        $this->erwarteHttpAntwort('https://parlament.winterthur.ch/stadtparlament/27428', $html);

        $entitaeten = $this->service->ladeMitglieder();

        $this->assertNotEmpty($entitaeten);
        $this->assertSame('285922', ScraperService::wert($entitaeten[0], ['id']));
        $this->assertSame('Albanese', ScraperService::wert($entitaeten[0], ['name']));
        $this->assertSame('Franco', ScraperService::wert($entitaeten[0], ['firstName']));
        $this->assertStringContainsString('SVP', ScraperService::wert($entitaeten[0], ['party']));
        $this->assertFalse((bool) ScraperService::wert($entitaeten[0], ['aktiv'], true));

        $mitEmail = array_values(array_filter(
            $entitaeten,
            static fn (array $m): bool => str_contains((string) ($m['email'] ?? ''), '@')
        ));
        $this->assertNotEmpty($mitEmail);
        $this->assertStringContainsString('@', (string) $mitEmail[0]['email']);

        $aktiveEintraege = array_values(array_filter(
            $entitaeten,
            static fn (array $m): bool => (bool) ($m['aktiv'] ?? false) === true
        ));
        $this->assertNotEmpty($aktiveEintraege);
        $aktiveIds = array_map(static fn (array $m): string => (string) ScraperService::wert($m, ['id']), $aktiveEintraege);
        $this->assertContains('403309', $aktiveIds);
    }

    public function testLadeKommissionenAusLokalerFixture(): void {
        $html = $this->ladeFixture('kommissionen-list.html');
        $detailHtml = $this->ladeFixture('kommission-detail-27446.html');

        $this->erwarteHttpAntwortenMitResolver(static function (string $url) use ($html, $detailHtml): string {
            if ($url === 'https://parlament.winterthur.ch/kommissionen') {
                return $html;
            }
            if ($url === 'https://parlament.winterthur.ch/_rte/behoerde/27446') {
                return $detailHtml;
            }
            if (str_contains($url, '/_rte/behoerde/')) {
                // Andere Kommissionen liefern leeres HTML → keine Mitglieder
                return '<html></html>';
            }
            throw new \RuntimeException("Unerwartete URL: {$url}");
        });

        $entitaeten = $this->service->ladeKommissionen();

        $this->assertNotEmpty($entitaeten);
        $this->assertSame('27446', ScraperService::wert($entitaeten[0], ['id']));
        $this->assertSame('Aufsichtskommission', ScraperService::wert($entitaeten[0], ['name']));

        // Mitgliederliste der Aufsichtskommission muss aus der Detailseite stammen
        $mitglieder = $entitaeten[0]['members'] ?? [];
        $this->assertIsArray($mitglieder);
        $this->assertNotEmpty($mitglieder, 'Aufsichtskommission sollte Mitglieder aus der Detailseite haben');

        $aktive = array_values(array_filter($mitglieder, static fn (array $m): bool => ($m['aktiv'] ?? false) === true));
        $inaktive = array_values(array_filter($mitglieder, static fn (array $m): bool => ($m['aktiv'] ?? true) === false));
        $this->assertNotEmpty($aktive, 'Es sollten aktive Mitglieder vorhanden sein');
        $this->assertNotEmpty($inaktive, 'Es sollten auch ehemalige Mitglieder vorhanden sein');

        $externIds = array_map(static fn (array $m): string => (string) $m['externId'], $mitglieder);
        $this->assertContains('403309', $externIds, 'Bachmann Miguel Pedro (aktiv) erwartet');
        $this->assertContains('285922', $externIds, 'Albanese Franco (ehemals) erwartet');

        // Dedupe: jede externId nur einmal
        $this->assertSame(count($externIds), count(array_unique($externIds)));
    }

    public function testExtrahiereBehoerdenMitgliederAusKommissionsDetail(): void {
        $html = $this->ladeFixture('kommission-detail-27446.html');

        $mitglieder = $this->service->extrahiereBehoerdenMitgliederAusHtml($html, 'Aufsichtskommission');

        $this->assertNotEmpty($mitglieder);

        $bachmann = array_values(array_filter(
            $mitglieder,
            static fn (array $m): bool => ($m['externId'] ?? '') === '403309'
        ))[0] ?? null;
        $this->assertNotNull($bachmann);
        $this->assertTrue($bachmann['aktiv']);
        $this->assertSame('Mitglied', $bachmann['funktion']);
        $this->assertSame('Bachmann Miguel', $bachmann['name']);
        $this->assertSame('Pedro', $bachmann['vorname']);
        $this->assertSame('2026-05-11', $bachmann['datumVon']);
        $this->assertSame('', $bachmann['datumBis']);

        $albanese = array_values(array_filter(
            $mitglieder,
            static fn (array $m): bool => ($m['externId'] ?? '') === '285922'
        ))[0] ?? null;
        $this->assertNotNull($albanese);
        $this->assertFalse($albanese['aktiv']);
        $this->assertSame('Mitglied', $albanese['funktion']);
        $this->assertSame('2018-05-13', $albanese['datumBis']);
    }

    public function testLadeFraktionenAusLokalerFixture(): void {
        $html = $this->ladeFixture('fraktionen-list.html');
        $detailHtml = $this->ladeFixture('fraktion-detail-28072.html');

        $this->erwarteHttpAntwortenMitResolver(static function (string $url) use ($html, $detailHtml): string {
            if ($url === 'https://parlament.winterthur.ch/fraktionen') {
                return $html;
            }
            if ($url === 'https://parlament.winterthur.ch/_rte/behoerde/28072') {
                return $detailHtml;
            }
            if (str_contains($url, '/_rte/behoerde/')) {
                return '<html></html>';
            }
            throw new \RuntimeException("Unerwartete URL: {$url}");
        });

        $entitaeten = $this->service->ladeFraktionen();

        $this->assertNotEmpty($entitaeten);
        $this->assertSame('28072', ScraperService::wert($entitaeten[0], ['id']));
        $this->assertSame('CVP-Fraktion', ScraperService::wert($entitaeten[0], ['name']));
        $this->assertSame('2012-01-01', ScraperService::wert($entitaeten[0], ['datumBis']));
        $this->assertFalse((bool) ScraperService::wert($entitaeten[0], ['aktiv'], true));

        $aktive = array_values(array_filter(
            $entitaeten,
            static fn (array $f): bool => (bool) ($f['aktiv'] ?? false) === true
        ));
        $this->assertNotEmpty($aktive);
        $this->assertSame('43405', ScraperService::wert($aktive[0], ['id']));
        $this->assertSame('Die Mitte (Die Mitte)', ScraperService::wert($aktive[0], ['name']));

        // CVP-Fraktion sollte Mitglieder aus der Detailseite enthalten
        $mitglieder = $entitaeten[0]['members'] ?? [];
        $this->assertIsArray($mitglieder);
        $this->assertNotEmpty($mitglieder, 'CVP-Fraktion sollte Mitglieder haben');
    }

    public function testLadeMitgliederBeiHttpFehlerGibtLeeresArrayZurueck(): void {
        $this->clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($this->client);

        $this->client->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('HTTP timeout'));

        $this->logger->expects($this->once())
            ->method('error');

        $entitaeten = $this->service->ladeMitglieder();
        $this->assertSame([], $entitaeten);
    }

    public function testExtrahiereMehrstufigeMotionDetailsAusLokalerFixture(): void {
        $html = $this->ladeFixture('geschaeft-detail-1582007.html');

        $details = $this->service->extrahiereGeschaeftDetailsAusHtml($html);

        $this->assertSame('2022.66', $details['number']);
        $this->assertSame('Motion', $details['type']);
        $this->assertSame('Erledigt', $details['status']);
        $this->assertSame('2022-06-27', $details['date']);

        $this->assertArrayHasKey('detailPairs', $details);
        $this->assertArrayHasKey('events', $details);
        $this->assertGreaterThan(10, count($details['detailPairs']));

        $ereignisTypen = array_map(
            static fn (array $event): string => (string) ($event['type'] ?? ''),
            array_filter($details['events'], 'is_array')
        );
        $ereignisWerte = array_map(
            static fn (array $event): string => (string) ($event['value'] ?? ''),
            array_filter($details['events'], 'is_array')
        );

        $this->assertContains('beschluss', $ereignisTypen);
        $this->assertContains('Erheblicherklärung', $ereignisWerte);
        $this->assertContains('Zustimmung', $ereignisWerte);
    }

    public function testExtrahierePostulatDetailsMitAbstimmungsdatenAusLokalerFixture(): void {
        $html = $this->ladeFixture('geschaeft-detail-2373919.html');

        $details = $this->service->extrahiereGeschaeftDetailsAusHtml($html);

        $this->assertSame('2025.28', $details['number']);
        $this->assertSame('Postulat', $details['type']);
        $this->assertArrayHasKey('detailFields', $details);
        $this->assertArrayHasKey('events', $details);

        $beschlussart = array_values(array_filter(
            $details['events'],
            static fn ($event): bool => is_array($event)
                && ($event['type'] ?? '') === 'beschlussart'
                && ($event['organ'] ?? '') === 'Stadtparlament'
        ));
        $this->assertNotEmpty($beschlussart);
        $this->assertSame('Überweisung', (string) $beschlussart[0]['value']);

        $fristen = array_values(array_filter(
            $details['events'],
            static fn ($event): bool => is_array($event) && ($event['type'] ?? '') === 'frist'
        ));
        $this->assertNotEmpty($fristen);
    }

    private function ladeFixture(string $datei): string
    {
        $pfad = __DIR__ . '/../Fixtures/html/' . $datei;
        $inhalt = @file_get_contents($pfad);
        $this->assertIsString($inhalt, "Fixture konnte nicht geladen werden: {$pfad}");
        return $inhalt;
    }

    private function erwarteHttpAntwort(string $url, string $html): void
    {
        $this->erwarteHttpAntwortenMitResolver(
            static fn(string $aufgerufeneUrl): string => $aufgerufeneUrl === $url
            ? $html
            : throw new \RuntimeException("Unerwartete URL: {$aufgerufeneUrl}")
        );
    }

    /**
     * @param callable(string): string $resolver
     */
    private function erwarteHttpAntwortenMitResolver(callable $resolver): void {
        $this->clientService->method('newClient')->willReturn($this->client);

        $this->client->method('get')->willReturnCallback(function (string $url, array $optionen) use ($resolver): IResponse {
            $this->assertArrayHasKey('timeout', $optionen);
            $this->assertGreaterThanOrEqual(5, (int) ($optionen['timeout'] ?? 0));
            $this->assertArrayHasKey('connect_timeout', $optionen);
            $this->assertGreaterThanOrEqual(1, (int) ($optionen['connect_timeout'] ?? 0));
            $this->assertArrayHasKey('User-Agent', $optionen['headers'] ?? []);
            $this->assertArrayHasKey('Accept', $optionen['headers'] ?? []);
            $this->assertArrayHasKey('Accept-Language', $optionen['headers'] ?? []);

            $html = $resolver($url);

            $response = $this->createMock(IResponse::class);
            $response->method('getBody')->willReturn($html);
            return $response;
        });
    }
}
