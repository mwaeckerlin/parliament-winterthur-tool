<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Die Druckansicht der Budgetanträge zeigt zu jeder Produktegruppe ihre Seite
 * im Budgetbuch (F129), nennt jeden Bereich mit seinem Namen und druckt einen
 * Antrag auf das Bewilligungsorgan als Wert statt als Betrag (F135 bis F137).
 * Ohne das stand dort «bewilligungsorgan» und daneben eine Null.
 */
final class BudgetAntraegePdfTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $eintraege */
    private function rendere(array $eintraege): string
    {
        $_ = [
            'jahr' => 2027,
            'kommission' => null,
            'fraktion' => 'Unsere Fraktion',
            'eintraege' => $eintraege,
        ];

        ob_start();
        include dirname(__DIR__, 2) . '/templates/budget_antraege_pdf.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $antrag
     * @return array<string, mixed>
     */
    private function eintrag(array $antrag, string $gruppe = 'Immobilien', int $seite = 0): array
    {
        return [
            'departement' => 'Finanzen',
            'produktegruppe' => $gruppe,
            'buchseite' => $seite,
            'antrag' => array_merge([
                'bereich' => 'globalbudget',
                'betragDelta' => 0,
                'stellenDelta' => 0,
                'antragsteller' => 'Unsere Fraktion',
                'begruendung' => '',
                'neuerWert' => '',
                'zielAenderungen' => [],
                'aufteilung' => [],
            ], $antrag),
        ];
    }

    public function testDieSeiteImBuchStehtAufDemPapier(): void
    {
        $html = $this->rendere([$this->eintrag(['betragDelta' => -1000], 'Immobilien', 240)]);

        self::assertStringContainsString('Buch S. 240', $html, 'die Seite im Budgetbuch fehlt im Ausdruck');
    }

    public function testOhneSeitenangabeStehtKeineSeite(): void
    {
        $html = $this->rendere([$this->eintrag(['betragDelta' => -1000], 'Immobilien', 0)]);

        self::assertStringNotContainsString('Buch S.', $html, 'ohne Seitenangabe steht eine leere Seitenzeile da');
    }

    public function testDieNeuenBereicheHabenEinenNamen(): void
    {
        $html = $this->rendere([
            $this->eintrag(['bereich' => 'departementssekretariat', 'betragDelta' => -5000], 'Departementssekretariat'),
            $this->eintrag(['bereich' => 'verpflichtungskredit', 'betragDelta' => -263000], 'Schulhaus · Kredit 3000.1'),
        ]);

        self::assertStringContainsString('Sekretariat', $html, 'der Antrag aufs Departementssekretariat trägt keinen Namen (F136)');
        self::assertStringContainsString('Verpflichtungskredit', $html, 'der Antrag auf einen Verpflichtungskredit trägt keinen Namen (F135)');
        self::assertStringNotContainsString('departementssekretariat', $html, 'der interne Schlüssel steht im Ausdruck');
        self::assertStringNotContainsString('verpflichtungskredit', $html, 'der interne Schlüssel steht im Ausdruck');
    }

    public function testDasBewilligungsorganStehtStattEinesBetrags(): void
    {
        $html = $this->rendere([
            $this->eintrag(
                ['bereich' => 'bewilligungsorgan', 'neuerWert' => '#', 'betragDelta' => 0],
                'Schulhaus · Kredit 3000.1',
            ),
        ]);

        self::assertStringContainsString('→ #', $html, 'das neue Bewilligungsorgan fehlt im Ausdruck (F137)');
        self::assertStringContainsString('Bewilligungsorgan', $html);
    }
}
