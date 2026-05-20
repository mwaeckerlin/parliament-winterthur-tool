<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Kategoriebasierte Entscheid-Workflows für politische Geschäfte.
 *
 * Die Kategorie stammt aus `_kategorieId` (bzw. `typ`) der Parlamentsdaten.
 */
class GeschaeftWorkflow {
    /**
     * @var array<string, string[]>
     */
    private const CATEGORY_DECISIONS = [
        'motion' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'miteinreichen_fraktion', 'miteinreichen_einzel',
            'ueberweisung_befuerworten', 'ueberweisung_ablehnen',
            'erheblich_erklaeren', 'abschreiben',
        ],
        'postulat' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'miteinreichen_fraktion', 'miteinreichen_einzel',
            'ueberweisung_befuerworten', 'ueberweisung_ablehnen',
            'kenntnisnahme_positiv', 'kenntnisnahme_negativ',
            'nachbericht_verlangen', 'abschreiben',
        ],
        'interpellation' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'kenntnisnahme_positiv', 'kenntnisnahme_negativ',
        ],
        'schriftliche_anfrage' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'kenntnisnahme_positiv', 'kenntnisnahme_negativ',
        ],
        'bericht' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'kenntnisnahme_positiv', 'kenntnisnahme_negativ',
        ],
        'wahlen' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
        ],
        'initiative' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
            'ueberweisung_befuerworten', 'ueberweisung_ablehnen',
        ],
        'vorlage' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
        ],
        'default' => [
            'unterstuetzen', 'ablehnen', 'stimmfreigabe',
        ],
    ];

    /**
     * Kategorien, die auf einen Basis-Workflow zeigen.
     *
     * @var array<string, string>
     */
    private const CATEGORY_ALIASES = [
        'dringliche_motion' => 'motion',
        'budget_motion' => 'motion',

        'dringliches_postulat' => 'postulat',
        'budget_postulat' => 'postulat',

        'dringliche_interpellation' => 'interpellation',
        'fragestunde' => 'interpellation',

        'jahresrechnung' => 'bericht',
        'kreditabrechnung' => 'bericht',

        'kreditantrag' => 'vorlage',
        'budget' => 'vorlage',
        'beschlussantrag' => 'vorlage',
        'parlamentseigene_vorlage' => 'vorlage',
        'verordnung_rechtserlass' => 'vorlage',
        'vertrag_vereinbarung' => 'vorlage',
        'rechtsmittel' => 'vorlage',
        'referendum' => 'vorlage',
        'uebrige_geschaefte' => 'vorlage',

        'volksinitiative' => 'initiative',
        'einzelinitiative' => 'initiative',
        'parlamentarische_initiative' => 'initiative',
    ];

    /**
     * @return string[]
     */
    public static function erlaubteBeschluesse(string $kategorieLabel, string $status = ''): array {
        $kategorie = self::kanonischeKategorie($kategorieLabel);
        $basis = self::CATEGORY_DECISIONS[$kategorie] ?? self::CATEGORY_DECISIONS['default'];

        // Für erledigte Geschäfte ist Kenntnisnahme in der Fraktionsarbeit oft weiterhin relevant.
        if (self::enthaelt($status, 'erledigt')) {
            $basis[] = 'kenntnisnahme_positiv';
            $basis[] = 'kenntnisnahme_negativ';
        }

        return array_values(array_unique($basis));
    }

    public static function kanonischeKategorie(string $kategorieLabel): string {
        $normalisiert = self::normalisiereKategorie($kategorieLabel);
        if ($normalisiert === '') {
            return 'default';
        }

        if (isset(self::CATEGORY_DECISIONS[$normalisiert])) {
            return $normalisiert;
        }
        if (isset(self::CATEGORY_ALIASES[$normalisiert])) {
            return self::CATEGORY_ALIASES[$normalisiert];
        }

        return 'default';
    }

    public static function normalisiereKategorie(string $kategorieLabel): string {
        $kategorie = trim($kategorieLabel);
        if ($kategorie === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $kategorie = mb_strtolower($kategorie, 'UTF-8');
        } else {
            $kategorie = strtolower($kategorie);
        }

        $kategorie = strtr($kategorie, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            '/' => ' ',
            '-' => ' ',
        ]);

        $kategorie = (string) preg_replace('/[^a-z0-9]+/', '_', $kategorie);
        $kategorie = trim($kategorie, '_');

        return $kategorie;
    }

    private static function enthaelt(string $text, string $teil): bool {
        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
            $teil = mb_strtolower($teil, 'UTF-8');
        } else {
            $text = strtolower($text);
            $teil = strtolower($teil);
        }
        return str_contains($text, $teil);
    }
}
