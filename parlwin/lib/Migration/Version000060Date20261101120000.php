<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Die Stadtratsbeschlüsse (F144, F146).
 *
 * Der Stadtrat veröffentlicht seine Beschlüsse auf der Webseite der Stadt,
 * getrennt von den Geschäften des Parlaments. Ein Mitglied des Parlaments
 * braucht sie: Der Stadtrat bereitet die Geschäfte des Parlaments vor und
 * vollzieht dessen Beschlüsse, und seine veröffentlichten Beschlüsse liegen
 * jedem Mitglied offen (PARLAMENTSBETRIEB.md).
 *
 * Je Beschluss stehen Nummer, Titel, Datum, zuständiges Departement, die
 * Sitzung und die angehängten Dokumente in dieser Tabelle; die Dokumente als
 * JSON-Liste, weil sie nur gemeinsam mit ihrem Beschluss gelesen werden.
 */
class Version000060Date20261101120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_stadtratsbeschluesse')) {
            $t = $schema->createTable('pw_stadtratsbeschluesse');
            $t->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            // Die Nummer, wie der Stadtrat sie schreibt: «2026/1279».
            $t->addColumn('nummer', Types::STRING, ['length' => 32, 'notnull' => false]);
            // Dieselbe Nummer ohne Schrägstrich, wie sie in der Adresse steht:
            // «/stadtratsbeschluesse/2026-10-21/20261279».
            $t->addColumn('extern_id', Types::STRING, ['length' => 64, 'notnull' => false]);
            $t->addColumn('titel', Types::STRING, ['length' => 500, 'notnull' => false]);
            $t->addColumn('datum', Types::STRING, ['length' => 10, 'notnull' => false]);
            $t->addColumn('departement', Types::STRING, ['length' => 200, 'notnull' => false]);
            // «Stadtratssitzung vom 21. Oktober 2026», wie auf der Quelle.
            $t->addColumn('sitzung', Types::STRING, ['length' => 200, 'notnull' => false]);
            $t->addColumn('publiziert_am', Types::STRING, ['length' => 10, 'notnull' => false]);
            $t->addColumn('url', Types::STRING, ['length' => 2000, 'notnull' => false]);
            // JSON-Liste der Dokumente: [{titel, url}].
            $t->addColumn('dokumente', Types::TEXT, ['notnull' => false]);
            // Die Notizen der Fraktion stehen nicht hier: sie laufen über den
            // geteilten Notiz-Code (NotizService) wie am Geschäft und am
            // Vorstoss, mit Versionen, Löschen und Wiederherstellen.
            $t->addColumn('erstellt_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('aktualisiert_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->setPrimaryKey(['id']);
            // Die Nummer identifiziert den Beschluss: Ein zweiter Lauf des
            // Imports aktualisiert den vorhandenen Eintrag statt einen zweiten
            // anzulegen.
            $t->addUniqueIndex(['extern_id'], 'pw_srb_extern');
            // Die Liste sortiert nach Datum und innerhalb eines Datums nach
            // Nummer; der Index trägt beide Spalten, sonst sortiert die
            // Datenbank jede Gruppe gleichen Datums im Speicher.
            $t->addIndex(['datum', 'nummer'], 'pw_srb_datum_nummer');
            $output->info('V60: pw_stadtratsbeschluesse angelegt');
        }

        return $schema;
    }
}
