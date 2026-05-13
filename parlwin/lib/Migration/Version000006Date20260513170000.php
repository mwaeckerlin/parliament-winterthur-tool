<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt ein editierbares Mapping von Parlamentsmitgliedern auf Nextcloud-UIDs.
 */
class Version000006Date20260513170000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_mitglieder')) {
            return $schema;
        }

        $table = $schema->getTable('pw_mitglieder');

        if (!$table->hasColumn('nextcloud_uid')) {
            $table->addColumn('nextcloud_uid', Types::STRING, [
                'notnull' => false,
                'length' => 255,
                'default' => '',
            ]);
        }

        if (!$table->hasIndex('pw_mitglieder_nextcloud_uid')) {
            $table->addIndex(['nextcloud_uid'], 'pw_mitglieder_nextcloud_uid');
        }

        return $schema;
    }
}

