<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetNovemberbriefParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Der Novemberbrief jedes Jahrgangs, in dem der Stadtrat einen nachgereicht hat
 * (2017 bis 2022), gelesen aus der echten Beilage des Budget-Geschäfts (F91).
 *
 * Die Prüfwerte stammen aus dem Dokument selbst: Die Beilage «Übersicht der
 * Positionen im Novemberbrief …» rechnet die Korrekturen je Produktegruppe vor
 * und nennt in der Zeile «Stadt Winterthur» ihre Summe. Genau daran wird das
 * Lesen gemessen — Σ der Produktegruppen muss diese Summe ergeben.
 *
 * Gruppe «pdf»: braucht vendor/ (smalot), läuft über `npm run test:pdf`.
 */
#[Group('pdf')]
class BudgetNovemberbriefParserTest extends TestCase {
    private function pfad(int $jahr): string {
        return \dirname(__DIR__, 1) . '/Fixtures/novemberbrief/' . $jahr . '/novemberbrief.pdf';
    }

    /** @return array{produktegruppen: list<array<string, mixed>>, total: int} */
    private function lies(int $jahr): array {
        return (new BudgetNovemberbriefParser())->parse($this->pfad($jahr));
    }

    /**
     * Die Jahrgänge mit Novemberbrief. 2021 fehlt: In jenem Jahr wies das
     * Parlament das Budget zurück, und der Stadtrat reichte einen zweiten
     * Budgetantrag statt eines Nachtrags ein.
     *
     * @return list<array{int}>
     */
    public static function jahrgaenge(): array {
        return [[2017], [2018], [2019], [2020], [2022]];
    }

    /**
     * Jeder Novemberbrief führt Korrekturen für mehrere Produktegruppen, jede mit
     * Namen und mindestens einer Zahl.
     */
    #[DataProvider('jahrgaenge')]
    public function testJederNovemberbriefFuehrtKorrekturenJeProduktegruppe(int $jahr): void {
        $gelesen = $this->lies($jahr);
        $gruppen = $gelesen['produktegruppen'];
        self::assertGreaterThan(2, \count($gruppen), "Produktegruppen im Novemberbrief $jahr");

        foreach ($gruppen as $g) {
            self::assertNotSame('', trim((string) $g['name']), "Produktegruppe ohne Namen ($jahr)");
            self::assertNotSame(
                [0, 0, 0],
                [(int) $g['aufwandNb'], (int) $g['ertragNb'], (int) $g['nettokostenNb']],
                "Produktegruppe {$g['name']} ohne jede Korrektur ($jahr)"
            );
        }
    }

    /**
     * Die Probe des Dokuments: Die Nettokosten-Korrekturen aller Produktegruppen
     * ergeben zusammen die Korrektur der Zeile «Stadt Winterthur». Weicht die
     * Summe ab, fehlt eine Produktegruppe oder eine Zahl steht in der falschen
     * Spalte.
     *
     * Die Korrektur der Stadt kann null sein: Der Novemberbrief 2020 verschiebt
     * Beträge zwischen den Departementen, ohne das Ergebnis der Stadt zu ändern
     * («0 Ergebnis –»).
     */
    #[DataProvider('jahrgaenge')]
    public function testSummeDerKorrekturenErgibtDasTotalDerStadt(int $jahr): void {
        $gelesen = $this->lies($jahr);
        self::assertNotNull($gelesen['total'], "Novemberbrief $jahr ohne Zeile «Stadt Winterthur»");
        $summe = 0;
        foreach ($gelesen['produktegruppen'] as $g) {
            $summe += (int) $g['nettokostenNb'];
        }
        self::assertSame(
            (int) $gelesen['total'],
            $summe,
            sprintf('Novemberbrief %d: Σ der Produktegruppen (%d) ≠ Total Stadt Winterthur (%d)', $jahr, $summe, $gelesen['total'])
        );
    }

