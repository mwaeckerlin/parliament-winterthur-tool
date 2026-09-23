<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Parst den «Novemberbrief» — den Nachtrag des Stadtrats zum Budgetentwurf, den
 * er im November nachreicht (F91).
 *
 * Bis zum Budget 2022 hängt er als eigene Beilage am Budget-Geschäft. Seine
 * Beilage «Übersicht der Positionen im Novemberbrief <Jahr> der Stadt
 * Winterthur» führt je Produktegruppe drei Zeilen:
 *
 *     Städtische Allgemeinkosten / Erlöse
 *       77'663'200    3 Aufwand      528'000     78'191'200
 *     -123'285'896    4 Ertrag       272'346   -123'013'550
 *      -45'622'696    0 Ergebnis     800'346    -44'822'350
 *
 * Die mittlere Spalte ist die Korrektur, die letzte das neue Budget. Der Ertrag
 * steht im Novemberbrief negativ (er mindert die Kosten); die App führt ihn
 * positiv, deshalb dreht dieser Parser sein Vorzeichen. Das Ergebnis ist die
 * Nettokosten-Korrektur, also die Änderung des Globalkredits.
 *
 * Die Produktegruppe steht nur mit ihrem NAMEN da, ohne Nummer. Die Zuordnung
 * zum Code macht der BudgetImportService über die Produktegruppen des Jahres.
 */
class BudgetNovemberbriefParser {
    public function __construct(
        private readonly PdfZeilenLeser $leser = new PdfZeilenLeser(),
    ) {
    }

    /**
     * @return array{produktegruppen: list<array{name: string, aufwandNb: int, ertragNb: int, nettokostenNb: int}>, total: ?int}
     *         `total` ist die Ergebnis-Korrektur der Zeile «Stadt Winterthur»,
     *         null in den Jahrgängen, deren Beilage keine solche Zeile führt
     */
    public function parse(string $pfad): array {
        $gruppen = [];
        $total = null;
        $name = '';
        $offen = null;
        // Gelesen wird nur die Beilage «Übersicht der Positionen im Novemberbrief
        // …»: Der Brief selbst führt vorne dieselbe Tabellenform für die
        // Erfolgsrechnung der ganzen Stadt, und ihre Zeilen zählten sonst als
        // Produktegruppen.
        $inBeilage = false;
        $fertig = false;
        foreach ($this->leser->zeilen($pfad) as $frags) {
            if ($fertig) {
                break;
            }
            $text = $this->zeilentext($frags);
            if ($text === '') {
                continue;
            }
            if (str_contains($text, 'Übersicht der Positionen im Novemberbrief')) {
                $inBeilage = true;
                continue;
            }
            // Die Beilage endet mit ihrer Legende; danach folgen die Detailseiten
            // je Kostenstelle und die Investitionsrechnung, die dieselbe
            // Tabellenform tragen.
            if ($inBeilage && str_starts_with($text, 'Legende')) {
                $offen = $this->schliesse($offen, $gruppen, $total);
                $fertig = true;
                continue;
            }
            if (!$inBeilage) {
                continue;
            }
            $zeile = $this->wertzeile($text);
            if ($zeile === null) {
                $neuerName = $this->name($text);
                if ($neuerName !== null && $neuerName !== $name) {
                    $offen = $this->schliesse($offen, $gruppen, $total);
                    $name = $neuerName;
                }
                continue;
            }
            $offen ??= ['name' => $name, 'aufwandNb' => 0, 'ertragNb' => 0, 'nettokostenNb' => 0, 'altAufwand' => 0];
            $offen['name'] = $name;
            if ($zeile['art'] === 'Aufwand') {
                $offen['aufwandNb'] = $zeile['nb'];
                $offen['altAufwand'] = $zeile['alt'];
            } elseif ($zeile['art'] === 'Ertrag') {
                // Der Novemberbrief führt den Ertrag negativ, die App positiv.
                $offen['ertragNb'] = -$zeile['nb'];
            } else {
                $offen['nettokostenNb'] = $zeile['nb'];
            }
        }
        $this->schliesse($offen, $gruppen, $total);

        return ['produktegruppen' => array_values($gruppen), 'total' => $total];
    }

