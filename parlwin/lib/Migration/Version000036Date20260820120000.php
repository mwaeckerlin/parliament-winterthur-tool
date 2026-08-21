<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Explizite Dokument-Verknüpfungen: Statt Dateien über einen Namenspräfix
 * (z.B. «V{id}-») einem Objekt zuzuordnen, hält diese Tabelle pro Objekt
 * (Vorstoss/Geschäft/Sitzung) die verknüpften Dateien — unabhängig vom
 * Dateinamen und vom Ablageort. So können Dokumente jahr-basiert
 * (…/40_Vorstösse/10_Eigene/{Jahr}/…) abgelegt und trotzdem eindeutig einem
 * Objekt zugeordnet werden, und eine bestehende Datei lässt sich per
 * «Verknüpfen» aus einem beliebigen Ordner hinzufügen.
 */
class Version000036Date20260820120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_dokument_links')) {
            return null;
        }

        $tabelle = $schema->createTable('pw_dokument_links');
        $tabelle->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $tabelle->addColumn('objekt_typ', Types::STRING, [
            'notnull' => true,
            'length' => 32,
        ]);
        $tabelle->addColumn('objekt_id', Types::BIGINT, [
            'notnull' => true,
        ]);
        $tabelle->addColumn('file_id', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
        ]);
        $tabelle->addColumn('pfad', Types::STRING, [
            'notnull' => false,
            'length' => 4000,
        ]);
        $tabelle->addColumn('erstellt_am', Types::STRING, [
            'notnull' => false,
            'length' => 32,
        ]);

        $tabelle->setPrimaryKey(['id']);
        $tabelle->addIndex(['objekt_typ', 'objekt_id'], 'pw_doklink_objekt');

        $output->info('V36: Tabelle pw_dokument_links angelegt');

        return $schema;
    }
}
