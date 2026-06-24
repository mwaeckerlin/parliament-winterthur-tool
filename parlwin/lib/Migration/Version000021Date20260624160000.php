<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Verknüpfung von Sitzungen mit Geschäften:
 *  - pw_sitzung_geschaeft: n:m-Zuordnung (welche Geschäfte werden in einer
 *    Sitzung behandelt). Wird manuell oder automatisch (über die Kommissionen
 *    des Sitzungstyps) befüllt.
 *  - pw_sitzungstypen.kommissionen: JSON-Liste von Kommissions-IDs, deren
 *    hängige Geschäfte vor der Sitzung automatisch verknüpft werden.
 */
class Version000021Date20260624160000 extends SimpleMigrationStep
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
        $tbl = $prefix . 'pw_sitzung_geschaeft';

        try {
            $vorhanden = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tbl]
            )->fetchOne();
            if ((int) $vorhanden === 0) {
                $this->connection->executeStatement(
                    "CREATE TABLE `{$tbl}` ("
                    . "`id` BIGINT AUTO_INCREMENT PRIMARY KEY, "
                    . "`sitzung_id` BIGINT NOT NULL, "
                    . "`geschaeft_id` BIGINT NOT NULL, "
                    . "`automatisch` SMALLINT NOT NULL DEFAULT 0, "
                    . "UNIQUE KEY `pw_sg_uniq` (`sitzung_id`, `geschaeft_id`)"
                    . ") DEFAULT CHARSET=utf8mb4"
                );
                $output->info("parlwin: Tabelle {$tbl} angelegt");
            } else {
                $output->info("parlwin: Tabelle {$tbl} bereits vorhanden");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} Migration übersprungen: " . $e->getMessage());
        }

        $typTbl = $prefix . 'pw_sitzungstypen';
        try {
            $spalte = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$typTbl, 'kommissionen']
            )->fetchOne();
            if ((int) $spalte === 0) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$typTbl}` ADD COLUMN `kommissionen` LONGTEXT NULL"
                );
                $this->connection->executeStatement(
                    "UPDATE `{$typTbl}` SET `kommissionen` = '[]' WHERE `kommissionen` IS NULL"
                );
                $output->info("parlwin: {$typTbl}.kommissionen hinzugefügt");
            } else {
                $output->info("parlwin: {$typTbl}.kommissionen bereits vorhanden");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$typTbl}.kommissionen Migration übersprungen: " . $e->getMessage());
        }
    }
}
