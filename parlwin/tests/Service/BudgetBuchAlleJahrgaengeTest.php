<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Jedes Budgetbuch, das die Stadt veröffentlicht hat, wird gelesen — Teil A und
 * Teil B der Jahrgänge 2017 bis 2027 (F89). Die Bücher liegen als Testfixtures
 * unter `tests/Fixtures/budget/<Jahr>/`, geladen von der Parlamentswebseite über
 * die Beilagen des jeweiligen Budget-Geschäfts.
 *
 * Die Prüfwerte stammen aus den Büchern selbst, nicht aus dem Parser. Zwei davon
 * sind im Buch nachgeschlagen (Personalamt 2017 und 2018); die übrigen sind
 * Identitäten, die jedes Buch erfüllt:
 *
 * - Nettokosten/Globalkredit = Total effektive Kosten − Total effektive Erlöse,
 *   je Produktegruppe und je Spalte. Eine verrutschte Spalte, eine als Wertzeile
 *   gelesene Fliesstextzeile und eine im PDF zerrissene Zahl brechen sie sofort.
 * - Gesamtergebnis = Total Ertrag − Total Aufwand (Teil A, gestufter
 *   Erfolgsausweis).
 *
 * Gruppe «pdf»: braucht vendor/ (smalot), läuft über `npm run test:pdf`.
 */
#[Group('pdf')]
class BudgetBuchAlleJahrgaengeTest extends TestCase {
    /**
     * Die Stellen, an denen das Buch seinen Globalkredit anders ausweist als
     * «Kosten − Erlöse», mit der Zahl, die im Dokument steht — Jahr:Code:Spalte.
     *
     * - **825 Ombudsstelle, Ist 2024:** Das Buch rechnet die Nettokosten aus den
     *   Zeilen «inkl. Verrechnung» (377'355 − 134'010 = 243'345), während die
     *   Kosten-Zeile «Total effektive Kosten» 376'532 nennt — die 823 Franken
     *   Differenz sind die Verrechnung innerhalb der Produktegruppe. Die
     *   Tabelle «Nettokosten» derselben Seite nennt dafür 242'522: Das Buch
     *   widerspricht sich, und massgebend ist die Zeile «Nettokosten /
     *   Globalkredit», über die das Parlament beschliesst.
     * - **845 Stadtrat, Soll 2026:** Kosten 4'296'248 − Erlöse 100'000 ergäben
     *   4'196'248; das Buch weist als Globalkredit 2'516'466 aus — denselben
     *   Betrag wie für die Planjahre. Die einmaligen Kosten aus dem Ausscheiden
     *   eines Stadtrats stehen im Aufwand, nicht im Globalkredit.
     */
    private const BUCH_WEICHT_AB = [
        '2026:825:ist' => 243_345,
        '2026:845:soll' => 2_516_466,
    ];

    /** @var array<string, array<string, mixed>> geparste Bücher je Jahr */
    private static array $gelesen = [];

    private function pfad(int $jahr, string $teil): string {
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $jahr . '/teil-' . $teil . '.pdf';
    }

    /** @return array<string, mixed> */
    private function buch(int $jahr): array {
        if (!isset(self::$gelesen[(string) $jahr])) {
            self::$gelesen[(string) $jahr] = (new BudgetBuchParser())
                ->struktur($this->pfad($jahr, 'b'), $this->pfad($jahr, 'a'), $jahr);
        }
        return self::$gelesen[(string) $jahr];
    }

    /**
     * Alle Jahrgänge, für die die Stadt Teil A und Teil B veröffentlicht hat.
     *
     * @return list<array{int}>
     */
    public static function jahrgaenge(): array {
        return [[2017], [2018], [2019], [2020], [2021], [2022], [2023], [2024], [2025], [2026], [2027]];
    }

