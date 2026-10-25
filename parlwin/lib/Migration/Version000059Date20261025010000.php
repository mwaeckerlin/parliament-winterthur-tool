<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Mehrere Pauschalanträge je Budgetjahr (F100).
 *
 * Die Tabelle der Pauschalanträge trug seit ihrer Anlage einen eindeutigen
 * Index auf dem Jahr — aus der Zeit, als ein Jahr genau eine automatische
 * Verteilung kannte. Seit F100 steht im Kasten «Pauschalanträge» eine Liste,
 * und «+ Pauschalantrag» legt einen weiteren an. Der zweite Antrag eines
 * Jahres scheiterte am Index: Die Oberfläche meldete einen Fehler, und es
 * entstand nichts (gemessen am 25.10.2026 im Browsertest F84).
 *
 * An die Stelle des eindeutigen Index tritt ein gewöhnlicher: Die Abfragen
 * suchen weiterhin nach dem Jahr.
 */
class Version000059Date20261025010000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_verteilung')) {
            return null;
        }
        $table = $schema->getTable('pw_budget_verteilung');
        if (!$table->hasIndex('pw_budget_vert_uniq')) {
            return null;
        }
        $table->dropIndex('pw_budget_vert_uniq');
        if (!$table->hasIndex('pw_budget_vert_jahr')) {
            $table->addIndex(['jahr'], 'pw_budget_vert_jahr');
        }
        $output->info('V59: pw_budget_verteilung ohne eindeutigen Index auf dem Jahr (mehrere Pauschalanträge)');
        return $schema;
    }
}
