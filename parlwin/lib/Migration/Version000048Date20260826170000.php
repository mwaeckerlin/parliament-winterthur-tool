<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Kostenzeilen der Produktegruppe (F109, WoV): die Zeilen der Kostentabelle im
 * Informationsteil (Personalkosten, Sachkosten …) als JSON — Auswahlgrundlage für
 * die Einsparungsverteilung eines Antrags.
 */
class Version000048Date20260826170000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_produktegruppe')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_produktegruppe');
        if ($t->hasColumn('kostenzeilen')) {
            return null;
        }
        $t->addColumn('kostenzeilen', Types::TEXT, ['notnull' => false]);
        $output->info('V48: Spalte kostenzeilen an pw_budget_produktegruppe angefügt');
        return $schema;
    }
}
