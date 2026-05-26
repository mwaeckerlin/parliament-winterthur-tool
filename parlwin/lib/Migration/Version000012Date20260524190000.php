<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000012Date20260524190000 extends SimpleMigrationStep
{
    public function __construct(private IDBConnection $db) {}

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        // pw_sitzungstypen.name: ensure NOT NULL (DBAL 4 rejects NOT NULL with empty-string default)
        $row = $this->db->executeQuery(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'oc_pw_sitzungstypen'
               AND COLUMN_NAME = 'name'"
        )->fetchAssociative();

        if ($row === false) {
            throw new \RuntimeException(
                'Migration V12: Column pw_sitzungstypen.name not found — schema is corrupt.'
            );
        }
        if (strtoupper((string) ($row['IS_NULLABLE'] ?? '')) === 'NO') {
            $output->info('V12: pw_sitzungstypen.name already NOT NULL — skipping');
        } else {
            $this->db->executeStatement(
                'ALTER TABLE `oc_pw_sitzungstypen` MODIFY `name` VARCHAR(255) NOT NULL'
            );
            $row = $this->db->executeQuery(
                "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'oc_pw_sitzungstypen'
                   AND COLUMN_NAME = 'name'"
            )->fetchAssociative();
            if ($row === false || strtoupper((string) ($row['IS_NULLABLE'] ?? '')) !== 'NO') {
                throw new \RuntimeException(
                    'Migration V12: Failed to set pw_sitzungstypen.name NOT NULL. ' .
                    'Check DB permissions or table locks.'
                );
            }
            $output->info('V12: pw_sitzungstypen.name set to NOT NULL');
        }

        // pw_traktanden.url: if column already exists and is NOT NULL, make it nullable.
        // If column does not exist yet, changeSchema will add it as nullable.
        $row = $this->db->executeQuery(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'oc_pw_traktanden'
               AND COLUMN_NAME = 'url'"
        )->fetchAssociative();

        if ($row === false) {
            $output->info('V12: pw_traktanden.url does not exist yet — will be added in changeSchema');
            return;
        }
        if (strtoupper((string) ($row['IS_NULLABLE'] ?? '')) === 'YES') {
            $output->info('V12: pw_traktanden.url already nullable — skipping');
            return;
        }
        $this->db->executeStatement(
            'ALTER TABLE `oc_pw_traktanden` MODIFY `url` VARCHAR(2048) NULL DEFAULT NULL'
        );
        $row = $this->db->executeQuery(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'oc_pw_traktanden'
               AND COLUMN_NAME = 'url'"
        )->fetchAssociative();
        if ($row === false || strtoupper((string) ($row['IS_NULLABLE'] ?? '')) !== 'YES') {
            throw new \RuntimeException(
                'Migration V12: Failed to make pw_traktanden.url nullable. ' .
                'Check DB permissions or table locks.'
            );
        }
        $output->info('V12: pw_traktanden.url made nullable');
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_traktanden')) {
            $table = $schema->getTable('pw_traktanden');
            if (!$table->hasColumn('url')) {
                $table->addColumn('url', Types::STRING, [
                    'notnull' => false,
                    'length'  => 2048,
                    'default' => null,
                ]);
            }
        }

        return $schema;
    }
}
