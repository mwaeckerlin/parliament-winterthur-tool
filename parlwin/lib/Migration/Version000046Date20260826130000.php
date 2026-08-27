<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ereignis-Protokoll (F105): eine persistente Historie der Synchronisationen und
 * Budget-Importe — wann, was neu/geändert, und der Ort, an dem Parsing-Probleme
 * (z.B. ein Budgetbuch ohne erkannte Produktegruppen) dokumentiert werden.
 */
class Version000046Date20260826130000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('pw_ereignis')) {
            return null;
        }
        $t = $schema->createTable('pw_ereignis');
        $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        // Unix-Zeitpunkt des Ereignisses.
        $t->addColumn('zeitpunkt', Types::BIGINT, ['notnull' => true, 'default' => 0]);
        // Art: sync | budget_import | budget_reimport | novemberbrief | sitzungsantraege | fehler
        // (String-Spalten nullable — eine NOT-NULL-String-Spalte mit leerem Default
        // bricht das occ-Upgrade ab; die Werte setzt immer der EreignisService.)
        $t->addColumn('art', Types::STRING, ['notnull' => false, 'length' => 32]);
        // Bereich: mitglieder | geschaefte | sitzungen | budget | ''
        $t->addColumn('bereich', Types::STRING, ['notnull' => false, 'length' => 32]);
        $t->addColumn('erfolg', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
        $t->addColumn('titel', Types::STRING, ['notnull' => false, 'length' => 255]);
        $t->addColumn('meldung', Types::TEXT, ['notnull' => false]);
        // «auto» (Hintergrundjob) oder die Nutzer-ID beim manuellen Auslösen.
        $t->addColumn('ausgeloest_von', Types::STRING, ['notnull' => false, 'length' => 64]);
        $t->setPrimaryKey(['id']);
        $t->addIndex(['zeitpunkt'], 'pw_ereignis_zeit_idx');
        $output->info('V46: Tabelle pw_ereignis (Ereignis-Protokoll) angelegt');
        return $schema;
    }
}
