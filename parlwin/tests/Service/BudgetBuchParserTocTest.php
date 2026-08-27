<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\TestCase;

/**
 * Inhaltsverzeichnis-Parsing (F80) ohne PDF: prüft die Departements-Zuordnung
 * über den privaten parseTeilB() an gestelltem Text. Reproduziert den Screenshot-
 * Bug, bei dem eine umgebrochene TOC-Zeile aus Füllpunkten und Seitenzahl
 * («……… 175») als Departement der folgenden Produktegruppe (480) landete.
 */
class BudgetBuchParserTocTest extends TestCase {
    /** @return list<array<string, mixed>> */
    private function parseTeilB(string $text): array {
        $parser = new BudgetBuchParser();
        // Seit PHP 8.1 sind private Methoden ohne setAccessible() aufrufbar.
        return (new \ReflectionMethod($parser, 'parseTeilB'))->invoke($parser, $text);
    }

    public function testUmgebrocheneFuellpunktZeileWirdNichtZumDepartement(): void {
        // Zwischen zwei sauberen TOC-Einträgen steht eine umgebrochene Zeile aus
        // nur Füllpunkten und einer Seitenzahl. Ohne den Fix übernahm der Parser
        // sie als Departementskopf und hängte die nächste Produktegruppe (480)
        // darunter.
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Sicherheit und Umwelt',
            'Schutz und Intervention Winterthur (470) ....................... 174',
            '............................. 175',
            'Umwelt- und Gesundheitsschutz (480) ................... 176',
            'Behörden und Stadtkanzlei',
            'Stadtkanzlei (110) .................. 12',
            'Einleitung Produktegruppe',
            // Kapitel-Körper: die Überschriften ohne Füllpunkte, jede registriert ihre Gruppe.
            'Schutz und Intervention Winterthur (470)',
            'Umwelt- und Gesundheitsschutz (480)',
            'Stadtkanzlei (110)',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }

