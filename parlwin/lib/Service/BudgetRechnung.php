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
     *        bereich (globalbudget|personal|pauschal|steuerfuss|investition),
     *        betragDelta (neg = Kürzung der Ausgaben bzw. Einnahmen), stellenDelta.
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
            } elseif ($bereich === 'investition') {
                // Investitionen fliessen nicht in die Erfolgsrechnung-Summe ein.
                continue;
            } else {
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
     * Verteilt einen Gesamtbetrag anteilig zum Aufwand auf die Gruppen. Der
     * Rundungsrest geht an die Gruppe mit dem grössten Aufwand, damit die Summe
     * exakt bleibt.
     *
     * @param array<int, array<string, float|int|string>> $gruppen
     * @return array<string, int> code => Delta
     */
    public static function verteileAnteiligAufwand(int $totalDelta, array $gruppen): array {
        $summeAufwand = 0;
        foreach ($gruppen as $g) {
            $summeAufwand += max(0, (int) ($g['aufwandSoll'] ?? 0));
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
            $aufwand = max(0, (int) ($g['aufwandSoll'] ?? 0));
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
