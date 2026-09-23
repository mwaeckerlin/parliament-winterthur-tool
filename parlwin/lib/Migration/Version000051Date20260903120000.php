<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Protokoll-Verlinkung (F115): Jede Sitzung merkt sich ihr eigenes Protokoll,
 * jedes Traktandum den Titel des Dokuments, das an ihm hängt.
 *
 * Damit steht der Link auf das Protokoll oben bei der Sitzung, die es
 * protokolliert, und noch einmal in der Folgesitzung beim Traktandum, das es
 * abnimmt — dort nennt der Dokumenttitel die protokollierte Sitzung.
 */
class Version000051Date20260903120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_sitzungen')) {
            $tabelle = $schema->getTable('pw_sitzungen');
            if (!$tabelle->hasColumn('protokoll_url')) {
                $tabelle->addColumn('protokoll_url', Types::STRING, ['notnull' => false, 'length' => 2000, 'default' => '']);
            }
            if (!$tabelle->hasColumn('protokoll_titel')) {
                $tabelle->addColumn('protokoll_titel', Types::STRING, ['notnull' => false, 'length' => 500, 'default' => '']);
            }
        }

        if ($schema->hasTable('pw_traktanden')) {
            $tabelle = $schema->getTable('pw_traktanden');
            if (!$tabelle->hasColumn('dokument_titel')) {
                $tabelle->addColumn('dokument_titel', Types::STRING, ['notnull' => false, 'length' => 500, 'default' => '']);
            }
        }

        return $schema;
    }
}
