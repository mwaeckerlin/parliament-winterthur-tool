<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetNovemberbriefParser;
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
    /**
     * @param string[]|null $weisungsTitel Titel der Budget-Weisungen; ohne Angabe
     *                                     eine einzelne Weisung «Budget 2026 …».
     */
    private function service(
        BudgetJahrMapper $jahre,
        ?array $weisungsTitel = null,
        ?string $seitenHtml = null,
        ?array &$geladeneUrls = null
    ): BudgetImportService {
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('insert')->willReturnArgument(0);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('insert')->willReturnArgument(0);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        // Parser gestubbt: eine minimale, gültige Struktur. Sie trägt so viele
        // Produktegruppen, wie der Plausibilitäts-Guard von ladeStruktur verlangt —
        // hier wird die Auslöse-Logik geprüft, nicht das Parsen.
        $gruppenListe = [];
        for ($i = 1; $i <= 25; $i++) {
            $gruppenListe[] = [
                'code' => (string) (100 + $i), 'name' => 'Gruppe ' . $i, 'departement' => 'Finanzen',
                'aufwand' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => $i === 1 ? 1000 : 0],
                'ertrag' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            ];
        }
        $parser = $this->createStub(BudgetBuchParser::class);
        $parser->method('struktur')->willReturn([
            'produktegruppen' => $gruppenListe,
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
        // Budget-Geschäfte: ohne eigene Angabe ein Budget 2026 → verfuegbareJahre = [2026].
        $weisungen = [];
        foreach ($weisungsTitel ?? ['Budget 2026 und Festsetzung des Steuerfusses'] as $titel) {
            $w = new Geschaeft();
            $w->setTitel($titel);
            $w->setUrl('https://test.local/budget');
            $weisungen[] = $w;
        }
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('alleBudgetWeisungen')->willReturn($weisungen);
        $geschaefte->method('findeBudgetWeisung')->willReturn($weisungen[0]);
        // HTTP: Geschäft-Seite (HTML mit Teil-A/B-Links) und die Dokument-URLs
        // (PDF-Bytes — ladeDokument prüft den %PDF-Kopf, der Parse selbst ist gestubbt).
        $html = $seitenHtml ?? '<a href="/_doc/a">Teil A</a><a href="/_doc/b">Teil B</a>';
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(
            function (string $url) use ($html, &$geladeneUrls): IResponse {
                if ($geladeneUrls !== null) {
                    $geladeneUrls[] = $url;
                }
                $resp = $this->createStub(IResponse::class);
                $resp->method('getBody')->willReturn(
                    str_contains($url, '/_doc/')
                        ? "%PDF-1.7\n" . str_repeat('x', 64)
                        : $html
                );
                return $resp;
            }
        );
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
     * Das Budgetjahr wird aus dem Titel der Weisung gelesen. Die Stadt schreibt
     * ihn seit dem Budget 2020 als «Genehmigung des BudgetS <Jahr>»; davor hiess
     * er «Budget <Jahr>». Wer nur die alte Form liest, findet von sieben
     * Budgetjahren nur drei — genau das zeigte die Auswahl «Vergangenes Budgetjahr
     * importieren» am 2026-08-28 (nur 2017, 2018, 2019).
     */
    public function testLiestDasBudgetjahrAusBeidenTitelformen(): void {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $dienst = $this->service($jahre, [
            'Budget 2026 und Festsetzung des Steuerfusses / Kenntnisnahme des Finanz- und Aufgabenplans 2026 bis 2029',
            'Genehmigung des Budgets 2025 und Festsetzung des Steuerfusses / Kenntnisnahme des Finanz- und Aufgabenplans 2025 bis 2028',
            'Genehmigung des Budgets 2024 und Festsetzung des Steuerfusses // Kenntnisnahme des Finanz- und Aufgabenplans 2024 bis 2027',
            'Genehmigung des Budgets 2021 und Festsetzung des Steuerfusses; Kenntnisnahme des Finanz- und Aufgabenplans 2022 bis 2024',
            'Budget 2019 und Festsetzung des Steuerfusses',
            'Budget 2017 und Festsetzung des Steuerfusses',
        ]);

        self::assertSame(
            [2026, 2025, 2024, 2021, 2019, 2017],
            $dienst->verfuegbareJahre(),
            'aus jeder Titelform muss das Budgetjahr gelesen werden, neuestes zuerst'
        );
    }

    /**
     * Das Budget 2027 steht seit dem 22.09.2026 auf der Parlamentswebseite. Sein
     * Geschäft «Budget 2027 und Festsetzung des Steuerfusses …» ist das neueste,
     * also liest der geplante Job genau dieses Jahr ein — nicht das schon
     * vorhandene 2026.
     */
    public function testAutomatischerImportNimmtDasBudget2027(): void {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturn(false);
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);

        $ergebnis = $this->service($jahre, [
            'Budget 2027 und Festsetzung des Steuerfusses / Kenntnisnahme des Finanz- und Aufgabenplans 2027 bis 2030',
            'Budget 2026 und Festsetzung des Steuerfusses / Kenntnisnahme des Finanz- und Aufgabenplans 2026 bis 2029',
        ])->automatischerImport();

        self::assertNotNull($ergebnis);
        self::assertSame(2027, $ergebnis['jahr'], 'das neueste verfügbare Budgetjahr');
        self::assertTrue($ergebnis['importiert'], 'Budget 2027 wird automatisch eingelesen');
    }

    /**
     * Die echte Geschäftsseite des Budgets 2027 (Fixture `geschaeft-detail-2990959.html`,
     * geladen am 22.09.2026). Die Stadt beschriftet ihre Beilagen dort anders als in
     * den Vorjahren — «2026.95W - Beilage 2: Teil A (Antrag) - Budget und Finanzplan»
     * statt «… Teil A - Antrag (Budget und Finanzplan)» —, und die Geschäftsnummer
     * nennt mit 2026 ein anderes Jahr als das Budget. Beides darf den Import nicht
     * daran hindern, genau die beiden Bücher zu laden.
     */
    public function testEchteWeisung2027LiefertBeideBuecher(): void {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $geladen = [];
        $dienst = $this->service(
            $jahre,
            ['Budget 2027 und Festsetzung des Steuerfusses / Kenntnisnahme des Finanz- und Aufgabenplans 2027 bis 2030'],
            (string) file_get_contents(\dirname(__DIR__, 1) . '/Fixtures/html/geschaeft-detail-2990959.html'),
            $geladen
        );

        $dienst->importiereJahr(2027);

        $dokumente = array_values(array_filter($geladen, static fn (string $u): bool => str_contains($u, '/_doc/')));
        self::assertSame(
            [
                'https://parlament.winterthur.ch/_doc/7242610',
                'https://parlament.winterthur.ch/_doc/7242604',
            ],
            $dokumente,
            'Teil B und Teil A des Budgets 2027, und sonst keine Beilage'
        );
    }

    /**
     * Die Beilagen einer Weisung sind nicht immer richtig beschriftet: An der
     * Weisung zum Budget 2019 (Geschäft 2018.98) hängt eine Beilage «Budget 2018 —
     * Teil A (Antrag)». Wer einfach den ersten Link mit «Teil A» nimmt, liest für
     * 2019 das Buch von 2018 ein. Ein Link, dessen Beschriftung ein ANDERES
     * Budgetjahr nennt, wird darum übersprungen.
     */
    public function testUeberspringtBeilagenMitFalschemBudgetjahr(): void {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $geladen = [];
        $dienst = $this->service(
            $jahre,
            ['Budget 2019 und Festsetzung des Steuerfusses'],
            '<a href="/_doc/falsch-a">Budget 2018 - Teil A (Antrag)</a>'
            . '<a href="/_doc/richtig-a">Budget 2019 - Teil A (definitive Fassung)</a>'
            . '<a href="/_doc/richtig-b">Budget 2019 - Teil B (definitive Fassung)</a>',
            $geladen
        );

        $dienst->importiereJahr(2019);

        $dokumente = array_values(array_filter($geladen, static fn (string $u): bool => str_contains($u, '/_doc/')));
        self::assertContains('https://parlament.winterthur.ch/_doc/richtig-a', $dokumente, 'Teil A des richtigen Jahres');
        self::assertNotContains(
            'https://parlament.winterthur.ch/_doc/falsch-a',
            $dokumente,
            'Eine Beilage, die ein anderes Budgetjahr nennt, darf nicht geladen werden'
        );
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

    /**
     * Dieselbe Bestandsgarantie für einen VERSTÜMMELTEN Parse: Die Stadt führt in
     * jedem Jahrgang über 40 Produktegruppen. Kommen nur drei zurück, hat sich am
     * Buchaufbau etwas geändert oder das falsche Dokument wurde geladen — dann darf
     * der bestehende Stand ebenso wenig ersetzt werden wie bei einem leeren Parse.
     */
    public function testVerstuemmelterParseLoeschtNichtsUndScheitertLaut(): void {
        $wenige = [];
        for ($i = 1; $i <= 3; $i++) {
            $wenige[] = [
                'code' => '10' . $i, 'name' => 'Gruppe ' . $i, 'departement' => 'Finanzen',
                'globalkredit' => ['ist' => 1, 'sollVorjahr' => 1, 'soll' => 1, 'plan1' => 0, 'plan2' => 0, 'plan3' => 0],
                'aufwand' => ['ist' => 1, 'sollVorjahr' => 1, 'soll' => 1],
                'ertrag' => ['ist' => 1, 'sollVorjahr' => 1, 'soll' => 1],
                'stellen' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
                'produkte' => [], 'zielvorgaben' => [], 'kostenzeilen' => [],
                'auftrag' => '', 'erlaeuterungStellen' => '', 'begruendungAbweichung' => '',
                'begruendungFap' => '', 'massnahmen' => '',
            ];
        }
        $parser = $this->createStub(BudgetBuchParser::class);
        $parser->method('struktur')->willReturn([
            'produktegruppen' => $wenige,
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
            self::fail('Import muss bei einem verstümmelten Parse laut scheitern');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('3 Produktegruppen', $e->getMessage());
        }
    }

    /**
     * Der Novemberbrief aus der Beilage des Budget-Geschäfts wird als KORREKTUR
     * angewendet: Seine Zahlen sind Änderungen, keine neuen Beträge (F91).
     *
     * Die Werte stammen aus dem Novemberbrief 2022, Beilage «Übersicht der
     * Positionen im Novemberbrief 2022 der Stadt Winterthur»:
     *
     *     Städtische Allgemeinkosten / Erlöse
     *        77'663'200   3 Aufwand     528'000    78'191'200
     *      -123'285'896   4 Ertrag      272'346  -123'013'550
     *       -45'622'696   0 Ergebnis    800'346   -44'822'350
     *
     * Bis zum 31.08.2026 suchte der Import nach neuen Absolutwerten, die keine
     * Quelle liefert: «Novemberbrief einlesen» markierte das Jahr als eingelesen
     * und änderte keine einzige Zahl.
     */
    public function testNovemberbriefAusDerBeilageWirdAlsKorrekturAngewendet(): void {
        $gruppe = $this->novemberbriefGruppe();
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2022);
        $service = $this->novemberbriefDienst($gruppe, $jahrRow);

        self::assertTrue($service->novemberbriefVerfuegbar(2022), 'Der Novemberbrief der Beilage wird nicht gefunden');

        $service->importiereNovemberbrief(2022);
        self::assertSame(-44_822_350, (int) $gruppe->getGlobalkreditSoll(), 'Globalkredit nach dem Novemberbrief');
        self::assertSame(78_191_200, (int) $gruppe->getAufwandSoll(), 'Aufwand nach dem Novemberbrief');
        self::assertSame(123_013_550, (int) $gruppe->getErtragSoll(), 'Ertrag nach dem Novemberbrief');
        self::assertSame(1, (int) $jahrRow->getNovemberbriefImportiert(), 'Das Jahr ist nicht als eingelesen markiert');
    }

    /**
     * Ein zweiter Aufruf addiert die Korrekturen NICHT ein zweites Mal: Die Zahlen
     * des Novemberbriefs sind Änderungen am Entwurf, und ein eingelesenes Jahr
     * trägt sie bereits. Die Oberfläche blendet den Knopf danach aus; die
     * Schnittstelle bleibt aufrufbar, und ein Doppelklick darf das Budget nicht um
     * die doppelte Korrektur verschieben.
     */
    public function testNovemberbriefWirdKeinZweitesMalAddiert(): void {
        $gruppe = $this->novemberbriefGruppe();
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2022);
        $service = $this->novemberbriefDienst($gruppe, $jahrRow);

        $service->importiereNovemberbrief(2022);
        $service->importiereNovemberbrief(2022);

        self::assertSame(-44_822_350, (int) $gruppe->getGlobalkreditSoll(), 'Globalkredit nach dem zweiten Aufruf');
        self::assertSame(78_191_200, (int) $gruppe->getAufwandSoll(), 'Aufwand nach dem zweiten Aufruf');
        self::assertSame(123_013_550, (int) $gruppe->getErtragSoll(), 'Ertrag nach dem zweiten Aufruf');
        self::assertFalse(
            $service->novemberbriefVerfuegbar(2022),
            'Ein eingelesener Novemberbrief wird erneut zum Einlesen angeboten'
        );
    }

    /**
     * Wer das Jahr neu einliest, holt den Budgetentwurf von der Webseite: Die
     * Korrekturen des Novemberbriefs stehen danach nicht mehr in den Zahlen, also
     * gilt das Jahr wieder als nicht eingelesen und die Oberfläche bietet den
     * Novemberbrief erneut an.
     */
    public function testNeuEinlesenDesJahresNimmtDieNovemberbriefMarkeZurueck(): void {
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setNovemberbriefImportiert(1);
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willReturn($jahrRow);
        $jahre->method('update')->willReturnArgument(0);

        $this->service($jahre)->importiereJahr(2026);

        self::assertSame(
            0,
            (int) $jahrRow->getNovemberbriefImportiert(),
            'Nach dem Neu-Einlesen gilt der Novemberbrief weiter als eingelesen, obwohl seine Korrekturen fort sind'
        );
    }

    /** Die Produktegruppe des Novemberbriefs 2022 im Stand des Budgetentwurfs. */
    private function novemberbriefGruppe(): BudgetProduktegruppe {
        $gruppe = new BudgetProduktegruppe();
        $gruppe->setJahr(2022);
        $gruppe->setCode('263');
        $gruppe->setName('Städtische Allgemeinkosten/Erlöse');
        $gruppe->setGlobalkreditSoll(-45_622_696);
        $gruppe->setAufwandSoll(77_663_200);
        $gruppe->setErtragSoll(123_285_896);
        return $gruppe;
    }

    /**
     * Der Dienst für die Novemberbrief-Fälle: Am Budget-Geschäft hängt die Beilage
     * «Novemberbrief», der Parser liefert die Korrekturen der einen Produktegruppe.
     */
    private function novemberbriefDienst(BudgetProduktegruppe $gruppe, BudgetJahr $jahrRow): BudgetImportService {
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$gruppe]);
        $gruppen->method('update')->willReturnArgument(0);

        // Die Beilage «Novemberbrief» hängt am Budget-Geschäft; ihre Beschriftung
        // trägt die Geschäftsnummer des VORJAHRES.
        $weisung = new Geschaeft();
        $weisung->setTitel('Genehmigung des Budgets 2022 und Festsetzung des Steuerfusses');
        $weisung->setUrl('https://test.local/budget');
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturn($weisung);
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(
            function (string $url): IResponse {
                $resp = $this->createStub(IResponse::class);
                $resp->method('getBody')->willReturn(
                    str_contains($url, '/_doc/')
                        ? "%PDF-1.7\n" . str_repeat('x', 64)
                        : '<a href="/_doc/nb">2021.81-2 «Novemberbrief»</a>'
                );
                return $resp;
            }
        );
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);

        $novemberbrief = $this->createStub(BudgetNovemberbriefParser::class);
        $novemberbrief->method('parse')->willReturn([
            'produktegruppen' => [[
                // Die Beilage schreibt den Namen mit Leerzeichen um den Schrägstrich.
                'name' => 'Städtische Allgemeinkosten / Erlöse',
                'aufwandNb' => 528_000,
                'ertragNb' => -272_346,
                'nettokostenNb' => 800_346,
            ]],
            'total' => 1_180_705,
        ]);

        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturn(true);
        $jahre->method('findByJahr')->willReturn($jahrRow);
        $jahre->method('update')->willReturnArgument(0);

        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        return new BudgetImportService(
            $jahre,
            $gruppen,
            $this->createStub(BudgetInvestitionMapper::class),
            $this->createStub(BudgetAntragMapper::class),
            $this->createStub(BudgetBuchParser::class),
            $time,
            $geschaefte,
            $clientService,
            $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class),
            $this->createStub(BudgetDrehbuchParser::class),
            $novemberbrief,
        );
    }
}