    /**
     * Teil B führt in jedem Jahrgang mehr als zwanzig Produktegruppen, jede mit
     * dreistelligem Code, Namen und Departement. Fehlt das Departement, steht die
     * Gruppe in der Anzeige unter keinem Titel.
     */
    #[DataProvider('jahrgaenge')]
    public function testJedeProduktegruppeTraegtCodeNameUndDepartement(int $jahr): void {
        $gruppen = $this->buch($jahr)['produktegruppen'];
        self::assertGreaterThan(20, \count($gruppen), "Produktegruppen im Buch $jahr");

        $ohneName = [];
        $ohneDepartement = [];
        foreach ($gruppen as $g) {
            self::assertMatchesRegularExpression('/^\d{3}$/', (string) $g['code'], "Code im Buch $jahr");
            if (trim((string) $g['name']) === '') {
                $ohneName[] = (string) $g['code'];
            }
            if (trim((string) $g['departement']) === '') {
                $ohneDepartement[] = $g['code'] . ' ' . $g['name'];
            }
        }
        self::assertSame([], $ohneName, "Produktegruppen ohne Namen im Buch $jahr");
        self::assertSame([], $ohneDepartement, "Produktegruppen ohne Departement im Buch $jahr");
    }

    /**
     * Die Identität jedes Budgetbuchs: Der Globalkredit einer Produktegruppe ist
     * ihr Total effektive Kosten minus ihr Total effektive Erlöse — in der
     * Ist-Spalte, in der Vorjahresspalte und im Budgetjahr.
     *
     * Sie deckt genau die Fehler auf, die beim Lesen eines PDF entstehen: eine
     * Spalte zu weit links gelesen, ein Satz aus dem Fliesstext als Wertzeile
     * genommen, zwei Zellen zu einer Zahl verklebt.
     *
     * Ein Franken Abweichung gehört dem Buch: Es rundet jede Zeile einzeln auf
     * ganze Franken, und die Nettokosten sind aus den ungerundeten Beträgen
     * gerechnet. In jedem Jahrgang trifft das ein paar Produktegruppen.
     *
     * An zwei Stellen weicht das Buch 2026 selbst ab; dort gilt sein eigener
     * Globalkredit (die Zahl, über die das Parlament beschliesst), und der Test
     * hält genau diese Zahl fest (BUCH_WEICHT_AB).
     */
    #[DataProvider('jahrgaenge')]
    public function testGlobalkreditIstKostenMinusErloese(int $jahr): void {
        $abweichungen = [];
        $geprueft = 0;
        foreach ($this->buch($jahr)['produktegruppen'] as $g) {
            foreach (['ist', 'sollVorjahr', 'soll'] as $spalte) {
                $aufwand = (int) $g['aufwand'][$spalte];
                $ertrag = (int) $g['ertrag'][$spalte];
                $gk = (int) $g['globalkredit'][$spalte];
                // Eine Gruppe ohne jede Zahl in dieser Spalte prüft nichts.
                if ($aufwand === 0 && $ertrag === 0 && $gk === 0) {
                    continue;
                }
                ++$geprueft;
                $eigen = self::BUCH_WEICHT_AB[$jahr . ':' . $g['code'] . ':' . $spalte] ?? null;
                if ($eigen !== null && $gk === $eigen) {
                    continue;
                }
                if (abs(($aufwand - $ertrag) - $gk) > 1) {
                    $abweichungen[] = sprintf(
                        '%s %s [%s]: %d − %d = %d, gelesen %d',
                        $g['code'],
                        $g['name'],
                        $spalte,
                        $aufwand,
                        $ertrag,
                        $aufwand - $ertrag,
                        $gk
                    );
                }
            }
        }
        self::assertGreaterThan(50, $geprueft, "zu wenige geprüfte Spalten im Buch $jahr");
        self::assertSame(
            [],
            $abweichungen,
            "Buch $jahr: Globalkredit stimmt nicht mit Kosten − Erlösen überein: "
            . implode(' | ', \array_slice($abweichungen, 0, 5))
        );
    }

    /**
     * Ein Globalkredit ist ein Betrag. Kleine Beträge kommen vor — die
     * Produktegruppe «Einkauf und Logistik» deckt ihre Kosten fast ganz aus
     * Verrechnungen und steht 2020 mit −111 Franken da —, eine Jahreszahl und ein
     * einstelliger Wert nicht. Beides entsteht, wenn eine Fliesstextzeile als
     * Wertzeile gelesen wird.
     */
    #[DataProvider('jahrgaenge')]
    public function testKeineJahreszahlenAlsGlobalkredit(int $jahr): void {
        $verdaechtig = [];
        foreach ($this->buch($jahr)['produktegruppen'] as $g) {
            foreach ($g['globalkredit'] as $feld => $wert) {
                $jahreszahl = $wert > 1900 && $wert < 2100;
                $winzig = $wert !== 0 && abs($wert) < 10;
                if ($jahreszahl || $winzig) {
                    $verdaechtig[] = "{$g['code']} {$g['name']}: $feld = $wert";
                }
            }
        }
        self::assertSame([], $verdaechtig, "Buch $jahr: Globalkredit-Werte, die kein Betrag sein können");
    }

