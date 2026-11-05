<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Das Lesen der Stadtratsbeschlüsse von der Webseite der Stadt (F144, F146).
 *
 * Gemessen wird gegen die gespeicherten Seiten in
 * `parlwin/tests/Fixtures/html`: die Listenseite mit zwölf Beschlüssen und die
 * Detailseite des Beschlusses 2026/1279 mit seinen zwei Dokumenten. Damit läuft
 * der Test ohne Netz und liefert jedes Mal dasselbe Ergebnis.
 */
class StadtratsbeschluesseTest extends TestCase
{
    private ScraperService $service;

    protected function setUp(): void
    {
        // Ohne das Leeren gilt, was der Lauf von aussen mitbringt: im
        // E2E-Stapel zeigt PARLWIN_STADT_BASE_URL auf die Gegenstelle.
        putenv('PARLWIN_STADT_BASE_URL');
        $this->service = new ScraperService(
            $this->createStub(IClientService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    protected function tearDown(): void
    {
        putenv('PARLWIN_STADT_BASE_URL');
    }

    private function fixture(string $name): string
    {
        $pfad = __DIR__ . '/../Fixtures/html/' . $name;
        $inhalt = file_get_contents($pfad);
        $this->assertNotFalse($inhalt, "Fixture fehlt: {$name}");
        return (string) $inhalt;
    }

    public function testListeLiestAlleZwoelfBeschluesse(): void
    {
        $eintraege = $this->service->extrahiereStadtratsbeschluesseAusHtml(
            $this->fixture('stadtratsbeschluesse-list.html')
        );

        $this->assertCount(12, $eintraege);
    }

    public function testListeLiestNummerTitelDatumUndDepartement(): void
    {
        $eintraege = $this->service->extrahiereStadtratsbeschluesseAusHtml(
            $this->fixture('stadtratsbeschluesse-list.html')
        );
        $erster = $eintraege[0];

        $this->assertSame('20261279', $erster['externId']);
        $this->assertSame('2026/1279', $erster['nummer']);
        $this->assertSame('Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)', $erster['titel']);
        $this->assertSame('2026-10-21', $erster['datum']);
        $this->assertSame('Stadtkanzlei', $erster['departement']);
        $this->assertSame('Stadtratssitzung vom 21. Oktober 2026', $erster['sitzung']);
        $this->assertStringEndsWith('/stadtratsbeschluesse/2026-10-21/20261279', $erster['url']);
    }

    public function testJederEintragTraegtNummerDatumUndDepartement(): void
    {
        $eintraege = $this->service->extrahiereStadtratsbeschluesseAusHtml(
            $this->fixture('stadtratsbeschluesse-list.html')
        );

        foreach ($eintraege as $eintrag) {
            $this->assertMatchesRegularExpression('#^\d{4}/\d+$#', $eintrag['nummer']);
            $this->assertMatchesRegularExpression('#^\d{4}-\d{2}-\d{2}$#', $eintrag['datum']);
            $this->assertNotSame('', $eintrag['titel']);
            $this->assertNotSame('', $eintrag['departement']);
        }
    }

    /** Die Departemente der gespeicherten Seite, ohne Erfindung. */
    public function testDieDepartementeDerGespeichertenSeite(): void
    {
        $eintraege = $this->service->extrahiereStadtratsbeschluesseAusHtml(
            $this->fixture('stadtratsbeschluesse-list.html')
        );
        $departemente = array_values(array_unique(array_map(
            static fn(array $e): string => $e['departement'],
            $eintraege
        )));
        sort($departemente);

        $this->assertSame([
            'Bau und Mobilität',
            'Finanzen',
            'Präsidiales',
            'Schule und Sport',
            'Soziales',
            'Stadtkanzlei',
        ], $departemente);
    }

    public function testSeitenzahlKommtAusDerPaginierung(): void
    {
        $this->assertSame(
            264,
            $this->service->extrahiereLetzteSeitenzahl($this->fixture('stadtratsbeschluesse-list.html'))
        );
    }

    public function testSeitenzahlOhnePaginierungIstEins(): void
    {
        $this->assertSame(1, $this->service->extrahiereLetzteSeitenzahl('<html><body>nichts</body></html>'));
    }

    public function testDetailseiteLiestDepartementSitzungUndVeroeffentlichung(): void
    {
        $details = $this->service->extrahiereStadtratsbeschlussDetailsAusHtml(
            $this->fixture('stadtratsbeschluss-detail-20261279.html')
        );

        $this->assertSame('Stadtkanzlei', $details['departement']);
        $this->assertSame('Stadtratssitzung vom 21. Oktober 2026', $details['sitzung']);
        // Die Quelle schreibt «28.&nbsp;Oktober&nbsp;2026».
        $this->assertSame('2026-10-28', $details['publiziertAm']);
    }

    public function testDetailseiteLiestBeideDokumenteMitTitelUndAdresse(): void
    {
        $details = $this->service->extrahiereStadtratsbeschlussDetailsAusHtml(
            $this->fixture('stadtratsbeschluss-detail-20261279.html')
        );

        $this->assertCount(2, $details['dokumente']);
        $this->assertSame('BEI 1 (gelayoutete Legislaturschwerpunkte)', $details['dokumente'][0]['titel']);
        $this->assertStringEndsWith(
            '/download/resolution-document/3252/COO.2338.100.3.6888912.pdf',
            $details['dokumente'][0]['url']
        );
        $this->assertStringContainsString('SRB_SRA', $details['dokumente'][1]['titel']);
        $this->assertStringEndsWith(
            '/download/resolution-document/3253/COO.2338.100.3.6892711.pdf',
            $details['dokumente'][1]['url']
        );
    }

    public function testJedeDokumentadresseIstAbsolut(): void
    {
        // Die Adresse der Quelle steckt in der gespeicherten Seite als Pfad;
        // absolut macht sie die Basis, und die ist hier die der Stadt.
        $details = $this->service->extrahiereStadtratsbeschlussDetailsAusHtml(
            $this->fixture('stadtratsbeschluss-detail-20261279.html')
        );

        foreach ($details['dokumente'] as $dokument) {
            $this->assertStringStartsWith('https://', $dokument['url']);
        }
    }

    public function testNummerUndTitelWerdenGetrennt(): void
    {
        $this->assertSame(
            ['2026/1279', 'Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)'],
            ScraperService::teileNummerUndTitel('2026/1279 Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)')
        );
    }

    public function testOhneNummerBleibtDerGanzeTextDerTitel(): void
    {
        $this->assertSame(['', 'Ein Titel ohne Nummer'], ScraperService::teileNummerUndTitel('Ein Titel ohne Nummer'));
    }

    public function testDieBasisAdresseDerStadtIstDieStadtWebseite(): void
    {
        $this->assertSame('https://stadt.winterthur.ch', ScraperService::stadtBasisUrl());
        $this->assertSame(
            'https://stadt.winterthur.ch/stadtratsbeschluesse',
            ScraperService::stadtAbsolutUrl('/stadtratsbeschluesse')
        );
    }

    public function testDieUmgebungSetztEineAndereQuelle(): void
    {
        putenv('PARLWIN_STADT_BASE_URL=http://parlwin-quelle:8080/');
        // Der Schrägstrich am Ende fällt weg, damit jede zusammengesetzte
        // Adresse genau einen trägt.
        $this->assertSame('http://parlwin-quelle:8080', ScraperService::stadtBasisUrl());
        $this->assertSame(
            'http://parlwin-quelle:8080/stadtratsbeschluesse',
            ScraperService::stadtAbsolutUrl('/stadtratsbeschluesse')
        );
    }

    public function testEineFremdeAdresseBleibtWieSieIst(): void
    {
        $this->assertSame(
            'https://example.org/datei.pdf',
            ScraperService::stadtAbsolutUrl('https://example.org/datei.pdf')
        );
    }

    public function testDasGeschuetzteLeerzeichenWirdZumGewoehnlichen(): void
    {
        $this->assertSame(
            '28. Oktober 2026',
            ScraperService::bereinigeHtmlText('<span>28.&nbsp;Oktober&nbsp;2026</span>')
        );
    }
}
