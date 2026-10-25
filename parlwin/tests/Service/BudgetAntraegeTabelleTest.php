<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetAntraegeTabelle;
use PHPUnit\Framework\TestCase;

/**
 * Die Anträge als Tabelle (F138): je Antrag eine Zeile mit festen Spalten,
 * Betrag und Stellen als Zahl, die Begründung ohne ihre Formatierung, und zu
 * jeder Produktegruppe ihre Seite im Budgetbuch (F129). Wer die Datei öffnet,
 * soll sortieren, filtern und summieren können.
 */
final class BudgetAntraegeTabelleTest extends TestCase
{
    /** @return array<string, mixed> wie BudgetImportService::antraegePdf es liefert */
    private function daten(): array
    {
        return [
            'jahr' => 2027,
            'kommission' => 'Sachkommission Bau und Betriebe',
            'fraktion' => 'Unsere Fraktion',
            'eintraege' => [
                [
                    'departement' => 'Finanzen',
                    'produktegruppe' => 'Immobilien',
                    'buchseite' => 240,
                    'antrag' => [
                        'bereich' => 'globalbudget',
                        'betragDelta' => -250000,
                        'stellenDelta' => 0,
                        'prozentDelta' => 0,
                        'neuerWert' => '',
                        'antragsteller' => 'Unsere Fraktion',
                        'haltung' => 'einreichen',
                        'begruendung' => "Die Kürzung ist **nötig**.\n\n- erstens\n- zweitens",
                        'zielAenderungen' => [
                            ['zielNummer' => 1, 'unterTitel' => 'Minimale Nettorendite', 'messgroesse' => 'Wohnliegenschaften (in %)', 'soll' => '1.00', 'neuerWert' => '2'],
                        ],
                        'aufteilung' => [
                            ['ebene' => 'produkt', 'ref' => '3', 'betrag' => -250000],
                        ],
                    ],
                ],
                [
                    'departement' => 'Bau',
                    'produktegruppe' => 'Schulhaus Neuwiesen · Kredit 3000.1 Ausführungskredit',
                    'buchseite' => 0,
                    'antrag' => [
                        'bereich' => 'bewilligungsorgan',
                        'betragDelta' => 0,
                        'stellenDelta' => 0,
                        'prozentDelta' => 0,
                        'neuerWert' => '#',
                        'antragsteller' => 'Unsere Fraktion',
                        'haltung' => 'unterstuetzen',
                        'begruendung' => 'Der Kredit fällt in die Kompetenz des Parlaments.',
                        'zielAenderungen' => [],
                        'aufteilung' => [],
                    ],
                ],
                [
                    'departement' => 'Präsidiales',
                    'produktegruppe' => 'Personalamt',
                    'buchseite' => 121,
                    'antrag' => [
                        'bereich' => 'personal',
                        'betragDelta' => -120000,
                        'stellenDelta' => -1.5,
                        'prozentDelta' => 0,
                        'neuerWert' => '',
                        'antragsteller' => 'Unsere Fraktion',
                        'haltung' => 'einreichen',
                        'begruendung' => '',
                        'automatisch' => true,
                        'zielAenderungen' => [],
                        'aufteilung' => [],
                    ],
                ],
            ],
        ];
    }

    public function testKopfzeileUndEineZeileJeAntrag(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());

