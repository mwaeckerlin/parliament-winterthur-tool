<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Wandelt den gespeicherten Wortlaut in HTML für die serverseitige Ausgabe —
 * das PDF eines Votums etwa.
 *
 * Der Editor der Anwendung speichert Markdown (dieselbe Schreibweise wie bei
 * den Notizen). Wer diesen Text unverändert als HTML ausgibt, druckt «**fett**»
 * wörtlich und verliert jeden Absatz, weil HTML eine Leerzeile nicht als solchen
 * liest. Darum wird hier zuerst gewandelt und erst danach bereinigt.
 *
 * Bestandsdaten aus der Zeit vor der Umstellung sind bereits HTML. CommonMark
 * lässt eingebettetes HTML stehen (html: true, wie im Editor), sodass beide
 * Fälle denselben Weg nehmen und nichts doppelt gewandelt wird.
 *
 * Bereinigt wird IMMER: Der Text stammt zwar aus dem Editor, wird aber über die
 * Schnittstelle gespeichert und ist damit frei wählbar (siehe HtmlSanitizer).
 */
final class RichText
{
    /**
     * Gibt den Wortlaut als bereinigtes HTML zurück. Leerer Text ergibt einen
     * Leerstring.
     */
    public static function alsHtml(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $umgebung = new Environment([
            // Eingebettetes HTML bleibt stehen — der Editor erlaubt es ebenso,
            // und Bestandsdaten bestehen ganz daraus. Gefährliches entfernt
            // anschliessend der HtmlSanitizer.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $umgebung->addExtension(new CommonMarkCoreExtension());
        // Eine hingeschriebene Adresse wird im PDF zum Verweis, wie im Editor.
        $umgebung->addExtension(new AutolinkExtension());

        $html = (string) (new MarkdownConverter($umgebung))->convert($text);

        return HtmlSanitizer::sauber($html);
    }
}
