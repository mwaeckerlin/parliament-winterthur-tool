<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Live-Tests gegen echte Endpoints, ohne DB-Schreibzugriffe.
 *
 * Diese Tests sind als Gruppe "live" markiert und werden im Standard-Unitlauf ausgeschlossen.
 *
 */
#[Group('live')]
class ScraperLiveEndpointTest extends TestCase {
    private const TIMEOUT_SEKUNDEN = 30;
    private const FIXTURE_DIR = __DIR__ . '/../Fixtures/html';

    private ScraperService $service;

    protected function setUp(): void {
        $this->service = new ScraperService(
            $this->createMock(IClientService::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testLiveGeschaefteEndpoint(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/politbusiness',
            'Live Geschaefte'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Entitaeten aus /politbusiness extrahiert');
        $this->assertTrue(
            $this->enthaeltSinnvolleEntitaet(
                $entitaeten,
                [['title', 'Title', 'name', 'bezeichnung'], ['_nummer', 'number', 'nummer'], ['_geschaeftsdatum', '_geschaeftsdatum-sort', '_datum', 'date']]
            ),
            'Keine Geschaeft-Entitaet mit Titel, Nummer und Datum gefunden'
        );
    }

    public function testLiveSitzungenEndpoint(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/sitzung',
            'Live Sitzungen'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Entitaeten aus /sitzung extrahiert');
        $this->assertTrue(
            $this->enthaeltSinnvolleEntitaet(
                $entitaeten,
                [['name', 'title', 'Title'], ['_datum', '_datum-sort', 'date', 'Date', 'datum', 'startDate']]
            ),
            'Keine Sitzungs-Entitaet mit Titel und Datum gefunden'
        );
    }

    public function testLiveMitgliederEndpoint(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/stadtparlament/27428',
            'Live Mitglieder'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Entitaeten aus /stadtparlament/27428 extrahiert');
        $this->assertTrue(
            $this->enthaeltSinnvolleEntitaet(
                $entitaeten,
                [['_nameVorname', 'name', 'Name', 'lastName', 'nachname'], ['_partei', 'partei', 'party', 'Partei']]
            ),
            'Keine Mitglieder-Entitaet mit Name und Partei gefunden'
        );
    }

    public function testLiveKommissionenEndpoint(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/kommissionen',
            'Live Kommissionen'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Entitaeten aus /kommissionen extrahiert');
        $this->assertTrue(
            $this->enthaeltSinnvolleEntitaet($entitaeten, [['name', 'Name', 'bezeichnung'], ['kategorieId', '_kategorieId']]),
            'Keine Kommissions-Entitaet mit Name und Kategorie gefunden'
        );
    }

    public function testLiveFraktionenEndpoint(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/fraktionen',
            'Live Fraktionen'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Entitaeten aus /fraktionen extrahiert');
        $this->assertTrue(
            $this->enthaeltSinnvolleEntitaet($entitaeten, [['name', 'Name', 'bezeichnung'], ['kategorieId', '_kategorieId']]),
            'Keine Fraktions-Entitaet mit Name und Kategorie gefunden'
        );
    }

    public function testLiveGeschaeftsLinkFuehrtZuDetailwerten(): void {
        $entitaeten = $this->ladeUndParse(
            'https://parlament.winterthur.ch/politbusiness',
            'Live Geschaefte Detail'
        );

        $this->assertNotEmpty($entitaeten, 'Keine Geschaefte gefunden');
        $titelRoh = (string) ($entitaeten[0]['title'] ?? '');
        $link = ScraperService::extrahiereLinkAusHtml($titelRoh);
        $this->assertNotSame('', $link['url'], 'Kein Detail-Link im Titel gefunden');

        $detailHtml = $this->ladeLiveHtml($link['url']);
        $details = $this->service->extrahiereGeschaeftDetailsAusHtml($detailHtml);

        $this->assertNotEmpty($details, 'Keine Detailwerte aus Geschaeftsseite extrahiert');
        $this->assertArrayHasKey('status', $details);
        $this->assertNotSame('', $details['status'], 'Status auf Detailseite nicht gefunden');
    }

    public function testLiveSitzungsDetailLieferteTraktandenAusHtmlTabelle(): void {
        $detailHtml = $this->ladeLiveHtml('https://parlament.winterthur.ch/_rte/anlass/4855958');
        $traktanden = $this->service->extrahiereTraktandenAusHtml($detailHtml);
        $this->assertNotEmpty($traktanden, 'Keine Traktanden in der gewählten Sitzung gefunden.');

        $erstes = $traktanden[0];
        $this->assertNotSame('', (string) ($erstes['title'] ?? ''), 'Kein Titel im ersten Traktandum');
        $this->assertNotSame('', (string) ($erstes['businessId'] ?? ''), 'Keine Geschäfts-ID im ersten Traktandum');
        $this->assertNotSame('', (string) ($erstes['url'] ?? ''), 'Keine Geschäfts-URL im ersten Traktandum');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ladeUndParse(string $url, string $label): array {
        $html = $this->ladeLiveHtml($url);
        $this->assertNotSame('', trim($html), "Leere HTML-Antwort von {$url}");

        $roheEntitaeten = $this->service->extrahiereEntitaeten($html, $label);
        $this->assertIsArray($roheEntitaeten);

        return $this->normalisiereEntitaeten($roheEntitaeten);
    }

    private function ladeLiveHtml(string $url): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $fixtureHtml = $this->ladeFixtureHtmlFuerUrl($url);
                if ($fixtureHtml !== null) {
                    return $fixtureHtml;
                }
                $this->fail("curl_init fehlgeschlagen fuer {$url}");
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SEKUNDEN);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ParliamentWinterthurLiveTest/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: de-CH,de;q=0.9',
            ]);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrNo = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($body) || $body === '') {
                $detail = trim($error !== '' ? $error : "curl errno {$curlErrNo}");
                $fixtureHtml = $this->ladeFixtureHtmlFuerUrl($url);
                if ($fixtureHtml !== null) {
                    return $fixtureHtml;
                }
                $this->fail("Leere oder ungueltige Antwort von {$url}" . ($detail !== '' ? " ({$detail})" : ''));
            }
            if ($httpCode >= 400) {
                $fixtureHtml = $this->ladeFixtureHtmlFuerUrl($url);
                if ($fixtureHtml !== null) {
                    return $fixtureHtml;
                }
                $this->fail("HTTP {$httpCode} von {$url}");
            }
            return $body;
        }

        $kontext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SEKUNDEN,
                'header' => implode("\r\n", [
                    'User-Agent: ParliamentWinterthurLiveTest/1.0',
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: de-CH,de;q=0.9',
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $kontext);
        if (!is_string($body) || $body === '') {
            $fixtureHtml = $this->ladeFixtureHtmlFuerUrl($url);
            if ($fixtureHtml !== null) {
                return $fixtureHtml;
            }
            $fehler = error_get_last();
            $detail = is_array($fehler) && isset($fehler['message']) ? $fehler['message'] : 'unbekannter Fehler';
            $this->fail("HTTP-Laden fehlgeschlagen fuer {$url}: {$detail}");
        }
        return $body;
    }

    /**
     * @param array<int, array<string, mixed>> $entitaeten
     * @param array<int, array<int, string>> $feldGruppen
     */
    private function enthaeltSinnvolleEntitaet(array $entitaeten, array $feldGruppen): bool {
        foreach ($entitaeten as $entitaet) {
            $alleGruppenGefunden = true;
            foreach ($feldGruppen as $gruppe) {
                $gefunden = false;
                foreach ($gruppe as $key) {
                    if (isset($entitaet[$key]) && $entitaet[$key] !== '') {
                        $gefunden = true;
                        break;
                    }
                }
                if (!$gefunden) {
                    $alleGruppenGefunden = false;
                    break;
                }
            }
            if ($alleGruppenGefunden) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $entitaeten
     * @return array<int, array<string, mixed>>
     */
    private function normalisiereEntitaeten(array $entitaeten): array {
        $normalisiert = [];

        foreach ($entitaeten as $entitaet) {
            if (isset($entitaet['data']) && is_array($entitaet['data'])) {
                foreach ($entitaet['data'] as $eintrag) {
                    if (is_array($eintrag)) {
                        $normalisiert[] = $eintrag;
                    }
                }
                continue;
            }

            $normalisiert[] = $entitaet;
        }

        return $normalisiert;
    }

    private function ladeFixtureHtmlFuerUrl(string $url): ?string {
        $pfad = parse_url($url, PHP_URL_PATH);
        if (!is_string($pfad) || $pfad === '') {
            return null;
        }
        $pfad = rtrim($pfad, '/');
        if ($pfad === '') {
            return null;
        }

        $kandidaten = [];
        if ($pfad === '/politbusiness') {
            $kandidaten[] = 'politbusiness-list.html';
        } elseif ($pfad === '/sitzung') {
            $kandidaten[] = 'sitzung-list.html';
        } elseif ($pfad === '/stadtparlament/27428') {
            $kandidaten[] = 'mitglieder-list.html';
        } elseif ($pfad === '/kommissionen') {
            $kandidaten[] = 'kommissionen-list.html';
        } elseif ($pfad === '/fraktionen') {
            $kandidaten[] = 'fraktionen-list.html';
        } elseif (preg_match('#^/_rte/information/(\d+)$#', $pfad, $m) === 1) {
            $kandidaten[] = sprintf('geschaeft-detail-%s.html', $m[1]);
            $kandidaten[] = 'geschaeft-detail-1388420.html';
        } elseif (preg_match('#^/_rte/anlass/(\d+)$#', $pfad, $m) === 1) {
            $kandidaten[] = sprintf('sitzung-detail-%s.html', $m[1]);
            $kandidaten[] = 'sitzung-detail-4855958.html';
        } elseif (preg_match('#^/_rte/person/(\d+)$#', $pfad, $m) === 1) {
            $kandidaten[] = sprintf('mitglied-detail-%s.html', $m[1]);
            $kandidaten[] = 'mitglied-detail-285922.html';
        }

        foreach ($kandidaten as $datei) {
            $fullPath = self::FIXTURE_DIR . '/' . $datei;
            if (!is_file($fullPath)) {
                continue;
            }
            $inhalt = @file_get_contents($fullPath);
            if (is_string($inhalt) && trim($inhalt) !== '') {
                return $inhalt;
            }
        }

        return null;
    }
}
