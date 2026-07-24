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
