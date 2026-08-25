<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: Pauschalantrag um Einreichen-Entscheid (F100) und positionsweise
 * Ausnahmen (F101) erweitern.
 *
 * - haltung:   «einreichen» (Standard) | «nicht_einreichen» — steuert, ob die je
 *              Position erzeugten Einzelanträge zählen und ins Antrags-PDF gehen.
 * - ausnahmen: JSON-Liste der von diesem Pauschalantrag ausgenommenen Positionen
 *              (zielRef); der einzusparende Gesamtbetrag verteilt sich zum
 *              Ausgleich neu auf die verbleibenden Positionen.
 */
class Version000041Date20260823140000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_verteilung')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_verteilung');
        if (!$t->hasColumn('haltung')) {
            $t->addColumn('haltung', Types::STRING, ['length' => 24, 'notnull' => true, 'default' => 'einreichen']);
        }
        if (!$t->hasColumn('ausnahmen')) {
            $t->addColumn('ausnahmen', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        }
        return $schema;
    }
}
