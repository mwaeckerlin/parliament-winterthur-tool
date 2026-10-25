<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Die Departementssekretariate eines Budgetjahres als JSON (F136): je
 * Departement Saldo, Aufwand und Ertrag vor der Umlage auf die Produktegruppen,
 * gelesen aus dem gleichnamigen Kapitel in Teil A.
 */
class Version000058Date20261024200000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_jahr')) {
            return null;
        }
        $table = $schema->getTable('pw_budget_jahr');
        if ($table->hasColumn('sekretariate')) {
            return null;
        }
        $table->addColumn('sekretariate', Types::TEXT, [
            'notnull' => false,
            'default' => null,
        ]);
        $output->info('V58: pw_budget_jahr.sekretariate ergänzt (Departementssekretariate aus Teil A)');
        return $schema;
    }
}
