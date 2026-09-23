<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Notizen erhalten einen Versionsverlauf und ein Kennzeichen für das Löschen:
 *
 * - pw_notiz_revisionen: archivierte Vorversionen einer Notiz (beim Bearbeiten).
 * - pw_geschaeft_aktionen.geloescht: Löschen entfernt die Notiz nicht mehr aus der
 *   Datenbank, sondern blendet sie nur aus — so lässt sie sich samt History
 *   wiederherstellen (Undo).
 */
class Version000023Date20260714120000 extends SimpleMigrationStep
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
        $revisionen = $prefix . 'pw_notiz_revisionen';
        $aktionen = $prefix . 'pw_geschaeft_aktionen';

        try {
            $vorhanden = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$revisionen]
            )->fetchOne();
            if ((int) $vorhanden === 0) {
                $this->connection->executeStatement(
                    "CREATE TABLE `{$revisionen}` ("
                    . "`id` BIGINT AUTO_INCREMENT PRIMARY KEY, "
                    . "`aktion_id` BIGINT NOT NULL DEFAULT 0, "
                    . "`text` LONGTEXT NULL, "
                    . "`autor_uid` VARCHAR(64) NOT NULL DEFAULT '', "
                    . "`autor_name` VARCHAR(255) NOT NULL DEFAULT '', "
                    . "`erstellt_am` VARCHAR(32) NOT NULL DEFAULT '', "
                    . "INDEX `pw_notiz_rev_aktion` (`aktion_id`)"
                    . ") DEFAULT CHARSET=utf8mb4"
                );
                $output->info("parlwin: Tabelle {$revisionen} angelegt");
            } else {
                $output->info("parlwin: Tabelle {$revisionen} bereits vorhanden");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$revisionen} Migration übersprungen: " . $e->getMessage());
        }

        try {
            $spalte = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$aktionen, 'geloescht']
            )->fetchOne();
            if ((int) $spalte === 0) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$aktionen}` ADD COLUMN `geloescht` SMALLINT NOT NULL DEFAULT 0"
                );
                $output->info("parlwin: Spalte {$aktionen}.geloescht angelegt");
            } else {
                $output->info("parlwin: Spalte {$aktionen}.geloescht bereits vorhanden");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$aktionen}.geloescht Migration übersprungen: " . $e->getMessage());
        }
    }
}
