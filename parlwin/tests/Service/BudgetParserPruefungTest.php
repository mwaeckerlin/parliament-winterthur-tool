<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * WERKZEUG, kein Regressionstest (Gruppe «pruefung», läuft über
 * `npm run test:pruefung` und ist in keinem Testlauf enthalten).
 *
 * Parst ein Budgetbuch über den echten Live-Pfad und legt ALLES Geparste ab:
 * `<ziel>/<jahr>.json` maschinenlesbar und `<jahr>-bericht.txt` zum Lesen, mit
 * einer Liste der Auffälligkeiten (Produktegruppe ohne Produkte, Produkt ohne
 * Kostentabelle, Produktsumme weit weg vom Globalkredit, verdächtige Namen).
 *
 * Zweck: die von Hand durchgeführte Prüfung «Buchinhalt gegen geparste Daten»
 * (TESTS.md, Abschnitt «Prüfungen von Hand»). Der Bericht sagt, WO im Buch
 * nachzusehen ist; beurteilt wird danach am Buch selbst, nie hier.
 */
#[Group('pruefung')]
class BudgetParserPruefungTest extends TestCase {
    private const ZIEL = '/tmp/parlwin-parser-pruefung';

    /** @var list<BudgetProduktegruppe> */
    private array $gruppen = [];
    /** @var list<BudgetInvestition> */
    private array $investitionen = [];
    /** @var array<string, mixed> */
    private array $jahrDaten = [];

    /** @return list<array{int}> */
    public static function jahrgaenge(): array {
        return [[2017], [2018], [2019], [2020], [2021], [2022], [2023], [2024], [2025], [2026], [2027]];
    }

    /**
     * Der Pfad zu einem Budgetbuch. Teil B liegt für 2019 bis 2026 als Fixture im
     * Repo (Teil A nur ab 2022); was fehlt, wird für die Prüfung von Hand nach
     * /tmp geladen — 2017 und 2018 sowie die alten Teil-A-Bücher bleiben draussen,
     * sie brächten weitere 40 MB ins Repo.
     */
    private static function buchPfad(int $jahr, string $teil): string {
        $fixture = \dirname(__DIR__, 1) . "/Fixtures/budget/$jahr/teil-$teil.pdf";
        return is_file($fixture) ? $fixture : "/tmp/budget-alt/$jahr/teil-$teil.pdf";
    }

