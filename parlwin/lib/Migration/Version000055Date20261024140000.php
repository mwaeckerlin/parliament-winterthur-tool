<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Die Seite, auf der eine Produktegruppe im Budgetbuch steht (F129). Wer in der
 * Fraktion, in einer Kommission oder in der Debatte mit dem Buch arbeitet,
 * findet damit jede Position sofort. Null heisst: aus diesem Buch nicht
 * gelesen.
 */
class Version000055Date20261024140000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_produktegruppe')) {
            return null;
        }
        $table = $schema->getTable('pw_budget_produktegruppe');
        if ($table->hasColumn('buchseite')) {
            return null;
        }
        $table->addColumn('buchseite', Types::INTEGER, [
            'notnull' => true,
            'default' => 0,
        ]);
        $output->info('V55: pw_budget_produktegruppe.buchseite ergänzt (Seite im Budgetbuch)');
        return $schema;
    }
}
