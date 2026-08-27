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
            // Eine umgebrochene TOC-Zeile aus nur Füllpunkten und einer Seitenzahl
            // (z.B. „……… 175“) ist KEIN Departementsname — ein Name enthält immer
            // Buchstaben. Ohne diese Prüfung landete eine solche Zeile als
            // Departement der nächsten Produktegruppe (Screenshot-Bug PG 480).
            if (!str_starts_with($zeile, '(') && preg_match('/\p{L}/u', $zeile)) {
                $tocDept = trim(preg_replace('/^Departement\s+/u', '', $zeile));
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
        $zvModus = false;
        $kostenModus = false;
        $leistungenAktiv = false;
        $leistungBullet = false;
        $produktKostenModus = false;
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
                $zvModus = false;
                $kostenModus = false;
                $leistungenAktiv = false;
                $produktKostenModus = false;
                continue;
            }
            if ($aktuellCode === null) {
                continue;
            }
            // Parlamentarische Zielvorgaben (F109): den ganzen Block bis zur
            // Globalkredit-Tabelle / zum Informationsteil sammeln; die Feinauswertung
            // (Ziele, Messgrössen, Wertspalten) folgt nach dem Loop, weil ein Ziel
            // über mehrere Zeilen und Seiten läuft.
            if (str_starts_with($zeile, 'Parlamentarische Zielvorgaben')) {
                $zvModus = true;
                continue; // die (auf jeder Seite wiederholte) Kopfzeile nicht sammeln
            }
            if ($zvModus) {
                if (
                    str_starts_with($zeile, 'Globalkredit')
                    || str_starts_with($zeile, 'Nettokosten / Globalkredit')
                    || str_contains($zeile, 'Informationsteil')
                ) {
                    $zvModus = false;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    // Laufende Kopf-/Fusszeilen und Deko überspringen; alles andere
                    // (Ziele, Beschreibung, Messgrössen, Wertzeilen) sammeln.
                    if (
                        !str_starts_with($zeile, 'Stadt Winterthur / Budget')
                        && !str_starts_with($zeile, '▼')
                        && !str_contains($zeile, 'Zum Beschluss')
                        && !preg_match('/\(\d{3}\)\s*$/u', $zeile)
                    ) {
                        $nachCode[$aktuellCode]['_zvZeilen'][] = $zeile;
                    }
                    continue;
                }
            }
            // Kostentabelle im Informationsteil (F109): ihre Kopfzeile trägt die
            // «in%»-Spalten und unterscheidet sie so von der Beschluss-Globalkredit-
            // Zeile. Die Zeilen (Personalkosten, Sachkosten …) werden für die
            // Antrags-Aufteilung gesammelt.
            if (preg_match('/^Nettokosten \/ Globalkredit\s+Ist\s+\d+\s+in%/u', $zeile)) {
                $kostenModus = true;
                continue;
            }
            if ($kostenModus) {
                if (
                    str_starts_with($zeile, 'Stellenplan')
                    || str_starts_with($zeile, 'Kostendeckungsgrad')
                    || str_starts_with($zeile, 'Erläuterungen zum Stellenplan')
                ) {
                    $kostenModus = false;
                } elseif (
                    !str_starts_with($zeile, 'Stadt Winterthur / Budget')
                    && !str_starts_with($zeile, '▼')
                    && !str_contains($zeile, 'Informationsteil')
                    && !preg_match('/\(\d{3}\)\s*$/u', $zeile)
                ) {
                    $nachCode[$aktuellCode]['_kostenZeilen'][] = $zeile;
                }
                // KEIN continue: die Zeile fällt zu fuelleZeile durch, damit Aufwand
                // und Ertrag (Total effektive Kosten/Erlöse) weiterhin gelesen werden.
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
                    $nachCode[$aktuellCode][$textFeld] = $this->erlaeuterungAnfuegen(
                        (string) $nachCode[$aktuellCode][$textFeld],
                        $zeile
                    );
                    continue;
                }
            }
            // Produkt-Überschrift „Produkt <N> <Name>“ (Produkte als Information,
            // F80). Leistungstext wie „… (zu Gunsten Produkt 1)“ beginnt nicht am
            // Zeilenanfang mit „Produkt <Ziffer> <Text>“ und wird nicht getroffen.
            if (preg_match('/^Produkt\s+(\d+)\s+(\S.*)$/u', $zeile, $pm)) {
                $nummer = (int) $pm[1];
                // Der Anhang («Gliederung von Budget und Jahresrechnung») listet am
                // Buchende jede Produktegruppe mit ihren Produkten noch einmal ohne
                // Zahlen. Ein Produkt mit schon vorhandener Nummer wird darum nicht
                // erneut angelegt (sonst erschiene es doppelt, das zweite ohne Werte).
                $bekannt = false;
                foreach ($nachCode[$aktuellCode]['produkte'] as $vorhanden) {
                    if ($vorhanden['nummer'] === $nummer) { $bekannt = true; break; }
                }
                if (!$bekannt) {
                    $nachCode[$aktuellCode]['produkte'][] = [
                        'nummer' => $nummer,
                        'name' => trim($pm[2]),
                        'nettokosten' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
                        'kostentabelle' => [],
                        'leistungen' => [],
                    ];
                }
                $leistungenAktiv = false;
                $produktKostenModus = false;
                continue;
            }
            // Leistungen eines Produkts (Information, F110): die Aufzählung zwischen
            // «Leistungen» und der Nettokosten-Tabelle des Produkts.
            if ($zeile === 'Leistungen' && $nachCode[$aktuellCode]['produkte'] !== []) {
                $leistungenAktiv = true;
                $leistungBullet = false;
                continue;
            }
            if ($leistungenAktiv) {
                if (
                    str_starts_with($zeile, 'Nettokosten')
                    || str_starts_with($zeile, 'Operative Ziele')
                    || str_starts_with($zeile, 'Leistungsmengen')
                    || str_starts_with($zeile, 'Kostendeckungsgrad')
                ) {
                    $leistungenAktiv = false;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    // Ein Bullet-Glyph am Zeilenanfang (smalot liefert z.B. U+F0A7)
                    // markiert eine NEUE Leistung und wird entfernt. Eine Zeile ohne
                    // Glyph ist die Fortsetzung der vorigen (Umbruch im Buch) und wird
                    // angehängt. Steht der Glyph allein auf der Zeile, folgt der Text
                    // erst danach — das Flag überträgt den Neubeginn auf die nächste Zeile.
                    $neu = preg_match('/^[^\p{L}\d]/u', $zeile) === 1;
                    $text = trim((string) preg_replace('/^[^\p{L}\d]+/u', '', $zeile));
                    $letzter = \count($nachCode[$aktuellCode]['produkte']) - 1;
                    $liste = &$nachCode[$aktuellCode]['produkte'][$letzter]['leistungen'];
                    if ($text === '') {
                        $leistungBullet = true;
                    } elseif ($neu || $leistungBullet || $liste === []) {
                        $liste[] = $text;
                        $leistungBullet = false;
                    } else {
                        $liste[\count($liste) - 1] = trim($liste[\count($liste) - 1] . ' ' . $text);
                    }
                    unset($liste);
                    continue;
                }
            }
            // Produkt-Kostentabelle: die Kopfzeile „Nettokosten Ist … Soll …“ eines
            // Produkts (nicht die PG-Globalkredit-Kopfzeile mit „Globalkredit“/„in%“)
            // startet die Sammlung der Kostenzeilen (Kosten, Erlös, Nettokosten,
            // Kostendeckungsgrad) des zuletzt gelesenen Produkts — als Budgetzahlen in
            // der Produkt-Karte (F110).
            if (
                $nachCode[$aktuellCode]['produkte'] !== []
                && str_starts_with($zeile, 'Nettokosten')
                && !str_contains($zeile, 'Globalkredit')
                && !str_contains($zeile, 'in%')
                && preg_match('/\b(Ist|Soll)\b/u', $zeile)
            ) {
                $produktKostenModus = true;
                continue;
            }
            if ($produktKostenModus) {
                // Eine Kostenzeile ist „<Label> <Zahl> <Zahl> <Zahl>“ (der Label darf
                // ein „in %“ tragen); jede andere Zeile beendet die Tabelle.
                if (preg_match('/^(\p{L}.*?)\s+((?:-?[\d’\'.,]+\s+){2}-?[\d’\'.,]+)\s*$/u', $zeile, $km)) {
                    $werte = $this->zahlen($km[2]);
                    if (\count($werte) >= 3) {
                        $letzter = \count($nachCode[$aktuellCode]['produkte']) - 1;
                        $label = trim($km[1]);
                        $nachCode[$aktuellCode]['produkte'][$letzter]['kostentabelle'][] = [
                            'label' => $label,
                            'werte' => [$werte[0], $werte[1], $werte[2]],
                        ];
                        if ($label === 'Nettokosten') {
                            $nachCode[$aktuellCode]['produkte'][$letzter]['nettokosten'] = [
                                'ist' => $werte[0], 'sollVorjahr' => $werte[1], 'soll' => $werte[2],
                            ];
                        }
                        continue;
                    }
                }
                $produktKostenModus = false;
                // fällt durch zur normalen Verarbeitung dieser Zeile
            }
            $this->fuelleZeile($nachCode[$aktuellCode], $zeile);
        }
        $gruppen = [];
        foreach ($reihenfolge as $code) {
            $g = $nachCode[$code];
            $g['zielvorgaben'] = $this->parseZielvorgabenBlock($g['_zvZeilen']);
            $g['kostenzeilen'] = $this->parseKostenzeilen($g['_kostenZeilen']);
            unset($g['_zvZeilen'], $g['_kostenZeilen']);
            $gruppen[] = $g;
        }
        return $gruppen;
    }

    /**
     * Wertet den gesammelten Zielvorgaben-Block einer Produktegruppe aus (F109,
     * WoV). Struktur im Buch: nummerierte Ziele («2 Kundenorientierung …»), je Ziel
     * eine Beschreibung und eine oder mehrere Messgrössen, jede mit einer Wertzeile
     * aus sechs Jahresspalten (Ist Vorjahr, Soll Vorjahr, Soll aktuell, 3× Plan).
     * Das Parlament entscheidet über die Spalte «Soll aktuell» (Index 2). Rein
     * qualitative Ziele (Wertzeile «erfüllt / zu erfüllen …») tragen keine Zahl und
     * werden nicht als beantragbare Zielvorgabe erfasst.
     *
     * @param list<string> $zeilen
     * @return list<array<string, mixed>>
     */
    private function parseZielvorgabenBlock(array $zeilen): array {
        $ziele = [];
        $goalNr = 0;
        $goalTitel = '';
        $titelOffen = false;   // direkt nach dem Zieltitel: eine Wortzeile ist Titel-Fortsetzung
        $mgBuf = null;         // Messgrösse-Label ab «Messgrösse:» bis zur Wertzeile
        $letzteTextzeile = ''; // Rückfall, wenn kein «Messgrösse:» vorausging
        // Wertzeile: sechs aufeinanderfolgende Zahlen (Schweizer Format) am Zeilenende.
        $wert = "[\\d’']+(?:[.,]\\d+)?";
        $wertzeile = '/^(.*?)((?:' . $wert . '\s+){5}' . $wert . ')\s*$/u';
        foreach ($zeilen as $zeile) {
            // Neues Ziel: Zahl + Leerzeichen + Buchstabe (eine Wertzeile beginnt mit
            // einer Zahl gefolgt von einer Zahl und wird hier nicht getroffen).
            if (preg_match('/^(\d{1,2})\s+(\p{L}.*)$/u', $zeile, $gm)) {
                $goalNr = (int) $gm[1];
                $goalTitel = trim($gm[1] . ' ' . $gm[2]);
                $titelOffen = true;
                $mgBuf = null;
                $letzteTextzeile = '';
                continue;
            }
            // Titel-Fortsetzung: das Buch bricht lange Titel um — die unmittelbar
            // folgende einzelne Wortzeile («… zentrales» / «Personalmanagement»)
            // gehört noch zum Titel. Ein Doppelpunkt (Struktur-Label) zählt nicht.
            if ($titelOffen && preg_match('/^\p{L}[^\s:]*$/u', trim($zeile))) {
                $goalTitel = trim($goalTitel . ' ' . trim($zeile));
                continue;
            }
            $titelOffen = false;
            // «Messgrösse:» UND «Messung / Bewertung:» starten je einen Label-Abschnitt.
            // Der Abschnitt, der unmittelbar vor der Wertzeile steht, liefert das Label —
            // so gewinnt bei Ziel 1 (wo «Messgrösse:» vor «Messung / Bewertung:» steht)
            // die kurze Messgrösse statt der langen Beschreibung.
            if (str_starts_with($zeile, 'Messgrösse:')) {
                $mgBuf = trim(substr($zeile, \strlen('Messgrösse:')));
                $letzteTextzeile = '';
                continue;
            }
            if (str_starts_with($zeile, 'Messung / Bewertung:')) {
                $mgBuf = trim(substr($zeile, \strlen('Messung / Bewertung:')));
                $letzteTextzeile = '';
                continue;
            }
            if (preg_match($wertzeile, $zeile, $wm)) {
                $werte = preg_split('/\s+/u', trim($wm[2])) ?: [];
                if (\count($werte) === 6 && $goalNr > 0) {
                    $vorne = trim($wm[1]);
                    $label = $vorne !== ''
                        ? $vorne
                        : (($mgBuf !== null && $mgBuf !== '') ? $mgBuf : $letzteTextzeile);
                    // Führende Aufzählungs-/Störglyphen (z.B. ein Bullet aus dem PDF)
                    // vor der Messgrösse entfernen.
                    $label = trim((string) preg_replace('/^[^\p{L}\d]+/u', '', trim($label)));
                    $ziele[] = [
                        'zielNummer' => $goalNr,
                        'zielTitel' => $goalTitel,
                        'messgroesse' => $label,
                        'werte' => $werte,
                        'soll' => $werte[2],
                    ];
                    // Nach der Wertzeile ist kein Abschnitt offen: die nächste Textzeile
                    // gehört zum Rückfall (eine zweite Messgrösse ohne «Messgrösse:»-Kopf).
                    $mgBuf = null;
                    $letzteTextzeile = '';
                }
                continue;
            }
            // Gewöhnliche Textzeile: im offenen Abschnitt ans Messgrösse-Label anhängen,
            // sonst als (mehrzeiligen) Rückfall akkumulieren.
            $text = trim($zeile);
            if ($text === '') {
                continue;
            }
            if ($mgBuf !== null) {
                $mgBuf = trim($mgBuf . ' ' . $text);
            } else {
                $letzteTextzeile = trim($letzteTextzeile . ' ' . $text);
            }
        }
        return $ziele;
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
            // Parlamentarische Zielvorgaben (WoV, F109): je Messgrösse ein Eintrag
            // mit Ziel-Nummer, Ziel-Titel, Messgrösse und den sechs Jahresspalten.
            'zielvorgaben' => [],
            // Kostenzeilen der Kostentabelle im Informationsteil (F109): je Zeile
            // ein Label und der Soll-Wert des Budgetjahres — für die Antrags-Aufteilung.
            'kostenzeilen' => [],
            // Sammelpuffer; nach dem Body-Loop geparst und wieder entfernt.
            '_zvZeilen' => [],
            '_kostenZeilen' => [],
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
     * Wertet die Kostentabelle des Informationsteils aus (F109): je Zeile ein Label
     * (Personalkosten, Sachkosten …) und der Soll-Wert des Budgetjahres. Die
     * Spaltenfolge ist Ist, in%, Soll Vorjahr, in%, Soll aktuell, in%, 3× Plan — der
     * Soll-Wert steht also an Zahlenindex 4. Summen- und Verrechnungszeilen sind
     * keine beantragbaren Positionen und werden weggelassen. Umbrochene Labels
     * («Kalk. Abschreibungen und» / «Zinsen / Finanzaufwand») werden zusammengeführt.
     *
     * @param list<string> $zeilen
     * @return list<array{label:string,soll:int}>
     */
    private function parseKostenzeilen(array $zeilen): array {
        $ausschluss = [
            'Kosten inkl. Verrechnung', 'Total effektive Kosten', 'Verrechnungen innerhalb PG',
            'Erlöse inkl. Verrechnung', 'Total effektive Erlöse', 'Total Nettokosten', 'Nettokosten / Globalkredit',
        ];
        $out = [];
        $labelPuffer = '';
        foreach ($zeilen as $z) {
            $z = trim($z);
            if ($z === '') {
                continue;
            }
            // Zeile mit Label und/oder Zahlen am Ende.
            if (preg_match('/^(.*?)\s*((?:[-\d’\'][\d’\'.\s%]*)?)$/u', $z, $m) && trim($m[2]) !== '') {
                $vorne = trim($m[1]);
                // Reine Zahlenzeile → Werte zum gepufferten Label.
                if ($vorne === '' || preg_match('/^[-\d’\'.]+$/u', $vorne)) {
                    $label = trim($labelPuffer);
                } else {
                    $label = trim($labelPuffer . ' ' . $vorne);
                }
                $labelPuffer = '';
                $zahlen = $this->zahlen($z);
                if ($label === '' || $this->kostenAusschluss($label, $ausschluss) || $zahlen === []) {
                    continue;
                }
                $soll = \count($zahlen) >= 5 ? $zahlen[4] : $zahlen[\count($zahlen) - 1];
                $out[] = ['label' => $label, 'soll' => $soll];
            } else {
                // Reine Textzeile: Label-Fortsetzung puffern.
                $labelPuffer = trim($labelPuffer . ' ' . $z);
            }
        }
        return $out;
    }

    /** @param list<string> $ausschluss */
    private function kostenAusschluss(string $label, array $ausschluss): bool {
        foreach ($ausschluss as $a) {
            if (str_starts_with($label, $a)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hängt eine Zeile an einen Erläuterungstext an und erhält dabei die Struktur
     * (F110): ein Aufzählungspunkt, ein Jahres-Zwischentitel («2027 (…)») oder eine
     * Titelzeile mit Doppelpunkt beginnt eine neue Zeile; alles andere setzt den
     * vorherigen (im Buch hart umbrochenen) Satz fort und wird mit Leerzeichen
     * angefügt. So bleiben Absätze und Listen erhalten statt zu einem Block zu
     * verschmelzen.
     */
    private function erlaeuterungAnfuegen(string $vorher, string $zeile): string {
        $zeile = trim($zeile);
        if ($vorher === '') {
            return $zeile;
        }
        $neueZeile = (bool) preg_match('/^[-–•]/u', $zeile)
            || (bool) preg_match('/^\d{4}\s*\(/u', $zeile)
            || (bool) preg_match('/^\p{Lu}[^.:]{0,48}:$/u', $zeile);
        return $vorher . ($neueZeile ? "\n" : ' ') . $zeile;
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
        // Offizielles Gesamtergebnis der Erfolgsrechnung (F89): «Das Ergebnis in der
        // Erfolgsrechnung wird mit einem Ertragsüberschuss von 113,8 Millionen Franken
        // …». Ertragsüberschuss = Überschuss (positiv), Aufwandüberschuss = Defizit
        // (negativ). Ist der Massstab, gegen den die errechnete Summe geprüft wird.
        $gesamtergebnis = 0;
        if (preg_match('/Erfolgsrechnung wird mit einem\s+(Ertragsüberschuss|Aufwandüberschuss) von\s+([\d’\'., ]+?)\s+Millionen/u', $text, $m)) {
            $betrag = $this->millionenZuFranken($m[2]);
            $gesamtergebnis = $m[1] === 'Aufwandüberschuss' ? -$betrag : $betrag;
        }
        // Gesamt-Aufwand und -Ertrag aus dem gestuften Erfolgsausweis (betrieblich +
        // Finanzierung + ausserordentlich), jeweils die BU-Spalte (erster Wert). Sie
        // sind die massgeblichen Totale (ohne interne Verrechnung), aus denen sich das
        // Gesamtergebnis ergibt (Ertrag − Aufwand).
        $aufwandLabels = ['Betrieblicher Aufwand', 'Finanzaufwand', 'Ausserordentlicher Aufwand'];
        $ertragLabels = ['Betrieblicher Ertrag', 'Finanzertrag', 'Ausserordentlicher Ertrag'];
        $totalAufwand = $this->mioSpalteNachLabels($text, $aufwandLabels, 0);
        $totalErtrag = $this->mioSpalteNachLabels($text, $ertragLabels, 0);
        // Vorjahr (zweite Wertspalte, BU des Vorjahres) für die Differenz-Anzeige.
        $totalAufwandVorjahr = $this->mioSpalteNachLabels($text, $aufwandLabels, 1);
        $totalErtragVorjahr = $this->mioSpalteNachLabels($text, $ertragLabels, 1);
        return [
            'steuerfuss' => $steuerfuss,
            'steuerertrag' => $steuerertrag,
            'personalsteuer' => $personalsteuer,
            'gesamtergebnis' => $gesamtergebnis,
            'totalAufwand' => $totalAufwand,
            'totalErtrag' => $totalErtrag,
            'totalAufwandVorjahr' => $totalAufwandVorjahr,
            'totalErtragVorjahr' => $totalErtragVorjahr,
            'investitionen' => $this->parseInvestitionen($text),
        ];
    }

    /**
     * Summiert je Beschriftung den Millionen-Wert der (0-basierten) Wertspalte aus
     * dem gestuften Erfolgsausweis (Spalte 0 = Budgetjahr, 1 = Vorjahr).
     *
     * @param list<string> $labels
     */
    private function mioSpalteNachLabels(string $text, array $labels, int $spalte): int {
        $summe = 0;
        foreach ($labels as $label) {
            if (preg_match('/' . preg_quote($label, '/') . '\s+((?:-?[\d’\'.,]+\s+){0,5}-?[\d’\'.,]+)/u', $text, $m)) {
                $werte = preg_split('/\s+/', trim($m[1])) ?: [];
                if (isset($werte[$spalte])) {
                    $summe += $this->millionenZuFranken($werte[$spalte]);
                }
            }
        }
        return $summe;
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
