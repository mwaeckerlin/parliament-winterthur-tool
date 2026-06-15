<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fügt AUTO_INCREMENT zu pw_geschaefte.id hinzu.
 * Version000016 verwendete einen falschen Tabellennamen (ohne DB-Prefix).
 */
class Version000017Date20260615160000 extends SimpleMigrationStep {
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
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $tbl = $prefix . 'pw_geschaefte';
        try {
            $this->connection->executeStatement(
                "ALTER TABLE `{$tbl}` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT"
            );
            $output->info("parlwin: {$tbl}.id AUTO_INCREMENT erfolgreich hinzugefügt");
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} ALTER TABLE übersprungen: " . $e->getMessage());
        }
    }
}
