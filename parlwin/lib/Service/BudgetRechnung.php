<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Reine Budget-Rechenlogik ohne Nextcloud-Abhängigkeiten — damit sie isoliert
 * testbar ist. Beträge in ganzen Franken (int), Stellen als Fliesskomma.
 *
 * Fachliche Entscheidungen (siehe FEATURES F79/F83–F85/F88, CONTRIBUTING):
 * - Pauschalverteilung anteilig zum Aufwand (Total effektive Kosten).
 * - Steuerprozent-Wert = Steuerertrag / geltender Steuerfuss (aus dem Buch).
 * - Automatik verteilt nur Kürzungen (negative Deltas), nie Mehrausgaben.
 */
class BudgetRechnung {
    /**
     * Summenzeile über die (gefilterten) Produktegruppen inklusive aller Anträge.
     *
     * @param array<int, array<string, float|int|string>> $gruppen  je Gruppe:
     *        aufwandSoll, ertragSoll, stellenSoll und *Vorjahr-Pendants.
     * @param array<int, array<string, float|int|string>> $antraege je Antrag:
     *        bereich (globalbudget|personal|pauschal|steuerfuss|investition|
     *        departementssekretariat|verpflichtungskredit|bewilligungsorgan),
     *        betragDelta (neg = Kürzung der Ausgaben bzw. Einnahmen), stellenDelta.
     *
     * Die künstliche Produktegruppe (F89) schliesst die Differenz zwischen den
     * operativen Produktegruppen (brutto inkl. interner Verrechnung) und dem
     * deklarierten Gesamtergebnis; sie ist eine reguläre Zeile in $gruppen, darum
     * ergibt Σ aller Produktegruppen ohne Sonderbehandlung das deklarierte Total.
     *
     * @return array<string, float|int>
     */
    public static function summen(array $gruppen, array $antraege): array {
        $ausgaben = 0;
        $einnahmen = 0;
        $ausgabenVorjahr = 0;
        $einnahmenVorjahr = 0;
        $stellen = 0.0;
        $stellenVorjahr = 0.0;
        foreach ($gruppen as $g) {
            $ausgaben += (int) ($g['aufwandSoll'] ?? 0);
            $einnahmen += (int) ($g['ertragSoll'] ?? 0);
            $ausgabenVorjahr += (int) ($g['aufwandVorjahr'] ?? 0);
            $einnahmenVorjahr += (int) ($g['ertragVorjahr'] ?? 0);
            $stellen += (float) ($g['stellenSoll'] ?? 0);
            $stellenVorjahr += (float) ($g['stellenVorjahr'] ?? 0);
        }
        foreach ($antraege as $a) {
            $bereich = (string) ($a['bereich'] ?? '');
            $betrag = (int) ($a['betragDelta'] ?? 0);
            if ($bereich === 'steuerfuss') {
                $einnahmen += $betrag;
            } elseif ($bereich === 'investition' || $bereich === 'verpflichtungskredit') {
                // Investitionen und Verpflichtungskredite (F135) stehen in der
                // Investitionsrechnung und fliessen nicht in die Summe der
                // Erfolgsrechnung ein.
                continue;
            } elseif ($bereich === 'bewilligungsorgan') {
                // F137: Ein Antrag auf das Bewilligungsorgan ändert die
                // Zuständigkeit für einen Kredit, keinen Betrag.
                continue;
            } else {
                // F136: Ein Antrag auf ein Departementssekretariat zählt wie ein
                // Antrag auf den Aufwand: Die Kosten des Sekretariats werden
                // über Umlagen auf die Globalkredite der Produktegruppen
                // verteilt und stecken damit in der Erfolgsrechnung.
                $ausgaben += $betrag;
                $stellen += (float) ($a['stellenDelta'] ?? 0);
            }
        }
        $ergebnis = $einnahmen - $ausgaben;
        $ergebnisVorjahr = $einnahmenVorjahr - $ausgabenVorjahr;
        return [
            'ausgaben' => $ausgaben,
            'einnahmen' => $einnahmen,
            'ergebnis' => $ergebnis,
            'stellen' => $stellen,
            'ausgabenVorjahr' => $ausgabenVorjahr,
            'einnahmenVorjahr' => $einnahmenVorjahr,
            'ergebnisVorjahr' => $ergebnisVorjahr,
            'stellenVorjahr' => $stellenVorjahr,
            'ausgabenDiff' => $ausgaben - $ausgabenVorjahr,
            'einnahmenDiff' => $einnahmen - $einnahmenVorjahr,
            'ergebnisDiff' => $ergebnis - $ergebnisVorjahr,
            'stellenDiff' => $stellen - $stellenVorjahr,
        ];
    }

    /**
     * Benötigter Gesamt-Delta (Kürzung, wenn negativ), um vom aktuellen Ergebnis
     * auf das Ziel zu kommen. Ziel: schwarze Null = 0, sonst der (vorzeichen-
     * behaftete) Zielbetrag (Defizit negativ, gewünschter Ertrag positiv).
     */
    public static function benoetigterDelta(int $ergebnis, string $zielModus, int $zielBetrag): int {
        $ziel = $zielModus === 'schwarze_null' ? 0 : $zielBetrag;
        return $ergebnis - $ziel;
    }

