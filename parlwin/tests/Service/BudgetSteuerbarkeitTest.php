<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetRechnung;
use OCA\ParliamentWinterthur\Service\BudgetSteuerbarkeit;
use PHPUnit\Framework\TestCase;

/**
 * Gebundene, interne und beeinflussbare Kosten (F128).
 *
 * Marc am 24.10.2026: «man kann in der Anzeige splitten zwischen fix und
 * flexibel. Vor allem sollte sich der kürzbare Anteil, insbesondere bei der
 * automatischen Verteilung prozentual, auf den flexiblen Teil beschränken. […]
 * Daher soll es vor allem eine Orientierung sein und nicht eine feste Sperre
 * oder feste Regel. Ausser bei der automatischen Verteilung, da soll nur auf
 * den flexiblen Anteil verteilt werden.»
 */
final class BudgetSteuerbarkeitTest extends TestCase {
    /**
     * Die zwölf Zeilen, die das Budgetbuch 2027 je Produktegruppe führt
     * (gemessen über alle Produktegruppen am 24.10.2026).
     */
    public function testDieZeilenDesBuchsSindRichtigEingestuft(): void {
        $erwartet = [
            'Personalkosten' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Informatikkosten' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Sachkosten' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Übrige Kosten' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Verkäufe' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Übrige externe Erlöse' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Übrige externe Erlöse, v.a. Steuererträge' => BudgetSteuerbarkeit::BEEINFLUSSBAR,
            'Residualkosten' => BudgetSteuerbarkeit::NEUTRAL,
            'Mietkosten' => BudgetSteuerbarkeit::NEUTRAL,
            'Interne Erlöse' => BudgetSteuerbarkeit::NEUTRAL,
            'Verrechnung innerhalb PG' => BudgetSteuerbarkeit::NEUTRAL,
            'Kalk. Abschreibungen und Zinsen / Finanzaufwand' => BudgetSteuerbarkeit::GEBUNDEN,
            'Beiträge an Dritte' => BudgetSteuerbarkeit::GEBUNDEN,
            'Beiträge von Dritten' => BudgetSteuerbarkeit::GEBUNDEN,
            'Beiträge von Dritten, v.a. Finanzausgleich' => BudgetSteuerbarkeit::GEBUNDEN,
            'Gebühren' => BudgetSteuerbarkeit::GEBUNDEN,
            'Gebühren / Entgelte' => BudgetSteuerbarkeit::GEBUNDEN,
        ];
        foreach ($erwartet as $label => $stufe) {
            self::assertSame($stufe, BudgetSteuerbarkeit::fuerLabel($label), $label);
        }
    }

    public function testAbschreibungenZinsenUndBeitraegeSindGebunden(): void {
        foreach ([
            'Abschreibungen Verwaltungsvermögen',
            'Kalkulatorische Zinsen',
            'Beiträge an Gemeinwesen und Dritte',
            'Einlage in Spezialfinanzierung',
            'Transferaufwand',
        ] as $label) {
            self::assertSame(
                BudgetSteuerbarkeit::GEBUNDEN,
                BudgetSteuerbarkeit::fuerLabel($label),
                $label
            );
        }
    }

    public function testGebuehrenSindGebunden(): void {
        // Eine Gebühr darf nur kostendeckend sein: sie lässt sich nicht frei
        // erhöhen, um ein Defizit zu decken.
        self::assertSame(BudgetSteuerbarkeit::GEBUNDEN, BudgetSteuerbarkeit::fuerLabel('Gebühren und Entgelte'));
    }

    public function testInterneVerrechnungenSindNeutral(): void {
        foreach (['Interne Verrechnungen', 'Verrechnung innerhalb der Stadt', 'Durchlaufende Beiträge', 'Interne Erlöse', 'Residualkosten'] as $label) {
            self::assertSame(BudgetSteuerbarkeit::NEUTRAL, BudgetSteuerbarkeit::fuerLabel($label), $label);
        }
    }

    public function testPersonalUndSachaufwandSindBeeinflussbar(): void {
        foreach (['Personalaufwand', 'Sach- und übriger Betriebsaufwand', 'Dienstleistungen Dritter'] as $label) {
            self::assertSame(BudgetSteuerbarkeit::BEEINFLUSSBAR, BudgetSteuerbarkeit::fuerLabel($label), $label);
        }
    }

