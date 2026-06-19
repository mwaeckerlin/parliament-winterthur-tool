<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fügt AUTO_INCREMENT zu pw_geschaefte.id hinzu via direktem SQL.
 *
 * Version000015 hat changeSchema() verwendet, aber Doctrine generierte kein
 * ALTER TABLE (falsches Diff bei PRIMARY KEY ohne AUTO_INCREMENT). Diese
 * Migration nutzt postSchemaChange() mit direktem SQL, was zuverlässig
 * funktioniert.
 *
 * Das Tabellen-Prefix kommt aus der System-Konfiguration (dbtableprefix). Das
 * früher genutzte IDBConnection::getTablePrefix() wurde in Nextcloud 34
 * entfernt und liess «occ app:enable parlwin» mit «Call to undefined method»
 * abstürzen.
 */
class Version000016Date20260615150000 extends SimpleMigrationStep {
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?Schema
    {
        return null;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $tbl = ((string) $this->config->getSystemValue('dbtableprefix', 'oc_')) . 'pw_geschaefte';
        try {
            $this->connection->executeStatement(
                "ALTER TABLE `{$tbl}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT"
            );
            $output->info("parlwin: {$tbl}.id AUTO_INCREMENT hinzugefügt");
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl}.id ALTER TABLE übersprungen: " . $e->getMessage());
        }
    }
}
