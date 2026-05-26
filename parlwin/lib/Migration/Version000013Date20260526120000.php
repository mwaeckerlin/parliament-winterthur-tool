<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000013Date20260526120000 extends SimpleMigrationStep
{
    public function __construct(private IDBConnection $db) {}

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        // Allow NULL in extern_id for internal sessions (no Parlament entry).
        // The UNIQUE index remains; MySQL/MariaDB allow multiple NULLs in a unique index.
        try {
            $this->db->executeStatement(
                'ALTER TABLE `oc_pw_sitzungen` MODIFY `extern_id` VARCHAR(255) NULL DEFAULT NULL'
            );
        } catch (\Throwable) {}
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