    /**
     * Der Aufwand einer Gruppe, der die Bezugsgrösse der Verteilung bildet: der
     * beeinflussbare Anteil, wo das Buch die Kosten gliedert, sonst der ganze
     * Aufwand (F128).
     *
     * Marc am 24.10.2026: «Vor allem sollte sich der kürzbare Anteil,
     * insbesondere bei der automatischen Verteilung prozentual, auf den
     * flexiblen Teil beschränken. […] Ausser bei der automatischen Verteilung,
     * da soll nur auf den flexiblen Anteil verteilt werden.»
     *
     * @param array<string, mixed> $gruppe
     */
    public static function verteilbarerAufwand(array $gruppe): int {
        $aufwand = max(0, (int) ($gruppe['aufwandSoll'] ?? 0));
        $zeilen = $gruppe['kostenzeilen'] ?? [];
        if (!\is_array($zeilen) || $zeilen === []) {
            return $aufwand;
        }
        return BudgetSteuerbarkeit::beeinflussbarerAufwand($zeilen, $aufwand);
    }

    /**
     * Verteilt einen Gesamtbetrag anteilig zum beeinflussbaren Aufwand auf die
     * Gruppen (F128). Der Rundungsrest geht an die Gruppe mit dem grössten
     * beeinflussbaren Aufwand, damit die Summe exakt bleibt.
     *
     * @param array<int, array<string, float|int|string>> $gruppen
     * @return array<string, int> code => Delta
     */
    public static function verteileAnteiligAufwand(int $totalDelta, array $gruppen): array {
        $summeAufwand = 0;
        foreach ($gruppen as $g) {
            $summeAufwand += self::verteilbarerAufwand($g);
        }
        $verteilung = [];
        if ($summeAufwand <= 0) {
            foreach ($gruppen as $g) {
                $verteilung[(string) $g['code']] = 0;
            }
            return $verteilung;
        }
        $zugeteilt = 0;
        $groessterCode = null;
        $groessterAufwand = -1;
        foreach ($gruppen as $g) {
            $code = (string) $g['code'];
            $aufwand = self::verteilbarerAufwand($g);
            $delta = (int) round($totalDelta * $aufwand / $summeAufwand);
            $verteilung[$code] = $delta;
            $zugeteilt += $delta;
            if ($aufwand > $groessterAufwand) {
                $groessterAufwand = $aufwand;
                $groessterCode = $code;
            }
        }
        $rest = $totalDelta - $zugeteilt;
        if ($rest !== 0 && $groessterCode !== null) {
            $verteilung[$groessterCode] += $rest;
        }
        return $totalDelta < 0 ? self::hoechstensAufNull($verteilung, $gruppen) : $verteilung;
    }

    /**
     * Die automatische Verteilung kürzt eine Position höchstens um ihren
     * beeinflussbaren Aufwand (F128) — sie ist die einzige Stelle mit einer
     * harten Grenze, ein Antrag von Hand kennt keine (F112). Was eine Position
     * darüber hinaus tragen müsste, geht an die übrigen, solange dort Platz
     * ist; reicht auch das nicht, bleibt der Rest liegen. Erfunden wird nichts:
     * die Summe der Verteilung ist dann kleiner als der gewünschte Betrag, und
     * das ist im Budget sichtbar.
     *
     * @param array<string, int> $verteilung
     * @param array<int, array<string, float|int|string>> $gruppen
     * @return array<string, int>
     */
    private static function hoechstensAufNull(array $verteilung, array $gruppen): array {
        // Die Grenze ist der beeinflussbare Aufwand (F128): mehr als das lässt
        // eine automatische Verteilung an einer Position nicht kürzen.
        $grenze = [];
        foreach ($gruppen as $g) {
            $grenze[(string) $g['code']] = self::verteilbarerAufwand($g);
        }
        // Immer wieder kappen und den Überhang auf die Positionen mit Platz
        // verteilen, bis nichts mehr umzulegen ist.
        for ($runde = 0; $runde < \count($verteilung) + 1; $runde++) {
            $ueberhang = 0;
            foreach ($verteilung as $code => $delta) {
                $zuviel = -$delta - ($grenze[$code] ?? 0);
                if ($delta < 0 && $zuviel > 0) {
                    $verteilung[$code] = -($grenze[$code] ?? 0);
                    $ueberhang += $zuviel;
                }
            }
            if ($ueberhang === 0) {
                return $verteilung;
            }
            $platz = [];
            foreach ($verteilung as $code => $delta) {
                $frei = ($grenze[$code] ?? 0) + $delta;
                if ($frei > 0) {
                    $platz[$code] = $frei;
                }
            }
            $summePlatz = array_sum($platz);
            if ($summePlatz <= 0) {
                return $verteilung; // nirgends mehr Platz — der Rest bleibt liegen
            }
            foreach ($platz as $code => $frei) {
                $verteilung[$code] -= (int) round(min($ueberhang, $ueberhang * $frei / $summePlatz));
            }
        }
        return $verteilung;
    }

    /**
     * Automatische Steuerfuss-Senkung: senkt den Steuerfuss in ganzen Prozent-
     * Schritten (abgerundet), solange der Überschuss reicht. 1 Steuerprozent =
     * Steuerertrag / geltender Steuerfuss.
     *
     * @return array{wertProProzent:int, gesenkteProzent:int, neuerSteuerfuss:int, reduktion:int}
     */
    public static function steuerfussSenkung(int $ueberschuss, int $steuerertrag, int $steuerfuss): array {
        $wertProProzent = $steuerfuss > 0 ? intdiv($steuerertrag, $steuerfuss) : 0;
        if ($ueberschuss <= 0 || $wertProProzent <= 0) {
            return [
                'wertProProzent' => $wertProProzent,
                'gesenkteProzent' => 0,
                'neuerSteuerfuss' => $steuerfuss,
                'reduktion' => 0,
            ];
        }
        $gesenkteProzent = min($steuerfuss, intdiv($ueberschuss, $wertProProzent));
        return [
            'wertProProzent' => $wertProProzent,
            'gesenkteProzent' => $gesenkteProzent,
            'neuerSteuerfuss' => $steuerfuss - $gesenkteProzent,
            'reduktion' => $gesenkteProzent * $wertProProzent,
        ];
    }
}
