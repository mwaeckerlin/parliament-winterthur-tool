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
     * Dieselben Zeilen, aber je Seite, mit ihrer Auszeichnung und mit den
     * einzelnen Textstücken samt x-Position — genau der Form, aus der schon der
     * Drehbuch- und der Budgetbuch-Parser ihre Spalten lesen. Daraus liest der
     * `DokumentInhaltParser` Titel, Überschriften, Absätze, Listen und Tabellen
     * eines amtlichen Dokuments.
     *
     * Die Schriftgrösse steht in zwei Feldern, und beide gehören multipliziert:
     * Das eine PDF trägt sie am Font («Tf 21.96»), das andere in der Textmatrix
     * («Tf 1» mal Skalierung 21.96). Wer nur eines liest, hält jede Überschrift
     * des anderen Dokuments für Lauftext. Kursiv und fett stehen im Namen der
     * Schrift («Arial-ItalicMT», «Arial-BoldMT»).
     *
     * @return list<list<array{y: float, x: float, groesse: float, fett: bool, kursiv: bool, text: string, stuecke: list<array{x: float, text: string}>}>>
     */
    public function seitenZeilen(string $pfad): array {
        $seiten = [];
        foreach ($this->seiten($pfad, true) as $seite) {
            $schriften = [];
            foreach ($seite->getFonts() as $id => $schrift) {
                $schriften[$id] = (string) $schrift->get('BaseFont');
            }
            $nachY = [];
            foreach ($seite->getDataTm() as $r) {
                $m = $r[0];
                // Auf ein Zehntel gerundet steht jede Zeile für sich: feiner
                // unterscheiden sich nur Rundungsfehler derselben Zeile.
                $y = (string) round((float) $m[5], 1);
                $name = $schriften[$r[2] ?? ''] ?? '';
                $skalierung = isset($m[0]) ? abs((float) $m[0]) : 1.0;
                $nachY[$y][] = [
                    'x' => isset($m[4]) ? (float) $m[4] : 0.0,
                    'groesse' => round((float) ($r[3] ?? 0) * ($skalierung > 0 ? $skalierung : 1.0), 2),
                    'fett' => str_contains($name, 'Bold'),
                    'kursiv' => str_contains($name, 'Italic') || str_contains($name, 'Oblique'),
                    't' => (string) $r[1],
                ];
            }
            $ys = array_map('floatval', array_keys($nachY));
            rsort($ys);
            $zeilen = [];
            foreach ($ys as $y) {
                $frags = $nachY[(string) $y];
                usort($frags, static fn ($a, $b) => $a['x'] <=> $b['x']);
                $text = '';
                $stuecke = [];
                foreach ($frags as $f) {
                    $text .= $f['t'];
                    $stueck = trim(preg_replace('/\s+/u', ' ', $f['t']) ?? '');
                    if ($stueck !== '') {
                        $stuecke[] = ['x' => $f['x'], 'text' => $stueck];
                    }
                }
                $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
                if ($text === '') {
                    continue;
                }
                $zeilen[] = [
                    'y' => $y,
                    'x' => $frags[0]['x'],
                    'groesse' => $frags[0]['groesse'],
                    'fett' => $frags[0]['fett'],
                    'kursiv' => $frags[0]['kursiv'],
                    'text' => $text,
                    'stuecke' => $stuecke,
                ];
            }
            $seiten[] = $zeilen;
        }
        return $seiten;
    }

    /**
     * Die Seiten des PDF über smalot/pdfparser.
     *
     * @return array<int, object>
     */
    private function seiten(string $pfad, bool $mitSchrift = false): array {
        $klasse = 'Smalot\\PdfParser\\Parser';
        if (!class_exists($klasse)) {
            throw new \RuntimeException(
                'PDF-Bibliothek smalot/pdfparser nicht verfügbar — im Image via Composer installieren'
            );
        }
        if (!$mitSchrift) {
            /** @var object $parser */
            $parser = new $klasse();
            return $parser->parseFile($pfad)->getPages();
        }
        $config = new \Smalot\PdfParser\Config();
        // Erst damit trägt jedes Textstück seine Schrift und ihre Grösse —
        // ohne sie sind Titel, Überschrift und Lauftext nicht zu unterscheiden.
        $config->setDataTmFontInfoHasToBeIncluded(true);
        /** @var object $parser */
        $parser = new $klasse([], $config);
        return $parser->parseFile($pfad)->getPages();
    }
}