    #[DataProvider('jahrgaenge')]
    public function testBerichtSchreiben(int $jahr): void {
        $this->gruppen = [];
        $this->investitionen = [];
        $this->jahrDaten = [];

        $this->service()->importiereJahr($jahr);
        self::assertNotSame([], $this->gruppen, "Jahrgang $jahr lieferte keine Produktegruppen");

        if (!is_dir(self::ZIEL)) {
            mkdir(self::ZIEL, 0o755, true);
        }
        // Der Text, den der Parser sieht (smalot). Er weicht von dem ab, was
        // `pdftotext -layout` zeigt — geprüft wird gegen DIESEN Text.
        foreach (['a', 'b'] as $teil) {
            $pfad = self::buchPfad($jahr, $teil);
            if (is_file($pfad)) {
                $klasse = 'Smalot\\PdfParser\\Parser';
                $roh = (new $klasse())->parseFile($pfad)->getText();
                file_put_contents(self::ZIEL . "/$jahr-teil-$teil-roh.txt", $roh);
            }
        }

        // Die Textfragmente von Teil A mit ihren Koordinaten — nur so ist zu
        // sehen, wie die Spalten des Investitionsanhangs wirklich liegen.
        $this->fragmenteSchreiben($jahr);

        $daten = $this->daten();
        file_put_contents(
            self::ZIEL . "/$jahr.json",
            json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents(self::ZIEL . "/$jahr-bericht.txt", $this->bericht($jahr, $daten));
    }

    /**
     * Schreibt die Textfragmente von Teil A mit Seite, Zeile und x-Position —
     * die Grundlage, um die Spalten des Investitionsanhangs zu beurteilen.
     */
    private function fragmenteSchreiben(int $jahr): void {
        $pfad = self::buchPfad($jahr, 'a');
        if (!is_file($pfad)) {
            return;
        }
        $klasse = 'Smalot\\PdfParser\\Parser';
        $zeilen = [];
        $seite = 0;
        foreach ((new $klasse())->parseFile($pfad)->getPages() as $page) {
            $seite++;
            $nachY = [];
            foreach ($page->getDataTm() as $r) {
                $m = $r[0];
                $nachY[(int) round(((float) ($m[5] ?? 0)) / 4)][] = [
                    'x' => (float) ($m[4] ?? 0),
                    't' => (string) $r[1],
                ];
            }
            krsort($nachY);
            foreach ($nachY as $frags) {
                usort($frags, static fn ($a, $b) => $a['x'] <=> $b['x']);
                $zeilen[] = sprintf(
                    'S%03d | %s',
                    $seite,
                    implode('  ', array_map(static fn ($f) => sprintf('[%.0f]%s', $f['x'], $f['t']), $frags))
                );
            }
        }
        file_put_contents(self::ZIEL . "/$jahr-teil-a-fragmente.txt", implode("\n", $zeilen) . "\n");
    }

    /** @return array<string, mixed> */
    private function daten(): array {
        $gruppen = [];
        foreach ($this->gruppen as $g) {
            $roh = $g->jsonSerialize();
            $produkte = $roh['produkte'];
            $gruppen[] = [
                'code' => $roh['code'],
                'name' => $roh['name'],
                'departement' => $roh['departement'],
                'kuenstlich' => $roh['kuenstlich'],
                'globalkredit' => $roh['globalkredit'],
                'aufwand' => $roh['aufwand'],
                'ertrag' => $roh['ertrag'],
                'stellen' => $roh['stellen'],
                'auszubildendeSoll' => $roh['auszubildendeSoll'],
                'auftragLaenge' => mb_strlen((string) $roh['auftrag']),
                // Der Anfang jedes Textes, damit die Prüfung ihn im Buch
                // nachschlagen kann: steht er dort wörtlich, stammt er aus dem
                // Buch und nicht aus einer Verkettung mehrerer Abschnitte.
                'auftragAnfang' => mb_substr((string) $roh['auftrag'], 0, 90),
                'zielvorgaben' => $roh['zielvorgaben'],
                'kostenzeilen' => $roh['kostenzeilen'],
                'texte' => [
                    'erlaeuterungStellen' => mb_strlen((string) $roh['erlaeuterungStellen']),
                    'begruendungAbweichung' => mb_strlen((string) $roh['begruendungAbweichung']),
                    'begruendungFap' => mb_strlen((string) $roh['begruendungFap']),
                    'massnahmen' => mb_strlen((string) $roh['massnahmen']),
                ],
                'textAnfaenge' => [
                    'erlaeuterungStellen' => mb_substr((string) $roh['erlaeuterungStellen'], 0, 90),
                    'begruendungAbweichung' => mb_substr((string) $roh['begruendungAbweichung'], 0, 90),
                    'begruendungFap' => mb_substr((string) $roh['begruendungFap'], 0, 90),
                    'massnahmen' => mb_substr((string) $roh['massnahmen'], 0, 90),
                ],
                'textEnden' => [
                    'erlaeuterungStellen' => mb_substr((string) $roh['erlaeuterungStellen'], -90),
                    'begruendungAbweichung' => mb_substr((string) $roh['begruendungAbweichung'], -90),
                    'begruendungFap' => mb_substr((string) $roh['begruendungFap'], -90),
                    'massnahmen' => mb_substr((string) $roh['massnahmen'], -90),
                ],
                'produkte' => $produkte,
            ];
        }
        $inv = [];
        foreach ($this->investitionen as $i) {
            $inv[] = $i->jsonSerialize();
        }
        return ['jahr' => $this->jahrDaten, 'produktegruppen' => $gruppen, 'investitionen' => $inv];
    }

    /**
     * Der lesbare Bericht: Kopfzahlen, je Produktegruppe eine Zeile, danach die
     * Auffälligkeiten. Alle Beträge in Franken, wie im Buch.
     *
     * @param array<string, mixed> $daten
     */
    private function bericht(int $jahr, array $daten): string {
        $z = [];
        $j = $daten['jahr'];
        $gruppen = $daten['produktegruppen'];
        $z[] = "PARSER-PRÜFUNG BUDGET $jahr";
        $z[] = str_repeat('=', 70);
        $z[] = '';
        $z[] = 'AUS TEIL A';
        $z[] = sprintf('  Steuerfuss              %s', $j['steuerfuss'] ?? '—');
        $z[] = sprintf('  Steuerertrag            %s', $this->fr($j['steuerertrag'] ?? 0));
        $z[] = sprintf('  Gesamtergebnis          %s', $this->fr($j['gesamtergebnis'] ?? 0));
        $z[] = sprintf('  Total Aufwand Soll      %s', $this->fr($j['totalAufwand'] ?? 0));
        $z[] = sprintf('  Total Ertrag Soll       %s', $this->fr($j['totalErtrag'] ?? 0));
        $z[] = sprintf('  Total Aufwand Vorjahr   %s', $this->fr($j['totalAufwandVorjahr'] ?? 0));
        $z[] = sprintf('  Total Ertrag Vorjahr    %s', $this->fr($j['totalErtragVorjahr'] ?? 0));
        $z[] = '';

        $summeErgebnis = 0;
        $summeStellen = 0.0;
        $departemente = [];
        foreach ($gruppen as $g) {
            $summeErgebnis += $g['ertrag']['soll'] - $g['aufwand']['soll'];
            $summeStellen += (float) $g['stellen']['soll'];
            $departemente[(string) $g['departement']] = ($departemente[(string) $g['departement']] ?? 0) + 1;
        }
        $z[] = 'AUS TEIL B';
        $z[] = sprintf('  Produktegruppen         %d (davon künstlich: %d)', count($gruppen), count(array_filter($gruppen, static fn ($g) => $g['kuenstlich'])));
        $z[] = sprintf('  Σ (Ertrag − Aufwand)    %s', $this->fr($summeErgebnis));
        $z[] = sprintf('  Abweichung zum Ergebnis %s', $this->fr($summeErgebnis - (int) ($j['gesamtergebnis'] ?? 0)));
        $z[] = sprintf('  Σ Stellen Soll          %s', number_format($summeStellen, 1, '.', "'"));
        $z[] = sprintf('  Investitionsprojekte    %d', count($daten['investitionen']));
        $z[] = '';
        $z[] = '  Departemente:';
        ksort($departemente);
        foreach ($departemente as $dep => $n) {
            $z[] = sprintf('    %-45s %3d Produktegruppen', $dep === '' ? '(LEER)' : $dep, $n);
        }
        $z[] = '';

        $z[] = 'PRODUKTEGRUPPEN';
        $z[] = sprintf(
            '  %-5s %-42s %-14s %14s %14s %14s %7s %3s %3s',
            'Code', 'Name', 'Departement', 'Globalkredit', 'Aufwand', 'Ertrag', 'Stellen', 'Pr', 'Zv'
        );
        foreach ($gruppen as $g) {
            $z[] = sprintf(
                '  %-5s %-42s %-14s %14s %14s %14s %7s %3d %3d',
                (string) $g['code'],
                mb_substr((string) $g['name'], 0, 42),
                mb_substr((string) $g['departement'], 0, 14),
                $this->fr($g['globalkredit']['soll']),
                $this->fr($g['aufwand']['soll']),
                $this->fr($g['ertrag']['soll']),
                number_format((float) $g['stellen']['soll'], 1, '.', ''),
                count($g['produkte']),
                count($g['zielvorgaben'])
            );
        }
        $z[] = '';

        $z[] = 'AUFFÄLLIGKEITEN (im Buch nachzusehen)';
        $funde = $this->auffaelligkeiten($gruppen);
        if ($funde === []) {
            $z[] = '  keine';
        }
        foreach ($funde as $f) {
            $z[] = '  ' . $f;
        }
        $z[] = '';

        $z[] = 'PRODUKTE JE PRODUKTEGRUPPE';
        foreach ($gruppen as $g) {
            if ($g['kuenstlich']) {
                continue;
            }
            $z[] = sprintf('  %s %s', (string) $g['code'], (string) $g['name']);
            foreach ($g['produkte'] as $p) {
                $kosten = $p['kostenzeilen'] ?? [];
                $z[] = sprintf(
                    '      %-4s %-46s netto %14s  Kostenzeilen %d  Leistungen %d',
                    (string) ($p['nummer'] ?? '?'),
                    mb_substr((string) ($p['name'] ?? ''), 0, 46),
                    $this->fr((int) ($p['nettokostenSoll'] ?? 0)),
                    count($kosten),
                    count($p['leistungen'] ?? [])
                );
            }
        }
        return implode("\n", $z) . "\n";
    }

    /**
     * Maschinell erkennbare Verdachtsmomente. Sie sind KEIN Urteil — jeder Fund
     * wird im Buch nachgesehen; die Stadt lässt selbst Lücken (z.B. eine
     * Produktegruppe, deren Produkte ihr Budget nicht ausschöpfen).
     *
     * @param list<array<string, mixed>> $gruppen
     * @return list<string>
     */
    private function auffaelligkeiten(array $gruppen): array {
        $funde = [];
        foreach ($gruppen as $g) {
            $code = (string) $g['code'];
            $name = (string) $g['name'];
            if ($g['kuenstlich']) {
                continue;
            }
            if ($name === '' || mb_strlen($name) < 4) {
                $funde[] = "PG $code: Name fehlt oder ist zu kurz («{$name}»)";
            }
            if (preg_match('/[.]{3,}|^\d+$|\bSeite\b/u', $name) === 1) {
                $funde[] = "PG $code: Name sieht nach Inhaltsverzeichnis aus («{$name}»)";
            }
            if ((string) $g['departement'] === '') {
                $funde[] = "PG $code: kein Departement";
            }
            if ($g['produkte'] === []) {
                $funde[] = "PG $code «{$name}»: keine Produkte";
            }
            if ($g['aufwand']['soll'] === 0 && $g['ertrag']['soll'] === 0) {
                $funde[] = "PG $code «{$name}»: Aufwand und Ertrag beide 0";
            }
            if ($g['zielvorgaben'] === []) {
                $funde[] = "PG $code «{$name}»: keine Zielvorgaben";
            }
            $ohneKosten = [];
            $summeNetto = 0;
            foreach ($g['produkte'] as $p) {
                if (($p['kostenzeilen'] ?? []) === []) {
                    $ohneKosten[] = (string) ($p['nummer'] ?? '?');
                }
                $summeNetto += (int) ($p['nettokostenSoll'] ?? 0);
            }
            if ($ohneKosten !== []) {
                $funde[] = "PG $code «{$name}»: Produkte ohne Kostentabelle: " . implode(', ', $ohneKosten);
            }
            // Die Produkte einer Gruppe sollten deren Globalkredit weitgehend erklären.
            $kredit = (int) $g['globalkredit']['soll'];
            if ($kredit > 0 && $summeNetto > 0) {
                $abw = ($summeNetto - $kredit) / $kredit;
                if (abs($abw) > 0.05) {
                    $funde[] = sprintf(
                        'PG %s «%s»: Σ Produkte %s weicht %+.0f%% vom Globalkredit %s ab',
                        $code, $name, $this->fr($summeNetto), $abw * 100, $this->fr($kredit)
                    );
                }
            }
        }
        return $funde;
    }

    private function fr(int|float $v): string {
        return number_format((float) $v, 0, '.', "'");
    }

    // ── Aufbau des Import-Service über den echten Live-Pfad ──────────────────

    private function service(): BudgetImportService {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $jahre->method('update')->willReturnCallback(function ($row) {
            $this->jahrDaten = $row->jsonSerialize();
            return $row;
        });
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('insert')->willReturnCallback(function ($g) {
            $this->gruppen[] = $g;
            return $g;
        });
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('insert')->willReturnCallback(function ($i) {
            $this->investitionen[] = $i;
            return $i;
        });
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturnCallback(static function (int $jahr): Geschaeft {
            $g = new Geschaeft();
            $g->setUrl('https://test.local/budget/' . $jahr);
            return $g;
        });
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $resp = $this->createStub(IResponse::class);
            $resp->method('getBody')->willReturn($this->netzInhalt($url));
            return $resp;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $traktanden = $this->createStub(TraktandumMapper::class);
        $traktanden->method('findByGeschaeft')->willReturn([]);
        $sitzungen = $this->createStub(SitzungMapper::class);
        return new BudgetImportService(
            $jahre, $gruppen, $investitionen, $antraege, new BudgetBuchParser(), $time,
            $geschaefte, $clientService, $traktanden, $sitzungen, new BudgetDrehbuchParser()
        );
    }

    private function netzInhalt(string $url): string {
        if (preg_match('#/budget/(\d{4})$#', $url, $m) === 1) {
            $jahr = (int) $m[1];
            return '<html><body>'
                . '<a href="/_doc/teil-a-' . $jahr . '">Beilage 2: Teil A - Antrag (Budget und Finanzplan)</a>'
                . '<a href="/_doc/teil-b-' . $jahr . '">Beilage 3: Teil B - Antrag (Produktegruppen Globalbudgets)</a>'
                . '</body></html>';
        }
        if (preg_match('#/_doc/teil-([ab])-(\d{4})#', $url, $m) === 1) {
            $pfad = self::buchPfad((int) $m[2], $m[1]);
            return is_file($pfad) ? (string) file_get_contents($pfad) : '';
        }
        return '';
    }
}
