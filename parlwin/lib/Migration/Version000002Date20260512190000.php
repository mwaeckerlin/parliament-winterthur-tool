<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Strukturierte Fraktionsarbeit ohne Rohdaten-JSON.
 *
 * - entfernt historische Rohdaten-Spalten
 * - ergänzt Aktionszeitleiste pro Geschäft
 * - ergänzt Mehrfach-Zuständigkeiten pro Geschäft
 */
class Version000002Date20260512190000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->entferneRohdatenSpalten($schema);
        $this->erstelleGeschaeftAktionen($schema);
        $this->erstelleGeschaeftZustaendigkeiten($schema);

        return $schema;
    }

    private function entferneRohdatenSpalten(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_geschaefte')) {
            $table = $schema->getTable('pw_geschaefte');
            if ($table->hasColumn('roh_daten')) {
                $table->dropColumn('roh_daten');
            }
        }

        if ($schema->hasTable('pw_vorstoss_entwuerfe')) {
            $table = $schema->getTable('pw_vorstoss_entwuerfe');
            if ($table->hasColumn('roh_daten')) {
                $table->dropColumn('roh_daten');
            }
        }
    }

    private function erstelleGeschaeftAktionen(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_geschaeft_aktionen')) {
            return;
        }

        $table = $schema->createTable('pw_geschaeft_aktionen');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('aktion_typ', Types::STRING, ['notnull' => true, 'length' => 50]);
        $table->addColumn('aktion_code', Types::STRING, ['notnull' => false, 'length' => 100, 'default' => '']);
        $table->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('text', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('entscheid_gueltig', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('autor_uid', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('autor_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['geschaeft_id'], 'pw_geschaeft_aktionen_geschaeft_id');
        $table->addIndex(['aktion_typ'], 'pw_geschaeft_aktionen_typ');
        $table->addIndex(['entscheid_gueltig'], 'pw_geschaeft_aktionen_ents_gueltig');
        $table->addIndex(['erstellt_am'], 'pw_geschaeft_aktionen_erstellt_am');
    }

    private function erstelleGeschaeftZustaendigkeiten(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_geschaeft_zustaendigkeiten')) {
            return;
        }

        $table = $schema->createTable('pw_geschaeft_zustaendigkeiten');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('person_key', Types::STRING, ['notnull' => true, 'length' => 300]);
        $table->addColumn('mitglied_extern_id', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('person_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('ist_haupt', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['geschaeft_id', 'person_key'], 'pw_geschaeft_zust_geschaeft_person');
        $table->addIndex(['geschaeft_id'], 'pw_geschaeft_zust_geschaeft_id');
        $table->addIndex(['ist_haupt'], 'pw_geschaeft_zust_haupt');
        $table->addIndex(['aktiv'], 'pw_geschaeft_zust_aktiv');
    }
}
