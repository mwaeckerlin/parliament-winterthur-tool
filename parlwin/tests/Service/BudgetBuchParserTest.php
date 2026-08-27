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
        foreach ([2022, 2023, 2024, 2025, 2026] as $jahr) {
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
        foreach ([2022, 2023, 2024, 2025] as $jahr) {
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
     * Ein Departementsname aus dem Inhaltsverzeichnis ist eine Textzeile — nie eine
     * reine Füllpunkt-/Seitenzahlzeile («……… 175»). Regressionsfall zum Bug, bei dem
     * eine umgebrochene TOC-Zeile als Departement der Produktegruppe 480 (Umwelt- und
     * Gesundheitsschutz) landete. Prüft alle committeten Jahrgänge.
     */
    public function testDepartementIstNieEineFuellpunktZeile(): void {
        $parser = new BudgetBuchParser();
        foreach ([2022, 2023, 2024, 2025, 2026] as $jahr) {
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
