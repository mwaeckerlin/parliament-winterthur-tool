<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Liest ein PDF als Zeilen von Textfragmenten mit ihren x-Positionen.
 *
 * Die Beilagen des Parlaments sind ungetaggte PDF: Im reinen Textstrom laufen
 * die Spalten ineinander, und erst die Positionsdaten von smalot/pdfparser
 * (`Page::getDataTm()`) trennen sie wieder. Diese Klasse liefert deshalb je
 * Seite von oben nach unten die Zeilen, je Zeile die Fragmente von links nach
 * rechts — die Grundlage für die Tabellen des Drehbuchs und des Novemberbriefs.
 */
class PdfZeilenLeser {
    /**
     * @return list<list<array{x: float, t: string}>>
     */
    public function zeilen(string $pfad): array {
        $out = [];
        foreach ($this->seiten($pfad) as $seite) {
            $nachY = [];
            foreach ($seite->getDataTm() as $r) {
                $m = $r[0];
                $x = isset($m[4]) ? (float) $m[4] : 0.0;
                $y = isset($m[5]) ? (float) $m[5] : 0.0;
                $schluessel = (int) round($y / 4);
                $nachY[$schluessel][] = ['x' => $x, 't' => (string) $r[1]];
            }
            krsort($nachY);
            foreach ($nachY as $frags) {
                usort($frags, static fn ($a, $b) => $a['x'] <=> $b['x']);
                $out[] = array_values($frags);
            }
        }
        return $out;
    }

    /**
     * Die Seiten des PDF über smalot/pdfparser.
     *
     * @return array<int, object>
     */
    private function seiten(string $pfad): array {
        $klasse = 'Smalot\\PdfParser\\Parser';
        if (!class_exists($klasse)) {
            throw new \RuntimeException(
                'PDF-Bibliothek smalot/pdfparser nicht verfügbar — im Image via Composer installieren'
            );
        }
        /** @var object $parser */
        $parser = new $klasse();
        return $parser->parseFile($pfad)->getPages();
    }
}
