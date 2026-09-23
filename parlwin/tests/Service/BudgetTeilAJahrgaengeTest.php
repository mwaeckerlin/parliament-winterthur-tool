<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Teil A über ALLE Jahrgänge (F89) — die Kopfzahlen des Budgets: Gesamtergebnis,
 * Steuerertrag, Totale von Aufwand und Ertrag, Investitionen je Projekt.
 *
 * Hintergrund (Prüfung von Hand, 2026-08-28): Die Stadt hat das Format ihres
 * gestuften Erfolgsausweises gewechselt. Bis zum Budget 2024 steht das Budgetjahr
 * in der DRITTEN Wertspalte («Rechnung 2020 | Budget 2021 | Budget 2022» bzw.
 * «RE 2022 | BU 2023 | BU 2024»), ab dem Budget 2025 in der ERSTEN («BU 2025 |
 * BU 2024 | Abw. | RE 2023»). Wer immer die erste Spalte liest, bekommt für die
 * alten Bücher die Rechnung von vor zwei Jahren — plausible Zahlen aus dem
 * falschen Jahr. Ebenso wechselt der Investitionsanhang seinen Titel
 * («Allgemeines Verwaltungsvermögen» → «Verwaltungsvermögen»), seine
 * Tausendertrennung (Leerzeichen → Apostroph) und die Länge der Projektnummern.
 *
 * Die erwarteten Werte stammen aus den Büchern selbst, nicht aus dem Parser.
 * Gruppe «pdf»: braucht vendor/ (smalot).
 */
#[Group('pdf')]
class BudgetTeilAJahrgaengeTest extends TestCase {
    private function pfad(int $jahr, string $teil): string {
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $jahr . '/teil-' . $teil . '.pdf';
    }

    /**
     * Je Jahrgang: Gesamtergebnis und Steuerertrag in Franken, wie im Buch
     * deklariert (Millionenangaben des gestuften Erfolgsausweises).
     *
     * @return list<array{int, int, int}>
     */
    public static function jahrgaenge(): array {
        return [
            // Jahr, Gesamtergebnis, Steuerertrag (Fiskalertrag Kostenart 40)
            [2022,     200_000, 436_600_000],
            [2023,  -2_500_000, 491_300_000],
            [2024,  -5_700_000, 488_800_000],
            [2025,   9_800_000, 504_200_000],
            [2026, 113_800_000, 521_200_000],
        ];
    }

    #[DataProvider('jahrgaenge')]
    public function testKopfzahlenAusDemRichtigenJahr(int $jahr, int $gesamtergebnis, int $steuerertrag): void {
        $s = (new BudgetBuchParser())->struktur($this->pfad($jahr, 'b'), $this->pfad($jahr, 'a'), $jahr);

        self::assertSame($gesamtergebnis, $s['gesamtergebnis'], "Gesamtergebnis $jahr");
        self::assertSame($steuerertrag, $s['steuerertrag'], "Steuerertrag $jahr");
        self::assertSame(125, $s['steuerfuss'], "Steuerfuss $jahr");

        // Kreuzprobe: Die Totale stammen aus derselben Spalte wie das
        // Gesamtergebnis, also muss Ertrag − Aufwand es ergeben. Die Bücher runden
        // jede Zeile auf 0,1 Mio, deshalb eine Toleranz von 0,3 Mio.
        $differenz = $s['totalErtrag'] - $s['totalAufwand'];
        self::assertEqualsWithDelta(
            $gesamtergebnis,
            $differenz,
            300_000,
            sprintf('Ertrag %s − Aufwand %s ergibt nicht das Gesamtergebnis', $s['totalErtrag'], $s['totalAufwand'])
        );

        // Das Vorjahr ist das BUDGET des Vorjahres, nie die Rechnung — es liegt
        // in derselben Grössenordnung wie das Budgetjahr.
        self::assertGreaterThan(1_000_000_000, $s['totalAufwandVorjahr'], "Total Aufwand Vorjahr $jahr");
        self::assertGreaterThan(1_000_000_000, $s['totalErtragVorjahr'], "Total Ertrag Vorjahr $jahr");
    }

    /**
     * Jahrgänge, deren Investitionsanhang vollständig gelesen wird: Die Summe der
     * Projekte ergibt das Total «Stadt Winterthur» des Budgetjahres.
     *
     * @return list<array{int, int}>
     */
    public static function investitionsTotale(): array {
        return [
            [2022, 107_507_532],
            [2023, 121_281_834],
            [2024, 121_503_604],
            [2025, 105_209_929],
            [2026,  86_299_002],
        ];
    }

