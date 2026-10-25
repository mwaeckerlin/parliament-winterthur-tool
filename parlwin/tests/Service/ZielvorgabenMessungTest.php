<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Messung, kein Nachweis: Zeigt die Zielvorgaben der Produktegruppe Immobilien
 * (240) im Buch 2027, so wie der Parser sie liefert.
 *
 * Marc am 24.10.2026: Drei Vorgaben stehen doppelt da, je mit Soll 1.00 und mit
 * 2.31, 2.98 und 2.38. Diese Messung zeigt, was das Buch an dieser Stelle
 * schreibt und welchen Teil davon die Anzeige heute weglässt.
 */
#[Group('messung')]
final class ZielvorgabenMessungTest extends TestCase {
    public function testZielvorgabenDerImmobilien2027(): void {
        $this->zeige('240');
    }

    /**
     * Stadtentwicklung (142) führt «Anteil Ressourcen in Projekten (in %)»
     * unter den Zielen 1, 2 und 3. Der Nachweis über alle Produktegruppen
     * meldet dort zwei Vorgaben mit derselben Zielnummer 1.
     */
    public function testZielvorgabenDerStadtentwicklung2027(): void {
        $this->zeige('142');
    }

    /**
     * Städtische Museen (158) führt «Prozentsatz der auf alle Fragen mit
     * zufrieden oder sehr zufrieden Antwortenden» zweimal unter Ziel 2.
     */
    public function testZielvorgabenDerMuseen2027(): void {
        $this->zeige('158');
    }

    /** Produktegruppe 350 führt unter Ziel 2 zweimal die Messgrösse «Anzahl». */
    public function testZielvorgabenDerPg350_2027(): void {
        $this->zeige('350');
    }

    /**
     * Schutz und Intervention (470) führt im Nachweis über alle Jahrgänge zwei
     * Vorgaben ohne Messgrösse, in den Büchern 2025, 2026 und 2027. Das Buch
     * gliedert dort mit dreistelligen Nummern («2.2.1») und bricht den Namen
     * vor der Einheit um («… Festangestellte SIW in» / «Stelleneinheiten 65 70
     * …»).
     */
    public function testZielvorgabenDerPg470_2027(): void {
        $this->zeige('470');
    }

    /**
     * Welche Kostenzeilen das Buch je Produktegruppe führt, mit ihrer
     * Häufigkeit. Grundlage für die Einstufung in gebundene und beeinflussbare
     * Kosten (Auftrag von Marc am 24.10.2026).
     */
    public function testKostenzeilenDesBuchs2027(): void {
        $parser = new BudgetBuchParser();
        $buch = \dirname(__DIR__, 1) . '/Fixtures/budget/2027/teil-b.pdf';
        $struktur = $parser->struktur($buch, null, 2027);
        $haeufig = [];
        foreach ($struktur['produktegruppen'] as $g) {
            foreach ($g['kostenzeilen'] as $k) {
                $label = (string) $k['label'];
                $haeufig[$label] = ($haeufig[$label] ?? 0) + 1;
            }
        }
        arsort($haeufig);
        foreach ($haeufig as $label => $anzahl) {
            fwrite(STDERR, sprintf("[kz] %3d  %s\n", $anzahl, $label));
        }
        self::assertNotEmpty($haeufig);
    }

    /**
     * Die Zeilen, die der Leser für den Zielvorgaben-Block sieht — die Grundlage
     * jeder Entscheidung über Überschrift, Label und Wertzeile.
     */
    public function testZeilenDesBlocks470_2027(): void {
        $parser = new BudgetBuchParser();
        $buch = \dirname(__DIR__, 1) . '/Fixtures/budget/2027/teil-b.pdf';
        $text = (new \ReflectionMethod(BudgetBuchParser::class, 'text'))->invoke($parser, $buch);
        $zeilen = (new \ReflectionMethod(BudgetBuchParser::class, 'zeilen'))->invoke($parser, $text);
        $im470 = false;
        foreach ($zeilen as $zeile) {
            // Die Zeile des Inhaltsverzeichnisses trägt Füllpunkte und ist nicht
            // der Anfang des Abschnitts.
            if (str_contains($zeile, '(470)') && str_contains($zeile, 'Parlamentarische Zielvorgaben')) {
                $im470 = true;
            }
            if (preg_match('/^Schutz und Intervention Winterthur \(470\)\s*$/u', trim($zeile)) === 1) {
                $im470 = true;
            }
            if (!$im470) {
                continue;
            }
            if (str_starts_with($zeile, 'Globalkredit') || str_contains($zeile, 'Informationsteil')) {
                break;
            }
            fwrite(STDERR, '[zeile] ' . $zeile . "\n");
        }
        self::assertTrue($im470, 'die Produktegruppe 470 steht nicht im Buch 2027');
    }

    private function zeige(string $code): void {
        $parser = new BudgetBuchParser();
        $buch = \dirname(__DIR__, 1) . '/Fixtures/budget/2027/teil-b.pdf';
        $struktur = $parser->struktur($buch, null, 2027);
        $pg = null;
        foreach ($struktur['produktegruppen'] as $g) {
            if ((string) $g['code'] === $code) {
                $pg = $g;
            }
        }
        self::assertNotNull($pg, "Produktegruppe $code fehlt im Buch 2027");
        foreach ($pg['zielvorgaben'] as $i => $zv) {
            fwrite(STDERR, sprintf(
                "[zv] %2d  Ziel %s | Titel «%s» | Unterabschnitt «%s» | Messgrösse «%s» | Soll %s | Werte %s\n",
                $i,
                (string) $zv['zielNummer'],
                (string) $zv['zielTitel'],
                (string) ($zv['unterTitel'] ?? ''),
                (string) $zv['messgroesse'],
                (string) $zv['soll'],
                implode(' / ', array_map('strval', $zv['werte']))
            ));
        }
        self::assertNotEmpty($pg['zielvorgaben']);
    }
}
