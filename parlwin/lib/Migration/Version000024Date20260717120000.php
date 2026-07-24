<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fügt die fraktionsinterne Priorität (hoch/mittel/tief) zu den Geschäften hinzu.
 * Leerstring als Default bedeutet «nicht gesetzt» und wird im Frontend wie
 * «mittel» behandelt.
 */
class Version000024Date20260717120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_geschaefte')) {
            $table = $schema->getTable('pw_geschaefte');
            if (!$table->hasColumn('prioritaet')) {
                // notnull=false: Nextcloud verbietet eine NOT-NULL-Spalte mit
                // leerem String als Default und bricht sonst das occ upgrade ab.
                // Leer = «nicht gesetzt» (wird wie «mittel» behandelt).
                $table->addColumn('prioritaet', Types::STRING, [
                    'notnull' => false,
                    'length' => 16,
                    'default' => '',
                ]);
                $output->info('V24: added pw_geschaefte.prioritaet');
            } else {
                $output->info('V24: pw_geschaefte.prioritaet already exists — skipping');
            }
        }

        return $schema;
    }
}
