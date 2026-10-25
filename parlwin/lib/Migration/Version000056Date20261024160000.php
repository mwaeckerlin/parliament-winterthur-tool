<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Der Stand des Einlesers, mit dem ein Budgetjahr gelesen wurde (F130). Ist er
 * kleiner als der heutige, liest der Hintergrundauftrag das Jahr neu ein. Null
 * heisst: vor der Einführung dieses Stempels eingelesen, also neu einzulesen.
 */
class Version000056Date20261024160000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_jahr')) {
            return null;
        }
        $table = $schema->getTable('pw_budget_jahr');
        if ($table->hasColumn('parser_version')) {
            return null;
        }
        $table->addColumn('parser_version', Types::INTEGER, [
            'notnull' => true,
            'default' => 0,
        ]);
        $output->info('V56: pw_budget_jahr.parser_version ergänzt (Stand des Einlesers)');
        return $schema;
    }
}
