<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: Phase eines Antrags (F93). «fraktion» = interne Vorbereitung (eigene
 * und abgesprochene fremde Anträge), «sitzung» = offizielle Sitzungsanträge aus
 * den Einladungs-/Traktandendokumenten, die während der Budgetdebatte live
 * verfolgt und in die Sitzungsansicht gespiegelt werden.
 */
class Version000039Date20260822190000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_antrag')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_antrag');
        if ($t->hasColumn('phase')) {
            return null;
        }
        $t->addColumn('phase', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'fraktion']);
        $t->addIndex(['jahr', 'phase'], 'pw_budget_antrag_phase');
        return $schema;
    }
}
