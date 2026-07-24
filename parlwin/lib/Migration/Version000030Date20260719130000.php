<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Stellt sicher, dass pw_sitzung_geschaeft und pw_notiz_revisionen existieren —
 * über den Nextcloud-Standardweg (Doctrine-Schema) statt Raw-SQL: Die früheren
 * Raw-SQL-Anlagen (V21/V23) verschluckten Fehler still, sodass frische
 * Installationen ohne diese Tabellen enden konnten (gleiche Fehlklasse wie
 * pw_vorstoesse, V29).
 */
class Version000030Date20260719130000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $geaendert = false;

        if (!$schema->hasTable('pw_sitzung_geschaeft')) {
            $table = $schema->createTable('pw_sitzung_geschaeft');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('sitzung_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('automatisch', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['sitzung_id', 'geschaeft_id'], 'pw_sg_uniq');
            $output->info('V30: pw_sitzung_geschaeft created');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_notiz_revisionen')) {
            $table = $schema->createTable('pw_notiz_revisionen');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('aktion_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $table->addColumn('text', Types::TEXT, ['notnull' => false]);
            $table->addColumn('autor_uid', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => '']);
            $table->addColumn('autor_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
            $table->addColumn('erstellt_am', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => '']);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['aktion_id'], 'pw_notiz_rev_aktion');
            $output->info('V30: pw_notiz_revisionen created');
            $geaendert = true;
        }

        return $geaendert ? $schema : null;
    }
}
