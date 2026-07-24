<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Bereinigt gespeicherten Rich-Text (z.B. den Wortlaut eines Votums) für die
 * serverseitige Ausgabe als HTML.
 *
 * Gearbeitet wird mit einer Positivliste: erlaubt ist ausschliesslich, was
 * hier aufgezählt ist — alles andere fällt weg. Eine Negativliste («entferne
 * script, entferne on…-Attribute») ist dafür untauglich, weil jede Lücke darin
 * sofort ausführbar wird: ein Schrägstrich statt eines Leerzeichens vor dem
 * Attribut, ein Verweis mit javascript-Schema, ein neu erfundenes Element.
 *
 * Der Text kommt zwar aus dem Editor der Anwendung, wird aber über die
 * Schnittstelle gespeichert und ist damit frei wählbar. Wer ein Votum erfassen
 * darf, darf deshalb trotzdem keinen Code in den Browser einer anderen Person
 * bringen.
 */
final class HtmlSanitizer
{
    /** Elemente, die die Formatierung tragen und darum bleiben dürfen. */
    private const ERLAUBTE_ELEMENTE = [
        'p', 'br', 'hr', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark', 'sub', 'sup', 'small',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'code', 'pre', 'a',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Attribute je Element; alles andere (inklusive sämtlicher Ereignis-Handler) fällt weg. */
    private const ERLAUBTE_ATTRIBUTE = [
        'a' => ['href', 'title'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    /** Nur diese Schemata bleiben in einem Verweis stehen — kein javascript, kein data. */
    private const ERLAUBTE_SCHEMATA = ['http', 'https', 'mailto', 'tel'];

    /** Diese Elemente verschwinden mitsamt ihrem Inhalt (statt nur aufgelöst zu werden). */
    private const INHALT_ENTFERNEN = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'noscript', 'template', 'svg', 'math', 'canvas', 'audio', 'video',
        'form', 'input', 'button', 'select', 'option', 'textarea', 'base', 'link', 'meta', 'title',
    ];

    /**
     * Gibt den Text mit erhaltener Formatierung, aber ohne jedes ausführbare
     * Element zurück. Leerer oder nicht lesbarer Text ergibt einen Leerstring.
     */
    public static function sauber(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dokument = new \DOMDocument();
        // Die Kodierungsangabe ist zwingend: ohne sie liest libxml den Text als
        // Latin-1 und zerstört alle Umlaute. Parse-Meldungen zu unbekannten
        // Elementen interessieren hier nicht — bereinigt wird ohnehin alles.
        $zuvor = libxml_use_internal_errors(true);
        $geladen = $dokument->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>' . $html . '</body>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($zuvor);

        $koerper = $geladen ? $dokument->getElementsByTagName('body')->item(0) : null;
        if ($koerper === null) {
            return '';
        }

        self::bereinigeKinder($koerper);

        $ergebnis = '';
        foreach (iterator_to_array($koerper->childNodes) as $kind) {
            $ergebnis .= (string) $dokument->saveHTML($kind);
        }

        return $ergebnis;
    }

    private static function bereinigeKinder(\DOMNode $eltern): void
    {
        foreach (iterator_to_array($eltern->childNodes) as $kind) {
            if ($kind instanceof \DOMText) {
                continue;
            }

            if (!($kind instanceof \DOMElement)) {
                // Kommentare, Verarbeitungsanweisungen, CDATA: nichts davon
                // gehört in einen Wortlaut.
                $eltern->removeChild($kind);
                continue;
            }

            $element = strtolower($kind->nodeName);

            if (in_array($element, self::INHALT_ENTFERNEN, true)) {
                $eltern->removeChild($kind);
                continue;
            }

            if (!in_array($element, self::ERLAUBTE_ELEMENTE, true)) {
                // Unbekanntes Element: Text behalten, Hülle auflösen.
                self::bereinigeKinder($kind);
                while ($kind->firstChild !== null) {
                    $eltern->insertBefore($kind->firstChild, $kind);
                }
                $eltern->removeChild($kind);
                continue;
            }

            self::bereinigeAttribute($kind, $element);
            self::bereinigeKinder($kind);
        }
    }

    private static function bereinigeAttribute(\DOMElement $element, string $name): void
    {
        $erlaubt = self::ERLAUBTE_ATTRIBUTE[$name] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribut) {
            $attributName = strtolower($attribut->nodeName);

            if (!in_array($attributName, $erlaubt, true)) {
                $element->removeAttribute($attribut->nodeName);
                continue;
            }

            if ($attributName === 'href' && !self::verweisErlaubt((string) $attribut->nodeValue)) {
                $element->removeAttribute($attribut->nodeName);
            }
        }
    }

    /**
     * Ein Verweis bleibt stehen, wenn er relativ ist oder ein harmloses Schema
     * trägt. Steuer- und Leerzeichen werden vor der Prüfung entfernt, weil der
     * Browser sie im Schema ebenfalls ignoriert («java\tscript:» ist javascript:).
     */
    private static function verweisErlaubt(string $verweis): bool
    {
        $normalisiert = (string) preg_replace('/[\x00-\x20]+/', '', $verweis);
        if ($normalisiert === '') {
            return false;
        }

        if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $normalisiert, $treffer) !== 1) {
            return true; // kein Schema ⇒ relativer Verweis oder Sprungmarke
        }

        return in_array(strtolower($treffer[1]), self::ERLAUBTE_SCHEMATA, true);
    }
}
