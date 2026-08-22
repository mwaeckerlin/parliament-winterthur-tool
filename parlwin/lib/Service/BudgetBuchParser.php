<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Extrahiert die strukturierten Budgetdaten aus den städtischen PDF (Teil A + B).
 *
 * Die Textextraktion nutzt zur Laufzeit die reine PHP-Bibliothek
 * smalot/pdfparser (im Image-Build via Composer installiert). Das Parsen ist
 * label-basiert und positionsunabhängig (F89-Toleranz): es sucht nach den
 * Beschriftungen der Bücher, nicht nach festen Zeilen/Spalten, und normalisiert
 * das Schweizer Zahlenformat (Apostroph als Tausendertrenner).
 *
 * HINWEIS: Die exakte Zeilenstruktur der smalot-Extraktion wird im e2e-Kontext
 * gegen die echten, im Repo liegenden Bücher (tests/fixtures/budget/<jahr>/)
 * validiert und dort feinjustiert — nicht anhand erfundener Beispieltexte.
 */
class BudgetBuchParser {
    /**
     * Baut die vollständige Jahresstruktur aus Teil B (+ optional Teil A).
     *
     * @return array<string, mixed>
     */
    public function struktur(string $teilBPfad, ?string $teilAPfad, int $jahr): array {
        $teilB = $this->text($teilBPfad);
        $struktur = ['produktegruppen' => $this->parseTeilB($teilB)];
        if ($teilAPfad !== null) {
            $struktur = array_merge($struktur, $this->parseTeilA($this->text($teilAPfad)));
        }
        return $struktur;
    }

    /** @return array<string, mixed> */
    public function parseNovemberbrief(string $pfad): array {
        // Der Novemberbrief listet Anpassungen je Produktegruppe (Code + neuer
        // Globalkredit/Aufwand). Gleiche Anker wie Teil B, hier nur die Deltas.
        return ['produktegruppen' => $this->parseTeilB($this->text($pfad))];
    }

    /** Extrahiert den reinen Text eines PDF über smalot/pdfparser. */
    private function text(string $pfad): string {
        $klasse = 'Smalot\\PdfParser\\Parser';
        if (!class_exists($klasse)) {
            throw new \RuntimeException(
                'PDF-Bibliothek smalot/pdfparser nicht verfügbar — im Image via Composer installieren'
            );
        }
        /** @var object $parser */
        $parser = new $klasse();
        $dokument = $parser->parseFile($pfad);
        return (string) $dokument->getText();
    }