    #[DataProvider('investitionsTotale')]
    public function testInvestitionenJeJahrgang(int $jahr, int $total): void {
        $inv = $this->investitionen($jahr);

        self::assertGreaterThan(100, count($inv), "Investitionsprojekte $jahr");
        $summe = 0;
        foreach ($inv as $p) {
            $summe += (int) ($p['bu'] ?? 0);
        }
        self::assertEqualsWithDelta(
            $total,
            $summe,
            $total * 0.02,
            sprintf('Σ Projekte %s weicht vom Total «Stadt Winterthur» %s ab', $summe, $total)
        );
    }

    /**
     * Kein Betrag des Anhangs ist grösser als das, was die ganze Stadt investiert,
     * und die Summe aller Projekte überschreitet dieses Total nicht. Damit fällt
     * ein zusammengesetzter Fantasiebetrag sofort auf — etwa 800'000'360, entstanden
     * aus den beiden Zellen «800 000» und «360 000» derselben Zeile.
     */
    #[DataProvider('investitionsTotale')]
    public function testKeineErfundenenInvestitionsbetraege(int $jahr, int $total): void {
        $inv = $this->investitionen($jahr);

        self::assertGreaterThan(100, count($inv), "Investitionsprojekte $jahr");
        $summe = 0;
        foreach ($inv as $p) {
            $betrag = (int) ($p['bu'] ?? 0);
            self::assertLessThanOrEqual(
                $total,
                abs($betrag),
                sprintf('Projekt «%s» trägt %s — mehr als die ganze Stadt investiert', $p['projekt'] ?? '?', $betrag)
            );
            $summe += $betrag;
        }
        self::assertLessThanOrEqual($total, $summe, "Σ Projekte $jahr über dem Total der Stadt");
        self::assertGreaterThan(0, $summe, "Σ Projekte $jahr");
    }

