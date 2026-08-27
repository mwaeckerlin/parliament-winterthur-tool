<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Parst das «Drehbuch zur Budgetbehandlung» (Beilage der Budgetsitzung) — die
 * Quelle für zwei Dinge (F90/F91):
 *
 *  - die **Sitzungsanträge**: die tatsächlich in der Sitzung behandelten
 *    Kommissions- und Fraktionsanträge je Produktegruppe (Quelle, Richtung,
 *    Betrag, Begründung, Abstimmungsergebnis).
 *  - den **Novemberbrief**: die Spalte «NB» der Nettokosten-Tabelle je
 *    Produktegruppe (Korrekturen des Stadtrats vom November). Fehlt die Spalte
 *    (kein Novemberbrief in dem Jahr), liefert der Parser hier eine leere Liste.
 *
 * Das Drehbuch ist ein ungetaggtes PDF, dessen Tabellen im reinen Textstrom
 * ineinanderlaufen (die NB- und Antrags-Spalten verkleben). Darum arbeitet
 * dieser Parser **koordinatenbasiert**: er nutzt die Positionsdaten von
 * smalot/pdfparser (`Page::getDataTm()`), gruppiert die Textfragmente zeilenweise
 * (nach y) und ordnet sie nach x — so bleiben Spalten getrennt. Die
 * Produktegruppe wird an ihrer sechsstelligen Nummer und dem Kommissionshinweis
 * in Klammern erkannt; die Anträge an der festen Formulierung «Antrag <Quelle>:
 * <Erhöhung|Reduktion> … um CHF <Betrag>».
 */
class BudgetDrehbuchParser {
    /** x-Band der Spalte «NB» (zwischen «BU <Jahr> Antrag» und «BU <Jahr> inkl. NB»). */
    private const NB_X_MIN = 400.0;
    private const NB_X_MAX = 450.0;

    /**
     * @return array{antraege: list<array<string, mixed>>, novemberbrief: array{produktegruppen: list<array<string, mixed>>}}
     */
    public function parse(string $pfad, int $jahr): array {
        $rows = $this->rows($pfad);
        $antraege = [];
        $nb = [];
        $code = null;
        // Index des Antrags, dem noch ein Ergebnis fehlt (-1 = keiner offen).
        $offen = -1;
        foreach ($rows as $frags) {
            $text = $this->rowText($frags);
            $neuCode = $this->produktegruppeCode($frags, $text);
            if ($neuCode !== null) {
                $code = $neuCode;
                $offen = -1;
                // NB-Wert dieser Produktegruppe steht erst in der Nettokosten-Zeile.
                continue;
            }
            if ($code === null) {
                continue;
            }
            // Nettokosten-Datenzeile: Novemberbrief-Wert (NB-Spalte).
            if ($this->istNettokostenDaten($frags, $text)) {
                $wert = $this->nbWert($frags);
                if ($wert !== null) {
                    $nb[] = ['code' => $code, 'nettokostenNb' => $wert];
                }
                continue;
            }
            // Abstimmungsergebnis (z.B. «11:0 angenommen») — dem zuletzt offenen
            // Antrag zuordnen; steht immer direkt nach dessen Begründung.
            if (preg_match('/(\d+)\s*:\s*(\d+)\s+(angenommen|abgelehnt)/u', $text, $em)) {
                if ($offen >= 0) {
                    $antraege[$offen]['ergebnis'] = $em[1] . ':' . $em[2] . ' ' . $em[3];
                    $offen = -1;
                }
                continue;
            }
            // Antrag «Antrag <Quelle>: <Erhöhung|Reduktion> [des] Globalkredit[s] um CHF <Betrag>».
            if (preg_match('/Antrag\s+(?:der\s+)?([A-ZÄÖÜ][^:]{0,40}?)\s*:\s*(Erhöhung|Reduktion)\s+(?:des\s+)?Globalkredit(?:s|es)?\s+um\s+CHF\s+([\d\'’]+)/u', $text, $am, PREG_OFFSET_CAPTURE)) {
                $betrag = (int) str_replace(["'", '’'], '', $am[3][0]);
                $delta = $am[2][0] === 'Reduktion' ? -$betrag : $betrag;
                $steller = trim($am[1][0]);
                // Begründung: der Text NACH dem Antragssatz auf derselben Zeile
                // (die Begründung steht oft inline hinter «um CHF …»). Der Antragssatz
                // beginnt nicht zwingend am Zeilenanfang (davor steht oft «Begründung
                // Kommission»), darum wird ab dem Match-Ende geschnitten.
                $rest = trim((string) substr($text, $am[0][1] + strlen($am[0][0])));
                $antraege[] = [
                    'code' => $code,
                    'bereich' => 'globalbudget',
                    'antragsteller' => $steller,
                    'gremium' => $this->gremium($steller),
                    'betragDelta' => $delta,
                    'begruendung' => $this->saeubereBegruendung($rest),
                    'ergebnis' => '',
                ];
                $offen = \count($antraege) - 1;
                continue;
            }
            // Fliesstext zwischen Antrag und Ergebnis: an die Begründung anhängen.
            if ($offen >= 0 && $this->istBegruendungstext($frags, $text)) {
                $stueck = $this->saeubereBegruendung($text);
                if ($stueck !== '') {
                    $antraege[$offen]['begruendung'] = trim($antraege[$offen]['begruendung'] . ' ' . $stueck);
                }
            }
        }
        return [
            'antraege' => $antraege,
            'novemberbrief' => ['produktegruppen' => $nb],
        ];
    }

