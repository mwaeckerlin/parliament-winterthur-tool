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
 * in Klammern erkannt.
 *
 * **Die Spalten stehen nicht in jedem Jahrgang gleich.** Im Drehbuch 2026 sitzt
 * «NB» zwischen «BU Antrag» und «BU inkl. NB», im Drehbuch 2025 direkt hinter
 * der Rechnungsspalte. Deshalb liest der Parser die Kopfzeilen jeder Tabelle und
 * bestimmt daraus, wo «NB», «Antrag Kom.» und «Antrag Fraktion» stehen.
 *
 * **Auch die Anträge stehen nicht in jedem Jahrgang gleich.** Von 2023 an nennt
 * der Antragssatz Richtung und Betrag («Antrag AK: Reduktion des Globalkredits
 * um CHF 100'000»), mit Abweichungen im Wortlaut: «Globalbudget» statt
 * «Globalkredit», ein Einschub zwischen Gegenstand und Betrag («… des
 * Globalkredits der städtischen Allgemeinkosten um CHF …»), und eine im PDF
 * zerrissene Zahl («250'00 0»). Im Drehbuch 2022 nennt der Satz nur die
 * Begründung, und der Betrag steht ausschliesslich in der Spalte «Antrag Kom.»
 * bzw. «Antrag Fraktion» der Nettokosten-Zeile. Beide Wege sind umgesetzt: der
 * Satz zuerst, die Spalte als Rückfall.
 */
class BudgetDrehbuchParser {
    public function __construct(
        private readonly PdfZeilenLeser $leser = new PdfZeilenLeser(),
    ) {
    }

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
        // Spaltenpositionen der zuletzt gelesenen Tabellenkopfzeile.
        $spalten = [];
        // Beträge aus den Antragsspalten der Nettokosten-Zeile, noch nicht verbraucht.
        $spaltenbetraege = [];
        // Welche der drei Tabellen einer Produktegruppe gerade läuft.
        $tabelle = null;
        foreach ($rows as $i => $frags) {
            $text = $this->rowText($frags);
            $neuCode = $this->produktegruppeCode($frags, $text);
            if ($neuCode !== null) {
                $code = $neuCode;
                $offen = -1;
                $spaltenbetraege = [];
                $tabelle = null;
                // NB-Wert dieser Produktegruppe steht erst in der Nettokosten-Zeile.
                continue;
            }
            // Eine Produktegruppe führt drei Tabellen, jede mit ihrem eigenen
            // Titel: «Nettokosten», «Zielvorgaben / Messgrössen» und
            // «Verpflichtungskredite». Der Betrag aus der Antragsspalte gehört nur
            // zur ersten; sonst bekäme ein Antrag zu einer parlamentarischen
            // Zielvorgabe oder zu einem Verpflichtungskredit denselben Betrag
            // noch einmal. Titel und Kopfzeile können auf einer Höhe liegen,
            // deshalb beide in derselben Zeile.
            $neueTabelle = $this->tabellenTitel($frags, $text);
            if ($neueTabelle !== null) {
                $tabelle = $neueTabelle;
            }
            if ($this->istKopfzeile($text)) {
                $spalten = $this->spalten($rows, $i);
                continue;
            }
            if ($neueTabelle !== null) {
                continue;
            }
            if ($code === null) {
                continue;
            }
            // Nettokosten-Datenzeile: Novemberbrief-Wert (NB-Spalte) und die
            // Beträge der beiden Antragsspalten.
            if ($this->istNettokostenDaten($frags, $text)) {
                $wert = $this->novemberbriefWert($frags, $spalten);
                if ($wert !== null && $wert !== 0) {
                    $nb[] = ['code' => $code, 'nettokostenNb' => $wert];
                }
                $spaltenbetraege = [];
                foreach (['antragKom' => 'kommission', 'antragFraktion' => 'fraktion'] as $spalte => $gremium) {
                    $betrag = $this->wertBeiSpalte($frags, $spalten, $spalte);
                    if ($betrag !== null && $betrag !== 0) {
                        $spaltenbetraege[] = ['betrag' => $betrag, 'gremium' => $gremium];
                    }
                }
                continue;
            }
            // Abstimmungsergebnis (z.B. «11:0 angenommen», «4:4 mit Stichentscheid
            // des Präsidenten angenommen») — dem zuletzt offenen Antrag zuordnen;
            // es steht immer direkt nach dessen Begründung.
            if (preg_match('/(\d+)\s*:\s*(\d+)\s+(?:mit\s+Stichentscheid\s+des\s+Präsidenten\s+)?(angenommen|abgelehnt)/u', $text, $em)) {
                if ($offen >= 0) {
                    $antraege[$offen]['ergebnis'] = $em[1] . ':' . $em[2] . ' ' . $em[3];
                    $offen = -1;
                }
                continue;
            }
            // Antragssatz «Antrag <Quelle>: …».
            $antrag = $this->antragAusSatz($text, $code, $tabelle === 'nettokosten' ? $spaltenbetraege : []);
            if ($antrag !== null) {
                if ($antrag['quelleSpalte']) {
                    array_shift($spaltenbetraege);
                }
                unset($antrag['quelleSpalte']);
                $antraege[] = $antrag;
                $offen = \count($antraege) - 1;
                continue;
            }
            // Fliesstext zwischen Antrag und Ergebnis: an die Begründung anhängen.
            if ($offen >= 0 && $this->istBegruendungstext($frags, $text)) {
                $stueck = $this->saeubereBegruendung($text);
                if ($stueck !== '') {
                    $antraege[$offen]['begruendung'] = trim($antraege[$offen]['begruendung'] . ' ' . $stueck);
                    // Der Antragssatz kann in der Zeile enden («Antrag Fraktion
                    // Grüne/AL:»), und erst die nächste Zeile sagt, worum es geht.
                    // Steht dort der Steuerfuss, ist es kein Antrag auf einen
                    // Globalkredit.
                    if ($this->istSteuerfussAntrag($antraege[$offen]['begruendung'])) {
                        $antraege[$offen]['bereich'] = 'steuerfuss';
                    }
                }
            }
        }
        return [
            'antraege' => $antraege,
            'novemberbrief' => ['produktegruppen' => $nb],
        ];
    }

    /**
     * Baut aus einer Zeile den Antrag, wenn sie einen Antragssatz trägt.
     *
     * Der Betrag steht entweder im Satz («… um CHF 100'000») oder — wenn der Satz
     * keinen nennt — in der Antragsspalte der Nettokosten-Zeile derselben
     * Produktegruppe. Trägt weder Satz noch Spalte einen Betrag, ist es kein
     * Globalbudget-Antrag: ein Antrag zu einer parlamentarischen Zielvorgabe, zu
     * einem Verpflichtungskredit oder zur Befristung einer Stelle.
     *
     * @param list<array{betrag:int, gremium:string}> $spaltenbetraege
     * @return array<string, mixed>|null
     */
    private function antragAusSatz(string $text, string $code, array $spaltenbetraege): ?array {
        if (!preg_match('/Antrag\s+(?:der\s+)?([A-ZÄÖÜ][^:]{0,60}?)\s*:\s*/u', $text, $am, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $steller = trim($am[1][0]);
        // Begründung: der Text NACH dem Antragssatz auf derselben Zeile (die
        // Begründung steht oft inline hinter «um CHF …»). Der Antragssatz beginnt
        // nicht zwingend am Zeilenanfang (davor steht oft «Begründung
        // Kommission»), darum wird ab dem Match-Ende geschnitten.
        $rest = trim((string) substr($text, $am[0][1] + \strlen($am[0][0])));

        $betrag = null;
        $ausSpalte = false;
        if (preg_match(
            '/(Erhöhung|Reduktion|Kürzung)\s+(?:des\s+|der\s+|von\s+)?Global\w*[^.]{0,90}?\bum\s+CHF\s+([\d\'’]+(?:\s+\d{1,3})*(?:[.,]\d+)?)\s*(Mio\.?|Millionen)?/u',
            $rest,
            $bm
        )) {
            // «CHF 123,6 Mio.» ist ein Betrag in Millionen (Drehbuch 2022).
            $inMillionen = ($bm[3] ?? '') !== '';
            $zahl = $inMillionen
                ? (int) round((float) str_replace([' ', "'", '’', ','], ['', '', '', '.'], $bm[2]) * 1_000_000)
                : $this->betragAusText($bm[2]);
            if ($zahl !== null && $zahl !== 0) {
                $betrag = $bm[1] === 'Erhöhung' ? $zahl : -$zahl;
            }
            // Ein in Millionen genannter Betrag ist gerundet; die Antragsspalte
            // führt ihn auf den Franken genau («123,6 Mio.» gegen 123'562'800).
            if ($inMillionen && $betrag !== null && \count($spaltenbetraege) === 1) {
                $genau = $spaltenbetraege[0]['betrag'];
                if (abs($genau - $betrag) < abs($betrag) / 100) {
                    $betrag = $genau;
                }
            }
        }
        if ($betrag === null && $spaltenbetraege !== []) {
            $betrag = $spaltenbetraege[0]['betrag'];
            $ausSpalte = true;
        }
        if ($betrag === null) {
            return null;
        }

        return [
            'code' => $code,
            // Ein Antrag auf einen anderen Steuerfuss ist kein Antrag auf einen
            // Globalkredit, auch wenn seine Wirkung in der Antragsspalte der
            // Produktegruppe «Steuern und Finanzausgleich» steht.
            'bereich' => $this->istSteuerfussAntrag($rest) ? 'steuerfuss' : 'globalbudget',
            'antragsteller' => $steller,
            'gremium' => $ausSpalte ? $spaltenbetraege[0]['gremium'] : $this->gremium($steller),
            'betragDelta' => $betrag,
            'begruendung' => $this->saeubereBegruendung($rest),
            'ergebnis' => '',
            'quelleSpalte' => $ausSpalte,
        ];
    }

    /**
     * Setzt einen im PDF zerrissenen Betrag zusammen: «250'00 0» ist eine Zahl,
     * deren letzte Tausendergruppe über zwei Textstücke läuft. Zusammengefügt
     * muss der Betrag der Schreibweise der Tabelle genügen (Tausendergruppen zu
     * drei Ziffern); sonst gilt der längste Anfang, der ihr genügt.
     */
    private function betragAusText(string $roh): ?int {
        $ziffern = (string) preg_replace('/\s+/u', '', str_replace('’', "'", $roh));
        if (preg_match("/^\d{1,3}(?:'\d{3})*$/", $ziffern) !== 1) {
            $gueltig = '';
            for ($laenge = \strlen($ziffern); $laenge > 0; --$laenge) {
                $anfang = substr($ziffern, 0, $laenge);
                if (preg_match("/^\d{1,3}(?:'\d{3})*$/", $anfang) === 1) {
                    $gueltig = $anfang;
                    break;
                }
            }
            $ziffern = $gueltig;
        }
        if ($ziffern === '') {
            return null;
        }
        return (int) str_replace("'", '', $ziffern);
    }

    /**
     * Zeilen des PDF in Lesereihenfolge: je Seite von oben nach unten, je Zeile
     * die Fragmente von links nach rechts. Ein Fragment ist ['x'=>float,'t'=>string].
     *
     * @return list<list<array{x: float, t: string}>>
     */
    private function rows(string $pfad): array {
        return $this->leser->zeilen($pfad);
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
     * Welche Kommissionen es gibt, ändert sich mit den Legislaturen: Das Drehbuch
     * 2022 nennt die «BBK», die es später nicht mehr gibt. Eine feste Liste liess
     * deren Produktegruppen unerkannt, und ihre Anträge landeten bei der zuletzt
     * erkannten Gruppe — im Drehbuch 2022 acht Anträge bei «Steuern und
     * Finanzausgleich». Erkannt wird deshalb jedes Kürzel aus Grossbuchstaben.
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
        if (!preg_match('/\(\s*[A-ZÄÖÜ]{2,6}\s*[,)]/u', $text)) {
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
     * Der Wert einer Spalte in einer Datenzeile.
     *
     * Zugeordnet wird ein Textstück der Spalte, deren Kopfposition seiner MITTE
     * am nächsten liegt. Die Mitte, nicht der Anfang: Die Beträge stehen
     * rechtsbündig unter ihrer Spalte, die Antragsspalten dagegen linksbündig —
     * wer nach dem Anfang geht, liest die Zahl der Nachbarspalte. Im Drehbuch
     * 2022 stand so der Wert «BU inkl. NB» der Produktegruppe «Städtische
     * Allgemeinkosten» als Novemberbrief-Korrektur da (−44'822 statt 800'346).
     * Die Breite wird aus der Zeichenzahl geschätzt; das PDF gibt sie nicht her.
     *
     * @param list<array{x: float, t: string}> $frags
     * @param array<string, mixed> $spalten
     */
    private function wertBeiSpalte(array $frags, array $spalten, string $name): ?int {
        $x = $spalten[$name] ?? null;
        if ($x === null) {
            return null;
        }
        /** @var list<float> $positionen */
        $positionen = $spalten['positionen'] ?? [];
        // Das PDF zerreisst Beträge («123'562'80» + «0», «- 100'000»): Ist der
        // Wert der Spalte noch keine vollständige Zahl, gehören die folgenden
        // Zahlstücke dazu, auch wenn ihre Position schon zur Nachbarspalte zeigt.
        $text = '';
        $sammelt = false;
        foreach ($frags as $f) {
            $stueck = str_replace(' ', '', trim($f['t']));
            if ($stueck === '') {
                continue;
            }
            if (!$sammelt) {
                if ($this->naechsteSpalte($f, $positionen) !== $x) {
                    continue;
                }
                $sammelt = true;
            } elseif (preg_match('/^[\d\'’]+$/u', $stueck) !== 1) {
                break;
            }
            $text .= $stueck;
            if ($this->istVollstaendigeZahl($text)) {
                break;
            }
        }
        if (preg_match('/^([+-]?)([\d\'’]+)$/u', $text, $m) !== 1) {
            return null;
        }
        $betrag = $this->betragAusText($m[2]);
        if ($betrag === null) {
            return null;
        }
        return $m[1] === '-' ? -$betrag : $betrag;
    }

    /** Ob der Text eine vollständige Zahl in der Schreibweise der Tabelle ist. */
    private function istVollstaendigeZahl(string $text): bool {
        return preg_match('/^[+-]?\d{1,3}(?:[\'’]\d{3})*$/u', $text) === 1;
    }

    /**
     * Die Kopfposition, der ein Textstück am nächsten steht — gemessen an seiner
     * geschätzten Mitte (rund 4,5 Punkt je Zeichen in der Tabellenschrift).
     *
     * @param array{x: float, t: string} $fragment
     * @param list<float> $positionen
     */
    private function naechsteSpalte(array $fragment, array $positionen): ?float {
        if ($positionen === []) {
            return null;
        }
        $mitte = $fragment['x'] + 4.5 * mb_strlen(trim($fragment['t'])) / 2;
        $beste = null;
        $abstand = null;
        foreach ($positionen as $x) {
            $d = abs($x - $mitte);
            if ($abstand === null || $d < $abstand) {
                $abstand = $d;
                $beste = $x;
            }
        }
        // Liegt die nächste Spalte weiter weg als eine halbe Spaltenbreite, gehört
        // das Stück zu keiner: Es steht in einer Spalte, die die Kopfzeile nicht
        // benennt. Lieber kein Wert als der Wert der Nachbarspalte.
        return $abstand !== null && $abstand <= 30.0 ? $beste : null;
    }

    /**
     * Die Novemberbrief-Korrektur einer Produktegruppe aus ihrer Nettokosten-Zeile.
     *
     * Die Tabelle rechnet sie vor: «BU <Jahr> Antrag» + «NB» = «BU <Jahr> inkl.
     * NB». Massgebend ist die Differenz der beiden Nettokosten-Spalten, denn sie
     * belegt sich selbst: Beide Beträge stehen breit und eindeutig in ihrer
     * Spalte, während die schmale NB-Spalte im PDF neben der Zahl der
     * Nachbarspalte liegt und deren Wert als Korrektur ausgäbe. Fehlt eine der
     * beiden Spalten, liefert die Methode nichts — lieber kein Wert als ein
     * falscher.
     *
     * @param list<array{x: float, t: string}> $frags
     * @param array<string, mixed> $spalten
     */
    private function novemberbriefWert(array $frags, array $spalten): ?int {
        $antrag = $this->wertBeiSpalte($frags, $spalten, 'buAntrag');
        $inklNb = $this->wertBeiSpalte($frags, $spalten, 'buInklNb');
        if ($antrag === null || $inklNb === null) {
            return null;
        }
        return $inklNb - $antrag;
    }

    /**
     * Ob die Zeile die Tabellenkopfzeile trägt («Bezeichnung …»). Sie kann im PDF
     * mit dem Tabellentitel darüber auf einer Höhe liegen.
     */
    private function istKopfzeile(string $text): bool {
        return str_contains($text, 'Bezeichnung');
    }

    /**
     * Der Titel einer der drei Tabellen einer Produktegruppe, wenn die Zeile
     * einer ist: «Nettokosten», «Zielvorgaben / Messgrössen»,
     * «Verpflichtungskredite». Der Titel steht allein am linken Rand; die
     * gleichnamige Datenzeile trägt dagegen Beträge.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function tabellenTitel(array $frags, string $text): ?string {
        if ($frags === [] || $frags[0]['x'] >= 60.0) {
            return null;
        }
        // Der Titel steht in der Zeile ganz links. Die Kopfzeile «Bezeichnung …»
        // kann im PDF auf derselben Höhe liegen und mit ihm zu einer Zeile
        // verschmelzen; entscheidend ist deshalb das erste Textstück, nicht die
        // ganze Zeile. Die gleichnamige DATENzeile trägt dagegen Beträge.
        $erstes = trim($frags[0]['t']);
        if (preg_match('/\d[\d\'’]{2,}/u', $text) === 1) {
            return null;
        }
        if ($erstes === 'Nettokosten') {
            return 'nettokosten';
        }
        if (str_starts_with($erstes, 'Zielvorgaben')) {
            return 'zielvorgaben';
        }
        if (str_starts_with($erstes, 'Verpflichtungskredite') || str_starts_with($erstes, 'Kto.-Nr.')) {
            return 'kredite';
        }
        return null;
    }

    /**
     * Die x-Positionen der Spalten «NB», «Antrag Kom.» und «Antrag Fraktion» aus
     * dem Tabellenkopf. Der Kopf steht über zwei bis drei Zeilen («Antrag» oben,
     * «Kom.» darunter); zusammen gehört, was an derselben x-Position steht.
     *
     * @param list<list<array{x: float, t: string}>> $rows
     * @return array<string, float>
     */
    private function spalten(array $rows, int $kopfIndex): array {
        $beschriftungen = [];
        for ($i = $kopfIndex; $i < min($kopfIndex + 4, \count($rows)); ++$i) {
            $text = $this->rowText($rows[$i]);
            if (
                $i > $kopfIndex
                && ($this->istNettokostenDaten($rows[$i], $text) || $this->produktegruppeCode($rows[$i], $text) !== null)
            ) {
                break;
            }
            foreach ($rows[$i] as $f) {
                $schluessel = (string) (int) round($f['x'] / 5);
                $beschriftungen[$schluessel]['x'] = $f['x'];
                $beschriftungen[$schluessel]['text'] = trim(($beschriftungen[$schluessel]['text'] ?? '') . ' ' . $f['t']);
            }
        }

        $spalten = ['positionen' => []];
        foreach ($beschriftungen as $eintrag) {
            $text = trim(preg_replace('/\s+/u', ' ', $eintrag['text']) ?? '');
            $spalten['positionen'][] = $eintrag['x'];
            if ($text === 'NB') {
                $spalten['nb'] = $eintrag['x'];
            } elseif (preg_match('/^BU \d{4}\s*Antrag$/u', $text) === 1) {
                $spalten['buAntrag'] = $eintrag['x'];
            } elseif (preg_match('/^BU \d{4}\s*inkl\.\s*NB$/u', $text) === 1) {
                $spalten['buInklNb'] = $eintrag['x'];
            } elseif (preg_match('/^Antrag Kom\.?$/u', $text) === 1) {
                $spalten['antragKom'] = $eintrag['x'];
            } elseif (preg_match('/^Antrag Fraktion$/u', $text) === 1) {
                $spalten['antragFraktion'] = $eintrag['x'];
            }
        }
        sort($spalten['positionen']);
        return $spalten;
    }

    /** Ob der Antragssatz den Steuerfuss festsetzt («… wird auf 127 Prozent … festgesetzt»). */
    private function istSteuerfussAntrag(string $text): bool {
        return preg_match('/Steuerfuss[^.]{0,120}?\bProzent\b/u', $text) === 1;
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
