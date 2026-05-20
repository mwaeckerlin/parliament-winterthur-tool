<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt aktive/zeitliche Fraktionsfelder.
 *
 * Grundlage ist die Parlamentsquelle `/fraktionen` mit `datumVon`/`datumBis`.
 */
class Version000005Date20260513143000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_fraktionen')) {
            return $schema;
        }

        $table = $schema->getTable('pw_fraktionen');

        if (!$table->hasColumn('datum_von')) {
            $table->addColumn('datum_von', Types::STRING, [
                'notnull' => false,
                'length' => 10,
                'default' => '',
            ]);
        }

        if (!$table->hasColumn('datum_bis')) {
            $table->addColumn('datum_bis', Types::STRING, [
                'notnull' => false,
                'length' => 10,
                'default' => '',
            ]);
        }

        if (!$table->hasColumn('aktiv')) {
            $table->addColumn('aktiv', Types::BOOLEAN, [
                'notnull' => true,
                'default' => true,
            ]);
        }

        if (!$table->hasIndex('pw_fraktionen_aktiv')) {
            $table->addIndex(['aktiv'], 'pw_fraktionen_aktiv');
        }

        return $schema;
    }
}

