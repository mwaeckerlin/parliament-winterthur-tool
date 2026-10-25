<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Der neue Wert eines Antrags, der keinen Betrag ändert (F137): Ein Antrag auf
 * das Bewilligungsorgan eines Verpflichtungskredits verschiebt die
 * Zuständigkeit, etwa vom Stadtrat ans Parlament. Leer bei jedem Antrag, der
 * einen Betrag trägt.
 */
class Version000057Date20261024190000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_antrag')) {
            return null;
        }
        $table = $schema->getTable('pw_budget_antrag');
        if ($table->hasColumn('neuer_wert')) {
            return null;
        }
        $table->addColumn('neuer_wert', Types::STRING, [
            'notnull' => false,
            'length' => 64,
            'default' => null,
        ]);
        $output->info('V57: pw_budget_antrag.neuer_wert ergänzt (Antrag auf das Bewilligungsorgan)');
        return $schema;
    }
}