    /**
     * Parst die Produktegruppen aus Teil B.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTeilB(string $text): array {
        $zeilen = $this->zeilen($text);

        // 1. Departement je Produktegruppe aus dem Inhaltsverzeichnis. Nur dort
        //    stehen die Einträge „<Name> (<Code>) …… <Seite>“ (Code, dann
        //    Füllpunkte/Leerraum, dann Seitenzahl am Zeilenende) unter einem
        //    „Departement <Name>“-Kopf — so trifft die Zuordnung nicht auf einen
        //    Prosasatz, der zufällig „Departement …“ enthält.
        $codeZuDept = [];
        $codeZuName = [];
        $tocDept = '';
        $imToc = false;
        foreach ($zeilen as $zeile) {
            if (!$imToc) {
                if (preg_match('/^Inhaltsverzeichnis/u', $zeile)) {
                    $imToc = true;
                }
                continue;
            }
            // Das Inhaltsverzeichnis endet, sobald das erste Kapitel beginnt
            // (laufende Kopfzeile „… Einleitung Produktegruppe …“).
            if (str_contains($zeile, 'Einleitung Produktegruppe')) {
                break;
            }
            // TOC-Eintrag: „<Name> (<Code>) …Füllpunkte… <Seite>“. Die Füllpunkte
            // unterscheiden ihn von laufenden Kopf-/Fusszeilen, die ebenfalls
            // „(Code)“ enthalten, aber keine Punkte. Der vollständige Name steht
            // hier auf einer Zeile — im Kapitel selbst kann die Überschrift auf
            // zwei Zeilen umbrechen.
            if (preg_match('/^(.+?)\s*\((\d{3})\)\s*\.{2,}/u', $zeile, $m)) {
                $codeZuDept[$m[2]] = $tocDept;
                $codeZuName[$m[2]] = trim($m[1]);
                continue;
            }
            // Departements-/Bereichskopf (auch ohne „Departement“-Präfix, z.B.
            // „Behörden und Stadtkanzlei“); der Hinweis in Klammern zählt nicht.
            if (!str_starts_with($zeile, '(')) {
                $tocDept = preg_replace('/^Departement\s+/u', '', $zeile);
            }
        }

        // 2. Produktegruppen aus den Kapiteln. Die Überschrift ist „<Name>
        //    (<Code>)“ ohne Tabulator — laufende Kopf-/Fusszeilen enthalten einen
        //    Tab (und Zusätze wie „Einleitung/Zum Beschluss … (Code)“) und werden
        //    so nicht als Überschrift gewertet, fliessen aber in die Zahlensuche.
        $nachCode = [];
        $reihenfolge = [];
        $aktuellCode = null;
        $auftragModus = false;
        $textFeld = null;
        foreach ($zeilen as $zeile) {
            if (
                !str_contains($zeile, "\t")
                && preg_match('/^(.+?)\s*\((\d{3})\)\s*$/u', $zeile, $m)
                && isset($codeZuDept[$m[2]])
            ) {
                $code = $m[2];
                if (!isset($nachCode[$code])) {
                    // Vollständiger Name aus dem Inhaltsverzeichnis; die Body-
                    // Überschrift ($m[1]) nur als Rückfall.
                    $name = $codeZuName[$code] ?? trim($m[1]);
                    $nachCode[$code] = $this->leereGruppe($name, $code, $codeZuDept[$code]);
                    $reihenfolge[] = $code;
                }
                $aktuellCode = $code;
                $auftragModus = false;
                $textFeld = null;
                continue;
            }
            if ($aktuellCode === null) {
                continue;
            }
            // Auftragstext: der Block zwischen der Überschrift „Auftrag“ und
            // „Rechtsgrundlagen …“ (Mission der Produktegruppe, Info in Tab 1).
            if ($zeile === 'Auftrag' && $nachCode[$aktuellCode]['auftrag'] === '') {
                $auftragModus = true;
                continue;
            }
            if ($auftragModus) {
                if (str_starts_with($zeile, 'Rechtsgrundlagen')) {
                    $auftragModus = false;
                } else {
                    $nachCode[$aktuellCode]['auftrag'] = trim(
                        $nachCode[$aktuellCode]['auftrag'] . ' ' . $zeile
                    );
                }
                continue;
            }
            // Erläuterungs-/Begründungs-Abschnitte (F80): Fliesstext zwischen einer
            // Abschnittsüberschrift und der nächsten Struktur-/Abschnittszeile.
            $startFeld = $this->erlaeuterungsFeld($zeile);
            if ($startFeld !== null) {
                $textFeld = $startFeld;
                continue;
            }
            if ($textFeld !== null) {
                if ($this->istAbschnittsende($zeile)) {
                    $textFeld = null;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    $nachCode[$aktuellCode][$textFeld] = trim(
                        $nachCode[$aktuellCode][$textFeld] . ' ' . $zeile
                    );
                    continue;
                }
            }
            // Produkt-Überschrift „Produkt <N> <Name>“ (Produkte als Information,
            // F80). Leistungstext wie „… (zu Gunsten Produkt 1)“ beginnt nicht am
            // Zeilenanfang mit „Produkt <Ziffer> <Text>“ und wird nicht getroffen.
            if (preg_match('/^Produkt\s+(\d+)\s+(\S.*)$/u', $zeile, $pm)) {
                $nachCode[$aktuellCode]['produkte'][] = [
                    'nummer' => (int) $pm[1],
                    'name' => trim($pm[2]),
                    'nettokosten' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
                ];
                continue;
            }
            // Nettokosten-Wertzeile des zuletzt gelesenen Produkts: „Nettokosten
            // <Ist> <SollVorjahr> <Soll>“ — nicht die Globalkredit-Zeile der
            // Produktegruppe und nicht die Jahres-Kopfzeile (die trägt Ist/Soll/Plan).
            if (
                str_starts_with($zeile, 'Nettokosten')
                && !str_contains($zeile, 'Globalkredit')
                && !preg_match('/\b(Ist|Soll|Plan)\b/u', $zeile)
                && $nachCode[$aktuellCode]['produkte'] !== []
            ) {
                $z = $this->zahlen($zeile);
                if (\count($z) >= 3) {
                    $letzter = \count($nachCode[$aktuellCode]['produkte']) - 1;
                    $nachCode[$aktuellCode]['produkte'][$letzter]['nettokosten'] = [
                        'ist' => $z[0], 'sollVorjahr' => $z[1], 'soll' => $z[2],
                    ];
                }
                continue;
            }
            $this->fuelleZeile($nachCode[$aktuellCode], $zeile);
        }
        $gruppen = [];
        foreach ($reihenfolge as $code) {
            $gruppen[] = $nachCode[$code];
        }
        return $gruppen;
    }

    /** @return array<string, mixed> */
    private function leereGruppe(string $name, string $code, string $departement): array {
        $leer = ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0, 'plan1' => 0, 'plan2' => 0, 'plan3' => 0];
        return [
            'code' => $code,
            'name' => $name,
            'departement' => $departement,
            'globalkredit' => $leer,
            'aufwand' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'ertrag' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'stellen' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'auftrag' => '',
            'erlaeuterungStellen' => '',
            'begruendungAbweichung' => '',
            'begruendungFap' => '',
            'massnahmen' => '',
            'produkte' => [],
        ];
    }

    /**
     * Ordnet eine Abschnittsüberschrift dem Textfeld zu, in das ihr Fliesstext
     * gesammelt wird (F80: Erläuterungen/Begründungen aus dem Buch). null, wenn
     * die Zeile keine solche Überschrift ist.
     */
    private function erlaeuterungsFeld(string $zeile): ?string {
        if (str_starts_with($zeile, 'Erläuterungen zum Stellenplan')) {
            return 'erlaeuterungStellen';
        }
        if (str_starts_with($zeile, 'Begründung Abweichung')) {
            return 'begruendungAbweichung';
        }
        if (str_starts_with($zeile, 'Begründung FAP')) {
            return 'begruendungFap';
        }
        if (str_starts_with($zeile, 'Wesentliche Massnahmen')) {
            return 'massnahmen';
        }
        return null;
    }

    /**
     * Ende eines Erläuterungs-/Begründungs-Textblocks: eine strukturelle Zeile
     * (Tabellenlabel, Produkt-/Kapitelüberschrift, laufende Kopf-/Fusszeile mit
     * Tab). Die Muster sind am Zeilenanfang verankert, damit ein Fliesstext, der
     * ein solches Wort mittendrin enthält, den Block nicht abbricht.
     */
    private function istAbschnittsende(string $zeile): bool {
        if (str_contains($zeile, "\t") || str_starts_with($zeile, '▼')) {
            return true;
        }
        if (preg_match('/^(Produkt\s+\d+\s|Nettokosten\b|Total\b|Stelleneinheiten\b|'
            . 'Auszubildende\b|Stellenplan\b|Personal:|Parlamentarische Zielvorgaben\b|'
            . 'Leistungen$|Leistungsmengen\b|Auftrag$|Rechtsgrundlagen|'
            . 'Verantwortliche Leitung|Globalkredit\b)/u', $zeile)) {
            return true;
        }
        // Kapitelüberschrift „<Name> (<Code>)“ (ohne Tab bereits oben behandelt).
        return (bool) preg_match('/\(\d{3}\)\s*$/u', $zeile);
    }

    /**
     * Ordnet eine Zahlen-Zeile ihrer Kennzahl zu (label-basiert). Die ersten drei
     * Werte der Aufwand-/Ertrag-Zeilen sind mit Prozent-Spalten verschachtelt; die
     * genaue Positionswahl wird im e2e gegen den realen Text justiert.
     *
     * @param array<string, mixed> $gruppe
     */
    private function fuelleZeile(array &$gruppe, string $zeile): void {
        // Die beschlussfähige Globalkredit-Wertzeile (Abschnitt «Zum Beschluss»)
        // trägt sechs Beträge. Die gleichnamige Kopfzeile der Informationsteil-
        // Tabelle enthält «in%» und die Jahreszahlen — die wird ausgeschlossen,
        // damit nicht «2026» statt des Betrags gelesen wird.
        if (preg_match('/Nettokosten\s*\/\s*Globalkredit/u', $zeile) && !str_contains($zeile, 'in%')) {
            $z = $this->zahlen($zeile);
            if (count($z) >= 3) {
                $gruppe['globalkredit'] = [
                    'ist' => $z[0], 'sollVorjahr' => $z[1], 'soll' => $z[2],
                    'plan1' => $z[3] ?? 0, 'plan2' => $z[4] ?? 0, 'plan3' => $z[5] ?? 0,
                ];
            }
            return;
        }
        if (str_starts_with($zeile, 'Total effektive Kosten')) {
            $gruppe['aufwand'] = $this->dreiWerteOhneProzent($zeile);
            return;
        }
        if (str_starts_with($zeile, 'Total effektive Erlöse')) {
            $gruppe['ertrag'] = $this->dreiWerteOhneProzent($zeile);
            return;
        }
        if (str_contains($zeile, 'Stelleneinheiten')) {
            // Zeile trägt oft einen Präfix («Personal: ▪ Stelleneinheiten …»);
            // die drei Werte (Ist/Soll_Vorjahr/Soll) sind die Dezimalzahlen.
            $z = $this->zahlenMitKomma($zeile);
            $gruppe['stellen'] = ['ist' => $z[0] ?? 0, 'sollVorjahr' => $z[1] ?? 0, 'soll' => $z[2] ?? 0];
        }
    }

    /**
     * Aus einer Aufwand-/Ertrag-Zeile die drei Jahreswerte (Ist/Soll_Vorjahr/Soll)
     * ohne die dazwischenstehenden Prozent-Spalten. Heuristik: die grossen Werte
     * (Beträge) von den kleinen (Prozent 0–100) trennen.
     *
     * @return array<string, int>
     */
    private function dreiWerteOhneProzent(string $zeile): array {
        $betraege = array_values(array_filter($this->zahlen($zeile), static fn ($n) => abs($n) > 1000));
        return ['ist' => $betraege[0] ?? 0, 'sollVorjahr' => $betraege[1] ?? 0, 'soll' => $betraege[2] ?? 0];
    }

    /**
     * Parst Teil A: Steuerfuss, Steuerertrag und Investitionsprojekte.
     *
     * @return array<string, mixed>
     */
    private function parseTeilA(string $text): array {
        $steuerfuss = 0;
        if (preg_match('/Steuerfuss[^.]*?auf\s+(\d{2,3})\s+Prozent/u', $text, $m)) {
            $steuerfuss = (int) $m[1];
        }
        $personalsteuer = 0;
        if (preg_match('/Personalsteuer\s*\((\d+)\s+Franken/u', $text, $m)) {
            $personalsteuer = (int) $m[1];
        }
        // Der Gesamt-Steuerertrag (natürliche + juristische Personen) steht als
        // Fliesstext: „Die erwarteten Steuererträge belaufen sich auf 521,2
        // Millionen Franken …“. Die frühere „^Steuerertrag“-Suche traf dagegen
        // die Inhaltsverzeichnis-Zeile „Steuerertrag und Steuerfuss  38“.
        $steuerertrag = 0;
        if (preg_match('/Steuererträge belaufen sich auf\s+([\d’\'., ]+?)\s+Millionen/u', $text, $m)) {
            $steuerertrag = $this->millionenZuFranken($m[1]);
        }
        return [
            'steuerfuss' => $steuerfuss,
            'steuerertrag' => $steuerertrag,
            'personalsteuer' => $personalsteuer,
            'investitionen' => $this->parseInvestitionen($text),
        ];
    }

    /**
     * Investitionen je Projekt aus dem Anhang «Investitionsplanung
     * Verwaltungsvermögen» (Teil A). Die Tabelle listet pro Zeile:
     *   <Nr> <Bezeichnung> <Budget Vorjahr> <Budget Budgetjahr> <Plan+1> <Plan+2> <Plan+3>
     * gruppiert nach «Departement <Name>» und Produktegruppe «<Name> (PG)».
     * Projektzeilen tragen eine mindestens 6-stellige Konto-Nr (z.B. 5001750);
     * ein- bis dreistellige Nr sind Total-/Bereichs-/Produktegruppen-Zeilen.
     *
     * Robust gegen Jahreszahlen IM Bezeichnungstext (z.B. „… 2028“): die fünf
     * Wertspalten sind immer die LETZTEN fünf Zahlen der Zeile, die Nr die erste.
     * Der Budgetjahr-Wert (BU) ist die zweite Wertspalte (Vorjahr, Budgetjahr, …).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseInvestitionen(string $text): array {
        $inv = [];
        $imBereich = false;
        $departement = '';
        $produktegruppe = '';
        $reihenfolge = 0;
        foreach ($this->zeilen($text) as $zeile) {
            if (!$imBereich) {
                // Anhang beginnt mit der Verwaltungsvermögens-Investitionsplanung.
                if (str_contains($zeile, 'Investitionsplanung') && str_contains($zeile, 'Verwaltungsvermögen')) {
                    $imBereich = true;
                }
                continue;
            }
            // Ende: die nächste Investitionsplanung-Sektion (Eigenwirtschafts-
            // betriebe/Finanzvermögen) — die entscheidet nicht das Parlament über
            // den Steuerhaushalt.
            if (
                str_contains($zeile, 'Investitionsplanung')
                && (str_contains($zeile, 'Eigenwirtschaftsbetriebe') || str_contains($zeile, 'Finanzvermögen'))
            ) {
                break;
            }
            // Laufende Kopf-/Fusszeilen der Tabelle.
            if (
                str_starts_with($zeile, 'Investitionsplanung')
                || str_starts_with($zeile, 'Nr.')
                || str_starts_with($zeile, 'Seite ')
                || preg_match('/^\d{4}\s+\d{4}\b/u', $zeile)
            ) {
                continue;
            }
            // Datenzeile: führende Nr, dann Name, dann fünf Wertspalten.
            if (!preg_match('/^(\d+)\s+(.+)$/u', $zeile, $m)) {
                continue;
            }
            $nr = $m[1];
            $name = trim((string) preg_replace("/(\\s+-?\\d[\\d'’]*){5}\\s*$/u", '', $m[2]));
            if ($name === 'Stadt Winterthur') {
                continue; // Gesamttotal
            }
            if (str_starts_with($name, 'Departement ')) {
                $departement = trim(substr($name, \strlen('Departement ')));
                continue;
            }
            if (str_ends_with($name, '(PG)')) {
                $produktegruppe = trim(substr($name, 0, -\strlen('(PG)')));
                continue;
            }
            if (\strlen($nr) < 6) {
                continue; // Bereichs-Subtotal (zwei-/dreistellige Nr)
            }
            $werte = $this->letzteZahlen($zeile, 5);
            if (\count($werte) < 5) {
                continue;
            }
            $inv[] = [
                'departement' => $departement,
                'cluster' => $produktegruppe,
                'projekt' => $nr . ' ' . $name,
                'bu' => $werte[1],
                'fap1' => $werte[2],
                'fap2' => $werte[3],
                'fap3' => $werte[4],
                'reihenfolge' => $reihenfolge++,
            ];
        }
        return $inv;
    }

    /**
     * Die letzten $n ganzen Zahlen einer Zeile (Schweizer Format), als int —
     * für Tabellen, deren Wertspalten rechtsbündig am Zeilenende stehen und
     * deren Bezeichnung selbst Zahlen enthalten kann.
     *
     * @return int[]
     */
    private function letzteZahlen(string $zeile, int $n): array {
        preg_match_all("/-?\\d[\\d'’]*/u", $zeile, $m);
        $alle = array_map(static fn ($s) => (int) str_replace(["'", '’'], '', $s), $m[0]);
        return array_slice($alle, -$n);
    }

    /** @return string[] */
    private function zeilen(string $text): array {
        $zeilen = preg_split('/\R/u', $text) ?: [];
        return array_values(array_filter(array_map('trim', $zeilen), static fn ($z) => $z !== ''));
    }

    /**
     * Alle ganzen Zahlen einer Zeile (Schweizer Format 1'234'567), als int.
     *
     * @return int[]
     */
    private function zahlen(string $zeile): array {
        preg_match_all("/-?\\d[\\d'’]*/u", $zeile, $m);
        return array_map(fn ($s) => (int) str_replace(["'", '’'], '', $s), $m[0]);
    }

    /**
     * Zahlen mit optionaler Nachkommastelle (Stelleneinheiten, z.B. 17.20), als
     * int-gerundete Werte reichen hier nicht — daher als float in ein int-Array
     * nicht sinnvoll; Stellen werden als float benötigt.
     *
     * @return float[]
     */
    private function zahlenMitKomma(string $zeile): array {
        preg_match_all("/-?\\d[\\d'’]*(?:[.,]\\d+)?/u", $zeile, $m);
        return array_map(static fn ($s) => (float) str_replace(["'", '’', ','], ['', '', '.'], $s), $m[0]);
    }

    /**
     * Wandelt eine als „Millionen“ angegebene Schweizer Zahl (z.B. „521,2“) in
     * ganze Franken (521'200'000). Apostroph-Tausender und Leerraum entfallen,
     * das Dezimalkomma wird zum Punkt.
     */
    private function millionenZuFranken(string $roh): int {
        $n = str_replace(["'", '’', ' '], '', trim($roh));
        $n = str_replace(',', '.', $n);
        return (int) round((float) $n * 1_000_000);
    }
}
