<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * n:m-Verknüpfung zwischen Sitzungen und Vorstössen (Tabelle
 * pw_sitzung_vorstoss) — damit lassen sich eigene und fremde Vorstösse direkt an
 * eine Sitzung traktandieren, analog zu Sitzung↔Geschäft.
 */
class Version000037Date20260820130000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_sitzung_vorstoss')) {
            return null;
        }

        $tabelle = $schema->createTable('pw_sitzung_vorstoss');
        $tabelle->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $tabelle->addColumn('sitzung_id', Types::BIGINT, [
            'notnull' => true,
        ]);
        $tabelle->addColumn('vorstoss_id', Types::BIGINT, [
            'notnull' => true,
        ]);

        $tabelle->setPrimaryKey(['id']);
        $tabelle->addIndex(['sitzung_id'], 'pw_sitzvorst_sitzung');
        $tabelle->addUniqueIndex(['sitzung_id', 'vorstoss_id'], 'pw_sitzvorst_uniq');

        $output->info('V37: Tabelle pw_sitzung_vorstoss angelegt');

        return $schema;
    }
}
