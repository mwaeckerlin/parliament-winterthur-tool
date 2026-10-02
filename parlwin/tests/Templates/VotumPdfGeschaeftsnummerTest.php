<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Die Druckansicht des Votums nennt die Geschäftsnummer, wie sie das Parlament
 * führt: «2025.82». Die Nummer der Parlamentswebseite («2504299») ist eine
 * interne Kennung, die auf keinem Papier und auf keiner Oberfläche steht —
 * gemeldet am 28.09.2026, als im Ausdruck «Geschäftsnummer 2504299» stand.
 */
final class VotumPdfGeschaeftsnummerTest extends TestCase
{
    /** Rendert die echte Druckansicht mit Nummer und interner Kennung. */
    private function rendere(string $nummer, string $externId): string
    {
        $_ = [
            'id' => 42,
            'nummer' => $nummer,
            'externId' => $externId,
            'titel' => 'Winterthur als Migrationsstadt',
            'status' => 'Pendent',
            'aktuellesVotum' => [
                'text' => '<p>Wortlaut</p>',
                'erstelltAm' => '2026-08-24 15:41',
                'autorName' => 'Marc Wäckerlin',
            ],
            'zustaendigkeiten' => [['personName' => 'Marc Wäckerlin', 'istHaupt' => true]],
            'letzterBeschluss' => null,
        ];

        ob_start();
        include dirname(__DIR__, 2) . '/templates/votum_pdf.php';

        return (string) ob_get_clean();
    }

    public function testDieGeschaeftsnummerIstDieDesParlaments(): void
    {
        $html = $this->rendere('2025.82', '2504299');

        self::assertStringContainsString('2025.82', $html, 'Die Geschäftsnummer des Parlaments fehlt');
        self::assertStringNotContainsString(
            '2504299',
            $html,
            'Die interne Kennung der Parlamentswebseite steht im Ausdruck',
        );
    }

    /**
     * Ein selbst angelegtes Geschäft hat keine Nummer des Parlaments. Dann
     * bleibt die Zeile weg, statt eine Kennung zu zeigen, die niemand kennt.
     */
    public function testOhneNummerFehltDieZeile(): void
    {
        $html = $this->rendere('', 'eigen:17');

        self::assertStringNotContainsString('Geschäftsnummer', $html, 'Die leere Zeile steht trotzdem da');
        self::assertStringNotContainsString('eigen:17', $html, 'Die interne Kennung steht im Ausdruck');
    }
}