    /**
     * Teil A: die Kopfzahlen des Budgets — Steuerfuss, Steuerertrag und die
     * Totale von Aufwand und Ertrag. Sie stammen aus der Spalte des Budgetjahres;
     * wer eine andere liest, bekommt plausible Zahlen aus dem falschen Jahr.
     */
    #[DataProvider('jahrgaenge')]
    public function testTeilAKopfzahlenSindInSichStimmig(int $jahr): void {
        $s = $this->buch($jahr);

        self::assertGreaterThan(100, (int) $s['steuerfuss'], "Steuerfuss $jahr");
        self::assertLessThan(150, (int) $s['steuerfuss'], "Steuerfuss $jahr");
        self::assertGreaterThan(300_000_000, (int) $s['steuerertrag'], "Steuerertrag $jahr");

        self::assertGreaterThan(1_000_000_000, (int) $s['totalAufwand'], "Total Aufwand $jahr");
        self::assertGreaterThan(1_000_000_000, (int) $s['totalErtrag'], "Total Ertrag $jahr");
        self::assertNotSame(0, (int) $s['gesamtergebnis'], "Gesamtergebnis $jahr");

        // Das Vorjahr ist das BUDGET des Vorjahres, nie die Rechnung — es liegt in
        // derselben Grössenordnung wie das Budgetjahr.
        self::assertGreaterThan(1_000_000_000, (int) $s['totalAufwandVorjahr'], "Total Aufwand Vorjahr $jahr");
        self::assertGreaterThan(1_000_000_000, (int) $s['totalErtragVorjahr'], "Total Ertrag Vorjahr $jahr");
    }

    /**
     * Die Jahrgänge, deren Erfolgsausweis nach HRM2 aufgebaut ist (ab Budget
     * 2020). Dort ist das Gesamtergebnis die Differenz der beiden Totale, und die
     * Kreuzprobe fällt auf jede verrutschte Spalte.
     *
     * Die Bücher 2017 bis 2019 rechnen nach HRM1: Ihr Ergebnis ist nicht Ertrag
     * minus Aufwand, sondern das, was der Steuerertrag des Rechnungsjahres vom zu
     * deckenden Aufwandüberschuss übrig lässt. Für sie gilt die Probe nicht; ihre
     * Ergebnisse stehen unten einzeln.
     *
     * @return list<array{int}>
     */
    public static function jahrgaengeHrm2(): array {
        return [[2020], [2021], [2022], [2023], [2024], [2025], [2026], [2027]];
    }

    /**
     * Kreuzprobe: Die Totale stammen aus derselben Spalte wie das Gesamtergebnis,
     * also muss Ertrag − Aufwand es ergeben. Die Bücher runden jede Zeile auf
     * 0,1 Mio, deshalb eine Toleranz von 0,3 Mio.
     */
    #[DataProvider('jahrgaengeHrm2')]
    public function testGesamtergebnisIstErtragMinusAufwand(int $jahr): void {
        $s = $this->buch($jahr);
        self::assertEqualsWithDelta(
            (int) $s['gesamtergebnis'],
            (int) $s['totalErtrag'] - (int) $s['totalAufwand'],
            300_000,
            sprintf(
                'Buch %d: Ertrag %s − Aufwand %s ergibt nicht das Gesamtergebnis %s',
                $jahr,
                $s['totalErtrag'],
                $s['totalAufwand'],
                $s['gesamtergebnis']
            )
        );
    }

    /**
     * Die Ergebnisse der HRM1-Bücher, im Buch nachgeschlagen:
     *
     * - Buch 2017, Deckungsübersicht: «Zu deckender Aufwandüberschuss
     *   356'205'996», «Steuerertrag Rechnungsjahr 356'090'000» — das Ergebnis ist
     *   die Differenz, also −115'996.
     * - Buch 2019, Prüfung der Ausgabenbremse: «Jahresergebnis Erfolgsrechnung
     *   Aufwandüberschuss (-) / Ertragsüberschuss (+) −37'111'533.25».
     *
     * @return list<array{int, int}>
     */
    public static function ergebnisseHrm1(): array {
        return [
            [2017, -115_996],
            [2019, -37_111_533],
        ];
    }

