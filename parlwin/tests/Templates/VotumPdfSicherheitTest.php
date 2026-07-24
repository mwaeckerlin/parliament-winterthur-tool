<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Prüft die AUSGELIEFERTE Druckansicht des Votums (templates/votum_pdf.php).
 *
 * Der Wortlaut eines Votums ist Rich-Text und wird als HTML ausgegeben, damit
 * die Formatierung im Ausdruck erhalten bleibt. Genau darum muss die Ausgabe
 * jedes ausführbare Element verlieren: Wer ein Votum erfassen darf, darf damit
 * keinen Code in den Browser einer anderen Person bringen. Getestet wird die
 * echte Vorlage, nicht eine nachgebaute Hilfsfunktion.
 */
final class VotumPdfSicherheitTest extends TestCase
{
    /** Rendert die echte Druckansicht mit dem übergebenen Votum-Wortlaut. */
    private function rendere(string $votumHtml): string
    {
        $_ = [
            'id' => 42,
            'externId' => '2026.1234',
            'titel' => 'Testgeschäft',
            'status' => 'Pendent',
            'aktuellesVotum' => [
                'text' => $votumHtml,
                'erstelltAm' => '2026-07-24 10:00',
                'autorName' => 'Vera Votum',
            ],
            'zustaendigkeiten' => [['personName' => 'Vera Votum', 'istHaupt' => true]],
            'letzterBeschluss' => null,
        ];

        ob_start();
        include dirname(__DIR__, 2) . '/templates/votum_pdf.php';

        return (string) ob_get_clean();
    }

    public function testEreignisAttributMitSlashAlsTrennerVerschwindet(): void
    {
        // Ein Schrägstrich trennt Attribute genauso wie ein Leerzeichen — der
        // Browser führt den Handler aus, obwohl kein Leerzeichen davor steht.
        $html = $this->rendere('<p>Sicherer Text</p><img/onerror="alert(1)" src=x>');

        $this->assertStringNotContainsStringIgnoringCase(
            'onerror',
            $html,
            'Die Druckansicht gibt einen Ereignis-Handler aus'
        );
        $this->assertStringContainsString('Sicherer Text', $html, 'Der Wortlaut fehlt in der Druckansicht');
    }

    public function testEreignisAttributeUndSkriptVerschwinden(): void
    {
        $html = $this->rendere(
            '<script>window.__x=1</script><svg onload="alert(1)"></svg><p onmouseover=alert(1)>Wortlaut</p>'
        );

        $this->assertStringNotContainsString('window.__x', $html, 'Skript-Inhalt landet in der Druckansicht');
        $this->assertStringNotContainsStringIgnoringCase('onload', $html, 'onload-Handler landet in der Druckansicht');
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $html, 'onmouseover-Handler landet in der Druckansicht');
        $this->assertStringContainsString('Wortlaut', $html, 'Der Wortlaut fehlt in der Druckansicht');
    }

    public function testJavascriptVerweisVerschwindet(): void
    {
        $html = $this->rendere('<a href="javascript:alert(1)">Klick</a>');

        $this->assertStringNotContainsStringIgnoringCase(
            'javascript:',
            $html,
            'Die Druckansicht gibt einen ausführbaren Verweis aus'
        );
        $this->assertStringContainsString('Klick', $html, 'Der Verweis-Text ging verloren');
    }

    public function testFormatierungUndUmlauteBleibenErhalten(): void
    {
        $html = $this->rendere(
            '<p><strong>Grüezi</strong> und <em>Prüfung</em></p>'
            . '<ul><li>Erster Punkt</li></ul>'
            . '<a href="https://example.org">Quelle</a>'
        );

        $this->assertStringContainsString('<strong>Grüezi</strong>', $html, 'Fettschrift oder Umlaut ging verloren');
        $this->assertStringContainsString('<em>Prüfung</em>', $html, 'Kursivschrift oder Umlaut ging verloren');
        $this->assertStringContainsString('<li>Erster Punkt</li>', $html, 'Aufzählung ging verloren');
        $this->assertStringContainsString('href="https://example.org"', $html, 'Zulässiger Verweis ging verloren');
    }

    public function testLeeresVotumZeigtHinweis(): void
    {
        $html = $this->rendere('');

        $this->assertStringContainsString('Noch kein Votum erfasst', $html, 'Hinweis auf ein leeres Votum fehlt');
    }
}
