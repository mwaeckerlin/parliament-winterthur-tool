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

    /**
     * Ein Antrag auf Zielwerte hat keinen Betrag, also steht der geänderte
     * Zielwert dort, wo sonst der Betrag steht, und keine Null daneben.
     */
    public function testDerGeaenderteZielwertStehtStattEinesBetrags(): void
    {
        $html = $this->rendere([
            $this->eintrag([
                'betragDelta' => 0,
                'begruendung' => 'Die bisherigen Zielwerte liegen unter den erzielten Nettorenditen.',
                'zielAenderungen' => [
                    [
                        'zielNummer' => 1,
                        'unterTitel' => 'Minimale Nettorendite',
                        'messgroesse' => 'Wohnliegenschaften (in %)',
                        'soll' => '1.00',
                        'neuerWert' => '4',
                    ],
                ],
            ], 'Immobilien', 74),
        ]);

        $spalte = $this->betragsSpalte($html);
        // Der Betrag 0 druckt sich als «0», darum zählt jede Zeile der Spalte,
        // die nur aus dieser Ziffer besteht.
        $zeilen = self::zeilen($spalte);
        self::assertNotContains('0', $zeilen, 'neben dem Zielwert steht ein Betrag von null: ' . implode(' | ', $zeilen));
        self::assertStringContainsString('Minimale Nettorendite', $spalte, 'der Zielwert fehlt in der Betragsspalte');
        self::assertStringContainsString('1.00 → 4', $spalte, 'der heutige und der neue Soll fehlen in der Betragsspalte');
        self::assertStringContainsString(
            'Die bisherigen Zielwerte liegen unter den erzielten Nettorenditen.',
            $html,
            'die Begründung selbst fehlt',
        );
    }

    /**
     * Trägt ein Antrag einen Betrag und Zielwerte, steht der Betrag in seiner
     * Spalte, und die Zielwerte stehen in der Begründung.
     */
    public function testMitBetragStehenDieZielwerteInDerBegruendung(): void
    {
        $html = $this->rendere([
            $this->eintrag([
                'betragDelta' => -20000,
                'begruendung' => 'Die Liegenschaften erzielen mehr, als das Budget annimmt.',
                'zielAenderungen' => [
                    [
                        'zielNummer' => 1,
                        'unterTitel' => 'Minimale Nettorendite',
                        'messgroesse' => 'Wohnliegenschaften (in %)',
                        'soll' => '1.00',
                        'neuerWert' => '4',
                    ],
                ],
            ], 'Immobilien', 74),
        ]);

        self::assertSame(['−20\'000'], self::zeilen($this->betragsSpalte($html)), 'in der Betragsspalte steht nicht genau der Betrag');
        $begruendung = self::text($this->begruendung($html));
        self::assertStringContainsString('Die Liegenschaften erzielen mehr, als das Budget annimmt.', $begruendung, 'die Begründung selbst fehlt');
        self::assertStringContainsString(
            'Zielvorgaben: Ziel 1 «Minimale Nettorendite — Wohnliegenschaften (in %)» → Soll 1.00 → 4;',
            $begruendung,
            'die Zielwerte fehlen in der Begründung',
        );
    }

    /** Der Inhalt der ersten Betragsspalte der ersten Antragszeile. */
    private function betragsSpalte(string $html): string
    {
        self::assertSame(
            1,
            preg_match('#<td class="betrag">(.*?)</td>#s', $html, $treffer),
            'der Ausdruck hat keine Betragsspalte',
        );
        return $treffer[1];
    }

    /** Der Inhalt der Spalte «Begründung» der ersten Antragszeile, der sechsten Zelle. */
    private function begruendung(string $html): string
    {
        preg_match_all('#<td(?: class="[^"]*")?>(.*?)</td>#s', $html, $zellen);
        self::assertGreaterThanOrEqual(6, count($zellen[1]), 'der Ausdruck hat keine Spalte «Begründung»');
        return $zellen[1][5];
    }

    /** Der Text einer Zelle ohne Auszeichnung und mit aufgelösten Zeichen. */
    private static function text(string $zelle): string
    {
        return trim(html_entity_decode(strip_tags($zelle), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Die Zeilen einer Zelle, getrennt an ihren Umbrüchen.
     *
     * @return list<string>
     */
    private static function zeilen(string $zelle): array
    {
        return array_map(self::text(...), preg_split('#<br\s*/?>#', $zelle) ?: []);
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