        self::assertArrayHasKey('480', $nachCode, 'Produktegruppe 480 wurde geparst');
        self::assertSame(
            'Sicherheit und Umwelt',
            $nachCode['480']['departement'],
            'PG 480 gehört zu «Sicherheit und Umwelt», nie zur Füllpunkt-/Seitenzahlzeile',
        );
        self::assertSame('Sicherheit und Umwelt', $nachCode['470']['departement']);
        self::assertSame('Behörden und Stadtkanzlei', $nachCode['110']['departement']);
    }

    public function testParstParlamentarischeZielvorgabenJeMessgroesse(): void {
        // Struktur wie im Buch (PG 121): nummerierte Ziele, je Messgrösse eine
        // Wertzeile aus sechs Jahresspalten; entscheidbar ist «Soll aktuell» (Index 2).
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Auftrag',
            'Das Personalamt bearbeitet die personalrechtlichen Fragen.',
            'Rechtsgrundlagen und verwaltungsinterne Grundlagen',
            'Parlamentarische Zielvorgaben Ist 2024 Soll 2025 Soll 2026 Plan 2027 Plan 2028 Plan 2029',
            '2 Kundenorientierung zentrales Personalmanagement',
            'Die Kundschaft ist mit den Dienstleistungen zufrieden.',
            'Messung / Bewertung:',
            'Der Zufriedenheitsgrad wird ermittelt.',
            'Messgrösse:',
            'Prozentsatz der zufrieden Antwortenden',
            '90 85 85 85 85 85',
            '3 Kundenorientierung Personalentwicklung',
            'Die Personalentwicklung ist bedarfsgerecht.',
            'Messgrösse:',
            " Anzahl Kurstage \t1'339 1'000 1'000 1'000 1'000 1'000",
            ' Durchführungsquote der internen Weiterbildungen',
            '87 80 80 80 80 80',
            'Globalkredit \tIst 2024 Soll 2025 Soll 2026',
            "Nettokosten / Globalkredit 4'061'060 4'395'510 4'866'124",
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        self::assertArrayHasKey('121', $nachCode);
        $zv = $nachCode['121']['zielvorgaben'];
        self::assertCount(3, $zv, 'drei Messgrössen (Ziel 2 eine, Ziel 3 zwei)');

        $kundenorientierung = array_values(array_filter($zv, static fn ($z) => $z['zielNummer'] === 2))[0];
        self::assertSame('2 Kundenorientierung zentrales Personalmanagement', $kundenorientierung['zielTitel']);
        self::assertSame('Prozentsatz der zufrieden Antwortenden', $kundenorientierung['messgroesse']);
        self::assertSame('90', $kundenorientierung['werte'][0], 'Ist 2024');
        self::assertSame('85', $kundenorientierung['soll'], 'Soll aktuell (Index 2) ist der beantragbare Wert');

        $kurstage = array_values(array_filter($zv, static fn ($z) => $z['messgroesse'] === 'Anzahl Kurstage'))[0];
        self::assertSame(3, $kurstage['zielNummer']);
        self::assertSame("1'000", $kurstage['soll']);

        $quote = array_values(array_filter($zv, static fn ($z) => str_starts_with($z['messgroesse'], 'Durchführungsquote')))[0];
        self::assertSame('80', $quote['soll']);
    }

    public function testParstKostenzeilenAusDemInformationsteil(): void {
        // Kostentabelle im Informationsteil: Label + «Soll aktuell» (Zahlenindex 4,
        // wegen der «in%»-Spalten); Summen-/Verrechnungszeilen sind keine Positionen;
        // ein umgebrochenes Label wird zusammengeführt.
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            '▼Informationsteil▼',
            'Nettokosten / Globalkredit Ist 2024 in% Soll 2025 in% Soll 2026 in% Plan 2027 Plan 2028 Plan 2029',
            "Personalkosten 4'610'991 71 4'654'220 68 5'178'974 70 5'337'045 4'909'047 4'825'682",
            "Sachkosten 889'959 14 1'093'912 16 990'017 13 850'017 880'017 825'017",
            'Kalk. Abschreibungen und',
            'Zinsen / Finanzaufwand',
            "58'823 1 58'256 1 57'518 1 0 85'200 84'160",
            "Total effektive Kosten 6'514'117 100 6'863'996 100 7'428'985 100 7'338'254 7'023'816 6'884'411",
            'Stellenplan Ist 2024 Soll 2025 Soll 2026',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $kz = $nachCode['121']['kostenzeilen'];
        $nachLabel = [];
        foreach ($kz as $z) {
            $nachLabel[$z['label']] = $z['soll'];
        }
        self::assertArrayHasKey('Personalkosten', $nachLabel);
        self::assertSame(5178974, $nachLabel['Personalkosten'], 'Soll aktuell (Zahlenindex 4)');
        self::assertSame(990017, $nachLabel['Sachkosten']);
        self::assertArrayHasKey('Kalk. Abschreibungen und Zinsen / Finanzaufwand', $nachLabel, 'umgebrochenes Label zusammengeführt');
        self::assertSame(57518, $nachLabel['Kalk. Abschreibungen und Zinsen / Finanzaufwand']);
        self::assertArrayNotHasKey('Total effektive Kosten', $nachLabel, 'Summenzeile ist keine Position');
    }

    public function testParstLeistungenJeProdukt(): void {
        // Leistungen je Produkt (F110): im Buch beginnt jede Leistung mit einem
        // Bullet-Glyph (smalot liefert U+F0A7) — teils auf derselben Zeile wie der
        // Text, teils allein; eine umgebrochene Leistung läuft ohne Glyph auf der
        // Folgezeile weiter. Erwartet: saubere Leistungen ohne Glyph, Fortsetzungen
        // zusammengeführt, keine leeren Einträge. Die Nettokosten-Wertzeile bleibt intakt.
        $g = "\u{F0A7}";
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Leistungen',
            $g . ' Erarbeitung der personalpolitischen Grundsätze',   // Glyph + Text auf einer Zeile
            $g . ' Überwachung des Vollzugs und Koordination der',    // Glyph + Text, umgebrochen
            'personalrechtlichen Praxis',                             // Fortsetzung (kein Glyph)
            $g . ' Bearbeitung personalrechtlicher Geschäfte',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Kosten 1'442'744 1'511'500 1'540'766",
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Produkt 2 Zentrales Personalmanagement',
            'Leistungen',
            $g,                                                       // Glyph allein (Live-Buch)
            'Beratung der Bereiche in Personalfragen',                // Text nach dem Glyph
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'318'580 1'300'000 1'318'580",
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $produkte = $nachCode['121']['produkte'];
        self::assertCount(2, $produkte);
        self::assertSame([
            'Erarbeitung der personalpolitischen Grundsätze',
            'Überwachung des Vollzugs und Koordination der personalrechtlichen Praxis',
            'Bearbeitung personalrechtlicher Geschäfte',
        ], $produkte[0]['leistungen'], 'Glyph entfernt, Fortsetzung zusammengeführt');
        self::assertSame(1135916, $produkte[0]['nettokosten']['soll'], 'die Nettokosten bleiben korrekt');
        self::assertSame(['Beratung der Bereiche in Personalfragen'], $produkte[1]['leistungen']);
        foreach ($produkte as $p) {
            foreach ($p['leistungen'] as $l) {
                self::assertNotSame('', trim($l), 'keine leere Leistung');
                self::assertStringNotContainsString("\u{F0A7}", $l, 'kein Bullet-Glyph im Text');
            }
        }
    }

    public function testAnhangDupliziertProdukteNicht(): void {
        // Der Anhang «Gliederung von Budget und Jahresrechnung» am Buchende listet
        // jede Produktegruppe mit ihren Produkten noch einmal, ohne Zahlen. Diese
        // Wiederholung darf die Produkte NICHT doppeln (F110-Bug: Produkte doppelt,
        // die zweite Reihe ohne Nettokosten/Leistungen).
        $g = "\u{F0A7}";
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Leistungen',
            $g . ' Erarbeitung der personalpolitischen Grundsätze',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Produkt 2 Zentrales Personalmanagement',
            'Leistungen',
            $g . ' Beratung der Bereiche in Personalfragen',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'318'580 1'300'000 1'318'580",
            // Anhang: dieselbe Produktegruppe mit ihren Produkten, ohne Zahlen.
            'Gliederung von Budget und Jahresrechnung',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Produkt 2 Zentrales Personalmanagement',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $produkte = $nachCode['121']['produkte'];
        self::assertCount(2, $produkte, 'der Anhang doppelt die Produkte nicht');
        self::assertSame(1135916, $produkte[0]['nettokosten']['soll'], 'die Zahlen des echten Produkts bleiben');
        self::assertSame(['Erarbeitung der personalpolitischen Grundsätze'], $produkte[0]['leistungen']);
        self::assertSame([1, 2], array_map(static fn ($p) => $p['nummer'], $produkte), 'jede Produktnummer genau einmal');
    }

    public function testParstProduktKostentabelle(): void {
        // Je Produkt führt das Buch eine Kostentabelle (Kosten, Erlös, Nettokosten,
        // Kostendeckungsgrad) über Ist / Soll Vorjahr / Soll aktuell — sie gehört als
        // Budgetzahlen in die Produkt-Karte (F110).
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Kosten 1'442'744 1'511'500 1'540'766",
            "Erlös 385'014 366'049 404'850",
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Kostendeckungsgrad in % 27 24 26',
            'Operative Ziele Ist 2024 Soll 2025 Soll 2026',
            'Siehe Massnahmen und Projekte',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $p1 = $nachCode['121']['produkte'][0];
        $nachLabel = [];
        foreach ($p1['kostentabelle'] as $z) {
            $nachLabel[$z['label']] = $z['werte'];
        }
        self::assertSame([1442744, 1511500, 1540766], $nachLabel['Kosten']);
        self::assertSame([385014, 366049, 404850], $nachLabel['Erlös']);
        self::assertSame([1057730, 1145451, 1135916], $nachLabel['Nettokosten']);
        self::assertSame([27, 24, 26], $nachLabel['Kostendeckungsgrad in %']);
        // «Operative Ziele …» beendet die Tabelle, ist selbst keine Kostenzeile.
        self::assertArrayNotHasKey('Operative Ziele Ist', $nachLabel);
        // Die prominente Nettokosten-Soll-Zahl der Karte bleibt korrekt.
        self::assertSame(1135916, $p1['nettokosten']['soll']);
    }
}
