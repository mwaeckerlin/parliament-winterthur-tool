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

    /**
     * Gibt den Wortlaut als reinen Text zurück — für eine Zelle einer Tabelle,
     * die keine Formatierung trägt (F138). Absätze, Listenpunkte und Zeilen
     * bleiben als Zeilenumbruch erhalten, die Auszeichnung fällt weg.
     */
    public static function alsKlartext(string $text): string
    {
        $html = self::alsHtml($text);
        if ($html === '') {
            return '';
        }
        // Jedes Element, das am Bildschirm eine Zeile beginnt, wird hier zu
        // einem Umbruch; ohne das klebt der nächste Absatz an das letzte Wort.
        $mitUmbruch = (string) preg_replace('#</(p|div|li|h[1-6]|blockquote|tr)>#i', "\n", $html);
        $mitUmbruch = (string) preg_replace('#<br\s*/?>#i', "\n", $mitUmbruch);
        $mitUmbruch = (string) preg_replace('#<li[^>]*>#i', '• ', $mitUmbruch);
        $klartext = html_entity_decode(strip_tags($mitUmbruch), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $klartext = (string) preg_replace('/[ \t]+/u', ' ', $klartext);
        // In einer Zelle steht je Absatz und je Listenpunkt eine Zeile, ohne
        // Leerzeile dazwischen: Das Markdown trägt den Umbruch schon zwischen
        // den Listenpunkten, und mit dem Umbruch aus «</li>» standen zwei
        // Zeilen zwischen «erstens» und «zweitens» (gemessen am 24.10.2026).
        $klartext = (string) preg_replace('/ *\n[\s\n]*/u', "\n", $klartext);
        return trim($klartext);
    }
}
