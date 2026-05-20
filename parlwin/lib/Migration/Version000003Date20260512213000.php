<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fraktionsrollen mit zeitlich befristeten Stellvertretungen.
 */
class Version000003Date20260512213000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_fraktionsrollen')) {
            return $schema;
        }

        $table = $schema->createTable('pw_fraktionsrollen');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('rolle_code', Types::STRING, ['notnull' => true, 'length' => 80]);
        $table->addColumn('gueltig_von', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('gueltig_bis', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('gesetzt_von_uid', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('gesetzt_von_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['rolle_code'], 'pw_fraktionsrollen_rolle');
        $table->addIndex(['uid'], 'pw_fraktionsrollen_uid');
        $table->addIndex(['aktiv'], 'pw_fraktionsrollen_aktiv');
        $table->addIndex(['gueltig_von'], 'pw_fraktionsrollen_von');
        $table->addIndex(['gueltig_bis'], 'pw_fraktionsrollen_bis');

        return $schema;
    }
}

