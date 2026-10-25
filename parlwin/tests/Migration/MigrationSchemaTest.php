<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Migration;

use PHPUnit\Framework\TestCase;

/**
 * Guard gegen einen Migrations-Fehler, der das ganze App-Upgrade abbricht und
 * Nextcloud im Wartungsmodus hängen lässt:
 *
 *   «Column "…"."…" is NotNull, but has empty string or null as default.»
 *
 * Nextcloud lehnt eine NOT-NULL-Spalte mit leerem String oder null als Default
 * beim `occ upgrade` ab. Solche Spalten müssen entweder nullable sein oder einen
 * nicht-leeren Default haben. Dieser Test prüft ALLE Migrationen, damit der
 * Fehler nie wieder ein Upgrade blockiert.
 */
class MigrationSchemaTest extends TestCase
{
    /**
     * Die Prüfsumme eines amtlichen Dokuments trägt die Fassung des Lesers vorn
     * («v2:» plus 64 Zeichen sha256). Passt sie nicht in die Spalte, endet jedes
     * Lesen mit «Data too long for column 'quelle_hash'» — gemessen am
     * 24.09.2026, als die Spalte 64 Zeichen lang war.
     */
    public function testDiePruefsummeMitFassungPasstInIhreSpalte(): void
    {
        $dienst = (string) file_get_contents(__DIR__ . '/../../lib/Service/GeschaeftDokumentService.php');
        self::assertSame(
            1,
            preg_match("/LESER_FASSUNG = '([^']+)'/", $dienst, $treffer),
            'die Fassung des Lesers steht im Dienst',
        );
        $gebraucht = \strlen($treffer[1]) + 1 + 64;

        $laengen = [];
        foreach (glob(__DIR__ . '/../../lib/Migration/*.php') ?: [] as $datei) {
            $code = (string) file_get_contents($datei);
            if (preg_match("/'quelle_hash'.*?'length'\s*=>\s*(\d+)/s", $code, $spalte) === 1) {
                $laengen[] = (int) $spalte[1];
            }
            if (preg_match('/quelle_hash.*?setLength\((\d+)\)/s', $code, $spalte) === 1) {
                $laengen[] = (int) $spalte[1];
            }
        }
        self::assertNotSame([], $laengen, 'die Spalte quelle_hash steht in einer Migration');
        foreach ($laengen as $laenge) {
            self::assertGreaterThanOrEqual(
                $gebraucht,
                $laenge,
                'quelle_hash braucht ' . $gebraucht . ' Zeichen: Fassung plus Prüfsumme',
            );
        }
    }

    /**
     * Jede Tabelle, die eine Migration anfasst, muss es geben. Eine Migration
     * prüft mit `hasTable()` und kehrt lautlos um, wenn die Tabelle fehlt: Ein
     * Tippfehler im Namen legt also keine Spalte an, bricht kein Upgrade ab und
     * fällt erst auf, wenn die Anwendung auf die fehlende Spalte schreibt.
     *
     * Gemessen am 24.10.2026: Vier Migrationen nannten die Budget-Tabellen im
     * Plural («pw_budget_jahre», «pw_budget_antraege»,
     * «pw_budget_produktegruppen»), angelegt sind sie im Singular. Die vier
     * Spalten der Seite im Buch, des Einleserstands, des Bewilligungsorgans und
     * der Departementssekretariate entstanden nie.
     */
    public function testJedeAngefassteTabelleWirdAuchAngelegt(): void
    {
        $verzeichnis = __DIR__ . '/../../lib/Migration';
        $dateien = glob($verzeichnis . '/*.php') ?: [];
        $angelegt = [];
        $angefasst = [];
        foreach ($dateien as $datei) {
            $code = (string) file_get_contents($datei);
            foreach (['createTable', 'hasTable', 'getTable'] as $methode) {
                preg_match_all('/' . $methode . '\s*\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $code, $treffer);
                foreach ($treffer[1] as $tabelle) {
                    if ($methode === 'createTable') {
                        $angelegt[$tabelle] = true;
                    } else {
                        $angefasst[$tabelle][basename($datei)] = true;
                    }
                }
            }
        }
        self::assertNotSame([], $angelegt, 'keine Migration legt eine Tabelle an');
        $unbekannt = [];
        foreach ($angefasst as $tabelle => $wo) {
            if (!isset($angelegt[$tabelle])) {
                $unbekannt[] = $tabelle . ' (' . implode(', ', array_keys($wo)) . ')';
            }
        }
        self::assertSame(
            [],
            $unbekannt,
            "Diese Tabellen fasst eine Migration an, angelegt werden sie nie — die Spalten entstehen "
            . "lautlos nicht:\n" . implode("\n", $unbekannt)
        );
    }

    public function testKeineNotNullSpalteMitLeeremOderNullDefault(): void
    {
        $verzeichnis = __DIR__ . '/../../lib/Migration';
        $verstoesse = [];

        foreach (glob($verzeichnis . '/*.php') ?: [] as $datei) {
            $code = (string) file_get_contents($datei);
            if (!preg_match_all('/addColumn\s*\(.*?\)\s*;/s', $code, $treffer)) {
                continue;
            }
            foreach ($treffer[0] as $aufruf) {
                $notnull = preg_match('/[\'"]notnull[\'"]\s*=>\s*true/', $aufruf) === 1;
                $leererDefault = preg_match('/[\'"]default[\'"]\s*=>\s*([\'"]{2}|null)/', $aufruf) === 1;
                if ($notnull && $leererDefault) {
                    $verstoesse[] = basename($datei) . ': ' . preg_replace('/\s+/', ' ', trim($aufruf));
                }
            }
        }

        $this->assertSame(
            [],
            $verstoesse,
            "NOT-NULL-Spalten dürfen keinen leeren/null Default haben — Nextcloud "
            . "bricht sonst das occ upgrade ab und bleibt im Wartungsmodus:\n"
            . implode("\n", $verstoesse)
        );
    }
}