    #[DataProvider('ergebnisseHrm1')]
    public function testGesamtergebnisDerAeltestenBuecher(int $jahr, int $ergebnis): void {
        self::assertSame($ergebnis, (int) $this->buch($jahr)['gesamtergebnis'], "Gesamtergebnis $jahr");
    }

    /**
     * Ein Produktname steht vollständig da. Die Überschrift im Kapitel bricht im
     * Satz um («Produkt 1 Ausführung von Vermessungsaufträgen sowie Unterhalt und»
     * / «Erneuerung des Vermessungswerks»), während der Anhang «Gliederung von
     * Budget und Jahresrechnung» denselben Namen auf einer Zeile führt. Wer nur
     * die erste Zeile nimmt, zeigt im Werkzeug einen Namen, der mitten im Satz
     * abbricht — im Geomatik- und Vermessungsamt (340) beide Produkte, in der
     * Volksschule (510) das erste.
     *
     * @return list<array{int, string, int, string}>
     */
    public static function langeProduktnamen(): array {
        return [
            [2027, '340', 1, 'Ausführung von Vermessungsaufträgen sowie Unterhalt und Erneuerung des Vermessungswerks'],
            [2027, '340', 2, 'Bereitstellung und Betrieb städtische Geodateninfrastruktur, Datenausgabe und Dienstleistungen'],
            [2027, '510', 1, 'Kindergarten- und Primarstufe, inkl. integrative sonderpädagogische Massnahmen'],
            [2026, '340', 1, 'Ausführung von Vermessungsaufträgen sowie Unterhalt und Erneuerung des Vermessungswerks'],
        ];
    }

    #[DataProvider('langeProduktnamen')]
    public function testProduktnameStehtVollstaendigDa(int $jahr, string $code, int $nummer, string $name): void {
        $gruppe = null;
        foreach ($this->buch($jahr)['produktegruppen'] as $g) {
            if ($g['code'] === $code) {
                $gruppe = $g;
            }
        }
        self::assertNotNull($gruppe, "Produktegruppe $code im Buch $jahr");

        $gefunden = null;
        foreach ($gruppe['produkte'] as $p) {
            if ((int) $p['nummer'] === $nummer) {
                $gefunden = $p;
            }
        }
        self::assertNotNull($gefunden, "Produkt $nummer der PG $code im Buch $jahr");
        self::assertSame($name, trim((string) $gefunden['name']), "Produktname $code/$nummer im Buch $jahr");
    }

    /**
     * Jede parlamentarische Zielvorgabe trägt eine lesbare Messgrösse: den Text,
     * der im Buch vor ihren Werten steht. Bleibt beim Lesen der Wertzeile ein
     * Zahlenrest übrig («N/A**** 17*****», «22’076 >», «… (in TCHF) 324 -1’275 -»),
     * ist die Zeile falsch zerlegt worden — dann stehen auch ihre Werte in den
     * falschen Spalten, und im Werkzeug steht eine Zielvorgabe ohne Namen.
     *
     * Die Fälle dahinter: Das Buch schreibt einen Sollwert auch als Vergleich
     * («>22000»), als Spanne («1 bis 2») und mit mehr als drei Fussnotenzeichen
     * («17*****»).
     */
    #[DataProvider('jahrgaengeAktuellerBuchsatz')]
    public function testJedeMessgroesseIstText(int $jahr): void {
        $schlecht = [];
        foreach ($this->buch($jahr)['produktegruppen'] as $g) {
            foreach ($g['zielvorgaben'] ?? [] as $z) {
                $mg = trim((string) $z['messgroesse']);
                // Eine Messgrösse ist ein Text. Sie darf mit ihrer Gliederungs-
                // nummer beginnen («2.1 Personalbestand …») und auf eine Einheit
                // enden, die eine Ziffer trägt («in Fr. / m2», «Priorität 1») —
                // aber sie besteht nicht aus Werten, und der letzte Wert der
                // Zeile steht nie noch in ihr.
                $ohneBuchstaben = preg_match('/\p{L}/u', $mg) !== 1;
                // Eine Zahl am Ende kann zum Namen gehören («Anzahl Störungen
                // Priorität 1»); ein Platzhalter oder ein Vergleichszeichen nie —
                // die stehen für eine Spalte, die als Text im Namen hängen blieb.
                $endetAufWert = preg_match('/(?:^|\s)(?:N\/A|n\.a\.|--|—)\**\s*$/u', $mg) === 1
                    || preg_match('/[<>≤≥–—-]\s*$/u', $mg) === 1;
                if ($mg === '' || $ohneBuchstaben || $endetAufWert) {
                    $schlecht[] = $g['code'] . ': «' . $mg . '»';
                }
            }
        }
        self::assertSame([], $schlecht, "Buch $jahr: Messgrössen mit Zahlenresten");
    }

