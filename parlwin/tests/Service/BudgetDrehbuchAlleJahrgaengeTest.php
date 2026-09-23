<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Jede Sitzungsunterlage mit den Anträgen, die das Parlament zum Budget gestellt
 * hat — das «Drehbuch zur Behandlung des Budgets» der Jahrgänge 2022 bis 2026
 * (F90). Die Dokumente liegen als Testfixtures unter
 * `tests/Fixtures/drehbuch/<Jahr>/` und stammen von der Parlamentswebseite. Für
 * die Budgets 2017 bis 2021 gibt es keine: Das Parlament veröffentlicht die
 * Anträge erst seit dem Budget 2022 als eigenes Dokument (2022 unter dem Titel
 * «Anträge zum Budget 2022», seither als «Drehbuch»).
 *
 * Die erwarteten Zahlen sind in den Dokumenten Seite für Seite nachgezählt, nicht
 * vom Parser übernommen. Die Drehbücher unterscheiden sich stark:
 *
 * - **2022** nennt im Antragssatz nur die Begründung; Betrag und Gremium stehen
 *   allein in den Spalten «Antrag Kom.» / «Antrag Fraktion». Ausserdem trägt es
 *   die Kommission «BBK», die es später nicht mehr gibt.
 * - **2023** schreibt teils «Globalbudget» statt «Globalkredit» und schiebt
 *   Wörter zwischen Gegenstand und Betrag («… des Globalkredits der städtischen
 *   Allgemeinkosten um CHF …»).
 * - **2024 bis 2026** nennen Richtung und Betrag im Satz.
 *
 * Gruppe «pdf»: braucht vendor/ (smalot), läuft über `npm run test:pdf`.
 */
#[Group('pdf')]
class BudgetDrehbuchAlleJahrgaengeTest extends TestCase {
    /** @return array{antraege: list<array<string, mixed>>, novemberbrief: array{produktegruppen: list<array<string, mixed>>}} */
    private function lies(int $jahr): array {
        $pfad = \dirname(__DIR__, 1) . '/Fixtures/drehbuch/' . $jahr . '/drehbuch.pdf';
        return (new BudgetDrehbuchParser())->parse($pfad, $jahr);
    }

    /**
     * Je Jahrgang die Zahl der Anträge, die das Drehbuch führt — im Dokument
     * Seite für Seite gezählt. Anträge zu parlamentarischen Zielvorgaben und zu
     * Verpflichtungskrediten zählen nicht mit: Sie ändern keinen Globalkredit.
     *
     * @return array<string, array{int, int}>
     */
    public static function jahrgaenge(): array {
        return [
            // 18 Seiten, davon 16 mit Produktegruppen; 425 und 470 tragen nur
            // Anträge zu Zielvorgaben und Verpflichtungskrediten.
            '2022' => [2022, 18],
            // 11 Produktegruppen-Seiten; 522 trägt nur einen Zielvorgaben-Antrag.
            '2023' => [2023, 10],
            '2024' => [2024, 16],
            // 14 Globalbudget-Anträge und der Steuerfuss-Antrag der Grünen/AL.
            '2025' => [2025, 15],
            '2026' => [2026, 35],
        ];
    }

    #[DataProvider('jahrgaenge')]
    public function testJedesDrehbuchLiefertSeineAntraege(int $jahr, int $anzahl): void {
        $antraege = $this->lies($jahr)['antraege'];
        self::assertCount($anzahl, $antraege, "Anträge im Drehbuch $jahr");

        foreach ($antraege as $a) {
            self::assertMatchesRegularExpression('/^\d{3}$/', (string) $a['code'], "PG-Code im Drehbuch $jahr");
            self::assertNotSame(0, (int) $a['betragDelta'], "Antrag ohne Betrag im Drehbuch $jahr");
            self::assertContains($a['gremium'], ['kommission', 'fraktion'], "Gremium im Drehbuch $jahr");
            self::assertContains($a['bereich'], ['globalbudget', 'steuerfuss'], "Bereich im Drehbuch $jahr");
            self::assertNotSame('', trim((string) $a['antragsteller']), "Antrag ohne Antragsteller ($jahr)");
        }
    }

