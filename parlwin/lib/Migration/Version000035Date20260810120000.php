<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt `verknuepft_geschaeft_id`: Bei einem selbst angelegten Geschäft hält
 * die Spalte das offizielle Parlamentsgeschäft, mit dem es verknüpft (und
 * dadurch abgeschlossen) wurde — analog zu Vorstoss→Geschäft. 0 = nicht
 * verknüpft.
 *
 * Nullable mit Default 0: bestehende Zeilen bleiben unangetastet, und Nextcloud
 * lehnt NOT-NULL-Spalten mit Default beim Upgrade nicht ab.
 */
class Version000035Date20260810120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_geschaefte')) {
            return null;
        }

        $tabelle = $schema->getTable('pw_geschaefte');
        if ($tabelle->hasColumn('verknuepft_geschaeft_id')) {
            return null;
        }

        $tabelle->addColumn('verknuepft_geschaeft_id', Types::BIGINT, [
            'notnull' => false,
            'default' => 0,
        ]);
        $output->info('V35: pw_geschaefte.verknuepft_geschaeft_id ergänzt');

        return $schema;
    }
}
