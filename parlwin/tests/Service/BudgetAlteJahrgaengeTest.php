<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Die älteren Bücher (bis Budget 2021) sind anders gesetzt als die aktuellen:
 * andere Spaltenreihenfolge, andere Kopfzeilen, Beträge in Franken statt in
 * Millionen. Dieser Test parst das echte Buch 2021 und prüft Werte, die im Buch
 * nachgeschlagen sind.
 *
 * Gruppe «pdf»: braucht vendor/ (smalot), läuft über `npm run test:pdf`.
 */
#[Group('pdf')]
class BudgetAlteJahrgaengeTest extends TestCase {
    private function fixture(string $rel): string {
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $rel;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function gruppen(int $jahr): array {
        $parser = new BudgetBuchParser();
        $struktur = $parser->struktur($this->fixture($jahr . '/teil-b.pdf'), null, $jahr);
        $nachCode = [];
        foreach ($struktur['produktegruppen'] as $g) {
            $nachCode[$g['code']] = $g;
        }
        return $nachCode;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function gruppen2021(): array {
        return $this->gruppen(2021);
    }

    /**
     * Personalamt (121) in den Büchern 2019 und 2020, Zeile für Zeile im Buch
     * nachgeschlagen. Beide Jahrgänge sind anders gesetzt als die aktuellen: die
     * Beträge stehen in Franken, die Spalte des Budgetjahres steht an dritter
     * Stelle, und die Wertzeile beginnt mit einem Leerzeichen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function personalamtJahrgaenge(): array {
        return [
            '2019' => [2019, [
                'gk' => [2867089, 2929179, 3481573, 3566124, 3478124, 3413124],
                'aufwand' => [4677224, 4843215, 5443242],
                'ertrag' => [1810135, 1914037, 1961669],
                'stellen' => [13.6, 14.05, 15.65],
            ]],
            '2020' => [2020, [
                'gk' => [2899372, 3386549, 3376637, 3667927, 3469649, 3396864],
                'aufwand' => [4926973, 5348217, 5478410],
                'ertrag' => [2027602, 1961669, 2101773],
                'stellen' => [13.7, 15.05, 15.55],
            ]],
        ];
    }

    /**
     * @param array<string, list<float>> $erwartet
     */
    #[DataProvider('personalamtJahrgaenge')]
    public function testPersonalamtAusDenAeltestenBuechern(int $jahr, array $erwartet): void {
        $g = $this->gruppen($jahr);
        $this->assertArrayHasKey('121', $g, "Personalamt (121) im Buch $jahr");
        $pa = $g['121'];

        $felder = ['ist', 'sollVorjahr', 'soll', 'plan1', 'plan2', 'plan3'];
        foreach ($erwartet['gk'] as $i => $wert) {
            $this->assertSame((int) $wert, $pa['globalkredit'][$felder[$i]], "Globalkredit {$felder[$i]} $jahr");
        }
        foreach (['ist', 'sollVorjahr', 'soll'] as $i => $feld) {
            $this->assertSame((int) $erwartet['aufwand'][$i], $pa['aufwand'][$feld], "Total effektive Kosten $feld $jahr");
            $this->assertSame((int) $erwartet['ertrag'][$i], $pa['ertrag'][$feld], "Total effektive Erlöse $feld $jahr");
            $this->assertSame($erwartet['stellen'][$i], $pa['stellen'][$feld], "Stelleneinheiten $feld $jahr");
        }

        $this->assertGreaterThan(20, \count($g), "Zu wenige Produktegruppen im Buch $jahr");
    }

    /**
     * Buch 2021, Produktegruppe Tiefbau (322): «Nettokosten / Globalkredit
     * 20'436'009  21'746'984  23'055'078  23'435'078  22'936'078  24'123'078».
     *
     * Im selben Kapitel steht weiter hinten der Satz «Die Nettokosten/Globalkredit
     * steigen gegenüber 2020 um rund 1,31 Mio. Franken … 5 Projektleiterstellen …
     * (0,7 Mio. Franken)». Wird der Begriff mitten im Satz als Wertzeile gelesen,
     * überschreibt der Satz die echten Beträge mit 2020/1/31/5/0/7.
     */
    public function testGlobalkreditTiefbau2021AusDerWertzeile(): void {
        $g = $this->gruppen2021();
        $this->assertArrayHasKey('322', $g, 'Tiefbau (322) nicht gefunden');
        $tiefbau = $g['322'];

        $this->assertSame(20436009, $tiefbau['globalkredit']['ist'], 'Globalkredit Ist 2019');
        $this->assertSame(21746984, $tiefbau['globalkredit']['sollVorjahr'], 'Globalkredit Soll 2020');
        $this->assertSame(23055078, $tiefbau['globalkredit']['soll'], 'Globalkredit Soll 2021');
        $this->assertSame(23435078, $tiefbau['globalkredit']['plan1'], 'Globalkredit Plan 2022');
        $this->assertSame(22936078, $tiefbau['globalkredit']['plan2'], 'Globalkredit Plan 2023');
        $this->assertSame(24123078, $tiefbau['globalkredit']['plan3'], 'Globalkredit Plan 2024');

        $this->assertSame(37114153, $tiefbau['aufwand']['soll'], 'Total effektive Kosten Soll 2021');
        $this->assertSame(14059076, $tiefbau['ertrag']['soll'], 'Total effektive Erlöse Soll 2021');
        $this->assertSame(132.1, $tiefbau['stellen']['soll'], 'Stelleneinheiten Soll 2021');
    }

    /**
     * Ein Globalkredit ist ein Betrag. Kleine Beträge kommen vor (Rechtspflege
     * 62'906, Einkauf und Logistik -1'291), eine Jahreszahl und ein einstelliger
     * Wert nicht. Beides entsteht, wenn eine Fliesstextzeile als Wertzeile gelesen
     * wird — der Satz-im-Fliesstext-Fall fällt genau hier auf.
     */
    public function testKeineJahreszahlenAlsGlobalkredit2021(): void {
        $verdaechtig = [];
        foreach ($this->gruppen2021() as $code => $g) {
            foreach ($g['globalkredit'] as $feld => $wert) {
                $jahreszahl = $wert > 1900 && $wert < 2100;
                $winzig = $wert !== 0 && abs($wert) < 1000;
                if ($jahreszahl || $winzig) {
                    $verdaechtig[] = "$code {$g['name']}: $feld = $wert";
                }
            }
        }
        $this->assertSame([], $verdaechtig, 'Globalkredit-Werte, die kein Betrag sein können');
    }
}
