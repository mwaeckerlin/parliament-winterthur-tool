<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetRechnung;
use PHPUnit\Framework\TestCase;

/**
 * Reine Budget-Rechenlogik: Summen, anteilige Verteilung (proportional zum
 * Aufwand), benötigter Verteilbetrag und automatische Steuerfuss-Senkung.
 * Deckt F79, F83–F85, F88.
 */
class BudgetRechnungTest extends TestCase {
    /** @return array<int, array<string, float|int>> */
    private static function gruppen(): array {
        return [
            ['code' => '121', 'aufwandSoll' => 7000000, 'ertragSoll' => 2000000, 'stellenSoll' => 19.5,
             'aufwandVorjahr' => 6800000, 'ertragVorjahr' => 1900000, 'stellenVorjahr' => 16.85],
            ['code' => '142', 'aufwandSoll' => 3000000, 'ertragSoll' => 1000000, 'stellenSoll' => 10.0,
             'aufwandVorjahr' => 2900000, 'ertragVorjahr' => 950000, 'stellenVorjahr' => 10.0],
        ];
    }

    public function testSummenOhneAntraege(): void {
        $s = BudgetRechnung::summen(self::gruppen(), []);
        $this->assertSame(10000000, $s['ausgaben']);
        $this->assertSame(3000000, $s['einnahmen']);
        $this->assertSame(-7000000, $s['ergebnis']);
        $this->assertSame(29.5, $s['stellen']);
        // Differenz zum Vorjahr.
        $this->assertSame(300000, $s['ausgabenDiff']);   // 10.0M - 9.7M
        $this->assertSame(150000, $s['einnahmenDiff']);  // 3.0M - 2.85M
        $this->assertSame(-150000, $s['ergebnisDiff']);  // -7.0M - (-6.85M)
    }

