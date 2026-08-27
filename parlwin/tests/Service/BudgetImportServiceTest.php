<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;

/**
 * Automatischer Budget-Import (F89): liest über den geplanten Job das neueste
 * verfügbare Budgetjahr ein, sobald dessen Budget-Geschäft (Weisung) vorliegt. Die
 * verfügbaren Jahre kommen aus den Budget-Geschäften; das Buch wird live von der
 * Parlamentswebseite geladen. Parser und HTTP sind hier gestubbt (die Auslöse-Logik
 * wird geprüft, nicht das Parsen — das prüft BudgetBuchParserTest an den echten
 * Büchern).
 */
class BudgetImportServiceTest extends TestCase {
    private function service(BudgetJahrMapper $jahre): BudgetImportService {
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('insert')->willReturnArgument(0);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('insert')->willReturnArgument(0);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        // Parser gestubbt: eine minimale, gültige Struktur (mindestens eine
        // Produktegruppe) — sonst greift der Leer-Guard von ladeStruktur.
        $parser = $this->createStub(BudgetBuchParser::class);
        $parser->method('struktur')->willReturn([
            'produktegruppen' => [[
                'code' => '121', 'name' => 'Personalamt', 'departement' => 'Finanzen',
                'aufwand' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 1000],
                'ertrag' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            ]],
            'steuerfuss' => 125, 'steuerertrag' => 1000, 'personalsteuer' => 24,
            'totalAufwand' => 2000, 'totalErtrag' => 2100,
            'totalAufwandVorjahr' => 0, 'totalErtragVorjahr' => 0,
            'gesamtergebnis' => 100, 'investitionen' => [],
        ]);
        // Der Test-Bootstrap definiert ITimeFactory als leeres Interface; eine
        // lokale Implementierung liefert das vom Import benötigte getTime().
        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        // Budget-Geschäfte: ein Budget 2026 vorhanden → verfuegbareJahre = [2026].
        $weisung = new Geschaeft();
        $weisung->setTitel('Budget 2026 und Festsetzung des Steuerfusses');
        $weisung->setUrl('https://test.local/budget/2026');
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('alleBudgetWeisungen')->willReturn([$weisung]);
        $geschaefte->method('findeBudgetWeisung')->willReturn($weisung);
        // HTTP: Geschäft-Seite (HTML mit Teil-A/B-Links) und die Dokument-URLs
        // (PDF-Bytes — ladeDokument prüft den %PDF-Kopf, der Parse selbst ist gestubbt).
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $resp = $this->createStub(IResponse::class);
            $resp->method('getBody')->willReturn(
                str_contains($url, '/_doc/')
                    ? "%PDF-1.7\n" . str_repeat('x', 64)
                    : '<a href="/_doc/a">Teil A</a><a href="/_doc/b">Teil B</a>'
            );
            return $resp;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $traktanden = $this->createStub(TraktandumMapper::class);
        $traktanden->method('findByGeschaeft')->willReturn([]);
        $sitzungen = $this->createStub(SitzungMapper::class);
        $drehbuch = $this->createStub(BudgetDrehbuchParser::class);
        return new BudgetImportService($jahre, $gruppen, $investitionen, $antraege, $parser, $time, $geschaefte, $clientService, $traktanden, $sitzungen, $drehbuch);
    }

    public function testAntraegePdfNutztFraktionUndFremdenOption(): void {
        // Im PDF steht als Antragsteller immer die Fraktion (eigene Anträge), und
        // unterstützte fremde Anträge erscheinen nur auf ausdrückliche Option (F92).
        $g = new BudgetProduktegruppe();
        $g->setCode('121');
        $g->setName('Personalamt');
        $g->setDepartement('Präsidiales');
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$g]);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);

        $eigen = new BudgetAntrag();
        $eigen->setId(1);
        $eigen->setJahr(2026);
        $eigen->setBereich('globalbudget');
        $eigen->setZielRef('121');
        $eigen->setHerkunft('eigene');
        $eigen->setHaltung('einreichen');
        $eigen->setAntragsteller('Marc Wäckerlin');
        $eigen->setBetragDelta(-1000);
        $fremd = new BudgetAntrag();
        $fremd->setId(2);
        $fremd->setJahr(2026);
        $fremd->setBereich('globalbudget');
        $fremd->setZielRef('121');
        $fremd->setHerkunft('fremde');
        $fremd->setHaltung('unterstuetzen');
        $fremd->setAntragsteller('Grüne/Anna Muster');
        $fremd->setBetragDelta(-500);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([$eigen, $fremd]);

        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        $svc = new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class), $gruppen, $investitionen, $antraege,
            $this->createStub(BudgetBuchParser::class), $time, $this->createStub(GeschaeftMapper::class),
            $this->createStub(IClientService::class), $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class), $this->createStub(BudgetDrehbuchParser::class),
        );
        $steller = static fn (array $d): array => array_map(static fn ($e) => (string) $e['antrag']['antragsteller'], $d['eintraege']);

        // Ohne Option: nur der eigene Antrag, im PDF als Fraktion (nicht «Marc Wäckerlin»).
        self::assertSame(['Unsere Fraktion'], $steller($svc->antraegePdf(2026, null, false, 'Unsere Fraktion')));

        // Mit Option: eigener (Fraktion) und der unterstützte fremde (Name bleibt).
        $mit = $steller($svc->antraegePdf(2026, null, true, 'Unsere Fraktion'));
        self::assertContains('Unsere Fraktion', $mit);
        self::assertContains('Grüne/Anna Muster', $mit);
        self::assertCount(2, $mit);

        // Der Fraktionsname wird zurückgegeben — die Druckseite setzt ihn in den Titel
        // «Budgetanträge der <Fraktion>» (F92).
        self::assertSame('Unsere Fraktion', $svc->antraegePdf(2026, null, false, 'Unsere Fraktion')['fraktion']);
    }

    public function testImportiertNeuestesJahrAutomatischWennNochNichtVorhanden(): void {
        $jahre = $this->createMock(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturnCallback(static fn (int $j): bool => false);
        // schreibeStruktur legt das Jahr an (findByJahr wirft zuerst).
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $jahre->expects(self::atLeastOnce())->method('update');

        $ergebnis = $this->service($jahre)->automatischerImport();

        self::assertNotNull($ergebnis);
        self::assertSame(2026, $ergebnis['jahr'], 'neuestes verfügbares Jahr');
        self::assertTrue($ergebnis['importiert'], 'wird automatisch eingelesen');
        self::assertFalse($ergebnis['novemberbrief'], 'ohne Novemberbrief-Quelle nichts nachgezogen');
    }

    public function testImportiertNichtsWennJahrBereitsVorhanden(): void {
        $jahre = $this->createMock(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturn(true);
        $jahre->expects(self::never())->method('insert');

        $ergebnis = $this->service($jahre)->automatischerImport();

        self::assertNotNull($ergebnis);
        self::assertSame(2026, $ergebnis['jahr']);
        self::assertFalse($ergebnis['importiert'], 'bereits vorhandenes Jahr wird nicht erneut importiert');
    }

    /**
     * Reimport-Sicherung: liefert der Parse KEINE Produktegruppen (falscher Link,
     * Fehlerseite, geänderte Struktur), scheitert der Import laut UND lässt den
     * bestehenden Bestand unangetastet — die alten Produktegruppen dürfen NICHT
     * gelöscht werden (sonst stünde das Budget ohne Produktegruppen da, und die
     * künstliche Position schluckt das ganze Total). npnp: ohne den Leer-Guard würde
     * `schreibeStruktur` `deleteByJahr` aufrufen und den Bestand vernichten.
     */
    public function testLeererTeilBParseLoeschtNichtsUndScheitertLaut(): void {
        // Parser liefert eine gültige Struktur MIT Totalen, aber OHNE Produktegruppen.
        $parser = $this->createStub(BudgetBuchParser::class);
        $parser->method('struktur')->willReturn([
            'produktegruppen' => [],
            'steuerfuss' => 125, 'steuerertrag' => 1000, 'personalsteuer' => 24,
            'totalAufwand' => 1782300000, 'totalErtrag' => 1896100000,
            'totalAufwandVorjahr' => 0, 'totalErtragVorjahr' => 0,
            'gesamtergebnis' => 113800000, 'investitionen' => [],
        ]);

        $weisung = new Geschaeft();
        $weisung->setTitel('Budget 2026 und Festsetzung des Steuerfusses');
        $weisung->setUrl('https://test.local/budget/2026');
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturn($weisung);

        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $resp = $this->createStub(IResponse::class);
            $resp->method('getBody')->willReturn(
                str_contains($url, '/_doc/')
                    ? "%PDF-1.7\n" . str_repeat('x', 64)
                    : '<a href="/_doc/a">Teil A</a><a href="/_doc/b">Teil B</a>'
            );
            return $resp;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);

        // Die Bestandsgarantie: deleteByJahr darf NIE aufgerufen werden.
        $gruppen = $this->createMock(BudgetProduktegruppeMapper::class);
        $gruppen->expects(self::never())->method('deleteByJahr');
        $gruppen->expects(self::never())->method('insert');

        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        $service = new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class),
            $gruppen,
            $this->createStub(BudgetInvestitionMapper::class),
            $this->createStub(BudgetAntragMapper::class),
            $parser, $time, $geschaefte, $clientService,
            $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class),
            $this->createStub(BudgetDrehbuchParser::class),
        );

        try {
            $service->importiereJahr(2026);
            self::fail('Import muss bei leerem Teil-B-Parse laut scheitern');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('keine Produktegruppen', $e->getMessage());
        }
    }
}
