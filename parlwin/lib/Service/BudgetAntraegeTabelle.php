<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Die Budgetanträge der Fraktion als Tabelle (F138).
 *
 * Dieselben Anträge, dieselbe Reihenfolge und dieselbe Auswahl wie im PDF; nur
 * steht hier je Antrag eine Zeile mit festen Spalten, damit sich die Liste
 * sortieren, filtern und summieren lässt. Betrag, Stellen und Prozent sind
 * Zahlen, alles andere ist Text.
 */
final class BudgetAntraegeTabelle
{
    /** @var list<string> */
    public const SPALTEN = [
        'Departement',
        'Produktegruppe',
        'Buch S.',
        'Bereich',
        'Betrag',
        'Stellen',
        'Prozent',
        'Neuer Wert',
        'Antragsteller',
        'Haltung',
        'Begründung',
        'Zielvorgaben',
        'Einsparung verteilt',
    ];

    /** Spaltenbreiten in Zeichen, in der Reihenfolge von SPALTEN. */
    public const BREITEN = [20, 34, 8, 20, 14, 9, 9, 18, 22, 13, 60, 44, 36];

    private const BEREICH = [
        'globalbudget' => 'Globalbudget',
        'personal' => 'Personal',
        'investition' => 'Investition',
        'verpflichtungskredit' => 'Verpflichtungskredit',
        'bewilligungsorgan' => 'Bewilligungsorgan',
        'departementssekretariat' => 'Sekretariat',
        'steuerfuss' => 'Steuerfuss',
    ];

    private const HALTUNG = [
        'einreichen' => 'eingereicht',
        'unterstuetzen' => 'unterstützt',
    ];

    /**
     * Kopfzeile und je Antrag eine Zeile.
     *
     * @param array<string, mixed> $daten das Ergebnis von BudgetImportService::antraegePdf
     * @return list<list<string|int|float|null>>
     */
    public static function zeilen(array $daten): array
    {
        $zeilen = [self::SPALTEN];
        $eintraege = is_array($daten['eintraege'] ?? null) ? $daten['eintraege'] : [];
        foreach ($eintraege as $e) {
            $a = is_array($e['antrag'] ?? null) ? $e['antrag'] : [];
            $bereich = (string) ($a['bereich'] ?? '');
            $seite = (int) ($e['buchseite'] ?? 0);
            $stellen = (float) ($a['stellenDelta'] ?? 0);
            $prozent = (float) ($a['prozentDelta'] ?? 0);
            $steller = (string) ($a['antragsteller'] ?? '');
            if (!empty($a['automatisch'])) {
                $steller = trim($steller . ' (automatisch)');
            }
            $zeilen[] = [
                (string) ($e['departement'] ?? ''),
                (string) ($e['produktegruppe'] ?? ''),
                $seite > 0 ? $seite : '',
                self::BEREICH[$bereich] ?? $bereich,
                (int) ($a['betragDelta'] ?? 0),
                $stellen !== 0.0 ? $stellen : '',
                $prozent !== 0.0 ? $prozent : '',
                trim((string) ($a['neuerWert'] ?? '')),
                $steller,
                self::HALTUNG[(string) ($a['haltung'] ?? '')] ?? (string) ($a['haltung'] ?? ''),
                RichText::alsKlartext((string) ($a['begruendung'] ?? '')),
                self::zielvorgaben(is_array($a['zielAenderungen'] ?? null) ? $a['zielAenderungen'] : []),
                self::aufteilung(is_array($a['aufteilung'] ?? null) ? $a['aufteilung'] : []),
            ];
        }
        return $zeilen;
    }

    /**
     * Der Name der Datei und des Blattes: Jahr und, wo gewählt, die Kommission.
     *
     * @param array<string, mixed> $daten
     */
    public static function name(array $daten): string
    {
        $jahr = (int) ($daten['jahr'] ?? 0);
        $kommission = trim((string) ($daten['kommission'] ?? ''));
        return 'Budgetantraege-' . $jahr . ($kommission !== '' ? '-' . self::sauber($kommission) : '');
    }

    /** Ein Dateiname ohne Zeichen, die ein Dateisystem oder ein Blattname nicht trägt. */
    private static function sauber(string $text): string
    {
        $sauber = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        return trim($sauber, '-');
    }

    /**
     * Die Zielvorgaben-Änderungen eines Antrags in einer Zelle, eine je Zeile
     * innerhalb der Zelle (F109).
     *
     * @param list<array<string, mixed>> $aenderungen
     */
    private static function zielvorgaben(array $aenderungen): string
    {
        $teile = [];
        foreach ($aenderungen as $z) {
            $unterTitel = trim((string) ($z['unterTitel'] ?? ''));
            $name = ($unterTitel !== '' ? $unterTitel . ' — ' : '') . (string) ($z['messgroesse'] ?? '');
            $heute = trim((string) ($z['soll'] ?? ''));
            $teile[] = 'Ziel ' . (string) ($z['zielNummer'] ?? '') . ' «' . $name . '»: '
                . ($heute !== '' ? $heute . ' → ' : '') . (string) ($z['neuerWert'] ?? '');
        }
        return implode("\n", $teile);
    }

    /**
     * Die Verteilung einer Einsparung in einer Zelle, eine Stelle je Zeile
     * innerhalb der Zelle (F109).
     *
     * @param list<array<string, mixed>> $aufteilung
     */
    private static function aufteilung(array $aufteilung): string
    {
        $teile = [];
        foreach ($aufteilung as $t) {
            $ebene = (string) ($t['ebene'] ?? '');
            $ref = (string) ($t['ref'] ?? '');
            $ort = match ($ebene) {
                'produkt' => 'Produkt ' . $ref,
                'produkt-kosten' => 'Produkt ' . (string) ($t['produkt'] ?? '') . ', ' . $ref,
                default => $ref,
            };
            $wert = isset($t['betrag'])
                ? number_format((float) $t['betrag'], 0, '.', "'")
                : (isset($t['prozent']) ? $t['prozent'] . '%' : '');
            $teile[] = $ort . ($wert !== '' ? ' (' . $wert . ')' : '');
        }
        return implode("\n", $teile);
    }
}