    public function testSummenMitGlobalbudgetUndPersonalAntrag(): void {
        $antraege = [
            ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -500000, 'stellenDelta' => 0.0],
            ['bereich' => 'personal', 'zielRef' => '142', 'betragDelta' => -400000, 'stellenDelta' => -2.0],
        ];
        $s = BudgetRechnung::summen(self::gruppen(), $antraege);
        $this->assertSame(9100000, $s['ausgaben']);   // 10.0M - 0.5M - 0.4M
        $this->assertSame(3000000, $s['einnahmen']);
        $this->assertSame(-6100000, $s['ergebnis']);
        $this->assertSame(27.5, $s['stellen']);       // 29.5 - 2
    }

    public function testMehrereAntraegeAufSelberPositionSummierenSich(): void {
        // F99: zwei Anträge auf dieselbe Produktegruppe addieren sich.
        $antraege = [
            ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -500000, 'stellenDelta' => 0.0],
            ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -300000, 'stellenDelta' => 0.0],
        ];
        $s = BudgetRechnung::summen(self::gruppen(), $antraege);
        $this->assertSame(9200000, $s['ausgaben']); // 10.0M - 0.5M - 0.3M
    }

    public function testKuerzungGehtHoechstensAufNull(): void {
        // Eine Position lässt sich höchstens auf null kürzen. Was sie nicht mehr
        // tragen kann, geht auf die übrigen; reicht auch das nicht, bleibt der Rest
        // liegen — erfunden wird nichts.
        $eine = BudgetRechnung::verteileAnteiligAufwand(-9000000, [self::gruppen()[1]]);
        $this->assertSame(-3000000, $eine['142'], 'die Position trägt nur ihr eigenes Budget');

        // Anteilig zum Aufwand bleibt jede Position von selbst im Rahmen, solange
        // der Gesamtbetrag das Gesamtbudget nicht übersteigt.
        $beide = BudgetRechnung::verteileAnteiligAufwand(-9500000, self::gruppen());
        $this->assertSame(-9500000, array_sum($beide), 'zusammen tragen sie den ganzen Betrag');
        foreach ($beide as $code => $delta) {
            $this->assertGreaterThanOrEqual(-10000000, $delta, "Position $code unter null");
        }

        $zuviel = BudgetRechnung::verteileAnteiligAufwand(-12000000, self::gruppen());
        $this->assertSame(-10000000, array_sum($zuviel), 'mehr als das ganze Budget geht nicht');
        $this->assertSame(-7000000, $zuviel['121'], 'die grosse Position steht auf null');
        $this->assertSame(-3000000, $zuviel['142'], 'die kleine Position steht auf null');
    }

    public function testAusnahmeVerteiltDenGesamtbetragNeu(): void {
        // F101: wird eine Position ausgenommen, verteilt sich derselbe Gesamt-
        // betrag vollständig auf die übrigen — die eingesparte Summe bleibt gleich.
        $ohne = BudgetRechnung::verteileAnteiligAufwand(-7000000, self::gruppen());
        $this->assertSame(-7000000, array_sum($ohne));
        $nurEine = BudgetRechnung::verteileAnteiligAufwand(-7000000, [self::gruppen()[0]]);
        $this->assertSame(-7000000, $nurEine['121'], 'alles geht auf die verbleibende Position');
        $this->assertSame(-7000000, array_sum($nurEine));
    }

    public function testSteuerfussAntragWirktAufEinnahmen(): void {
        $antraege = [['bereich' => 'steuerfuss', 'zielRef' => '', 'betragDelta' => -2000000, 'stellenDelta' => 0.0]];
        $s = BudgetRechnung::summen(self::gruppen(), $antraege);
        $this->assertSame(10000000, $s['ausgaben']);
        $this->assertSame(1000000, $s['einnahmen']);  // 3.0M - 2.0M
        $this->assertSame(-9000000, $s['ergebnis']);
    }

    public function testBenoetigterDelta(): void {
        // Defizit -7M → schwarze Null verlangt Kürzung von 7M.
        $this->assertSame(-7000000, BudgetRechnung::benoetigterDelta(-7000000, 'schwarze_null', 0));
        // Akzeptiertes Defizit von 2M → nur 5M kürzen.
        $this->assertSame(-5000000, BudgetRechnung::benoetigterDelta(-7000000, 'defizit', -2000000));
        // Gewünschter Ertrag von 1M → 8M kürzen.
        $this->assertSame(-8000000, BudgetRechnung::benoetigterDelta(-7000000, 'ertrag', 1000000));
        // Überschuss über Ziel → positiver Delta (wird von der Automatik nicht verteilt).
        $this->assertSame(5000000, BudgetRechnung::benoetigterDelta(5000000, 'schwarze_null', 0));
    }

    public function testVerteilungAnteiligZumAufwand(): void {
        $d = BudgetRechnung::verteileAnteiligAufwand(-7000000, self::gruppen());
        $this->assertSame(-4900000, $d['121']);  // 7M-Anteil
        $this->assertSame(-2100000, $d['142']);  // 3M-Anteil
        $this->assertSame(-7000000, array_sum($d));
    }

    public function testVerteilungRundungsrestGehtAnGroessteGruppe(): void {
        // Gleiche Aufwände, damit der Rundungsrest entsteht; gross genug, dass die
        // Kürzung nicht an der Nullgrenze der Positionen hängen bleibt.
        $gruppen = [
            ['code' => 'A', 'aufwandSoll' => 1000, 'ertragSoll' => 0, 'stellenSoll' => 0],
            ['code' => 'B', 'aufwandSoll' => 1000, 'ertragSoll' => 0, 'stellenSoll' => 0],
            ['code' => 'C', 'aufwandSoll' => 1000, 'ertragSoll' => 0, 'stellenSoll' => 0],
        ];
        $d = BudgetRechnung::verteileAnteiligAufwand(-100, $gruppen);
        // Summe exakt, kein Rappen geht verloren.
        $this->assertSame(-100, array_sum($d));
    }

    public function testSteuerfussSenkung(): void {
        // 1 Steuerprozent = 250M / 125 = 2M. Überschuss 7M → 3 Prozent senkbar.
        $r = BudgetRechnung::steuerfussSenkung(7000000, 250000000, 125);
        $this->assertSame(2000000, $r['wertProProzent']);
        $this->assertSame(3, $r['gesenkteProzent']);
        $this->assertSame(122, $r['neuerSteuerfuss']);
        $this->assertSame(6000000, $r['reduktion']);
    }

    public function testKeineSenkungOhneUeberschuss(): void {
        $r = BudgetRechnung::steuerfussSenkung(-5000000, 250000000, 125);
        $this->assertSame(0, $r['gesenkteProzent']);
        $this->assertSame(125, $r['neuerSteuerfuss']);
        $this->assertSame(0, $r['reduktion']);
    }
}
