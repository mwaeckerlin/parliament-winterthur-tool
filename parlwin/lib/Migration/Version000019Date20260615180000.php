<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Stellt sicher, dass pw_geschaefte.einreicher existiert.
 *
 * Version000014 hat die Spalte über das Doctrine-Schema-Diff angelegt. Auf
 * Installationen, auf denen V14 nicht sauber durchlief, fehlt die Spalte —
 * der Sync bricht dann mit «Unknown column 'einreicher' in 'SET'» ab.
 * Diese Migration legt die Spalte idempotent via direktem SQL nach, geprüft
 * über information_schema, sodass sie unabhängig vom Schema-Diff zuverlässig
 * vorhanden ist.
 */
class Version000019Date20260615180000 extends SimpleMigrationStep
{
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
            $vorhanden = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tbl, 'einreicher']
            )->fetchOne();

            if ((int) $vorhanden > 0) {
                $output->info("parlwin: {$tbl}.einreicher bereits vorhanden");
                return;
            }

            $this->connection->executeStatement(
                "ALTER TABLE `{$tbl}` ADD COLUMN `einreicher` LONGTEXT NULL"
            );
            $this->connection->executeStatement(
                "UPDATE `{$tbl}` SET `einreicher` = '[]' WHERE `einreicher` IS NULL"
            );
            $output->info("parlwin: {$tbl}.einreicher nachträglich hinzugefügt");
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl}.einreicher Migration übersprungen: " . $e->getMessage());
        }
    }
}
