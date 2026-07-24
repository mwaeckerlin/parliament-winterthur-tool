<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use PHPUnit\Framework\TestCase;

/**
 * Interne Fraktionssitzungen (extern_id IS NULL) dürfen vom Parlaments-Sync
 * NIE gelöscht oder als gelöscht markiert werden. Der Schutz steckt in den
 * Sync-Abfragen des Mappers: beide arbeiten ausschliesslich auf Zeilen mit
 * gesetzter extern_id.
 */
class SitzungMapperInternSchutzTest extends TestCase
{
    public function testSyncAbfragenSchuetzenInterneSitzungen(): void
    {
        $quelle = (string) file_get_contents(__DIR__ . '/../../lib/Db/SitzungMapper.php');

        foreach (['findAllExternIds', 'markiereNichtMehrVorhandeneAlsGeloescht'] as $methode) {
            $treffer = preg_match(
                '/function ' . $methode . '\b.*?\n    \}/s',
                $quelle,
                $rumpf
            );
            self::assertSame(1, $treffer, "Methode {$methode} fehlt im SitzungMapper");
            self::assertStringContainsString(
                "isNotNull('extern_id')",
                $rumpf[0],
                "{$methode} muss interne Sitzungen (extern_id IS NULL) ausschliessen"
            );
        }
    }
}
