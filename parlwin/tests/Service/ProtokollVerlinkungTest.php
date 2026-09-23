<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * F115: Jedes Protokoll ist verlinkt — bei der Sitzung, die es protokolliert,
 * und beim Traktandum der Folgesitzung, das es abnimmt.
 *
 * Die Testdaten sind die gespeicherte Sitzungsseite vom 1. Dezember 2025
 * (`sitzung-detail-6866848.html`): Sie trägt oben ihr eigenes Protokoll
 * («Protokoll Stradtparlament vom 1. Dezember 2025», Tippfehler der Quelle)
 * und im ersten Traktandum «Abnahme Parlaments-Protokolle» den Entwurf des
 * Protokolls der Sitzung vom 10. November 2025.
 */
class ProtokollVerlinkungTest extends TestCase
{
    private const SEITE = 'https://parlament.winterthur.ch/_rte/anlass/6866848';
    private const PROTOKOLL_01_12 = 'https://parlament.winterthur.ch/_doc/6625061';
    private const ENTWURF_10_11 = 'https://parlament.winterthur.ch/_doc/6378164';
    private const PROTOKOLL_10_11 = 'https://parlament.winterthur.ch/_doc/6396524';

    private function scraper(): ScraperService
    {
        return new ScraperService(
            $this->createStub(\OCP\Http\Client\IClientService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function fixture(): string
    {
        $pfad = __DIR__ . '/../Fixtures/html/sitzung-detail-6866848.html';
        $inhalt = @file_get_contents($pfad);
        $this->assertIsString($inhalt, "Fixture konnte nicht geladen werden: {$pfad}");
        return $inhalt;
    }

    public function testDokumenteDerSitzungEnthaltenIhrProtokollOhneDieDesTraktandums(): void
    {
        $dokumente = $this->scraper()->extrahiereSitzungsdokumenteAusHtml($this->fixture());

        $titel = array_map(static fn (array $d): string => $d['titel'], $dokumente);
        $this->assertContains('Protokoll Stradtparlament vom 1. Dezember 2025', $titel);
        $this->assertNotContains(
            'Protokoll Stadtparlament vom 10. November 2025 - Entwurf',
            $titel,
            'Dokumente eines Traktandums gehören nicht zu den Dokumenten der Sitzung'
        );
        $this->assertNotContains('Download', $titel);

        $protokoll = array_values(array_filter(
            $dokumente,
            static fn (array $d): bool => $d['titel'] === 'Protokoll Stradtparlament vom 1. Dezember 2025'
        ))[0];
        $this->assertSame(self::PROTOKOLL_01_12, $protokoll['url']);
        $this->assertSame('2025-12-01', $protokoll['datum']);
    }

    public function testProtokollDerSitzungWirdUeberDasDatumGewaehlt(): void
    {
        $dokumente = $this->scraper()->extrahiereSitzungsdokumenteAusHtml($this->fixture());

        $protokoll = ScraperService::waehleProtokoll($dokumente, '2025-12-01');
        $this->assertNotNull($protokoll);
        $this->assertSame(self::PROTOKOLL_01_12, $protokoll['url']);
        $this->assertSame('Protokoll Stradtparlament vom 1. Dezember 2025', $protokoll['titel']);

        $this->assertNull(
            ScraperService::waehleProtokoll($dokumente, '2025-11-10'),
            'Nur das Protokoll der Sitzung selbst zählt, nicht ein fremdes Datum'
        );
    }

    public function testDatumAusTextLiestDieSchreibweisenDerQuelle(): void
    {
        $this->assertSame('2025-12-01', ScraperService::datumAusText('Protokoll Stradtparlament vom 1. Dezember 2025'));
        $this->assertSame('2025-11-10', ScraperService::datumAusText('Protokoll Stadtparlament vom 10. November 2025 - Entwurf'));
        $this->assertSame('2026-03-02', ScraperService::datumAusText('Beschlüsse Stadtparlament vom 02.03.2026'));
        $this->assertSame('', ScraperService::datumAusText('Traktandenliste Stadtparlament'));
    }

    public function testTraktandumTraegtTitelUndAdresseSeinesDokuments(): void
    {
        $traktanden = $this->scraper()->extrahiereTraktandenAusHtml($this->fixture());

        $this->assertNotEmpty($traktanden);
        $erstes = $traktanden[0];
        $this->assertSame(1, $erstes['number']);
        $this->assertSame(self::ENTWURF_10_11, $erstes['traktandumUrl']);
        $this->assertSame('Protokoll Stadtparlament vom 10. November 2025 - Entwurf', $erstes['dokumentTitel']);
    }

    /**
     * Der volle Weg über die Sitzungsseite: Die Seite trägt ihre Traktanden
     * sowohl als JSON-Entitäten als auch im HTML. Der Dokumenttitel muss auf
     * dem Weg ankommen, den die Synchronisation tatsächlich nimmt.
     */
    public function testDokumenttitelKommtUeberDenWegDerSynchronisationAn(): void
    {
        $html = $this->fixture();
        $clientService = $this->createStub(\OCP\Http\Client\IClientService::class);
        $client = $this->createStub(\OCP\Http\Client\IClient::class);
        $antwort = $this->createStub(\OCP\Http\Client\IResponse::class);
        $antwort->method('getBody')->willReturn($html);
        $client->method('get')->willReturn($antwort);
        $clientService->method('newClient')->willReturn($client);

        $service = new ScraperService($clientService, $this->createStub(LoggerInterface::class));
        $seite = $service->ladeSitzungsseite(self::SEITE);

        $this->assertNotEmpty($seite['traktanden']);
        $erstes = $seite['traktanden'][0];
        $this->assertSame(1, (int) ($erstes['number'] ?? 0));
        $this->assertSame(self::ENTWURF_10_11, (string) ($erstes['traktandumUrl'] ?? ''));
        $this->assertSame(
            'Protokoll Stadtparlament vom 10. November 2025 - Entwurf',
            (string) ($erstes['dokumentTitel'] ?? '')
        );
        $this->assertNotNull(ScraperService::waehleProtokoll($seite['dokumente'], '2025-12-01'));
    }

    public function testSynchronisationSpeichertProtokollUndDokumenttitel(): void
    {
        $scraper = $this->createStub(ScraperService::class);
        $scraper->method('ladeSitzungen')->willReturn([[
            'id' => '6866848',
            'title' => '13./14. Sitzungen',
            'date' => '2025-12-01',
            'url' => self::SEITE,
        ]]);
        $scraper->method('ladeSitzungsseitenJeUrlParallel')->willReturn([
            self::SEITE => [
                'traktanden' => [[
                    'number' => 1,
                    'title' => 'Abnahme Parlaments-Protokolle',
                    'description' => 'Abnahme Parlaments-Protokolle',
                    'businessId' => '',
                    'url' => '',
                    'traktandumUrl' => self::ENTWURF_10_11,
                    'dokumentTitel' => 'Protokoll Stadtparlament vom 10. November 2025 - Entwurf',
                ]],
                'dokumente' => [
                    ['titel' => 'Traktandenliste Stadtparlament 1. und 8. Dezember 2025', 'url' => 'https://parlament.winterthur.ch/_doc/6346265', 'datum' => '2025-12-01'],
                    ['titel' => 'Protokoll Stradtparlament vom 1. Dezember 2025', 'url' => self::PROTOKOLL_01_12, 'datum' => '2025-12-01'],
                ],
            ],
        ]);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('findByExternId')->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('neu'));
        $gespeicherteSitzung = null;
        $sitzungMapper->method('insert')->willReturnCallback(function (Sitzung $s) use (&$gespeicherteSitzung): Sitzung {
            $s->setId(7);
            $gespeicherteSitzung = $s;
            return $s;
        });

        $traktandumMapper = $this->createStub(TraktandumMapper::class);
        $traktandumMapper->method('findErstesBySitzungUndNummer')->willReturn(null);
        $gespeichertesTraktandum = null;
        $traktandumMapper->method('insert')->willReturnCallback(function (Traktandum $t) use (&$gespeichertesTraktandum): Traktandum {
            $gespeichertesTraktandum = $t;
            return $t;
        });

        $service = new SitzungService(
            $sitzungMapper,
            $traktandumMapper,
            $this->createStub(GeschaeftMapper::class),
            $scraper,
            $this->createStub(LoggerInterface::class),
        );
        $service->synchronisieren();

        $this->assertInstanceOf(Sitzung::class, $gespeicherteSitzung);
        $this->assertSame(self::PROTOKOLL_01_12, $gespeicherteSitzung->getProtokollUrl());
        $this->assertSame('Protokoll Stradtparlament vom 1. Dezember 2025', $gespeicherteSitzung->getProtokollTitel());

        $this->assertInstanceOf(Traktandum::class, $gespeichertesTraktandum);
        $this->assertSame(self::ENTWURF_10_11, $gespeichertesTraktandum->getUrl());
        $this->assertSame('Protokoll Stadtparlament vom 10. November 2025 - Entwurf', $gespeichertesTraktandum->getDokumentTitel());
    }

    public function testProtokollZumTraktandumZeigtAufDieProtokollierteSitzung(): void
    {
        $protokollierte = new Sitzung();
        $protokollierte->setId(3);
        $protokollierte->setDatum('2025-11-10');
        $protokollierte->setProtokollUrl(self::PROTOKOLL_10_11);
        $protokollierte->setProtokollTitel('Protokoll Stadtparlament vom 10. November 2025');

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('findByDatum')->willReturnMap([['2025-11-10', [$protokollierte]]]);

        $service = new SitzungService(
            $sitzungMapper,
            $this->createStub(TraktandumMapper::class),
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(ScraperService::class),
            $this->createStub(LoggerInterface::class),
        );

        $protokoll = $service->protokollFuerTraktandum($this->abnahmeTraktandum());
        $this->assertNotNull($protokoll);
        $this->assertSame(self::PROTOKOLL_10_11, $protokoll['url']);
        $this->assertSame('Protokoll Stadtparlament vom 10. November 2025', $protokoll['titel']);
        $this->assertSame('2025-11-10', $protokoll['datum']);
    }

    public function testOhneProtokollierteSitzungBleibtDasDokumentDesTraktandums(): void
    {
        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('findByDatum')->willReturn([]);

        $service = new SitzungService(
            $sitzungMapper,
            $this->createStub(TraktandumMapper::class),
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(ScraperService::class),
            $this->createStub(LoggerInterface::class),
        );

        $protokoll = $service->protokollFuerTraktandum($this->abnahmeTraktandum());
        $this->assertNotNull($protokoll);
        $this->assertSame(self::ENTWURF_10_11, $protokoll['url']);
        $this->assertSame('Protokoll Stadtparlament vom 10. November 2025 - Entwurf', $protokoll['titel']);
        $this->assertSame('2025-11-10', $protokoll['datum']);
    }

    public function testTraktandumOhneProtokollHatKeinenProtokolllink(): void
    {
        $traktandum = new Traktandum();
        $traktandum->setTitel('Budget 2026 und Festsetzung des Steuerfusses');
        $traktandum->setDokumentTitel('2025.110W - Antrag inkl. Beilage 1');
        $traktandum->setUrl('https://parlament.winterthur.ch/_doc/6122695');

        $service = new SitzungService(
            $this->createStub(SitzungMapper::class),
            $this->createStub(TraktandumMapper::class),
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(ScraperService::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->assertNull($service->protokollFuerTraktandum($traktandum));
    }

    /**
     * Zeilen aus der Zeit vor den neuen Spalten tragen NULL; eine Eigenschaft
     * ohne NULL bräche dann das Laden der ganzen Liste.
     */
    public function testNullAusDerDatenbankBrichtDasLadenNicht(): void
    {
        $sitzung = new Sitzung();
        $sitzung->setProtokollUrl(null);
        $sitzung->setProtokollTitel(null);
        $this->assertSame('', $sitzung->getProtokollUrl());
        $this->assertSame('', $sitzung->getProtokollTitel());
        $this->assertSame('', $sitzung->jsonSerialize()['protokollUrl']);
        $this->assertSame('', $sitzung->jsonSerialize()['protokollTitel']);

        $traktandum = new Traktandum();
        $traktandum->setDokumentTitel(null);
        $this->assertSame('', $traktandum->getDokumentTitel());
        $this->assertSame('', $traktandum->jsonSerialize()['dokumentTitel']);
    }

    private function abnahmeTraktandum(): Traktandum
    {
        $traktandum = new Traktandum();
        $traktandum->setNummer(1);
        $traktandum->setTitel('Abnahme Parlaments-Protokolle');
        $traktandum->setDokumentTitel('Protokoll Stadtparlament vom 10. November 2025 - Entwurf');
        $traktandum->setUrl(self::ENTWURF_10_11);
        return $traktandum;
    }
}
