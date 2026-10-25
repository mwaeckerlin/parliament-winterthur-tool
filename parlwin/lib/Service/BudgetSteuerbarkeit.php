<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Wie weit eine Kosten- oder Ertragszeile überhaupt beeinflussbar ist (F128).
 *
 * Marc am 24.10.2026: «Vieles sind gebundene Kosten. Manches sind neutrale
 * interne Verrechnungen. Auch bei den Einnahmen sind nur wenige steuerbar, die
 * Steuern, Einnahmen aus wirtschaftlicher Tätigkeit. Selbst Gebühren dürfen nur
 * kostedeckend sein. […] Daher soll es vor allem eine Orientierung sein und
 * nicht eine feste Sperre oder feste Regel. Ausser bei der automatischen
 * Verteilung, da soll nur auf den flexiblen Anteil verteilt werden.»
 *
 * Das Budgetbuch 2027 führt je Produktegruppe dieselben zwölf Zeilen (gemessen
 * am 24.10.2026 über alle Produktegruppen):
 *
 * | Zeile | Stufe |
 * | --- | --- |
 * | Personalkosten, Informatikkosten, Sachkosten, Übrige Kosten | beeinflussbar |
 * | Verkäufe, Übrige externe Erlöse (darin die Steuererträge) | beeinflussbar |
 * | Residualkosten, Mietkosten, Interne Erlöse, Verrechnung innerhalb PG | neutral |
 * | Kalk. Abschreibungen und Zinsen / Finanzaufwand | gebunden |
 * | Beiträge an Dritte, Beiträge von Dritten (darin der Finanzausgleich) | gebunden |
 * | Gebühren, Gebühren / Entgelte | gebunden |
 *
 * Die Muster decken darüber hinaus die Bezeichnungen der Artengliederung von
 * HRM2 ab, damit ein Buch mit anderen Zeilen nicht alles als beeinflussbar
 * zeigt.
 *
 * Die Einstufung ist eine Orientierung und keine Sperre: Sie sagt, wo das
 * Suchen lohnt, und hält niemanden davon ab, einen Antrag auf eine gebundene
 * Position zu stellen — ein solcher Antrag fordert dann eine Rechtsänderung.
 * Bindend ist sie an einer einzigen Stelle: Die automatische Pauschalverteilung
 * verteilt ausschliesslich auf den beeinflussbaren Anteil.
 */
class BudgetSteuerbarkeit
{
    public const GEBUNDEN = 'gebunden';
    public const NEUTRAL = 'neutral';
    public const BEEINFLUSSBAR = 'beeinflussbar';

    /**
     * Die Bezeichnungen des Buchs je Stufe, in der Reihenfolge der Prüfung.
     * Eine Zeile, auf die nichts passt, gilt als beeinflussbar: die Einstufung
     * soll den Spielraum nicht kleiner zeigen, als er ist.
     *
     * @var array<string, list<string>>
     */
    private const MUSTER = [
        self::NEUTRAL => [
            // Die Zeilen, mit denen das Buch innerhalb der Stadt verrechnet:
            // «Residualkosten» sind die umgelegten Kosten der Querschnitts-
            // funktionen, «Mietkosten» die Miete an die Immobilien, «Interne
            // Erlöse» die Gegenbuchung dazu.
            'residualkosten',
            'mietkosten',
            'interne erlöse',
            'interne verrechnung',
            'verrechnung innerhalb',
            'verrechnete leistung',
            'interne leistungsverrechnung',
            'durchlaufende beitr',
        ],
        self::GEBUNDEN => [
            'abschreibung',
            'kalkulatorisch',
            'kalk.',
            'zins',
            'finanzaufwand',
            'finanzertrag',
            'transferaufwand',
            'transferertrag',
            // «Beiträge an Dritte» ist Transferaufwand, «Beiträge von Dritten»
            // Transferertrag — beide sind in Gesetz oder Vertrag bemessen, und
            // unter den Beiträgen von Dritten steht der Finanzausgleich.
            'beiträge an',
            'beitrag an',
            'beiträge von',
            'beitrag von',
            'entschädigung an gemeinwesen',
            'einlage in',
            'entnahme aus',
            'fonds',
            'spezialfinanzierung',
            'gebühr',
            'rückerstattung',
            'bundesbeitr',
            'kantonsbeitr',
            'finanzausgleich',
        ],
    ];

    /** Die Stufe einer Zeile, abgeleitet aus ihrer Bezeichnung im Buch. */
    public static function fuerLabel(string $label): string
    {
        $klein = mb_strtolower(trim($label));
        if ($klein === '') {
            return self::BEEINFLUSSBAR;
        }
        foreach (self::MUSTER as $stufe => $muster) {
            foreach ($muster as $teil) {
                if (str_contains($klein, $teil)) {
                    return $stufe;
                }
            }
        }
        return self::BEEINFLUSSBAR;
    }

    /**
     * Die Kostenzeilen einer Produktegruppe, jede um ihre Stufe ergänzt.
     *
     * @param list<array<string, mixed>> $kostenzeilen
     * @return list<array<string, mixed>>
     */
    public static function mitStufe(array $kostenzeilen): array
    {
        return array_map(
            static function (array $k): array {
                $k['steuerbarkeit'] = self::fuerLabel((string) ($k['label'] ?? ''));
                return $k;
            },
            $kostenzeilen
        );
    }

    /**
     * Die Summen je Stufe über die Kostenzeilen einer Produktegruppe. Gezählt
     * wird der Aufwand, also die positiven Beträge; ein Ertrag steht im Buch mit
     * negativem Vorzeichen und gehört nicht in die Bezugsgrösse einer Kürzung.
     *
     * @param list<array<string, mixed>> $kostenzeilen
     * @return array{gebunden: int, neutral: int, beeinflussbar: int}
     */
    public static function summen(array $kostenzeilen): array
    {
        $summen = [self::GEBUNDEN => 0, self::NEUTRAL => 0, self::BEEINFLUSSBAR => 0];
        foreach ($kostenzeilen as $k) {
            $betrag = (int) ($k['soll'] ?? 0);
            if ($betrag <= 0) {
                continue;
            }
            $summen[self::fuerLabel((string) ($k['label'] ?? ''))] += $betrag;
        }
        return $summen;
    }

    /**
     * Der beeinflussbare Aufwand einer Produktegruppe — die Bezugsgrösse der
     * automatischen Pauschalverteilung. Wo das Buch keine Kostenzeilen führt,
     * bleibt der ganze Aufwand die Bezugsgrösse: ohne Gliederung lässt sich
     * nichts ausscheiden, und eine Produktegruppe darf wegen fehlender Daten
     * nicht aus der Verteilung fallen.
     *
     * @param list<array<string, mixed>> $kostenzeilen
     */
    public static function beeinflussbarerAufwand(array $kostenzeilen, int $aufwandSoll): int
    {
        if ($kostenzeilen === []) {
            return max(0, $aufwandSoll);
        }
        $summen = self::summen($kostenzeilen);
        $flexibel = $summen[self::BEEINFLUSSBAR];
        return $flexibel > 0 ? $flexibel : 0;
    }
}
