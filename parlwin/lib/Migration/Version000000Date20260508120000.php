<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erstmalige Erstellung aller Datenbanktabellen für das Parlament Winterthur Tool.
 *
 * Tabellen:
 * - pw_geschaefte      – politische Geschäfte
 * - pw_sitzungen       – Parlamentssitzungen
 * - pw_traktanden      – Traktanden zu Sitzungen
 * - pw_mitglieder      – Parlamentsmitglieder
 * - pw_kommissionen    – Kommissionen
 * - pw_fraktionen      – Fraktionen
 */
class Version000000Date20260508120000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->erstelleGeschaefte($schema);
        $this->erstelleSitzungen($schema);
        $this->erstelleTraktanden($schema);
        $this->erstelleMitglieder($schema);
        $this->erstelleKommissionen($schema);
        $this->erstelleFraktionen($schema);

        return $schema;
    }

    private function erstelleGeschaefte(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_geschaefte')) {
            return;
        }
        $table = $schema->createTable('pw_geschaefte');
        $table->addColumn('id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('nummer', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('typ', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('status', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('datum', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000, 'default' => '']);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        // Fraktionsinterne Felder
        $table->addColumn('bemerkungen', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('zustaendige_person', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('antrag_fraktion', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('entscheid_fraktion', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('notizen', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['extern_id'], 'pw_geschaefte_extern_id');
        $table->addIndex(['geloescht'], 'pw_geschaefte_geloescht');
        $table->addIndex(['datum'], 'pw_geschaefte_datum');
    }

    private function erstelleSitzungen(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_sitzungen')) {
            return;
        }
        $table = $schema->createTable('pw_sitzungen');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('datum', Types::STRING, ['notnull' => false, 'length' => 50, 'default' => '']);
        $table->addColumn('zeit_von', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => '']);
        $table->addColumn('zeit_bis', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => '']);
        $table->addColumn('ort', Types::STRING, ['notnull' => false, 'length' => 500, 'default' => '']);
        $table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000, 'default' => '']);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('bemerkungen', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['extern_id'], 'pw_sitzungen_extern_id');
        $table->addIndex(['geloescht'], 'pw_sitzungen_geloescht');
        $table->addIndex(['datum'], 'pw_sitzungen_datum');
    }

    private function erstelleTraktanden(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_traktanden')) {
            return;
        }
        $table = $schema->createTable('pw_traktanden');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('sitzung_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true, 'default' => 0]);
        $table->addColumn('nummer', Types::INTEGER, ['notnull' => false, 'default' => 0]);
        $table->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('beschreibung', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('bemerkungen', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('notizen', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['sitzung_id'], 'pw_traktanden_sitzung_id');
        $table->addIndex(['geschaeft_id'], 'pw_traktanden_geschaeft_id');
    }

    private function erstelleMitglieder(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_mitglieder')) {
            return;
        }
        $table = $schema->createTable('pw_mitglieder');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('vorname', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('partei', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('fraktion', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('foto_url', Types::STRING, ['notnull' => false, 'length' => 2000, 'default' => '']);
        $table->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['extern_id'], 'pw_mitglieder_extern_id');
        $table->addIndex(['fraktion'], 'pw_mitglieder_fraktion');
        $table->addIndex(['aktiv'], 'pw_mitglieder_aktiv');
    }

    private function erstelleKommissionen(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_kommissionen')) {
            return;
        }
        $table = $schema->createTable('pw_kommissionen');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 500, 'default' => '']);
        $table->addColumn('beschreibung', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('mitglieder', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['extern_id'], 'pw_kommissionen_extern_id');
    }

    private function erstelleFraktionen(ISchemaWrapper $schema): void {
        if ($schema->hasTable('pw_fraktionen')) {
            return;
        }
        $table = $schema->createTable('pw_fraktionen');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('extern_id', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('beschreibung', Types::TEXT, ['notnull' => false, 'default' => '']);
        $table->addColumn('mitglieder', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        $table->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
        $table->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['extern_id'], 'pw_fraktionen_extern_id');
    }
}