    /**
     * Zeilen des PDF in Lesereihenfolge: je Seite von oben nach unten, je Zeile
     * die Fragmente von links nach rechts. Ein Fragment ist ['x'=>float,'t'=>string].
     *
     * @return list<list<array{x: float, t: string}>>
     */
    private function rows(string $pfad): array {
        $out = [];
        foreach ($this->pages($pfad) as $page) {
            $byY = [];
            foreach ($page->getDataTm() as $r) {
                $m = $r[0];
                $x = isset($m[4]) ? (float) $m[4] : 0.0;
                $y = isset($m[5]) ? (float) $m[5] : 0.0;
                $key = (int) round($y / 4);
                $byY[$key][] = ['x' => $x, 't' => (string) $r[1]];
            }
            krsort($byY);
            foreach ($byY as $frags) {
                usort($frags, static fn ($a, $b) => $a['x'] <=> $b['x']);
                $out[] = array_values($frags);
            }
        }
        return $out;
    }

    /**
     * Die Seiten des PDF über smalot/pdfparser. Gleiche Bibliothek wie
     * BudgetBuchParser; hier werden zusätzlich die Positionsdaten gebraucht.
     *
     * @return array<int, object>
     */
    private function pages(string $pfad): array {
        $klasse = 'Smalot\\PdfParser\\Parser';
        if (!class_exists($klasse)) {
            throw new \RuntimeException(
                'PDF-Bibliothek smalot/pdfparser nicht verfügbar — im Image via Composer installieren'
            );
        }
        /** @var object $parser */
        $parser = new $klasse();
        $dokument = $parser->parseFile($pfad);
        return $dokument->getPages();
    }

    /** @param list<array{x: float, t: string}> $frags */
    private function rowText(array $frags): string {
        $teile = array_map(static fn ($f) => $f['t'], $frags);
        return trim(preg_replace('/\s+/u', ' ', implode(' ', $teile)) ?? '');
    }

