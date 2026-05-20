<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt Quellversions-Tracking für Geschäfte.
 *
 * Damit kann fachlich sauber erkannt werden, ob seit dem letzten
 * Fraktionsbeschluss externe Änderungen publiziert wurden.
 */
class Version000004Date20260513090000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_geschaefte')) {
            return $schema;
        }

        $table = $schema->getTable('pw_geschaefte');

        if (!$table->hasColumn('quelle_hash')) {
            $table->addColumn('quelle_hash', Types::STRING, [
                'notnull' => false,
                'length' => 128,
                'default' => '',
            ]);
        }

        if (!$table->hasColumn('quelle_aktualisiert_am')) {
            $table->addColumn('quelle_aktualisiert_am', Types::DATETIME, [
                'notnull' => false,
            ]);
        }

        if (!$table->hasIndex('pw_geschaefte_quelle_aktualisiert_am')) {
            $table->addIndex(['quelle_aktualisiert_am'], 'pw_geschaefte_quelle_aktualisiert_am');
        }

        return $schema;
    }
}
