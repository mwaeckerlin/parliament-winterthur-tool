<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000013Date20260526120000 extends SimpleMigrationStep
{
    public function __construct(private IDBConnection $db) {}

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        // pw_sitzungen.extern_id: must be nullable so internal sessions (no Parliament entry) can store NULL.
        $row = $this->db->executeQuery(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'oc_pw_sitzungen'
               AND COLUMN_NAME = 'extern_id'"
        )->fetchAssociative();

        if ($row === false) {
            throw new \RuntimeException(
                'Migration V13: Column extern_id not found in pw_sitzungen — schema is corrupt. ' .
                'Ensure Migration V0 ran successfully.'
            );
        }
        if (strtoupper((string) ($row['IS_NULLABLE'] ?? '')) === 'YES') {
            $output->info('V13: pw_sitzungen.extern_id already nullable — skipping');
            return;
        }
        $this->db->executeStatement(
            'ALTER TABLE `oc_pw_sitzungen` MODIFY `extern_id` VARCHAR(255) NULL DEFAULT NULL'
        );
        $row = $this->db->executeQuery(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'oc_pw_sitzungen'
               AND COLUMN_NAME = 'extern_id'"
        )->fetchAssociative();
        if ($row === false || strtoupper((string) ($row['IS_NULLABLE'] ?? '')) !== 'YES') {
            throw new \RuntimeException(
                'Migration V13: Failed to make pw_sitzungen.extern_id nullable. ' .
                'Check DB permissions or table locks.'
            );
        }
        $output->info('V13: pw_sitzungen.extern_id made nullable');
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_sitzungen')) {
            $table = $schema->getTable('pw_sitzungen');
            $col = $table->getColumn('extern_id');
            if ($col->getNotnull()) {
                $col->setNotnull(false);
                $col->setDefault(null);
            }
        }

        return $schema;
    }
}
