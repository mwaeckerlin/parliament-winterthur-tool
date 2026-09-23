<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fügt die Priorität (hoch/mittel/tief, standardmässig nicht gesetzt) zu den Vorstössen
 * hinzu – analog zu den Geschäften.
 */
class Version000026Date20260718140000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $tbl = $prefix . 'pw_vorstoesse';

        try {
            $vorhanden = (int) $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tbl]
            )->fetchOne();
            if ($vorhanden === 0) {
                $output->info("parlwin: {$tbl} fehlt — V26 übersprungen");
                return;
            }

            $spalte = (int) $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tbl, 'prioritaet']
            )->fetchOne();
            if ($spalte === 0) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tbl}` ADD COLUMN `prioritaet` VARCHAR(16) NOT NULL DEFAULT ''"
                );
                $output->info("parlwin: {$tbl}.prioritaet hinzugefügt");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} V26 übersprungen: " . $e->getMessage());
        }
    }
}