    /**
     * Drehbuch 2022, Seite für Seite nachgeschlagen. Der Antragssatz nennt dort
     * keinen Betrag; er steht in der Spalte «Antrag Kom.» bzw. «Antrag Fraktion»
     * der Nettokosten-Zeile:
     *
     *   222000 Informatikdienste   I. Kuster (Mitte/EDU)  - 100'000   (7:4 angenommen)
     *   280000 Steuern und Fin.    M. Wäckerlin (PP)      123'562'800
     *   322000 Tiefbau             Fraktion SVP           - 22'500
     *   860000 Ombuds- und Daten.  D. Oswald (SVP)        - 10'000    (6:5 angenommen)
     *
     * Die Produktegruppen 322, 328, 360 und 770 stehen unter der Kommission
     * «BBK»; ohne sie fielen ihre acht Anträge der Produktegruppe «Steuern und
     * Finanzausgleich» zu.
     */
    public function testDrehbuch2022NimmtDenBetragAusDerAntragsspalte(): void {
        $antraege = $this->lies(2022)['antraege'];
        $nach = [];
        foreach ($antraege as $a) {
            $nach[$a['code'] . '|' . $a['betragDelta']] = $a;
        }

        self::assertArrayHasKey('222|-100000', $nach, 'Informatikdienste: Kürzung um 100\'000');
        self::assertSame('7:4 angenommen', $nach['222|-100000']['ergebnis']);
        self::assertSame('kommission', $nach['222|-100000']['gremium']);

        self::assertArrayHasKey('280|123562800', $nach, 'Steuern und Finanzausgleich: Erhöhung um 123,6 Mio.');
        self::assertArrayHasKey('322|-22500', $nach, 'Tiefbau: Reduktion um 22\'500 (Kommission BBK)');
        self::assertSame('fraktion', $nach['322|-22500']['gremium']);
        self::assertArrayHasKey('860|-10000', $nach, 'Ombuds- und Datenschutzstelle: Reduktion um 10\'000');
        self::assertSame('6:5 angenommen', $nach['860|-10000']['ergebnis']);
    }

    /**
     * Drehbuch 2023, Seite für Seite nachgeschlagen — alle zehn Anträge mit
     * Produktegruppe und Betrag:
     *
     *   142 Fraktion FDP            -80'000   («Globalbudgets»)
     *   157 Fraktion SVP           -225'000
     *   222 Fraktion Die Mitte/EDU -300'000   («Globalbudgets»)
     *   263 AK                    +2'200'000  («… des Globalkredits der städtischen Allgemeinkosten um …»)
     *   360 Fraktion Die Mitte/EDU -250'000
     *   514 BSKK                   -500'000
     *   621 Fraktion FDP           -250'000
     *   770 UBK                    +100'000   («Erhöhung Globalkredit für die Zertifizierung … um …»)
     *   810 Fraktion FDP           -160'000
     *   860 AK                      -83'000
     */
    public function testDrehbuch2023MitAllenAntraegen(): void {
        $gelesen = [];
        foreach ($this->lies(2023)['antraege'] as $a) {
            $gelesen[] = $a['code'] . ' ' . $a['betragDelta'];
        }
        self::assertSame(
            [
                '142 -80000',
                '157 -225000',
                '222 -300000',
                '263 2200000',
                '360 -250000',
                '514 -500000',
                '621 -250000',
                '770 100000',
                '810 -160000',
                '860 -83000',
            ],
            $gelesen,
            'Die Anträge des Drehbuchs 2023'
        );
    }

    /**
     * Drehbuch 2024: Ein Antrag auf eine parlamentarische Zielvorgabe steht in
     * einer eigenen Tabelle und trägt keinen Betrag. Er darf den Betrag der
     * Nettokosten-Zeile nicht erben — sonst zählte die Reduktion des Personalamts
     * um 100'000 Franken zweimal.
     */
    public function testEinZielvorgabenAntragErbtKeinenBetrag(): void {
        $ausPersonalamt = array_values(array_filter(
            $this->lies(2024)['antraege'],
            static fn ($a) => (string) $a['code'] === '121'
        ));
        self::assertCount(1, $ausPersonalamt, 'Personalamt 2024: nur der Antrag auf den Globalkredit');
        self::assertSame(-100000, (int) $ausPersonalamt[0]['betragDelta']);
    }

    /**
     * Drehbuch 2025: Der Antrag der Fraktion Grüne/AL auf einen Steuerfuss von
     * 127 Prozent steht in der Antragsspalte der Produktegruppe «Steuern und
     * Finanzausgleich» mit -6'000'000. Er ist kein Antrag auf einen Globalkredit
     * und wird deshalb als Steuerfuss-Antrag geführt.
     */
    public function testEinSteuerfussAntragWirdAlsSolcherGefuehrt(): void {
        $steuerfuss = array_values(array_filter(
            $this->lies(2025)['antraege'],
            static fn ($a) => $a['bereich'] === 'steuerfuss'
        ));
        self::assertCount(1, $steuerfuss, 'Steuerfuss-Antrag im Drehbuch 2025');
        self::assertSame('280', (string) $steuerfuss[0]['code']);
        self::assertSame(-6_000_000, (int) $steuerfuss[0]['betragDelta']);
        self::assertStringContainsString('127 Prozent', (string) $steuerfuss[0]['begruendung']);
    }

    /**
     * Ein im PDF zerrissener Betrag wird zusammengesetzt: Das Drehbuch 2023 setzt
     * «250'000» als «250'00» und «0». Gelesen als zwei Stücke ergäbe das 25'000 —
     * den zehnten Teil des Antrags.
     */
    public function testEinZerrissenerBetragWirdZusammengesetzt(): void {
        $ausStaedtebau = array_values(array_filter(
            $this->lies(2023)['antraege'],
            static fn ($a) => (string) $a['code'] === '360'
        ));
        self::assertCount(1, $ausStaedtebau, 'Städtebau 2023');
        self::assertSame(-250000, (int) $ausStaedtebau[0]['betragDelta'], 'Reduktion um 250\'000, nicht um 25\'000');
    }
}