    public function testEineUnbekannteZeileGiltAlsBeeinflussbar(): void {
        // Die Einstufung soll den Spielraum nicht kleiner zeigen, als er ist.
        self::assertSame(BudgetSteuerbarkeit::BEEINFLUSSBAR, BudgetSteuerbarkeit::fuerLabel('Irgendetwas Neues'));
    }

    public function testSummenZaehlenNurDenAufwand(): void {
        $zeilen = [
            ['label' => 'Personalkosten', 'soll' => 1000],
            ['label' => 'Kalk. Abschreibungen und Zinsen / Finanzaufwand', 'soll' => 500],
            ['label' => 'Residualkosten', 'soll' => 200],
            ['label' => 'Gebühren', 'soll' => -300],
        ];
        self::assertSame(
            ['gebunden' => 500, 'neutral' => 200, 'beeinflussbar' => 1000],
            BudgetSteuerbarkeit::summen($zeilen)
        );
    }

    public function testJedeZeileTraegtIhreStufe(): void {
        $mit = BudgetSteuerbarkeit::mitStufe([['label' => 'Kalk. Abschreibungen und Zinsen / Finanzaufwand', 'soll' => 10]]);
        self::assertSame(BudgetSteuerbarkeit::GEBUNDEN, $mit[0]['steuerbarkeit']);
    }

    public function testOhneGliederungBleibtDerGanzeAufwandDieBezugsgroesse(): void {
        // Eine Produktegruppe darf nicht wegen fehlender Daten aus der
        // Verteilung fallen.
        self::assertSame(7000, BudgetSteuerbarkeit::beeinflussbarerAufwand([], 7000));
    }

    public function testDieAutomatischeVerteilungTrifftNurDenFlexiblenAnteil(): void {
        // Zwei Gruppen mit demselben Aufwand, aber verschieden grossem
        // beeinflussbarem Anteil: die Kürzung folgt dem beeinflussbaren Teil.
        $gruppen = [
            [
                'code' => '100',
                'aufwandSoll' => 1000,
                'kostenzeilen' => [
                    ['label' => 'Personalkosten', 'soll' => 900],
                    ['label' => 'Kalk. Abschreibungen und Zinsen / Finanzaufwand', 'soll' => 100],
                ],
            ],
            [
                'code' => '200',
                'aufwandSoll' => 1000,
                'kostenzeilen' => [
                    ['label' => 'Personalkosten', 'soll' => 100],
                    ['label' => 'Kalk. Abschreibungen und Zinsen / Finanzaufwand', 'soll' => 900],
                ],
            ],
        ];
        $verteilung = BudgetRechnung::verteileAnteiligAufwand(-100, $gruppen);
        self::assertSame(-90, $verteilung['100'], 'die Gruppe mit viel Spielraum trägt zu wenig');
        self::assertSame(-10, $verteilung['200'], 'die gebundene Gruppe trägt zu viel');
    }

    public function testEineGruppeOhneFlexiblenAnteilTraegtNichts(): void {
        $gruppen = [
            ['code' => '100', 'aufwandSoll' => 1000, 'kostenzeilen' => [['label' => 'Personalkosten', 'soll' => 1000]]],
            ['code' => '200', 'aufwandSoll' => 1000, 'kostenzeilen' => [['label' => 'Kalk. Abschreibungen und Zinsen / Finanzaufwand', 'soll' => 1000]]],
        ];
        $verteilung = BudgetRechnung::verteileAnteiligAufwand(-100, $gruppen);
        self::assertSame(-100, $verteilung['100']);
        self::assertSame(0, $verteilung['200'], 'eine ganz gebundene Gruppe wurde gekürzt');
    }

    public function testDieKuerzungHoertAmFlexiblenAnteilAuf(): void {
        // Mehr als den beeinflussbaren Anteil kürzt die Automatik nicht; der
        // Rest bleibt liegen und ist im Budget sichtbar.
        $gruppen = [
            ['code' => '100', 'aufwandSoll' => 1000, 'kostenzeilen' => [['label' => 'Personalkosten', 'soll' => 200]]],
        ];
        $verteilung = BudgetRechnung::verteileAnteiligAufwand(-500, $gruppen);
        self::assertSame(-200, $verteilung['100']);
    }
}
