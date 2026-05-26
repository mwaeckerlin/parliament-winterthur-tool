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
        // DBAL 4 rejects NOT NULL columns with empty-string default — fix before schema is read
        try {
            $this->db->executeStatement(
                'ALTER TABLE `oc_pw_sitzungstypen` MODIFY `name` VARCHAR(255) NOT NULL'
            );
        } catch (\Throwable) {}
        try {
            $this->db->executeStatement(
                'ALTER TABLE `oc_pw_traktanden` MODIFY `url` VARCHAR(2048) NULL DEFAULT NULL'
            );
        } catch (\Throwable) {
            // Column doesn't exist yet — will be created in changeSchema
        }
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