    /**
     * Erkennt den Produktegruppen-Kopf und liefert den dreistelligen PG-Code
     * (aus der sechsstelligen Nummer «121000» → «121»). Der Kopf trägt die Nummer
     * am linken Rand und im selben Zeilenzug den Kommissionshinweis «(AK, …)» —
     * daran unterscheidet er sich von einer gleich aussehenden Konto-Nummer
     * («520000 Software …») in den Verpflichtungskrediten.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function produktegruppeCode(array $frags, string $text): ?string {
        if ($frags === []) {
            return null;
        }
        $erste = trim($frags[0]['t']);
        if ($frags[0]['x'] >= 60.0 || !preg_match('/^(\d{3})\d{3}$/', $erste, $m)) {
            return null;
        }
        if (!preg_match('/\((?:AK|SBK|BSKK|SSK|UBK|GPK|RPK)[,)]/u', $text)) {
            return null;
        }
        return $m[1];
    }

    /**
     * Ob die Zeile die Nettokosten-Datenzeile einer Produktegruppe ist (beginnt
     * mit «Nettokosten» am linken Rand und trägt Beträge) — die Kopfzeile
     * «Nettokosten» der Tabelle trägt keine Zahlen.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function istNettokostenDaten(array $frags, string $text): bool {
        if ($frags === [] || $frags[0]['x'] >= 40.0) {
            return false;
        }
        if (!str_starts_with(trim($frags[0]['t']), 'Nettokosten')) {
            return false;
        }
        return (bool) preg_match('/\d[\d\'’]{2,}/u', $text);
    }

    /**
     * Der Novemberbrief-Wert (Spalte «NB») aus einer Nettokosten-Datenzeile:
     * das Zahlenfragment im NB-x-Band. Fehlt es (kein Novemberbrief für die
     * Produktegruppe), liefert die Methode null.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function nbWert(array $frags): ?int {
        foreach ($frags as $f) {
            if ($f['x'] < self::NB_X_MIN || $f['x'] > self::NB_X_MAX) {
                continue;
            }
            if (preg_match('/^-?\d[\d\'’]*$/u', trim($f['t']), $m)) {
                return (int) str_replace(["'", '’'], '', trim($f['t']));
            }
        }
        return null;
    }

    /** Kommission (AK/SBK/…) oder Fraktion — für die Herkunftsanzeige. */
    private function gremium(string $steller): string {
        return preg_match('/^Fraktion\b/u', $steller) === 1 ? 'fraktion' : 'kommission';
    }

    /**
     * Säubert einen Begründungs-Textteil: entfernt die «Begründung:»-Marke, die
     * Antrags-Sprungmarken («A3», «A 46»), Tabellen-/Quorum-Reste und
     * doppelten Leerraum. Liefert '' für einen reinen Struktur-/Markerrest.
     */
    private function saeubereBegruendung(string $text): string {
        $text = preg_replace('/^.*?Begründung:\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\bBegründung\s+(?:Kommission|Fraktion)\b/u', '', $text) ?? $text;
        $text = preg_replace('/«erhöhtes Quorum»/u', '', $text) ?? $text;
        // Antrags-Sprungmarken «A3»/«A 46» (isoliert, ein- bis dreistellig).
        $text = preg_replace('/\bA\s?\d{1,3}\b/u', '', $text) ?? $text;
        // Ein voller Antragssatz, der versehentlich mitläuft, gehört nicht in die Begründung.
        $text = preg_replace('/Antrag\s+(?:der\s+)?[A-ZÄÖÜ][^:]{0,40}?:\s*(?:Erhöhung|Reduktion)[^.]*\.\s*/u', '', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        // Führende Satzreste (der Punkt hinter dem Betrag) entfernen.
        return (string) preg_replace('/^[\s.,;:]+/u', '', $text);
    }

    /**
     * Ob eine Zeile Fliesstext einer Begründung ist (eingerückt bei x≈150+, keine
     * Tabellen-/Kopfzeile), der an die laufende Begründung angehängt wird.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function istBegruendungstext(array $frags, string $text): bool {
        if ($frags === [] || $frags[0]['x'] < 140.0) {
            return false;
        }
        if ($text === '' || preg_match('/^«erhöhtes Quorum»$/u', $text)) {
            return false;
        }
        return true;
    }
}
