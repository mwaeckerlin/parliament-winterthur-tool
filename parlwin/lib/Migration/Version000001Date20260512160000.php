<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erweiterung des Datenmodells:
 * - Entwurfs-/Einreichungsphase ohne Geschaeftsnummer
 * - Verfahrensereignisse aus Geschaefts-Detailseiten
 */
class Version000001Date20260512160000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->erstelleVorstossEntwuerfe($schema);
        $this->erstelleGeschaeftEreignisse($schema);

        return $schema;
    }

    private function erstelleVorstossEntwuerfe(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_vorstoss_entwuerfe')) {
            return;
        }

        $table = $schema->createTable('pw_vorstoss_entwuerfe');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('titel_normalisiert', Types::STRING, ['notnull' => false, 'length' => 1000, 'default' => '']);
        $table->addColumn('typ', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('eingangsdatum', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000, 'default' => '']);
        $table->addColumn('status', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => 'offen']);
        $table->addColumn('match_art', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => 0]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['extern_id'], 'pw_vorstoss_entwuerfe_extern_id');
        $table->addIndex(['titel_normalisiert'], 'pw_vorstoss_entwuerfe_titel_norm');
        $table->addIndex(['status'], 'pw_vorstoss_entwuerfe_status');
        $table->addIndex(['geschaeft_id'], 'pw_vorstoss_entwuerfe_geschaeft_id');
    }

    private function erstelleGeschaeftEreignisse(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_geschaeft_ereignisse')) {
            return;
        }

        $table = $schema->createTable('pw_geschaeft_ereignisse');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('reihenfolge', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('typ', Types::STRING, ['notnull' => false, 'length' => 100, 'default' => 'info']);
        $table->addColumn('organ', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('label', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('wert', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('datum', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['geschaeft_id'], 'pw_geschaeft_ereignisse_geschaeft_id');
        $table->addIndex(['typ'], 'pw_geschaeft_ereignisse_typ');
        $table->addIndex(['datum'], 'pw_geschaeft_ereignisse_datum');
        $table->addUniqueIndex(['geschaeft_id', 'reihenfolge'], 'pw_geschaeft_ereignisse_geschaeft_seq');
    }
}