    /**
     * Die Jahrgänge im heutigen Buchsatz. Die Bücher bis 2021 setzen die
     * Zielvorgaben anders (Messgrössen und Werte in einem Fliesstext, teils ohne
     * eigene Wertzeile); was dort zu holen ist, steht in TESTS.md unter P1.
     *
     * @return list<array{int}>
     */
    public static function jahrgaengeAktuellerBuchsatz(): array {
        return [[2022], [2023], [2024], [2025], [2026], [2027]];
    }

    /**
     * Der Investitionsanhang von Teil A wird in jedem Jahrgang gelesen: mehr als
     * hundert Projekte, deren Summe in der Grössenordnung liegt, die die Stadt
     * jährlich investiert, und kein einzelnes Projekt, das grösser ist als diese
     * Summe (so fällt ein aus zwei Zellen zusammengesetzter Betrag auf).
     */
    #[DataProvider('jahrgaenge')]
    public function testInvestitionsanhangWirdGelesen(int $jahr): void {
        $inv = $this->buch($jahr)['investitionen'] ?? [];
        self::assertGreaterThan(100, \count($inv), "Investitionsprojekte im Buch $jahr");

        $summe = 0;
        foreach ($inv as $p) {
            $summe += (int) ($p['bu'] ?? 0);
        }
        self::assertGreaterThan(50_000_000, $summe, "Σ Investitionen $jahr");
        self::assertLessThan(400_000_000, $summe, "Σ Investitionen $jahr");

        $zuGross = [];
        foreach ($inv as $p) {
            if (abs((int) ($p['bu'] ?? 0)) > $summe) {
                $zuGross[] = ($p['projekt'] ?? '?') . ' = ' . ($p['bu'] ?? 0);
            }
        }
        self::assertSame([], $zuGross, "Buch $jahr: Projekt mit mehr als der Summe aller Investitionen");
    }

    /**
     * Personalamt (121) in den beiden ältesten Büchern, Zeile für Zeile im Buch
     * nachgeschlagen. Beide Jahrgänge sind anders gesetzt als die aktuellen: die
     * Beträge stehen in Franken, und das Buch 2017 setzt seine Tabellenköpfe in
     * einer eigenen Kodierung.
     *
     * Buch 2017: «Nettokosten / Globalkredit 2'825'626 2'930'413 3'033'344
     * 3'188'344 3'073'344 3'016'344» (Ist 2015, Soll 2016, Soll 2017, Plan).
     * Buch 2018: «… 2'683'484 3'033'344 2'974'517 3'017'517 2'864'517 2'949'517»
     * (Ist 2016, Soll 2017, Soll 2018, Plan). Der Soll-Wert 2017 steht in beiden
     * Büchern gleich.
     *
     * @return array<string, array{int, list<int>}>
     */
    public static function personalamtJahrgaenge(): array {
        return [
            '2017' => [2017, [2_825_626, 2_930_413, 3_033_344, 3_188_344, 3_073_344, 3_016_344]],
            '2018' => [2018, [2_683_484, 3_033_344, 2_974_517, 3_017_517, 2_864_517, 2_949_517]],
        ];
    }

    /**
     * @param list<int> $erwartet
     */
    #[DataProvider('personalamtJahrgaenge')]
    public function testPersonalamtAusDenAeltestenBuechern(int $jahr, array $erwartet): void {
        $gruppen = [];
        foreach ($this->buch($jahr)['produktegruppen'] as $g) {
            $gruppen[(string) $g['code']] = $g;
        }
        self::assertArrayHasKey('121', $gruppen, "Personalamt (121) im Buch $jahr");

        $felder = ['ist', 'sollVorjahr', 'soll', 'plan1', 'plan2', 'plan3'];
        foreach ($erwartet as $i => $wert) {
            self::assertSame(
                $wert,
                (int) $gruppen['121']['globalkredit'][$felder[$i]],
                "Globalkredit {$felder[$i]} im Buch $jahr"
            );
        }
    }
}
