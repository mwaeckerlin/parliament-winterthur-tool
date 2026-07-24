<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Stellt sicher, dass die Vorstoss-Tabelle existiert — über den
 * Nextcloud-Standardweg (Doctrine-Schema) statt Raw-SQL: Die frühere
 * Raw-SQL-Anlage (V22) verschluckte Fehler still, sodass frische
 * Installationen ohne pw_vorstoesse endeten (jeder Vorstoss-Zugriff → 500).
 * Enthält alle aktuellen Spalten inkl. der später ergänzten (V25–V27).
 * String-Spalten sind nullable (Nextcloud verbietet NOT NULL mit leerem
 * String-Default); die Anwendung schreibt beim Anlegen immer alle Felder.
 */
class Version000029Date20260719120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_vorstoesse')) {
            $output->info('V29: pw_vorstoesse already exists — skipping');
            return null;
        }

        $table = $schema->createTable('pw_vorstoesse');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('titel', Types::STRING, ['notnull' => false, 'length' => 512, 'default' => '']);
        $table->addColumn('art', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => '']);
        $table->addColumn('herkunft', Types::STRING, ['notnull' => false, 'length' => 16, 'default' => 'eigene']);
        $table->addColumn('status', Types::STRING, ['notnull' => false, 'length' => 16, 'default' => 'neu']);
        $table->addColumn('prioritaet', Types::STRING, ['notnull' => false, 'length' => 16, 'default' => '']);
        $table->addColumn('beschluss', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('zustaendigkeit', Types::TEXT, ['notnull' => false]);
        $table->addColumn('herkunft_fraktion', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
        $table->addColumn('ansprechpartner', Types::TEXT, ['notnull' => false]);
        $table->addColumn('inhalt', Types::TEXT, ['notnull' => false]);
        $table->addColumn('dokument', Types::STRING, ['notnull' => false, 'length' => 1024, 'default' => '']);
        $table->addColumn('notizen', Types::TEXT, ['notnull' => false]);
        $table->addColumn('geschaeft_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
        $table->addColumn('geloescht', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
        $table->addColumn('erstellt_am', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => '']);
        $table->addColumn('aktualisiert_am', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => '']);
        $table->setPrimaryKey(['id']);
        $output->info('V29: pw_vorstoesse created');

        return $schema;
    }
}