    /**
     * Schliesst die laufende Produktegruppe ab: Führt die Beilage die Zeile
     * «Stadt Winterthur», ist sie das Total, alles andere eine Produktegruppe.
     * Erkannt wird sie an ihrer Grösse — der Gesamtaufwand der Stadt liegt über
     * einer Milliarde, die grösste Produktegruppe unter 300 Millionen. Am Namen
     * ist sie nicht zu erkennen: Die Beilage setzt ihn in einer eigenen
     * Textspalte, die im PDF erst nach den Zahlen kommt, und ihr eigener Titel
     * endet auf «… der Stadt Winterthur».
     *
     * Eine Gruppe ohne jede Korrektur fällt weg.
     *
     * @param array{name:string, aufwandNb:int, ertragNb:int, nettokostenNb:int, altAufwand:int}|null $offen
     * @param list<array{name: string, aufwandNb: int, ertragNb: int, nettokostenNb: int}> $gruppen
     */
    private function schliesse(?array $offen, array &$gruppen, ?int &$total): ?array {
        if ($offen === null) {
            return null;
        }
        if (abs($offen['altAufwand']) > 1_000_000_000) {
            $total = $offen['nettokostenNb'];
            return null;
        }
        if (
            $offen['name'] !== ''
            && ($offen['aufwandNb'] !== 0 || $offen['ertragNb'] !== 0 || $offen['nettokostenNb'] !== 0)
        ) {
            unset($offen['altAufwand']);
            $gruppen[] = $offen;
        }
        return null;
    }

    /**
     * Der Text einer Zeile aus ihren Fragmenten.
     *
     * Eine im PDF zerrissene Zahl wird dabei wieder eine: Die fett gesetzte
     * Totalzeile kommt als «-2'778'» + «734», und mit einem Leerzeichen dazwischen
     * wäre die Zeile keine Wertzeile mehr — die Korrektur der Stadt fiel deshalb
     * auf null. Zusammengefügt wird nur am Tausenderapostroph: Die Nummer der
     * Zeile («3 Aufwand») ist ebenfalls eine Ziffer und darf nicht an den Betrag
     * davor kleben.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function zeilentext(array $frags): string {
        $text = '';
        foreach ($frags as $f) {
            $stueck = trim((string) preg_replace('/\s+/u', ' ', $f['t']));
            if ($stueck === '') {
                continue;
            }
            $klebt = $text !== ''
                && (preg_match('/[\'’]$/u', $text) === 1 || preg_match('/^[\'’]/u', $stueck) === 1);
            $text .= ($text === '' || $klebt ? '' : ' ') . $stueck;
        }
        return trim($text);
    }

    /**
     * Eine Wertzeile «<alt> <Nr> <Aufwand|Ertrag|Ergebnis> <Korrektur> <neu>».
     * Ein Strich steht für «keine Veränderung».
     *
     * @return array{art: string, alt: int, nb: int}|null
     */
    private function wertzeile(string $text): ?array {
        $muster = '/^(-?[\d\'’]+|[-–—])\s+[034]\s+(Aufwand|Ertrag|Ergebnis)\s+(-?[\d\'’]+|[-–—])\s+(-?[\d\'’]+|[-–—])$/u';
        if (preg_match($muster, $text, $m) !== 1) {
            return null;
        }
        return ['art' => $m[2], 'alt' => $this->zahl($m[1]), 'nb' => $this->zahl($m[3])];
    }

    /**
     * Der Name einer Produktegruppe: eine Zeile ohne Zahlen, die weder ein
     * Departement noch eine Kopf- oder Fusszeile ist.
     */
    private function name(string $text): ?string {
        if (preg_match('/\d/u', $text) === 1) {
            return null;
        }
        if (
            str_starts_with($text, 'Departement')
            || str_starts_with($text, 'Behörden')
            || str_starts_with($text, 'Keine Veränderungen')
            || str_starts_with($text, 'Übersicht der Positionen')
            || str_starts_with($text, 'Seite ')
            || mb_strlen($text) < 4
            || mb_strlen($text) > 80
        ) {
            return null;
        }
        return $text;
    }

    private function zahl(string $roh): int {
        if (preg_match('/^-?[\d\'’]+$/u', $roh) !== 1) {
            return 0;
        }
        return (int) str_replace(["'", '’'], '', $roh);
    }
}
