<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Parst die echten, committeten Budgetbücher (reference/budget) mit
 * smalot/pdfparser und prüft die extrahierten Werte gegen die im Buch gemessenen
 * Zahlen. Gruppe «pdf»: braucht vendor/ (smalot), läuft darum nicht im schnellen
 * Standardlauf, sondern über `npm run test:pdf` und im Image.
 */
#[Group('pdf')]
class BudgetBuchParserTest extends TestCase {
    private function fixture(string $rel): string {
        // Testfixtures liegen unter parlwin/tests/Fixtures/budget (via .dockerignore
        // NICHT im Image). Die echten Bücher werden zur Laufzeit live von der
        // Parlamentsseite geladen — hier nur zum Vergleich des Parser-Resultats.
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $rel;
    }

    public function testParstProduktegruppenAusTeilB2026(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), $this->fixture('2026/teil-a.pdf'), 2026);

        $gruppen = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $gruppen[$g['code']] = $g;
        }

        $this->assertNotEmpty($gruppen, 'Keine Produktegruppen extrahiert');
        $this->assertArrayHasKey('121', $gruppen, 'Personalamt (121) nicht gefunden');

        $pa = $gruppen['121'];
        $this->assertSame('Personalamt', $pa['name']);
        $this->assertSame('Präsidiales', $pa['departement']);
        $this->assertSame(4866124, $pa['globalkredit']['soll'], 'Globalkredit Soll 2026');
        $this->assertSame(4395510, $pa['globalkredit']['sollVorjahr'], 'Globalkredit Soll 2025');
        $this->assertSame(7428985, $pa['aufwand']['soll'], 'Total effektive Kosten Soll 2026');
        $this->assertSame(2562862, $pa['ertrag']['soll'], 'Total effektive Erlöse Soll 2026');
        $this->assertSame(19.5, $pa['stellen']['soll'], 'Stelleneinheiten Soll 2026');

        // Mehrzeilige Kapitelüberschrift: der vollständige Name kommt aus dem
        // Inhaltsverzeichnis, nicht aus der (umgebrochenen) Body-Überschrift.
        $this->assertArrayHasKey('158', $gruppen);
        $this->assertSame(
            'Städtische Museen, Kulturinstitutionen und Bauten',
            $gruppen['158']['name'],
            'Vollständiger Gruppenname aus dem Inhaltsverzeichnis'
        );

        // Auftragstext (Mission) als Info aus dem Kapitel.
        $this->assertStringStartsWith(
            'Das Personalamt der Stadt Winterthur bearbeitet die personalrechtlichen Fragen',
            $pa['auftrag'],
            'Auftragstext der Produktegruppe'
        );
        $this->assertStringContainsString('Beratungsleistungen', $pa['auftrag']);
        $this->assertStringNotContainsString('Rechtsgrundlagen', $pa['auftrag'], 'Auftrag endet vor den Rechtsgrundlagen');

        // Produkte als Information (F80): die einzelnen Produkte der Produktegruppe
        // mit Nummer, Name und Nettokosten (Soll). Der Anhang am Buchende listet die
        // Produkte ein zweites Mal — sie dürfen NICHT gedoppelt sein (F110).
        $this->assertCount(4, $pa['produkte'], 'Produktegruppe 121 hat genau 4 Produkte (kein Anhang-Duplikat)');
        $nummern = array_map(static fn ($p) => $p['nummer'], $pa['produkte']);
        $this->assertSame($nummern, array_values(array_unique($nummern)), 'jede Produktnummer genau einmal');
        $p1 = $pa['produkte'][0];
        $this->assertSame(1, $p1['nummer']);
        $this->assertSame('Personalpolitik / Personalrecht', $p1['name']);
        $this->assertSame(1135916, $p1['nettokosten']['soll'], 'Nettokosten Soll Produkt 1');
        $this->assertSame('Zentrales Personalmanagement', $pa['produkte'][1]['name']);
        $this->assertSame(1318580, $pa['produkte'][1]['nettokosten']['soll'], 'Nettokosten Soll Produkt 2');
        // Kostentabelle je Produkt (Budgetzahlen, F110): Kosten, Erlös, Nettokosten,
        // Kostendeckungsgrad über Ist / Soll Vorjahr / Soll aktuell (drei Werte je Zeile).
        $kt1 = [];
        foreach ($p1['kostentabelle'] as $z) {
            $this->assertCount(3, $z['werte'], 'jede Kostenzeile hat Ist/SollVorjahr/Soll');
            $kt1[$z['label']] = $z['werte'];
        }
        $this->assertArrayHasKey('Kosten', $kt1, 'Kostentabelle des Produkts 1');
        $this->assertArrayHasKey('Nettokosten', $kt1);
        $this->assertArrayHasKey('Kostendeckungsgrad in %', $kt1);
        $this->assertSame($p1['nettokosten']['soll'], $kt1['Nettokosten'][2], 'die Nettokosten-Soll-Zahl der Karte stammt aus der Tabelle');
        // Leistungen je Produkt (F110): die Aufzählung aus dem Buch — sauber, ohne
        // Bullet-Glyph und mit über den Zeilenumbruch zusammengeführten Fortsetzungen.
        $this->assertNotEmpty($p1['leistungen'], 'Leistungen des Produkts 1 nicht extrahiert');
        $this->assertStringContainsString('personalpolitischen Grundsätze', implode(' ', $p1['leistungen']));
        $this->assertStringNotContainsString('Nettokosten', implode(' ', $p1['leistungen']), 'die Nettokosten-Tabelle ist keine Leistung');
        // Der Bullet-Glyph (smalot: U+F0A7) darf in keiner Leistung stehen, und keine
        // Leistung ist leer.
        foreach ($pa['produkte'] as $prod) {
            foreach ($prod['leistungen'] as $l) {
                $this->assertStringNotContainsString("\u{F0A7}", $l, 'kein Bullet-Glyph in der Leistung');
                $this->assertNotSame('', trim($l), 'keine leere Leistung');
            }
        }
        // Die im Buch umgebrochene Leistung «… Koordination der personalrechtlichen
        // Praxis» steht als EIN Eintrag, nicht als «Praxis» allein.
        $this->assertNotContains('Praxis', $p1['leistungen'], 'Fortsetzung nicht als eigener Eintrag');
        $trefferPraxis = array_filter($p1['leistungen'], static fn ($l) => str_contains($l, 'personalrechtlichen Praxis'));
        $this->assertCount(1, $trefferPraxis, 'die umgebrochene Leistung ist zusammengeführt');

        // Erläuterungen/Begründungen (F80): Textblöcke aus dem Buch, sauber
        // abgegrenzt (keine Label-/Tabellen-/Struktur-Reste).
        foreach (['erlaeuterungStellen', 'begruendungAbweichung', 'begruendungFap', 'massnahmen'] as $feld) {
            $this->assertNotSame('', $pa[$feld], "Erläuterungstext $feld ist leer");
            $this->assertStringNotContainsString("\t", $pa[$feld], "$feld enthält eine Tabellen-/Kopfzeile");
            $this->assertDoesNotMatchRegularExpression('/\(\d{3}\)\s*$/u', $pa[$feld], "$feld enthält eine Kapitelüberschrift");
            $this->assertStringNotContainsString('Erläuterungen zum Stellenplan', $pa[$feld], "$feld enthält ein Abschnitts-Label");
        }

        // Parlamentarische Zielvorgaben (F109, WoV): je Messgrösse ein beantragbarer
        // «Soll aktuell»-Wert. Ziel 2 «Kundenorientierung zentrales Personalmanagement»,
        // Messgrösse Zufriedenheit: Ist 2024 = 90, Soll 2026 = 85.
        $this->assertNotEmpty($pa['zielvorgaben'], 'Zielvorgaben der PG 121 nicht extrahiert');
        $kundenorientierung = null;
        foreach ($pa['zielvorgaben'] as $z) {
            if ($z['zielNummer'] === 2 && str_contains($z['messgroesse'], 'zufrieden')) {
                $kundenorientierung = $z;
            }
        }
        $this->assertNotNull($kundenorientierung, 'Zielvorgabe «Kundenorientierung zentrales Personalmanagement» nicht gefunden');
        $this->assertSame('2 Kundenorientierung zentrales Personalmanagement', $kundenorientierung['zielTitel']);
        $this->assertSame('90', $kundenorientierung['werte'][0], 'Ist 2024');
        $this->assertSame('85', $kundenorientierung['soll'], 'Soll 2026 (beantragbar)');

        // Kostenzeilen des Informationsteils (F109): Personalkosten mit Soll 2026.
        $kosten = [];
        foreach ($pa['kostenzeilen'] as $k) {
            $kosten[$k['label']] = $k['soll'];
        }
        $this->assertArrayHasKey('Personalkosten', $kosten, 'Kostenzeile Personalkosten nicht gefunden');
        $this->assertSame(5178974, $kosten['Personalkosten'], 'Personalkosten Soll 2026');
        $this->assertArrayNotHasKey('Total effektive Kosten', $kosten, 'Summenzeile ist keine Kostenzeile');

        // Ziel 1 «Leistungserbringung»: die Messgrösse ist die kurze Kennzahl «Betrag
        // pro Anstellung …», nicht die verschmolzene Beschreibung. Zugleich Invariante:
        // keine Messgrösse ist absurd lang (Verschmelzungs-Bug).
        $ziel1 = null;
        foreach ($pa['zielvorgaben'] as $z) {
            if ($z['zielNummer'] === 1) {
                $ziel1 = $z;
            }
            $this->assertLessThan(120, mb_strlen($z['messgroesse']), "Messgrösse zu lang (verschmolzen): {$z['messgroesse']}");
        }
        $this->assertNotNull($ziel1);
        $this->assertStringStartsWith('Betrag pro Anstellung', $ziel1['messgroesse']);
        $this->assertStringNotContainsString('Gesamtkosten', $ziel1['messgroesse'], 'die Beschreibung wurde nicht ins Label verschmolzen');
    }

    public function testParstSteuerfussAusTeilA2026(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), $this->fixture('2026/teil-a.pdf'), 2026);
        $this->assertSame(125, $struktur['steuerfuss'], 'Steuerfuss 2026');
        $this->assertSame(521200000, $struktur['steuerertrag'], 'Gesamt-Steuerertrag 2026 (521,2 Mio.)');
        $this->assertSame(113800000, $struktur['gesamtergebnis'], 'Gesamtergebnis 2026: Ertragsüberschuss 113,8 Mio.');
        $this->assertSame(1782300000, $struktur['totalAufwand'], 'Total Aufwand 2026 (1744,8 + 37,5 + 0,0 Mio.)');
        $this->assertSame(1896100000, $struktur['totalErtrag'], 'Total Ertrag 2026 (1718,4 + 177,6 + 0,1 Mio.)');
        // Das Total geht auf: Ertrag − Aufwand == Gesamtergebnis.
        $this->assertSame($struktur['gesamtergebnis'], $struktur['totalErtrag'] - $struktur['totalAufwand'], 'Totale ergeben das Gesamtergebnis');
        // Vorjahr (BU 2025) für die Differenz-Anzeige.
        $this->assertSame(1698400000, $struktur['totalAufwandVorjahr'], 'Total Aufwand 2025 (1664,6 + 33,8 + 0,0 Mio.)');
        $this->assertSame(1717000000, $struktur['totalErtragVorjahr'], 'Total Ertrag 2025 (1664,9 + 52,0 + 0,1 Mio.)');
    }

    public function testParstInvestitionenJeProjektAusTeilA2026(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), $this->fixture('2026/teil-a.pdf'), 2026);
        $inv = [];
        foreach ($struktur['investitionen'] as $i) {
            $nr = explode(' ', $i['projekt'], 2)[0];
            $inv[$nr] = $i;
        }
        // Viele Projekte (nicht mehr departemental aggregiert).
        $this->assertGreaterThan(100, \count($struktur['investitionen']), 'Investitionen je Projekt');

        // Projekt mit Jahreszahl IM Namen (Robustheit): die fünf Wertspalten
        // sind die letzten fünf Zahlen, nicht die «2028» im Namen.
        $this->assertArrayHasKey('5001750', $inv);
        $p = $inv['5001750'];
        $this->assertSame('Präsidiales', $p['departement']);
        $this->assertSame('Personalamt', $p['cluster']);
        $this->assertStringContainsString('Zeiterfassungssystem', $p['projekt']);
        $this->assertSame(0, $p['bu'], 'BU 2026');
        $this->assertSame(400000, $p['fap2'], 'Plan 2028');

        // Projekt mit Werten in Budget- und Vorjahresspalte.
        $this->assertArrayHasKey('5001240', $inv);
        $this->assertSame('Stadtentwicklung', $inv['5001240']['cluster']);
        $this->assertSame(349000, $inv['5001240']['bu'], 'QA Güterschuppen BU 2026');

        $this->assertArrayHasKey('5001250', $inv);
        $this->assertSame(715000, $inv['5001250']['bu'], 'QA Gutschick-Mattenbach BU 2026');
        $this->assertSame(3080000, $inv['5001250']['fap1'], 'QA Gutschick-Mattenbach Plan 2027');

        // Departement Finanzen, Produktegruppen Finanzamt und Informatikdienste.
        $this->assertArrayHasKey('5003040', $inv);
        $this->assertSame('Finanzen', $inv['5003040']['departement']);
        $this->assertSame('Finanzamt', $inv['5003040']['cluster']);

        $this->assertArrayHasKey('5002550', $inv);
        $this->assertSame('Informatikdienste', $inv['5002550']['cluster']);
        $this->assertSame(0, $inv['5002550']['bu'], 'Core Switches BU 2026');
        $this->assertSame(720000, $inv['5002550']['fap1'], 'Core Switches Plan 2027');
    }

    /**
     * Der Parser generalisiert über alle committeten Jahrgänge: jeder liefert
     * genügend Produktegruppen, und der Soll-Wert eines Jahres ist der Vorjahres-
     * Soll des Folgejahres (starke, buchübergreifende Invariante — beweist, dass
     * echte Werte gelesen werden, keine Kopien).
     */
    public function testParserGeneralisiertUeberAlleJahrgaenge(): void {
        $parser = new BudgetBuchParser();
        $personalamtSoll = [];
        $personalamtSollVorjahr = [];
        foreach ([2022, 2023, 2024, 2025, 2026, 2027] as $jahr) {
            $teilB = $this->fixture($jahr . '/teil-b.pdf');
            if (!is_file($teilB)) {
                continue;
            }
            $teilA = $this->fixture($jahr . '/teil-a.pdf');
            $struktur = $parser->struktur($teilB, is_file($teilA) ? $teilA : null, $jahr);
            $this->assertGreaterThan(
                20,
                \count($struktur['produktegruppen']),
                "Zu wenige Produktegruppen für $jahr — Parser generalisiert nicht"
            );
            foreach ($struktur['produktegruppen'] as $g) {
                if ($g['code'] === '121') {
                    $personalamtSoll[$jahr] = $g['globalkredit']['soll'];
                    $personalamtSollVorjahr[$jahr] = $g['globalkredit']['sollVorjahr'];
                }
            }
        }
        foreach ([2022, 2023, 2024, 2025, 2026] as $jahr) {
            if (isset($personalamtSoll[$jahr], $personalamtSollVorjahr[$jahr + 1])) {
                $this->assertSame(
                    $personalamtSoll[$jahr],
                    $personalamtSollVorjahr[$jahr + 1],
                    "Personalamt-Soll $jahr muss dem Vorjahres-Soll " . ($jahr + 1) . ' entsprechen'
                );
            }
        }
    }

    /**
     * Buch 2024: Die Kapitelüberschrift «Subventionsverträge und Beiträge an Dritte
     * (157)» bricht im Satz um und trägt darum einen Tabulator. Wird sie deswegen
     * nicht als Kapitelanfang erkannt, läuft das vorangehende Kapitel weiter: die
     * Bibliotheken (155) bekamen die Beträge der Subventionsverträge (20'925'007),
     * die Subventionsverträge blieben leer, und die echten Zahlen der Bibliotheken
     * (8'034'751) gingen verloren.
     */
    public function testUmgebrocheneKapitelueberschriftTrenntDieKapitel2024(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2024/teil-b.pdf'), null, 2024);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('155', $nachCode, 'Bibliotheken (155)');
        $this->assertSame(8026112, $nachCode['155']['globalkredit']['ist'], 'Bibliotheken Ist 2022');
        $this->assertSame(7978634, $nachCode['155']['globalkredit']['sollVorjahr'], 'Bibliotheken Soll 2023');
        $this->assertSame(8034751, $nachCode['155']['globalkredit']['soll'], 'Bibliotheken Soll 2024');

        $this->assertArrayHasKey('157', $nachCode, 'Subventionsverträge (157)');
        $this->assertSame(19578863, $nachCode['157']['globalkredit']['ist'], 'Subventionsverträge Ist 2022');
        $this->assertSame(19641371, $nachCode['157']['globalkredit']['sollVorjahr'], 'Subventionsverträge Soll 2023');
        $this->assertSame(20925007, $nachCode['157']['globalkredit']['soll'], 'Subventionsverträge Soll 2024');
    }

    /**
     * Buch 2026, Produktegruppe «Steuern und Finanzausgleich» (280): Ihre Erlöse
     * sind ein Vielfaches der Kosten, deshalb steht in der Prozentspalte ein
     * vierstelliger Wert («805’963’318 1’130 772’727’840 8’293 793’774’498 8’778
     * …»). Eine Heuristik, die Beträge über ihre Grösse von Prozentwerten trennt,
     * liest die Zeile dadurch um eine Spalte verschoben und meldet als Ertrag des
     * Budgetjahres den Wert des Vorjahres.
     */
    public function testProzentspalteUeberTausendVerschiebtDieWerteNicht(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), null, 2026);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('280', $nachCode, 'Steuern und Finanzausgleich (280)');
        $st = $nachCode['280'];
        $this->assertSame(805963318, $st['ertrag']['ist'], 'Total effektive Erlöse Ist 2024');
        $this->assertSame(772727840, $st['ertrag']['sollVorjahr'], 'Total effektive Erlöse Soll 2025');
        $this->assertSame(793774498, $st['ertrag']['soll'], 'Total effektive Erlöse Soll 2026');
        $this->assertSame(71300558, $st['aufwand']['ist'], 'Total effektive Kosten Ist 2024');
        $this->assertSame(9318362, $st['aufwand']['sollVorjahr'], 'Total effektive Kosten Soll 2025');
        $this->assertSame(9043126, $st['aufwand']['soll'], 'Total effektive Kosten Soll 2026');
    }

    /**
     * Buch 2026, Informatikdienste (222): Die Stelleneinheiten stehen im
     * Personalteil («Stelleneinheiten 75.40 82.02 85.02»). Weiter hinten führt
     * dieselbe Produktegruppe eine Leistungsmenge «Geschätzter Zeitaufwand
     * umgerechnet in Stelleneinheiten 7 7 7» — eine Zeile, die das Wort nur
     * enthält. Wer danach sucht statt nach dem Zeilenanfang, meldet 7 Stellen.
     */
    public function testStelleneinheitenKommenNichtAusEinerLeistungsmenge(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), null, 2026);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('222', $nachCode, 'Informatikdienste (222)');
        $idw = $nachCode['222'];
        $this->assertSame(75.4, $idw['stellen']['ist'], 'Stelleneinheiten Ist 2024');
        $this->assertSame(82.02, $idw['stellen']['sollVorjahr'], 'Stelleneinheiten Soll 2025');
        $this->assertSame(85.02, $idw['stellen']['soll'], 'Stelleneinheiten Soll 2026');

        // Entsorgung (328): «Der Aufbau … (2.0 Stelleneinheiten) um rund 350'000
        // Franken …» steht im selben Kapitel und beginnt nach dem Zeilenumbruch mit
        // dem Wort. Eine Stellenzeile trägt hinter dem Wort NUR Zahlen.
        $this->assertArrayHasKey('328', $nachCode, 'Entsorgung (328)');
        $this->assertSame(78.4, $nachCode['328']['stellen']['ist'], 'Entsorgung Stellen Ist 2024');
        $this->assertSame(80.7, $nachCode['328']['stellen']['sollVorjahr'], 'Entsorgung Stellen Soll 2025');
        $this->assertSame(82.6, $nachCode['328']['stellen']['soll'], 'Entsorgung Stellen Soll 2026');
    }

    /**
     * Buch 2024, Stadtentwicklung (142): Unter der Kostentabelle der Produkte steht
     * der Hinweis «Die Produkte wurden Anfang 2023 neu definiert. Die Vorjahreswerte
     * …». Er endet auf einer Jahreszahl und wurde deshalb als fünfte Kostenzeile mit
     * den Werten 0/0/2023 in jede Produkt-Tabelle geschrieben.
     */
    public function testFliesstextIstKeineKostenzeile(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2024/teil-b.pdf'), null, 2024);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('142', $nachCode, 'Stadtentwicklung (142)');
        foreach ($nachCode['142']['produkte'] as $p) {
            // Der Erlös trägt in dieser Gruppe eine Fussnote («Erlös*»).
            $labels = array_map(static fn ($z) => rtrim($z['label'], '*'), $p['kostentabelle']);
            $this->assertSame(
                ['Kosten', 'Erlös', 'Nettokosten', 'Kostendeckungsgrad in %'],
                $labels,
                "Produkt {$p['nummer']} trägt genau die vier Zeilen der Kostentabelle"
            );
        }

        // Über alle Produktegruppen aller committeten Jahrgänge: kein anderes Label.
        $erlaubt = ['Kosten', 'Erlös', 'Nettokosten', 'Kostendeckungsgrad in %'];
        foreach ([2019, 2021, 2022, 2024, 2026, 2027] as $jahr) {
            $teilB = $this->fixture($jahr . '/teil-b.pdf');
            if (!is_file($teilB)) {
                continue;
            }
            foreach ($parser->struktur($teilB, null, $jahr)['produktegruppen'] as $g) {
                foreach ($g['produkte'] as $p) {
                    foreach ($p['kostentabelle'] as $z) {
                        $this->assertContains(
                            rtrim($z['label'], '*'),
                            $erlaubt,
                            "Jahr $jahr, PG {$g['code']}, Produkt {$p['nummer']}: «{$z['label']}» ist keine Kostenzeile"
                        );
                    }
                }
            }
        }
    }

    /**
     * Buch 2027, Sozial- und Erwachsenenhilfe (621): Die Produktegruppe beantragt,
     * ihre bisherigen Indikatoren zu löschen und neue einzuführen. Die alten tragen
     * ab dem Budgetjahr den Text «wird gelöscht», die neuen stehen erst ab Soll 2027
     * — ihre Wertzeile führt darum nur vier statt sechs Zahlen, und die beiden
     * leeren Spalten fehlen LINKS («… Sozialhilfe  69 69 69 69»). Wer sechs Werte
     * verlangt, liest für diese Produktegruppe gar keine Zielvorgabe: Das Parlament
     * sähe im Werkzeug keine einzige Vorgabe, über die es beschliesst.
     */
    public function testNeueZielvorgabeMitVierSpaltenWirdGelesen2027(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2027/teil-b.pdf'), null, 2027);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('621', $nachCode, 'Sozial- und Erwachsenenhilfe (621)');
        $ziele = $nachCode['621']['zielvorgaben'];
        $this->assertNotEmpty($ziele, 'Zielvorgaben der PG 621 nicht gelesen');

        $sozialhilfe = null;
        foreach ($ziele as $z) {
            if (str_contains($z['messgroesse'], 'Langzeitabteilungen Sozialhilfe')) {
                $sozialhilfe = $z;
            }
        }
        $this->assertNotNull($sozialhilfe, 'Messgrösse «Fallführende Langzeitabteilungen Sozialhilfe»');
        $this->assertSame('69', $sozialhilfe['soll'], 'Soll 2027 der neuen Messgrösse');

        // Ein Indikator, der ab dem Budgetjahr «wird gelöscht» trägt, ist nichts,
        // worüber das Parlament beschliesst — er bleibt aussen vor.
        foreach ($ziele as $z) {
            $this->assertStringNotContainsString(
                'Kaufm. Fallführung',
                $z['messgroesse'],
                'ein zur Löschung beantragter Indikator ist keine Zielvorgabe des Budgetjahres'
            );
        }
    }

    /**
     * Buch 2027, Berufsbildung (540): Das PDF klebt die Kopfzeile der
     * Globalkredit-Tabelle und ihre Wertzeile zu EINER Zeile zusammen —
     * «Globalkredit  Ist 2025 Soll 2026 Soll 2027 Plan 2028 Plan 2029 Plan 2030
     * Nettokosten / Globalkredit  7'892'389 8'703'400 8'869'746 …». Wer die
     * Wertzeile nur am Zeilenanfang sucht, findet sie hier nicht: Die
     * Produktegruppe stand mit einem Globalkredit von 0 in der Anwendung,
     * obwohl das Buch 8'869'746 Franken ausweist. Die Kontrolltabelle im Anhang
     * des Buchs nennt dieselben drei Werte.
     */
    public function testVerklebteKopfUndWertzeileLiefertDenGlobalkredit2027(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2027/teil-b.pdf'), null, 2027);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('540', $nachCode, 'Berufsbildung (540)');
        $bb = $nachCode['540'];
        $this->assertSame(7892389, $bb['globalkredit']['ist'], 'Berufsbildung Globalkredit Ist 2025');
        $this->assertSame(8703400, $bb['globalkredit']['sollVorjahr'], 'Berufsbildung Globalkredit Soll 2026');
        $this->assertSame(8869746, $bb['globalkredit']['soll'], 'Berufsbildung Globalkredit Soll 2027');
        // Die Probe des Buchs: Globalkredit = Kosten − Erlöse.
        $this->assertSame(
            $bb['aufwand']['soll'] - $bb['ertrag']['soll'],
            $bb['globalkredit']['soll'],
            'Globalkredit ist Total effektive Kosten minus Total effektive Erlöse'
        );
    }

    /**
     * Buch 2022, Öffentliche Beleuchtung (720): Die Produktegruppe ist neu, die
     * Ist-Spalte deshalb leer. Die Kostenzeile führt darum nur zwei Betrag-Anteil-
     * Paare («4'882'598 100  5'455'201 100  5'353'321  5'413'207  5'397'597»), und
     * die fünf Zahlen der Reihe nach zu lesen macht aus dem ersten Planjahr das
     * Budgetjahr.
     */
    public function testEineLeereJahresspalteVerschiebtDieKostenNicht(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2022/teil-b.pdf'), null, 2022);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('720', $nachCode, 'Öffentliche Beleuchtung (720)');
        $ob = $nachCode['720'];
        $this->assertSame(0, $ob['aufwand']['ist'], 'Kosten Ist 2020 (im Buch leer)');
        $this->assertSame(4882598, $ob['aufwand']['sollVorjahr'], 'Kosten Soll 2021');
        $this->assertSame(5455201, $ob['aufwand']['soll'], 'Kosten Soll 2022');
        $this->assertSame(0, $ob['ertrag']['ist'], 'Erlöse Ist 2020 (im Buch leer)');
        $this->assertSame(1524459, $ob['ertrag']['sollVorjahr'], 'Erlöse Soll 2021');
        $this->assertSame(1684641, $ob['ertrag']['soll'], 'Erlöse Soll 2022');
    }

    /**
     * Buch 2026, Schulpflege (855): Die Stellenzeile führt nur zwei Werte, weil die
     * Ist-Spalte leer ist («Stelleneinheiten                3.00     3.00»). Die
     * Werte stehen rechtsbündig unter ihren Spalten, es fehlt also die LINKE —
     * wer von links zuordnet, macht aus Soll 2025 den Ist-Wert und lässt das
     * Budgetjahr leer.
     */
    public function testEineLeereSpalteFehltLinks(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), null, 2026);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }

        $this->assertArrayHasKey('855', $nachCode, 'Schulpflege (855)');
        $sp = $nachCode['855'];
        $this->assertSame(0.0, $sp['stellen']['ist'], 'Schulpflege Stellen Ist 2024 (im Buch leer)');
        $this->assertSame(3.0, $sp['stellen']['sollVorjahr'], 'Schulpflege Stellen Soll 2025');
        $this->assertSame(3.0, $sp['stellen']['soll'], 'Schulpflege Stellen Soll 2026');
    }

    /**
     * Zwei Produktegruppen tragen nie denselben Globalkredit: Gleiche Beträge in
     * zwei Kapiteln heissen, dass eine Wertzeile der falschen Gruppe zugeschlagen
     * wurde. Nullen sind zulässig (Eigenwirtschaftsbetriebe führen keinen
     * Globalkredit). Prüft alle committeten Jahrgänge.
     */
    public function testKeineZweiGruppenMitDemselbenGlobalkredit(): void {
        $parser = new BudgetBuchParser();
        foreach ([2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027] as $jahr) {
            $teilB = $this->fixture($jahr . '/teil-b.pdf');
            if (!is_file($teilB)) {
                continue;
            }
            $struktur = $parser->struktur($teilB, null, $jahr);
            $gesehen = [];
            foreach ($struktur['produktegruppen'] as $g) {
                if (!empty($g['kuenstlich'])) {
                    continue;
                }
                $soll = $g['globalkredit']['soll'];
                if ($soll === 0) {
                    continue;
                }
                $this->assertArrayNotHasKey(
                    (string) $soll,
                    $gesehen,
                    "Jahr $jahr: Produktegruppen {$g['code']} und " . ($gesehen[(string) $soll] ?? '?')
                    . " tragen denselben Globalkredit $soll"
                );
                $gesehen[(string) $soll] = $g['code'];
            }
        }
    }

    /**
     * Ein Departementsname aus dem Inhaltsverzeichnis ist eine Textzeile — nie eine
     * reine Füllpunkt-/Seitenzahlzeile («……… 175»). Regressionsfall zum Bug, bei dem
     * eine umgebrochene TOC-Zeile als Departement der Produktegruppe 480 (Umwelt- und
     * Gesundheitsschutz) landete. Prüft alle committeten Jahrgänge.
     */
    public function testDepartementIstNieEineFuellpunktZeile(): void {
        $parser = new BudgetBuchParser();
        foreach ([2022, 2023, 2024, 2025, 2026, 2027] as $jahr) {
            $teilB = $this->fixture($jahr . '/teil-b.pdf');
            if (!is_file($teilB)) {
                continue;
            }
            $struktur = $parser->struktur($teilB, null, $jahr);
            foreach ($struktur['produktegruppen'] as $g) {
                $dept = (string) $g['departement'];
                $this->assertMatchesRegularExpression(
                    '/\p{L}/u',
                    $dept,
                    "Jahr $jahr, Produktegruppe {$g['code']}: kein echter Departementsname («{$dept}»)"
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/^[\s.·]*\d+\s*$/u',
                    $dept,
                    "Jahr $jahr, Produktegruppe {$g['code']}: Füllpunkt-/Seitenzahlzeile als Departement («{$dept}»)"
                );
            }
        }
    }
}
