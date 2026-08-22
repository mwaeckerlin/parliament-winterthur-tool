<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Parst die echten, committeten Budgetbücher (tests/fixtures/budget) mit
 * smalot/pdfparser und prüft die extrahierten Werte gegen die im Buch gemessenen
 * Zahlen. Gruppe «pdf»: braucht vendor/ (smalot), läuft darum nicht im schnellen
 * Standardlauf, sondern über `npm run test:pdf` und im Image.
 */
#[Group('pdf')]
class BudgetBuchParserTest extends TestCase {
    private function fixture(string $rel): string {
        return \dirname(__DIR__, 3) . '/tests/fixtures/budget/' . $rel;
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
        // mit Nummer, Name und Nettokosten (Soll).
        $this->assertGreaterThanOrEqual(4, \count($pa['produkte']), 'Produkte der Produktegruppe 121');
        $p1 = $pa['produkte'][0];
        $this->assertSame(1, $p1['nummer']);
        $this->assertSame('Personalpolitik / Personalrecht', $p1['name']);
        $this->assertSame(1135916, $p1['nettokosten']['soll'], 'Nettokosten Soll Produkt 1');
        $this->assertSame('Zentrales Personalmanagement', $pa['produkte'][1]['name']);
        $this->assertSame(1318580, $pa['produkte'][1]['nettokosten']['soll'], 'Nettokosten Soll Produkt 2');

        // Erläuterungen/Begründungen (F80): Textblöcke aus dem Buch, sauber
        // abgegrenzt (keine Label-/Tabellen-/Struktur-Reste).
        foreach (['erlaeuterungStellen', 'begruendungAbweichung', 'begruendungFap', 'massnahmen'] as $feld) {
            $this->assertNotSame('', $pa[$feld], "Erläuterungstext $feld ist leer");
            $this->assertStringNotContainsString("\t", $pa[$feld], "$feld enthält eine Tabellen-/Kopfzeile");
            $this->assertDoesNotMatchRegularExpression('/\(\d{3}\)\s*$/u', $pa[$feld], "$feld enthält eine Kapitelüberschrift");
            $this->assertStringNotContainsString('Erläuterungen zum Stellenplan', $pa[$feld], "$feld enthält ein Abschnitts-Label");
        }
    }

    public function testParstSteuerfussAusTeilA2026(): void {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture('2026/teil-b.pdf'), $this->fixture('2026/teil-a.pdf'), 2026);
        $this->assertSame(125, $struktur['steuerfuss'], 'Steuerfuss 2026');
        $this->assertSame(521200000, $struktur['steuerertrag'], 'Gesamt-Steuerertrag 2026 (521,2 Mio.)');
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
}
