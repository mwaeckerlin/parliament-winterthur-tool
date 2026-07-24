<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Stellt die per Raw-SQL ergänzten Sitzungstyp-/Sitzungs-Spalten über den
 * zuverlässigen Doctrine-Weg sicher:
 *  - pw_sitzungstypen.verknuepfen (V20, Raw)
 *  - pw_sitzungstypen.kommissionen (V21, Raw)
 *  - pw_sitzungen.verknuepfung_id (V20, Raw)
 *
 * Raw-`ALTER TABLE` in `postSchemaChange` greift in frischen Installationen
 * nicht zuverlässig — dadurch fehlte `kommissionen` (bzw. blieb NULL), worauf
 * das Anlegen/Laden eines Sitzungstyps mit HTTP 500 scheiterte («Cannot assign
 * null to property Sitzungstyp::$kommissionen»). Doctrine legt fehlende Spalten
 * an und fasst vorhandene nicht an.
 */
class Version000033Date20260720140000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $geaendert = false;

        if ($schema->hasTable('pw_sitzungstypen')) {
            $tabelle = $schema->getTable('pw_sitzungstypen');
            if (!$tabelle->hasColumn('verknuepfen')) {
                $tabelle->addColumn('verknuepfen', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
                $output->info('V33: pw_sitzungstypen.verknuepfen ergänzt');
                $geaendert = true;
            }
            if (!$tabelle->hasColumn('kommissionen')) {
                $tabelle->addColumn('kommissionen', Types::TEXT, ['notnull' => false, 'default' => '[]']);
                $output->info('V33: pw_sitzungstypen.kommissionen ergänzt');
                $geaendert = true;
            }
        }

        if ($schema->hasTable('pw_sitzungen')) {
            $sitzungen = $schema->getTable('pw_sitzungen');
            if (!$sitzungen->hasColumn('verknuepfung_id')) {
                $sitzungen->addColumn('verknuepfung_id', Types::BIGINT, ['notnull' => false]);
                $output->info('V33: pw_sitzungen.verknuepfung_id ergänzt');
                $geaendert = true;
            }
        }

        return $geaendert ? $schema : null;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        // Bestehende NULL-Werte in kommissionen auf die leere Liste normalisieren.
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $tbl = $prefix . 'pw_sitzungstypen';
        try {
            $betroffen = $this->connection->executeStatement(
                "UPDATE `{$tbl}` SET `kommissionen` = '[]' WHERE `kommissionen` IS NULL"
            );
            if ($betroffen > 0) {
                $output->info("parlwin: V33 {$tbl}.kommissionen für {$betroffen} Zeile(n) normalisiert");
            }
        } catch (\Throwable $e) {
            $output->warning('parlwin: V33 Normalisierung übersprungen: ' . $e->getMessage());
        }
    }
}