    /**
     * Bis 2024 trennt die Stadt die Tausender mit einem Leerzeichen, und eine Zelle
     * trägt mehrere Beträge hintereinander («100 000 751 000»). Wer das nicht
     * zerlegen kann, verliert die halbe Tabelle: 2024 fielen 244 von 485
     * Projektzeilen weg, die Summe blieb bei 48 statt 121 Millionen.
     *
     * Zerlegt wird an den Beträgen, die auf eine volle Tausendergruppe enden —
     * das ist in dieser Tabelle die Regel. Der Test hält ein Projekt fest, dessen
     * Zelle zwei Beträge trägt, und die Vollständigkeit der ganzen Tabelle.
     */
    public function testMehrereBetraegeInEinerZelleWerdenZerlegt(): void {
        $inv = $this->investitionen(2024);

        $halle = null;
        foreach ($inv as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '13173 ')) {
                $halle = $p;
            }
        }
        self::assertNotNull($halle, 'Projekt 13173 (Halle 710: Dachsanierung) fehlt');
        self::assertSame(100000, (int) $halle['bu'], 'Budget 2024 des Projekts 13173');
        self::assertSame(751000, (int) $halle['fap1'], 'Plan 2025 des Projekts 13173');

        self::assertGreaterThan(400, \count($inv), 'Projektzeilen 2024 (die Tabelle führt 485)');
    }

    /**
     * Jedes Investitionsprojekt trägt neben dem Betrag des Budgetjahres auch, was
     * bereits investiert wurde und was es insgesamt kostet. Beides steht im Buch:
     * die Vorjahresspalte im Anhang «Investitionsplanung», der Gesamtkredit im
     * Anhang «Kontrolle der Investitionskredite». Bis zum 29.08.2026 zeigte die
     * Karte für JEDES Projekt 0 in beiden Feldern.
     *
     * Beispiel Buch 2025, Projekt 5001240 «QA Güterschuppen Töss»:
     *   Investitionsplanung  430'000 | 348'000 | 79'000 | 0 | 0   (2024 … 2028)
     *   Kreditkontrolle      348'000   698'000                    (Budget, Gesamtkredit)
     */
    public function testProjektTraegtBereitsGetaetigtUndGesamtkosten(): void {
        $inv = $this->investitionen(2025);
        $projekt = null;
        foreach ($inv as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '5001240 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 5001240 fehlt');
        self::assertSame(348000, (int) $projekt['bu'], 'Budget 2025');
        self::assertSame(79000, (int) $projekt['fap1'], 'Plan 2026');
        self::assertSame(430000, (int) $projekt['bereitsGetaetigt'], 'Investition 2024');
        self::assertSame(698000, (int) $projekt['gesamtkosten'], 'Gesamtkredit');

        // Und über die ganze Tabelle: die Felder sind nicht durchgehend leer.
        $mitGesamt = 0;
        $mitVorjahr = 0;
        foreach ($inv as $p) {
            $mitGesamt += (int) ($p['gesamtkosten'] ?? 0) !== 0 ? 1 : 0;
            $mitVorjahr += (int) ($p['bereitsGetaetigt'] ?? 0) !== 0 ? 1 : 0;
        }
        self::assertGreaterThan(100, $mitGesamt, 'Projekte mit Gesamtkredit');
        self::assertGreaterThan(100, $mitVorjahr, 'Projekte mit Investition im Vorjahr');
    }

    /**
     * Buch 2026, Projekt 5023660 «IR Plan: Kantons- und Bundesbeiträge»: Die Zeile
     * trägt nur im letzten Planjahr einen Betrag, und die letzten beiden Zellen
     * stehen im PDF als ein Textstück («0 -1'500'000»). Wer die Werte von links
     * ab der Position dieses Stücks zählt, schiebt den Betrag über die letzte
     * Spalte hinaus — das Projekt stand mit lauter Nullen da.
     */
    public function testWerteAmZeilenendeFallenNichtAusDerTabelle(): void {
        $inv = $this->investitionen(2026);
        $projekt = null;
        foreach ($inv as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '5023660 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 5023660 fehlt');
        self::assertSame(0, (int) $projekt['bu'], 'Budget 2026');
        self::assertSame(0, (int) $projekt['fap1'], 'Plan 2027');
        self::assertSame(0, (int) $projekt['fap2'], 'Plan 2028');
        self::assertSame(-1500000, (int) $projekt['fap3'], 'Plan 2029');
    }

    /**
     * Jedes Projekt gehört zu einer Produktegruppe; ihre Zeile im Anhang endet auf
     * «(PG)». Im Buch 2025 steht dort «( PG )» — mit Leerzeichen in den Klammern,
     * weil das PDF die Zeichen einzeln setzt. Wer wörtlich auf «(PG)» prüft, findet
     * keine einzige und lässt das Feld bei JEDEM Projekt leer; die Karte zeigt dann
     * «Projekt» statt der Produktegruppe.
     */
    public function testJedesProjektKenntSeineProduktegruppe(): void {
        foreach ([2024, 2025, 2026] as $jahr) {
            $inv = $this->investitionen($jahr);
            $mitGruppe = 0;
            foreach ($inv as $p) {
                if (trim((string) ($p['cluster'] ?? '')) !== '') {
                    $mitGruppe++;
                }
            }
            self::assertGreaterThan(
                \count($inv) / 2,
                $mitGruppe,
                "Jahr $jahr: nur $mitGruppe von " . \count($inv) . ' Projekten kennen ihre Produktegruppe'
            );
        }
    }

    /**
     * Das PDF zerreisst Beträge mitten in der Zahl — im Buch 2025 steht eine
     * Zeile als «960’00 0 240’00 0 0 0 0» statt «960'000 240'000 0 0 0». Wer die
     * Stücke einzeln liest, bekommt 240 statt 240'000, also den tausendsten Teil;
     * betroffen waren 24 Zeilen des Jahrgangs.
     *
     * Geprüft wird die Wirkung über die ganze Tabelle statt an einem Projekt: Die
     * Investitionsplanung führt Tausenderbeträge, ein Betrag zwischen 1 und 999
     * ist deshalb verdächtig. Fünf Zeilen tragen einen solchen Wert trotzdem
     * zurecht oder unvermeidbar; sie stehen unten mit ihrem Grund. Kommt eine
     * sechste dazu, ist das ein neuer Lesefehler. Die Zerlegung selbst prüft
     * `BudgetBuchParserTocTest` mit Normal- und Fehlerfall.
     */
    #[DataProvider('jahrgaengeMitKrediten')]
    public function testZerrisseneZahlenWerdenZusammengesetzt(int $jahr): void {
        // Belegte Ausnahmen, je Jahr die Projektnummern:
        //  13371, 13317: Das Buch führt für die beiden Zoo-Projekte tatsächlich
        //    «-1»; die Kreditkontrolle nennt für 13371 im Buch 2022 denselben Wert.
        //  5012160, 5012620, 5019550: In diesen drei Zeilen steht im Buch 2025 nur
        //    eine einzelne kleine Zahl in der ersten Wertspalte, und die
        //    Kreditkontrolle führt die Projekte nicht — die Zahl ist damit weder zu
        //    belegen noch zu berichtigen.
        //  5019960: Das Buch 2027 führt für «Gotzenwilerstrasse, HK Obeseen, Umbau
        //    BehiG» im Budgetjahr 500 Franken; die Nachbarzeilen derselben Tabelle
        //    tragen 1'000 und 3'000, die Zahl steht also so im Dokument.
        $bekannt = [
            2022 => ['13371'],
            2023 => ['13317', '13371'],
            2024 => ['13317'],
            2025 => ['5012160', '5012620', '5019550'],
            2026 => [],
            2027 => ['5019960'],
        ];
        $klein = [];
        foreach ($this->investitionen($jahr) as $p) {
            $nr = explode(' ', (string) $p['projekt'], 2)[0];
            if (\in_array($nr, $bekannt[$jahr] ?? [], true)) {
                continue;
            }
            foreach (['bereitsGetaetigt', 'bu', 'fap1', 'fap2', 'fap3'] as $feld) {
                $wert = abs((int) ($p[$feld] ?? 0));
                if ($wert > 0 && $wert < 1000) {
                    $klein[] = $p['projekt'] . " [$feld] = " . $p[$feld];
                }
            }
        }
        self::assertSame(
            [],
            $klein,
            "Jahr $jahr: neuer Betrag unter tausend Franken — eine zerrissene Zahl wurde "
            . 'als ihr tausendster Teil gelesen: ' . implode(' | ', \array_slice($klein, 0, 5))
        );
    }

    /**
     * Ein geparstes Budgetbuch belegt mehrere hundert Megabyte. Zwei davon
     * zugleich im Speicher sprengten die Grenze von PHP: Der Import eines
     * Jahrgangs endete mit «Allowed memory size exhausted», und ein laufender Sync
     * starb still, worauf sein Fortschritt als «läuft» stehen blieb. Gehalten wird
     * deshalb immer nur das zuletzt gelesene Buch.
     */
    public function testEsLiegtImmerNurEinBuchImSpeicher(): void {
        $parser = new BudgetBuchParser();
        $lesen = new \ReflectionMethod(BudgetBuchParser::class, 'fragmentZeilen');
        $gehalten = new \ReflectionProperty(BudgetBuchParser::class, 'gehaltenerPfad');
        $dokument = new \ReflectionProperty(BudgetBuchParser::class, 'gehaltenesDokument');

        $erstes = $this->pfad(2025, 'a');
        $zweites = $this->pfad(2026, 'a');
        $lesen->invoke($parser, $erstes);
        self::assertSame($erstes, $gehalten->getValue($parser), 'das gelesene Buch wird nicht gehalten');

        $lesen->invoke($parser, $zweites);
        self::assertSame(
            $zweites,
            $gehalten->getValue($parser),
            'nach dem zweiten Buch wird nicht dieses gehalten'
        );
        self::assertNotNull($dokument->getValue($parser), 'das aktuelle Buch fehlt');

        // Und das erste ist weg: Ein erneutes Lesen parst es neu, statt es aus dem
        // Speicher zu nehmen.
        $vorher = memory_get_usage();
        $lesen->invoke($parser, $erstes);
        self::assertSame($erstes, $gehalten->getValue($parser));
        self::assertLessThan(
            400 * 1024 * 1024,
            memory_get_usage() - $vorher,
            'der Speicher wächst mit jedem gelesenen Buch'
        );
    }

    /**
     * Je Jahrgang die Summe des Budgetjahres, die das Buch für jedes Departement
     * der Investitionsplanung nennt. Sie steht in dessen Kopfzeile («300000
     * Departement Bau und Mobilität …»), und die acht Summen ergeben zusammen das
     * Total der Stadt.
     *
     * @return list<array{int, array<string, int>}>
     */
    public static function departementssummen(): array {
        return [
            [2022, [
                'Kulturelles und Dienste' => 6_444_400,
                'Finanzen' => 8_092_150,
                'Bau' => 18_546_033,
                'Sicherheit und Umwelt' => 21_824_000,
                'Schule und Sport' => 41_921_280,
                'Soziales' => 532_000,
            ]],
            [2023, [
                'Kulturelles und Dienste' => 11_195_000,
                'Finanzen' => 11_672_663,
                'Bau' => 18_504_423,
                'Sicherheit und Umwelt' => 7_933_100,
                'Schule und Sport' => 62_481_000,
                'Soziales' => 278_068,
                'Technische Betriebe' => 7_767_580,
                'Behörden und Stadtkanzlei' => 1_450_000,
            ]],
            [2024, [
                'Präsidiales' => 17_097_750,
                'Finanzen' => 17_833_263,
                'Bau und Mobilität' => 20_630_158,
                'Sicherheit und Umwelt' => 3_507_600,
                'Schule und Sport' => 51_415_750,
                'Soziales' => 1_683_000,
            ]],
            [2025, [
                'Präsidiales' => 16_938_000,
                'Finanzen' => 16_189_601,
                'Bau und Mobilität' => 15_552_786,
                'Sicherheit und Umwelt' => 2_005_620,
                'Schule und Sport' => 40_138_651,
                'Soziales' => 2_587_000,
            ]],
            [2026, [
                'Präsidiales' => 6_878_000,
                'Finanzen' => 8_012_767,
                'Bau und Mobilität' => 20_881_294,
                'Sicherheit und Umwelt' => 2_612_800,
                'Schule und Sport' => 32_585_376,
                'Soziales' => 808_000,
            ]],
        ];
    }

    /**
     * Jedes Departement trifft die Summe, die das Buch für es nennt. Diese Probe
     * findet, was die Summe der Stadt verdeckt: Solange an einer Stelle Beträge
     * fehlen und an einer anderen zu viele stehen, bleibt das Total unauffällig.
     * So blieb «11677 IR-Plan: RK Verbesserung der Veloinfrastruktur» unentdeckt —
     * die Planungszeile zum Rahmenkredit darüber, die den Kredit beim Namen nennt
     * statt bei der Nummer und deshalb als eigenes Projekt zählte: 430'000 Franken
     * zu viel im Departement Bau und Mobilität des Buchs 2024.
     *
     * Die Technischen Betriebe fehlen in der Liste: Ihre Summe weicht in jedem
     * Jahrgang um einen Franken ab, weil das Buch dort eine Zeile rundet.
     *
     * @param array<string, int> $soll
     */
    #[DataProvider('departementssummen')]
    public function testDepartementeTreffenIhreSummeImBuch(int $jahr, array $soll): void {
        $ist = [];
        foreach ($this->investitionen($jahr) as $p) {
            $d = (string) $p['departement'];
            $ist[$d] = ($ist[$d] ?? 0) + (int) $p['bu'];
        }
        foreach ($soll as $departement => $betrag) {
            self::assertSame(
                $betrag,
                $ist[$departement] ?? 0,
                "Jahr $jahr, Departement $departement: die Summe der Projekte weicht vom Buch ab"
            );
        }
    }

    /**
     * Der Name eines Departements ist ein Name, keine Zahlenreihe. Wo das PDF die
     * Zeile nicht an den Spalten trennt, stand er mit allen fünf Beträgen auf jeder
     * Karte: «Behörden und Stadtkanzlei 2 670 000 1 287 000 900 000 900 000
     * 748 000» in den Büchern 2022 und 2024.
     */
    #[DataProvider('jahrgaengeMitKrediten')]
    public function testDepartementsnamenTragenKeineBetraege(int $jahr): void {
        $mitZahlen = [];
        foreach ($this->investitionen($jahr) as $p) {
            $d = (string) $p['departement'];
            if (preg_match('/\d/u', $d) === 1) {
                $mitZahlen[$d] = true;
            }
        }
        self::assertSame(
            [],
            array_keys($mitZahlen),
            "Jahr $jahr: ein Departementsname trägt Zahlen"
        );
    }

    /**
     * Die «Kontrolle der Investitionskredite» führt denselben Betrag des
     * Budgetjahres wie die Investitionsplanung, in einer Tabelle mit nur zwei
     * Wertspalten. Beide müssen übereinstimmen — über die fünf Jahrgänge sind das
     * mehr als 1300 Projekte.
     *
     * Der Vergleich deckte vier Lesefehler der Kreditkontrolle auf: Zahlen aus dem
     * Namen («Rahmenkredit (11334) AP1 + AP2» ergab Betrag 1 und Kredit 2, «…
     * Grüze AP2» den Betrag 2) und eine im PDF zerrissene Zahl («1» + «15'000»
     * statt 115'000, Projekt 19755). Seither liest sie nur noch die Fragmente der
     * beiden Wertspalten.
     */
    #[DataProvider('jahrgaengeMitKrediten')]
    public function testKreditkontrolleUndPlanungStimmenUeberein(int $jahr): void {
        // Zwei Zeilen weichen im Buch selbst ab: 13371 (2022) führt seinen Wert in
        // der Planung erst im Planjahr, 5000300 (2025) nennt in beiden Tabellen
        // verschiedene Beträge.
        $bekannt = [2022 => ['13371'], 2025 => ['5000300']];
        $pfad = $this->pfad($jahr, 'a');
        $parser = new BudgetBuchParser();
        $zeilen = new \ReflectionMethod(BudgetBuchParser::class, 'investitionsZeilen');
        $kreditLesen = new \ReflectionMethod(BudgetBuchParser::class, 'parseInvestitionskredite');

        $planung = $zeilen->invoke($parser, $pfad, $jahr, []);
        $nummern = [];
        foreach ($planung['inv'] as $p) {
            $nummern[$p['nr']] = true;
        }
        foreach ($planung['offen'] as $nr) {
            $nummern[$nr] = true;
        }
        $kredite = $kreditLesen->invoke($parser, $pfad, $nummern);

        $anders = [];
        $geprueft = 0;
        foreach ($planung['inv'] as $p) {
            $k = $kredite[$p['nr']] ?? null;
            if ($k === null || \in_array($p['nr'], $bekannt[$jahr] ?? [], true)) {
                continue;
            }
            ++$geprueft;
            if ((int) $k['betrag'] !== (int) $p['bu']) {
                $anders[] = $p['nr'] . ': Planung ' . $p['bu'] . ' gegen Kontrolle ' . $k['betrag'];
            }
        }
        self::assertGreaterThan(
            200,
            $geprueft,
            "Jahr $jahr: zu wenige Projekte mit Eintrag in der Kreditkontrolle — sie wurde nicht gelesen"
        );
        self::assertSame(
            [],
            $anders,
            "Jahr $jahr: Planung und Kreditkontrolle widersprechen sich: " . implode(' | ', \array_slice($anders, 0, 5))
        );
    }

    /**
     * Buch 2025, Projekt 5018220 «CAS, Eichliwaldstrasse Neuerschliessung»: Seine
     * Zeile trägt die ersten beiden Zellen als ein Textstück («0 2'500'000»), das
     * bei der Spalte 2024 beginnt. Wird die Spalte aus der Mitte des ganzen
     * Stücks bestimmt, rutscht sie eine nach rechts — der Betrag landet im
     * Planjahr, das Budgetjahr bleibt leer. In der Produktegruppe Immobilien
     * fehlten dadurch 3,5 Millionen (dieses Projekt und 5018240).
     */
    public function testEinFragmentMitZweiZahlenBeginntInSeinerSpalte(): void {
        $inv = $this->investitionen(2025);
        $nach = [];
        foreach ($inv as $p) {
            $nach[explode(' ', (string) $p['projekt'], 2)[0]] = $p;
        }

        self::assertArrayHasKey('5018220', $nach, 'Projekt 5018220 fehlt');
        self::assertSame(2500000, (int) $nach['5018220']['bu'], 'Budget 2025 des Projekts 5018220');
        self::assertSame(0, (int) $nach['5018220']['fap1'], 'Plan 2026 des Projekts 5018220');

        self::assertArrayHasKey('5018240', $nach, 'Projekt 5018240 fehlt');
        self::assertSame(1000000, (int) $nach['5018240']['bu'], 'Budget 2025 des Projekts 5018240');
    }

    /**
     * Ein Sammelposten («SP: …») und ein Rahmenkredit («RK: …») nennen den ganzen
     * Betrag; die Zeilen darunter führen einzelne Vorhaben daraus auf. Beides zu
     * zählen verdoppelt den Kredit. Das Buch 2025 zeigt das im Departement Bau und
     * Mobilität: unter «5000260 SP: Sanierung von überkommunalen Verkehrswegen»
     * stehen 5020010 und 5020040, unter 5000270 die Zeilen 5004920 und 5020030,
     * unter 5000280 die beiden Lichtsignalanlagen — zusammen 698'000 Franken, die
     * das Departementstotal überschritten.
     *
     * Die Zugehörigkeit steht in der Stellung: Alles zwischen einer Sammelzeile und
     * der nächsten Produktegruppe, dem nächsten Bereich oder der nächsten Sammelzeile
     * gehört zu ihr. Am Namen ist sie nicht zu erkennen (nur ein Teil trägt «Tr.»
     * oder «Tranche»), an der Einrückung auch nicht (alle Zeilen beginnen bei
     * derselben x-Position).
     *
     * Der Test prüft jedes Departement gegen seine Zeile im Anhang.
     */
    public function testDepartementstotaleStimmenMitDemBuch(): void {
        $buch = [
            'Präsidiales' => 16_938_000,
            'Finanzen' => 16_189_601,
            'Bau und Mobilität' => 15_552_786,
            'Sicherheit und Umwelt' => 2_005_620,
            'Schule und Sport' => 40_138_651,
            'Soziales' => 2_587_000,
            'Technische Betriebe' => 9_982_271,
            'Behörden und Stadtkanzlei' => 1_816_000,
        ];

        $gelesen = [];
        foreach ($this->investitionen(2025) as $p) {
            $d = trim((string) ($p['departement'] ?? ''));
            $gelesen[$d] = ($gelesen[$d] ?? 0) + (int) ($p['bu'] ?? 0);
        }

        foreach ($buch as $departement => $total) {
            self::assertSame(
                $total,
                $gelesen[$departement] ?? 0,
                "Investitionen 2025, Departement $departement"
            );
        }
        self::assertSame(array_sum($buch), array_sum($gelesen), 'Σ Investitionen 2025');
    }

    /**
     * Buch 2022, Projekt 13031 «Schloss Hegi: Sanierung»: Seine Zelle trägt vier
     * Beträge in einem Stück, «550 000 1 390 000 1 130 000 350 000». Das sind zehn
     * Ziffergruppen bei fünf Spalten — glatt teilbar, und wer sie deshalb in fünf
     * Paare schneidet, liest 550'000, 1'390, 1, 130'000, 350'000. Im Budgetjahr
     * standen 1'390 statt 1'390'000; der Produktegruppe «Städtische Kultur-
     * einrichtungen» fehlten damit 1'388'610 Franken, dem ganzen Departement genau
     * derselbe Betrag.
     *
     * Die gleichmässige Aufteilung gilt nur, wenn sie den Regeln der Tabelle
     * genügt: kein Betrag beginnt mit «000», und unter tausend Franken kommt nur
     * die Null vor. Sonst entscheidet die Aufteilung, die als einzige passt.
     */
    public function testGleicheGruppenzahlWirdGegenDieRegelnGeprueft(): void {
        $inv = $this->investitionen(2022);
        $projekt = null;
        foreach ($inv as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '13031 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 13031 fehlt');
        self::assertSame(550000, (int) $projekt['bereitsGetaetigt'], 'Ist 2020 des Projekts 13031');
        self::assertSame(1390000, (int) $projekt['bu'], 'Budget 2022 des Projekts 13031');
        self::assertSame(1130000, (int) $projekt['fap1'], 'Plan 2023 des Projekts 13031');
        self::assertSame(350000, (int) $projekt['fap2'], 'Plan 2024 des Projekts 13031');
    }

    /**
     * Buch 2022, Projekt 19828 der Produktegruppe Steuerbezug: «261 500 530 050
     * 670 050 517 900». Hier trennt dasselbe Leerzeichen Tausender und Spalten,
     * und «050» liesse sich auch als Beginn eines Betrags lesen — dann gäbe es
     * mehrere Lesarten, und die Zeile fiele ganz weg. Ein Betrag beginnt aber nie
     * mit einer führenden Null; damit bleibt genau eine Lesart übrig.
     */
    public function testBetragBeginntNieMitFuehrenderNull(): void {
        $inv = $this->investitionen(2022);
        $projekt = null;
        foreach ($inv as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '19828 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 19828 fehlt');
        self::assertSame(261500, (int) $projekt['bereitsGetaetigt'], 'Ist 2020 des Projekts 19828');
        self::assertSame(530050, (int) $projekt['bu'], 'Budget 2022 des Projekts 19828');
        self::assertSame(670050, (int) $projekt['fap1'], 'Plan 2023 des Projekts 19828');
        self::assertSame(517900, (int) $projekt['fap2'], 'Plan 2024 des Projekts 19828');
    }

    /**
     * Buch 2026, Projekt 5019310 «Wülflingerstr., Neftenbacherstrasse — Stadt-
     * grenze»: Das PDF setzt die Zeile zweimal, einmal mit gekürztem Namen
     * («… - Strassensanie...») und einmal vollständig, beide mit denselben
     * Beträgen. Ein Zeichenvergleich sieht darin zwei Zeilen, und das Projekt
     * zählte doppelt — dem Departement Bau und Mobilität fehlten dadurch 50'000
     * Franken, weil sein Betrag negativ ist.
     */
    public function testEineGekuerzteWiederholungZaehltNichtNochmals(): void {
        $treffer = [];
        foreach ($this->investitionen(2026) as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '5019310 ')) {
                $treffer[] = $p;
            }
        }
        self::assertCount(1, $treffer, 'Projekt 5019310 steht mehrfach in der Liste');
        self::assertSame(-50000, (int) $treffer[0]['bu'], 'Budget 2026 des Projekts 5019310');
    }

    /**
     * Buch 2023, Projekt 13411 «Erhalt und Optimierung Logistik Werkhof»: Das PDF
     * zerreisst die Projektnummer und setzt sie als «134 11». Gelesen wurde daraus
     * die Nummer 134 mit dem Namen «11 Erhalt und Optimierung …» — eine dreistellige
     * Nummer gilt als Bereichs-Subtotal, und die Zeile fiel weg. Der Produktegruppe
     * Stadtgrün fehlten damit 50'000 Franken.
     */
    public function testZerrisseneProjektnummerWirdZusammengesetzt(): void {
        $projekt = null;
        foreach ($this->investitionen(2023) as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '13411 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 13411 fehlt');
        self::assertSame(50000, (int) $projekt['bu'], 'Budget 2023 des Projekts 13411');
        self::assertSame(250000, (int) $projekt['fap1'], 'Plan 2024 des Projekts 13411');
    }

    /**
     * Buch 2024, Projekt 19973 «SP: Ersatzbeschaffung Fahrzeuge der Stadtpolizei»:
     * Die Zelle beginnt in der ersten Spalte und trägt die ganze Zeile,
     * «1 561 600 1 379 600 128 900 444 300 522 000». Zwölf Ziffergruppen lassen
     * sich regelkonform auf zwei Arten lesen — als fünf Beträge oder als vier,
     * wenn «128 900 444» zu einem verschmilzt. Die Zeile fiel deshalb ganz weg,
     * und dem Departement Sicherheit und Umwelt fehlten 1'379'600 Franken.
     *
     * Eine Zelle, die in der ersten Spalte beginnt, hat für jede Spalte einen
     * Wert; damit bleibt genau eine Lesart.
     */
    public function testZelleAbDerErstenSpalteFuelltAlleSpalten(): void {
        $projekt = null;
        foreach ($this->investitionen(2024) as $p) {
            if (str_starts_with((string) ($p['projekt'] ?? ''), '19973 ')) {
                $projekt = $p;
            }
        }
        self::assertNotNull($projekt, 'Projekt 19973 fehlt');
        self::assertSame(1561600, (int) $projekt['bereitsGetaetigt'], 'Ist 2022 des Projekts 19973');
        self::assertSame(1379600, (int) $projekt['bu'], 'Budget 2024 des Projekts 19973');
        self::assertSame(128900, (int) $projekt['fap1'], 'Plan 2025 des Projekts 19973');
        self::assertSame(444300, (int) $projekt['fap2'], 'Plan 2026 des Projekts 19973');
        self::assertSame(522000, (int) $projekt['fap3'], 'Plan 2027 des Projekts 19973');
    }

    /**
     * Die Karten der Investitionsrechnung zeigten in jedem Jahrgang fast überall
     * eine Null. Was im Buch steht, steht auch auf der Karte: das bereits
     * Investierte in der Jahresspalte vor dem Budgetjahr, die Gesamtkosten im
     * Anhang «Kontrolle der Investitionskredite».
     *
     * Nicht jedes Projekt trägt jeden Wert — ein Vorhaben, das erst in einem
     * Planjahr beginnt, hat im Budgetjahr nichts, und Gesamtkosten führt nur, wer
     * einen bewilligten Kredit hat. Ein Projekt aber, bei dem ALLE sechs Zahlen
     * fehlen, ist eine leere Karte: davon gibt es im Buch 2026 dreizehn von 567.
     *
     * @return list<array{int}>
     */
    public static function jahrgaengeMitKrediten(): array {
        return [[2022], [2023], [2024], [2025], [2026], [2027]];
    }

    #[DataProvider('jahrgaengeMitKrediten')]
    public function testJederJahrgangTraegtGesamtkostenUndVorjahr(int $jahr): void {
        $inv = $this->investitionen($jahr);
        self::assertGreaterThan(100, \count($inv), "Investitionsprojekte $jahr");

        $mitGesamt = 0;
        $mitVorjahr = 0;
        $leer = 0;
        foreach ($inv as $p) {
            $mitGesamt += (int) ($p['gesamtkosten'] ?? 0) !== 0 ? 1 : 0;
            $mitVorjahr += (int) ($p['bereitsGetaetigt'] ?? 0) !== 0 ? 1 : 0;
            $summe = 0;
            foreach (['bu', 'fap1', 'fap2', 'fap3', 'gesamtkosten', 'bereitsGetaetigt'] as $feld) {
                $summe += abs((int) ($p[$feld] ?? 0));
            }
            $leer += $summe === 0 ? 1 : 0;
        }
        self::assertGreaterThan(
            \count($inv) / 3,
            $mitGesamt,
            sprintf('Jahr %d: nur %d von %d Projekten tragen Gesamtkosten', $jahr, $mitGesamt, \count($inv))
        );
        self::assertGreaterThan(
            \count($inv) / 3,
            $mitVorjahr,
            sprintf('Jahr %d: nur %d von %d Projekten tragen das bereits Investierte', $jahr, $mitVorjahr, \count($inv))
        );
        self::assertLessThan(
            \count($inv) / 10,
            $leer,
            sprintf('Jahr %d: %d von %d Karten tragen überhaupt keine Zahl', $jahr, $leer, \count($inv))
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function investitionen(int $jahr): array {
        $s = (new BudgetBuchParser())->struktur($this->pfad($jahr, 'b'), $this->pfad($jahr, 'a'), $jahr);
        return $s['investitionen'] ?? [];
    }
}