    /**
     * Novemberbrief 2019, im Dokument nachgeschlagen (Beilage «Übersicht der
     * Positionen im Novemberbrief 2019 der Stadt Winterthur», Seiten 4 bis 6):
     *
     *   Stadt Winterthur              -2'778'734  (Zeile «0 Ergebnis»)
     *   Städtische Kultureinrichtungen   100'000
     *   Steuerbezug                       -60'000
     *   Steuern und Finanzausgleich   -5'249'328
     *   Stadtgrün                        170'000
     *
     * Die Totalzeile ist fett gesetzt, und das PDF zerreisst ihre Zahl («-2'778'»
     * + «734»); ohne das Zusammensetzen stand die Korrektur der Stadt auf null.
     */
    public function testNovemberbrief2019MitDenWertenAusDemDokument(): void {
        $gelesen = $this->lies(2019);
        self::assertSame(-2_778_734, (int) $gelesen['total'], 'Korrektur der Stadt im Novemberbrief 2019');

        $nach = [];
        foreach ($gelesen['produktegruppen'] as $g) {
            $nach[(string) $g['name']] = (int) $g['nettokostenNb'];
        }
        self::assertSame(100_000, $nach['Städtische Kultureinrichtungen'] ?? null, 'Städtische Kultureinrichtungen');
        self::assertSame(-60_000, $nach['Steuerbezug'] ?? null, 'Steuerbezug');
        self::assertSame(-5_249_328, $nach['Steuern und Finanzausgleich'] ?? null, 'Steuern und Finanzausgleich');
        self::assertSame(170_000, $nach['Stadtgrün'] ?? null, 'Stadtgrün');
    }

    /**
     * Jede Korrektur ist in sich stimmig: Aufwand − Ertrag ergibt die
     * Nettokosten. So steht es in jeder der drei Zeilen einer Produktegruppe.
     */
    #[DataProvider('jahrgaenge')]
    public function testAufwandMinusErtragErgibtDieNettokosten(int $jahr): void {
        $abweichungen = [];
        foreach ($this->lies($jahr)['produktegruppen'] as $g) {
            $gerechnet = (int) $g['aufwandNb'] - (int) $g['ertragNb'];
            if ($gerechnet !== (int) $g['nettokostenNb']) {
                $abweichungen[] = sprintf(
                    '%s: %d − %d = %d, gelesen %d',
                    $g['name'],
                    $g['aufwandNb'],
                    $g['ertragNb'],
                    $gerechnet,
                    $g['nettokostenNb']
                );
            }
        }
        self::assertSame([], $abweichungen, "Novemberbrief $jahr: Aufwand − Ertrag ≠ Nettokosten");
    }

    /**
     * Novemberbrief 2022, Zeile für Zeile im Dokument nachgeschlagen (Beilage
     * «Übersicht der Positionen im Novemberbrief 2022 der Stadt Winterthur»,
     * Seite 4 von 36):
     *
     *   Städtische Allgemeinkosten / Erlöse   528'000 | 272'346 | 800'346
     *   Tiefbau                                     – | -272'346 | -272'346
     *   Baupolizei                             60'205 | -67'500 |   -7'295
     *   Stadtpolizei                                – | 500'000 |  500'000
     *
     * Der Ertrag steht im Dokument negativ; die App führt ihn positiv, deshalb
     * ist sein Vorzeichen hier gedreht.
     */
    public function testNovemberbrief2022MitDenWertenAusDemDokument(): void {
        $nach = [];
        foreach ($this->lies(2022)['produktegruppen'] as $g) {
            $nach[(string) $g['name']] = $g;
        }

        self::assertArrayHasKey('Städtische Allgemeinkosten / Erlöse', $nach);
        $allgemein = $nach['Städtische Allgemeinkosten / Erlöse'];
        self::assertSame(528_000, (int) $allgemein['aufwandNb'], 'Aufwand-Korrektur Städtische Allgemeinkosten');
        self::assertSame(-272_346, (int) $allgemein['ertragNb'], 'Ertrag-Korrektur Städtische Allgemeinkosten');
        self::assertSame(800_346, (int) $allgemein['nettokostenNb'], 'Nettokosten-Korrektur Städtische Allgemeinkosten');

        self::assertArrayHasKey('Tiefbau', $nach);
        self::assertSame(-272_346, (int) $nach['Tiefbau']['nettokostenNb'], 'Nettokosten-Korrektur Tiefbau');

        self::assertArrayHasKey('Baupolizei', $nach);
        self::assertSame(-7_295, (int) $nach['Baupolizei']['nettokostenNb'], 'Nettokosten-Korrektur Baupolizei');

        self::assertArrayHasKey('Stadtpolizei', $nach);
        self::assertSame(500_000, (int) $nach['Stadtpolizei']['nettokostenNb'], 'Nettokosten-Korrektur Stadtpolizei');

        self::assertSame(1_180_705, (int) $this->lies(2022)['total'], 'Total der Stadt im Novemberbrief 2022');
    }
}
