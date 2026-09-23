<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\RichText;
use PHPUnit\Framework\TestCase;

/**
 * Der Wortlaut eines Votums wird im Editor als Markdown gespeichert — dieselbe
 * Schreibweise wie bei den Notizen. Das PDF gab ihn bisher unverändert als HTML
 * aus: «**fett**» stand wörtlich da, und Leerzeilen wurden zu nichts, weil HTML
 * sie nicht als Absatz liest. Beides hat Marc am Votum im Rat gesehen.
 */
class VotumPdfMarkdownTest extends TestCase
{
    public function testFettschriftWirdAusgezeichnet(): void
    {
        $html = RichText::alsHtml('Das ist **wichtig** für uns.');

        self::assertStringContainsString('<strong>wichtig</strong>', $html, 'Fettschrift fehlt im PDF');
        self::assertStringNotContainsString('**', $html, 'die Markdown-Zeichen stehen noch im Text');
    }

    public function testAbsaetzeBleibenAbsaetze(): void
    {
        $html = RichText::alsHtml("Erster Absatz.\n\nZweiter Absatz.");

        self::assertSame(2, substr_count($html, '<p>'), 'aus zwei Absätzen wurde nicht zweimal <p>');
        self::assertStringContainsString('Erster Absatz.', $html);
        self::assertStringContainsString('Zweiter Absatz.', $html);
    }

    public function testKursivUndListenKommenDurch(): void
    {
        $html = RichText::alsHtml("*betont*\n\n- eins\n- zwei");

        self::assertStringContainsString('<em>betont</em>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertSame(2, substr_count($html, '<li>'), 'die Liste hat nicht zwei Einträge');
    }

    public function testUeberschriftenWerdenUeberschriften(): void
    {
        $html = RichText::alsHtml("## Antrag\n\nText dazu.");

        self::assertStringContainsString('<h2>Antrag</h2>', $html);
    }

    /**
     * Bestandsdaten sind bereits HTML (vor der Umstellung auf Markdown
     * gespeichert). Sie dürfen nicht doppelt gewandelt werden, sonst stünden
     * die Auszeichnungen als Text da.
     */
    public function testVorhandenesHtmlBleibtHtml(): void
    {
        $html = RichText::alsHtml('<p>Das ist <strong>wichtig</strong>.</p>');

        self::assertStringContainsString('<strong>wichtig</strong>', $html);
        self::assertStringNotContainsString('&lt;strong&gt;', $html, 'das HTML wurde als Text ausgegeben');
    }

    public function testAusfuehrbaresBleibtDraussen(): void
    {
        $html = RichText::alsHtml("Text\n\n<script>alert(1)</script>");

        self::assertStringNotContainsString('<script', $html, 'ein Skript hat es ins PDF geschafft');
        self::assertStringNotContainsString('alert(1)', $html);
    }

    public function testLeererTextGibtLeerenString(): void
    {
        self::assertSame('', RichText::alsHtml(''));
        self::assertSame('', RichText::alsHtml("   \n  "));
    }

    /**
     * Die Vorlage des PDF muss den Wandler auch WIRKLICH benutzen. Ein Test nur
     * gegen RichText bliebe grün, während das PDF den Text weiterhin roh ausgibt
     * — genau der Zustand, den Marc gesehen hat.
     */
    public function testDieVorlageWandeltDenWortlaut(): void
    {
        $vorlage = __DIR__ . '/../../templates/votum_pdf.php';
        self::assertFileExists($vorlage);

        $quelle = (string) file_get_contents($vorlage);
        self::assertStringContainsString(
            'RichText::alsHtml($votumText)',
            $quelle,
            'die PDF-Vorlage wandelt den Wortlaut nicht (Markdown bliebe roh stehen)'
        );
        self::assertStringNotContainsString(
            'HtmlSanitizer::sauber($votumText)',
            $quelle,
            'die Vorlage gibt den Wortlaut noch ungewandelt aus'
        );
    }
}
