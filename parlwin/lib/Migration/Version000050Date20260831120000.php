<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fragestunde (F114): Die Fraktionsmitglieder sammeln ihre Fragen für die
 * Fragestunde des Parlaments, besprechen sie in der Fraktionssitzung und teilen
 * sie einander zum Einreichen zu.
 *
 * Zwei Tabellen: die Fragestunde selbst (Datum, Frist, Verknüpfung mit dem
 * Geschäft des Parlaments) und die Fragen dazu.
 */
class Version000050Date20260831120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_fragestunden')) {
            $t = $schema->createTable('pw_fragestunden');
            $t->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('datum', Types::STRING, ['length' => 10, 'notnull' => false]);
            $t->addColumn('titel', Types::STRING, ['length' => 255, 'notnull' => false]);
            // Frist für das schriftliche Einreichen beim Parlamentsdienst
            // (Art. 103 Abs. 2 der Organisationsverordnung: Donnerstag vor der
            // Fragestunde). Wird beim Anlegen aus dem Datum berechnet und lässt
            // sich überschreiben.
            $t->addColumn('frist', Types::STRING, ['length' => 10, 'notnull' => false]);
            $t->addColumn('geschaeft_id', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->addColumn('erstellt_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('aktualisiert_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['datum'], 'pw_fragestunde_datum');
        }

        if (!$schema->hasTable('pw_fragestunde_fragen')) {
            $t = $schema->createTable('pw_fragestunde_fragen');
            $t->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('fragestunde_id', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            // Wer die Frage eingebracht hat.
            $t->addColumn('urheber_key', Types::STRING, ['length' => 64, 'notnull' => false]);
            $t->addColumn('urheber_name', Types::STRING, ['length' => 255, 'notnull' => false]);
            $t->addColumn('frage', Types::TEXT, ['notnull' => false]);
            $t->addColumn('kommentar', Types::TEXT, ['notnull' => false]);
            // Wer sie einreicht — leer, solange die Fraktion sie noch nicht
            // stellt. Meist der Urheber; jedes Mitglied darf nur eine Frage
            // einreichen, deshalb manchmal jemand anderes.
            $t->addColumn('einreicher_key', Types::STRING, ['length' => 64, 'notnull' => false]);
            $t->addColumn('einreicher_name', Types::STRING, ['length' => 255, 'notnull' => false]);
            // neu | besprochen | eingereicht | zurueckgezogen
            $t->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => 'neu']);
            $t->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $t->addColumn('erstellt_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('aktualisiert_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['fragestunde_id'], 'pw_frage_fragestunde');
        }

        return $schema;
    }
}