        self::assertCount(4, $zeilen, 'Kopfzeile plus drei Anträge');
        self::assertSame(BudgetAntraegeTabelle::SPALTEN, $zeilen[0]);
        self::assertCount(count(BudgetAntraegeTabelle::SPALTEN), BudgetAntraegeTabelle::BREITEN, 'jede Spalte braucht ihre Breite');
        foreach (array_slice($zeilen, 1) as $zeile) {
            self::assertCount(count(BudgetAntraegeTabelle::SPALTEN), $zeile, 'eine Zeile hat so viele Felder wie Spalten');
        }
    }

    public function testBetragStellenUndSeiteStehenAlsZahlen(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());
        $spalte = static fn (string $name): int => (int) array_search($name, BudgetAntraegeTabelle::SPALTEN, true);

        self::assertSame(-250000, $zeilen[1][$spalte('Betrag')], 'der Betrag muss summierbar sein');
        self::assertSame(240, $zeilen[1][$spalte('Buch S.')], 'die Seite im Budgetbuch fehlt');
        self::assertSame('', $zeilen[2][$spalte('Buch S.')], 'ohne Seitenangabe bleibt die Zelle leer');
        self::assertSame(-1.5, $zeilen[3][$spalte('Stellen')], 'die Stellen fehlen als Zahl');
        self::assertSame('', $zeilen[1][$spalte('Stellen')], 'ohne Stellenänderung bleibt die Zelle leer');
    }

    public function testBegruendungStehtOhneFormatierungInDerZelle(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());
        $spalte = (int) array_search('Begründung', BudgetAntraegeTabelle::SPALTEN, true);
        $text = (string) $zeilen[1][$spalte];

        self::assertStringNotContainsString('**', $text, 'die Markdown-Zeichen stehen in der Zelle');
        self::assertStringNotContainsString('<', $text, 'HTML steht in der Zelle');
        // Je Absatz und je Listenpunkt eine Zeile, ohne Leerzeile dazwischen:
        // In der Zelle eines Tabellenprogramms kostet jede Leerzeile Höhe, und
        // zwischen «erstens» und «zweitens» standen zwei (gemessen 24.10.2026).
        self::assertSame("Die Kürzung ist nötig.\n• erstens\n• zweitens", $text);
    }

    public function testBewilligungsorganStehtAlsNeuerWertUndNichtAlsBetrag(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());
        $spalte = static fn (string $name): int => (int) array_search($name, BudgetAntraegeTabelle::SPALTEN, true);

        self::assertSame('Bewilligungsorgan', $zeilen[2][$spalte('Bereich')]);
        self::assertSame('#', $zeilen[2][$spalte('Neuer Wert')], 'das neue Bewilligungsorgan fehlt (F137)');
        self::assertSame(0, $zeilen[2][$spalte('Betrag')], 'ein Antrag auf das Bewilligungsorgan trägt keinen Betrag');
        self::assertSame('unterstützt', $zeilen[2][$spalte('Haltung')]);
    }

    public function testZielvorgabenUndVerteilungStehenLesbarInEinerZelle(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());
        $spalte = static fn (string $name): int => (int) array_search($name, BudgetAntraegeTabelle::SPALTEN, true);

        $ziel = (string) $zeilen[1][$spalte('Zielvorgaben')];
        self::assertStringContainsString('Minimale Nettorendite', $ziel, 'der Unterabschnitt fehlt');
        self::assertStringContainsString('1.00 → 2', $ziel, 'der heutige und der beantragte Soll fehlen');
        self::assertStringContainsString("Produkt 3 (-250'000)", (string) $zeilen[1][$spalte('Einsparung verteilt')]);
    }

    public function testEinAutomatischerAntragIstAlsSolcherErkennbar(): void
    {
        $zeilen = BudgetAntraegeTabelle::zeilen($this->daten());
        $spalte = (int) array_search('Antragsteller', BudgetAntraegeTabelle::SPALTEN, true);

        self::assertSame('Unsere Fraktion (automatisch)', $zeilen[3][$spalte]);
    }

    public function testDerDateinameNenntJahrUndKommission(): void
    {
        self::assertSame(
            'Budgetantraege-2027-Sachkommission-Bau-und-Betriebe',
            BudgetAntraegeTabelle::name($this->daten()),
        );
        self::assertSame(
            'Budgetantraege-2027',
            BudgetAntraegeTabelle::name(['jahr' => 2027, 'kommission' => null]),
            'ohne Kommission steht nur das Jahr im Namen',
        );
    }
}
