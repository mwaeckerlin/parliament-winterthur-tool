<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Zielvorgaben-Anträge (F109, WoV): ein Budgetantrag kann eine oder mehrere
 * parlamentarische Zielvorgaben ändern — mit oder ohne Budgetwirkung. Die
 * Änderungen liegen als JSON-Liste am Antrag (natürlicher Schlüssel PG-Code +
 * Ziel-Nummer + Messgrösse, damit sie ein Neu-Einlesen des Budgets überleben).
 */
class Version000047Date20260826160000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_antrag')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_antrag');
        if (!$t->hasColumn('ziel_aenderungen')) {
            $t->addColumn('ziel_aenderungen', Types::TEXT, ['notnull' => false]);
        }
        // Hierarchische Einsparungsverteilung (F109): JSON-Liste, wo innerhalb der
        // Produktegruppe der beantragte Betrag einzusparen ist (Kostenzeilen, Produkte,
        // Produkt-Kostenzeilen, je mit optionalem Betrag/Prozent) — für die Begründung.
        if (!$t->hasColumn('aufteilung')) {
            $t->addColumn('aufteilung', Types::TEXT, ['notnull' => false]);
        }
        $output->info('V47: Spalten ziel_aenderungen und aufteilung an pw_budget_antrag angefügt');
        return $schema;
    }
}
