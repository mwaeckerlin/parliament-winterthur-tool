<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Drehbuch-Parser (F90/F91): liest die Sitzungsanträge und die Novemberbrief-
 * Spalte «NB» aus dem echten «Drehbuch zur Budgetbehandlung» der Budgetsitzung
 * 2026 (committete Testfixture — nur als Vergleich, immer neu geparst). Ohne die
 * koordinatenbasierte Zuordnung verkleben im Textstrom die Antrags- und NB-
 * Spalten; der Test beweist, dass Quelle, Richtung, Betrag und Ergebnis je
 * Produktegruppe korrekt getrennt werden.
 *
 * Gruppe «pdf»: braucht vendor/ (smalot/pdfparser).
 */
#[Group('pdf')]
class BudgetDrehbuchParserTest extends TestCase {
    private function fixture(): string {
        return \dirname(__DIR__, 1) . '/Fixtures/drehbuch/2026/drehbuch.pdf';
    }

    /** @return array{antraege: list<array<string, mixed>>, novemberbrief: array{produktegruppen: list<array<string, mixed>>}} */
    private function parse(): array {
        return (new BudgetDrehbuchParser())->parse($this->fixture(), 2026);
    }

    /**
     * @param list<array<string, mixed>> $antraege
     * @return array<string, mixed>|null
     */
    private function finde(array $antraege, string $code, string $antragsteller, int $betrag): ?array {
        foreach ($antraege as $a) {
            if ((string) $a['code'] === $code
                && (string) $a['antragsteller'] === $antragsteller
                && (int) $a['betragDelta'] === $betrag) {
                return $a;
            }
        }
        return null;
    }

    public function testAlleSitzungsantraegeMitBetragUndGremium(): void {
        $antraege = $this->parse()['antraege'];
        // Das Drehbuch 2026 führt 35 Globalbudget-Anträge über alle Produktegruppen.
        self::assertCount(35, $antraege, 'alle Globalbudget-Sitzungsanträge des Drehbuchs 2026');
        foreach ($antraege as $a) {
            self::assertSame('globalbudget', $a['bereich']);
            self::assertMatchesRegularExpression('/^\d{3}$/', (string) $a['code'], 'PG-Code dreistellig');
            self::assertNotSame(0, (int) $a['betragDelta'], 'jeder Antrag trägt einen Betrag');
            self::assertContains($a['gremium'], ['kommission', 'fraktion']);
        }
    }

    public function testKommissionsantragMitRichtungBetragUndErgebnis(): void {
        $antraege = $this->parse()['antraege'];
        // 121000 Personalamt: AK +103'000 (angenommen 11:0) und AK −216'000.
        $erhoehung = $this->finde($antraege, '121', 'AK', 103000);
        self::assertNotNull($erhoehung, 'Erhöhung um CHF 103\'000 (AK, Personalamt)');
        self::assertSame('11:0 angenommen', $erhoehung['ergebnis']);
        self::assertSame('kommission', $erhoehung['gremium']);
        self::assertStringContainsString('HR-Tool', (string) $erhoehung['begruendung']);

        self::assertNotNull($this->finde($antraege, '121', 'AK', -216000), 'Reduktion um CHF 216\'000 (AK)');
        // Grösster Kürzungsantrag: 263000 Städtische Allgemeinkosten, AK −1'400'000.
        $gross = $this->finde($antraege, '263', 'AK', -1400000);
        self::assertNotNull($gross, 'Reduktion um CHF 1\'400\'000 (AK, Allgemeinkosten)');
        self::assertSame('11:0 angenommen', $gross['ergebnis']);
    }

    public function testFraktionsantragOhneErgebnis(): void {
        $antraege = $this->parse()['antraege'];
        // 157000: zwei SP-Fraktionsanträge (+50'000, +30'000), in der Kommission
        // nicht abgestimmt → Ergebnis leer, Gremium «fraktion».
        $sp = $this->finde($antraege, '157', 'Fraktion SP', 50000);
        self::assertNotNull($sp, 'Fraktion SP: Erhöhung um CHF 50\'000');
        self::assertSame('', $sp['ergebnis'], 'Fraktionsantrag ohne Kommissionsergebnis');
        self::assertSame('fraktion', $sp['gremium']);
        self::assertNotNull($this->finde($antraege, '157', 'Fraktion SP', 30000), 'Fraktion SP: Erhöhung um CHF 30\'000');
    }

    public function testMehrereAntraegeProProduktegruppe(): void {
        $antraege = $this->parse()['antraege'];
        // 510000 Volksschule: fünf Anträge (BSKK-Budgetkorrektur +190'000,
        // BSKK −167'000/−40'000/−155'000, Fraktion SP +120'000).
        $ausSchule = array_values(array_filter($antraege, static fn ($a) => (string) $a['code'] === '510'));
        self::assertCount(5, $ausSchule, 'alle fünf Anträge zur Volksschule');
    }

    public function testNovemberbriefSpalteLeerFuer2026(): void {
        // Das Drehbuch 2026 führt in der Spalte «NB» keine Stadtratskorrekturen —
        // für 2026 gibt es also keine per-Produktegruppe-Novemberbrief-Anpassung.
        $nb = $this->parse()['novemberbrief']['produktegruppen'];
        self::assertSame([], $nb, 'keine NB-Werte im Drehbuch 2026');
    }
}
