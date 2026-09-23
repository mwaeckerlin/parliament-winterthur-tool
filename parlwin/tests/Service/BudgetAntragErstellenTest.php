<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetAntragEntscheidMapper;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\BudgetVerteilung;
use OCA\ParliamentWinterthur\Db\BudgetVerteilungMapper;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Feld- und Betragslogik beim Anlegen eines Antrags:
 *  - F94  Herkunft «eigene»/«fremde»
 *  - F95  Betrag in CHF und Prozent, wechselseitig aus dem Budgetwert der Position
 *  - F96  Steuerfuss-Antrag in Prozentpunkten → abgeleiteter Ertrag-Effekt
 *  - F97  Haltung nach Herkunft
 *  - F98  unterstützende Fraktionen
 */
class BudgetAntragErstellenTest extends TestCase {
    private function gruppe(string $code, int $globalkreditSoll): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setDepartement('Finanzen');
        $g->setGlobalkreditSoll($globalkreditSoll);
        $g->setAufwandSoll($globalkreditSoll);
        $g->setAufwandSollVorjahr($globalkreditSoll);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll(0);
        $g->setStellenSollVorjahr(0);
        return $g;
    }

    /**
     * Service mit Insert-Durchreichung; Automatik aus, damit nur der Antrag entsteht.
     *
     * @param list<BudgetAntrag> $bestehende Anträge, die schon auf den Zielen liegen
     */
    private function service(?NotizService $notiz = null, array $bestehende = []): BudgetService {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(1000000);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121', 100000)]);

        $inv = new BudgetInvestition();
        $inv->setId(7);
        $inv->setJahr(2026);
        $inv->setDepartement('Finanzen');
        $inv->setBu(500000);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([$inv]);

        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn($bestehende);
        $antraege->method('insert')->willReturnArgument(0);

        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn([]);

        $verteilung = new BudgetVerteilung();
        $verteilung->setAutomatikEin(0);
        $verteilungen = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungen->method('findeOderStandard')->willReturn($verteilung);
        $verteilungen->method('alleFuerJahr')->willReturn([]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $notiz = $notiz ?? $this->createStub(NotizService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungen,
            $entscheide, $config, $time, $userSession, $realtime, $notiz,
        );
    }

    public function testProzentAusChfBerechnet(): void {
        // F95: −20'000 CHF auf ein Budget von 100'000 → −20%.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -20000]);
        self::assertSame(-20000, $a->getBetragDelta());
        self::assertEqualsWithDelta(-20.0, $a->getProzentDelta(), 0.001, 'Prozent folgt aus dem Budgetwert der Position (F95)');
    }

    public function testChfAusProzentBerechnet(): void {
        // F95: −20% auf ein Budget von 100'000 → −20'000 CHF.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'prozentDelta' => -20]);
        self::assertSame(-20000, $a->getBetragDelta(), 'CHF folgt aus dem Prozentwert (F95)');
        self::assertEqualsWithDelta(-20.0, $a->getProzentDelta(), 0.001);
    }

    public function testInvestitionProzentBasisIstBudgetwert(): void {
        // F95: Prozent bei Investitionen bezieht sich auf den Budgetwert (bu).
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'investition', 'zielTyp' => 'investition', 'zielRef' => '7', 'prozentDelta' => -10]);
        self::assertSame(-50000, $a->getBetragDelta(), '−10% von 500\'000 = −50\'000 (F95)');
    }

    public function testSteuerfussProzentpunkteErtragEffekt(): void {
        // F96: −2 Prozentpunkte bei Steuerfuss 125% → Ertrag × 123/125,
        // Delta = −2 × Ertrag/125 = −16'000.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'steuerfuss', 'zielTyp' => 'steuerfuss', 'prozentDelta' => -2]);
        self::assertEqualsWithDelta(-2.0, $a->getProzentDelta(), 0.001, 'Prozentpunkte werden geführt');
        self::assertSame(-16000, $a->getBetragDelta(), 'Ertrag-Effekt = Ertrag × Prozentpunkte / Steuerfuss (F96)');
    }

    /**
     * Ein Budget lässt sich höchstens auf null kürzen — was nicht ausgegeben wird,
     * kann nicht gespart werden. Die Grenze gilt für die SUMME aller Anträge auf
     * dasselbe Ziel, nicht für den einzelnen: drei Anträge zu je 40% eines Budgets
     * kürzen zusammen um 120% und sind damit unmöglich.
     */
    public function testKuerzungHoechstensAufNull(): void {
        // Genau auf null geht.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -100000]);
        self::assertSame(-100000, $a->getBetragDelta(), 'die Kürzung auf genau null ist erlaubt');

        // Einen Franken weiter nicht.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/mehr gekürzt|höchstens auf null|100/u');
        $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -100001]);
    }

    public function testKuerzungsgrenzeGiltFuerDieSummeAllerAntraege(): void {
        $vorhanden = new BudgetAntrag();
        $vorhanden->setJahr(2026);
        $vorhanden->setBereich('globalbudget');
        $vorhanden->setZielTyp('produktegruppe');
        $vorhanden->setZielRef('121');
        $vorhanden->setBetragDelta(-60000);
        $vorhanden->setPhase('fraktion');

        // −60'000 liegen schon auf der Gruppe; −50'000 mehr wären zusammen −110'000
        // auf ein Budget von 100'000.
        $this->expectException(\RuntimeException::class);
        $this->service(null, [$vorhanden])->antragErstellen(
            2026,
            ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -50000],
        );
    }

    /**
     * Bug 2026-08-31: Das Einlesen der Sitzungsanträge aus dem Drehbuch scheiterte
     * an der Kürzungsgrenze. In einer Sitzung stellen mehrere Fraktionen Anträge
     * auf dieselbe Produktegruppe; zusammengezählt kürzen sie weit über das Budget
     * hinaus, ohne dass davon je mehr als einer angenommen würde. Die Grenze gilt
     * für die Anträge, die ZUSAMMEN WIRKEN — die eigenen und die unterstützten —,
     * nicht für konkurrierende fremde Anträge, die nur festhalten, was im Rat
     * gestellt wurde.
     */
    public function testFremdeAntraegeBlockierenEinanderNicht(): void {
        $fremd = new BudgetAntrag();
        $fremd->setJahr(2026);
        $fremd->setBereich('globalbudget');
        $fremd->setZielTyp('produktegruppe');
        $fremd->setZielRef('121');
        $fremd->setBetragDelta(-90000);
        $fremd->setPhase('sitzung');
        $fremd->setHerkunft('fremde');

        // Ein zweiter fremder Antrag auf dieselbe Gruppe, zusammen weit über dem
        // Budget von 100'000 — er wird trotzdem angelegt.
        $a = $this->service(null, [$fremd])->antragErstellen(2026, [
            'bereich' => 'globalbudget',
            'zielRef' => '121',
            'betragDelta' => -80000,
            'herkunft' => 'fremde',
            'phase' => 'sitzung',
        ]);
        self::assertSame(-80000, $a->getBetragDelta(), 'ein fremder Antrag wird an der Grenze abgewiesen');
    }

    /** Ein unterstützter fremder Antrag zählt dagegen mit. */
    public function testUnterstuetzteFremdeAntraegeZaehlenZurGrenze(): void {
        $fremd = new BudgetAntrag();
        $fremd->setJahr(2026);
        $fremd->setBereich('globalbudget');
        $fremd->setZielTyp('produktegruppe');
        $fremd->setZielRef('121');
        $fremd->setBetragDelta(-90000);
        $fremd->setPhase('fraktion');
        $fremd->setHerkunft('fremde');
        $fremd->setHaltung('unterstuetzen');

        $this->expectException(\RuntimeException::class);
        $this->service(null, [$fremd])->antragErstellen(2026, [
            'bereich' => 'globalbudget',
            'zielRef' => '121',
            'betragDelta' => -20000,
        ]);
    }

    public function testKuerzungsgrenzeGiltAuchFuerInvestitionen(): void {
        $this->expectException(\RuntimeException::class);
        $this->service()->antragErstellen(
            2026,
            ['bereich' => 'investition', 'zielTyp' => 'investition', 'zielRef' => '7', 'betragDelta' => -500001],
        );
    }

    public function testErhoehungBleibtUnbegrenzt(): void {
        // Nach oben gibt es keine Grenze — nur das Sparen ist bei null zu Ende.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => 900000]);
        self::assertSame(900000, $a->getBetragDelta());
    }

    public function testHerkunftUndHaltungStandard(): void {
        // F94/F97: Standard-Herkunft «eigene», Standard-Haltung «einreichen».
        $eigen = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000]);
        self::assertSame('eigene', $eigen->getHerkunft());
        self::assertSame('einreichen', $eigen->haltungOderStandard());
        // Fremd ohne Haltung → «offen».
        $fremd = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000, 'herkunft' => 'fremde']);
        self::assertSame('fremde', $fremd->getHerkunft());
        self::assertSame('offen', $fremd->haltungOderStandard());
    }

    public function testHaltungWirdAufHerkunftBereinigt(): void {
        // F97: eine für die Herkunft unpassende Haltung fällt auf den Standard zurück.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000, 'herkunft' => 'eigene', 'haltung' => 'unterstuetzen']);
        self::assertSame('einreichen', $a->haltungOderStandard(), 'ungültige Haltung → Herkunfts-Standard (F97)');
    }

    public function testNotizHinzufuegenNutztBudgetAntragTyp(): void {
        // F103: Notizen laufen über den geteilten NotizService mit dem Objekttyp
        // «budget-antrag» — dieselbe Mechanik wie beim Vorstoss.
        $notiz = $this->createStub(NotizService::class);
        $erfasst = [];
        $notiz->method('hinzufuegen')->willReturnCallback(function ($typ, $id, $text) use (&$erfasst) {
            $erfasst = [$typ, $id, $text];
            return ['id' => 1, 'text' => $text];
        });
        $this->service($notiz)->notizHinzufuegen(42, 'Bitte kürzen');
        self::assertSame(['budget-antrag', 42, 'Bitte kürzen'], $erfasst, 'Notiz delegiert an den geteilten Dienst (F103)');
    }

    public function testUnterstuetzerFraktionenGespeichert(): void {
        // F98: die Liste der unterstützenden Fraktionen wird als [{key,name}] gespeichert.
        $a = $this->service()->antragErstellen(2026, [
            'bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000,
            'unterstuetzer' => ['Grüne', 'SP'],
        ]);
        $namen = array_map(static fn ($f) => $f['name'], $a->getUnterstuetzerArray());
        self::assertSame(['Grüne', 'SP'], $namen, 'unterstützende Fraktionen gespeichert (F98)');
    }

    public function testZielAenderungenGespeichert(): void {
        // F109: ein Antrag kann Zielvorgaben ändern; die Änderungen liegen als Liste
        // [{zielNummer, messgroesse, neuerWert}] am Antrag. Reiner Zielvorgaben-Antrag
        // ohne Budget: der Betrag bleibt null.
        $a = $this->service()->antragErstellen(2026, [
            'bereich' => 'globalbudget', 'zielRef' => '121',
            'zielAenderungen' => [
                ['zielNummer' => 2, 'messgroesse' => 'Prozentsatz der zufrieden Antwortenden', 'neuerWert' => '100'],
                ['zielNummer' => 3, 'messgroesse' => 'Anzahl Kurstage', 'neuerWert' => '1200'],
                ['zielNummer' => 0, 'messgroesse' => '', 'neuerWert' => ''], // verworfen
            ],
        ]);
        $zv = $a->getZielAenderungenArray();
        self::assertCount(2, $zv, 'leere Änderung verworfen');
        self::assertSame(2, $zv[0]['zielNummer']);
        self::assertSame('100', $zv[0]['neuerWert']);
        self::assertSame(0, $a->getBetragDelta(), 'reiner Zielvorgaben-Antrag hat keine Budgetwirkung');
    }

    public function testAufteilungOhneObenBetragErgibtSumme(): void {
        // F109: ist auf PG-Ebene kein Betrag gesetzt, unten aber schon, wird oben die
        // Summe der unteren Beträge eingesetzt (npnp: ohne aufteilungSummeAnwenden 0).
        $a = $this->service()->antragErstellen(2026, [
            'bereich' => 'globalbudget', 'zielRef' => '121',
            'aufteilung' => [
                ['ebene' => 'produkt', 'ref' => '1', 'betrag' => -20000],
                ['ebene' => 'produkt-kosten', 'produkt' => '2', 'ref' => 'Sachkosten', 'betrag' => -30000],
                ['ebene' => 'pg-kosten', 'ref' => 'Personalkosten'], // ohne Betrag → zählt nicht zur Summe
            ],
        ]);
        self::assertSame(-50000, $a->getBetragDelta(), 'PG-Betrag = Summe der unteren Beträge');
        self::assertCount(3, $a->getAufteilungArray(), 'die Aufteilung selbst bleibt vollständig erhalten');
    }

    public function testAufteilungNurBegruendungWennObenBetrag(): void {
        // F109: ist oben UND unten ein Betrag gesetzt, wird nichts gerechnet — die
        // Aufteilung dient nur der Begründung; der obere Betrag bleibt unverändert.
        $a = $this->service()->antragErstellen(2026, [
            'bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -100000,
            'aufteilung' => [
                ['ebene' => 'produkt', 'ref' => '1', 'betrag' => -20000],
            ],
        ]);
        self::assertSame(-100000, $a->getBetragDelta(), 'oben gesetzter Betrag bleibt unverändert');
        self::assertCount(1, $a->getAufteilungArray());
    }
}
