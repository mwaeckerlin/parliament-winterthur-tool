<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: mehrere unabhängige Pauschalverteilungen je Jahr (F100). Bisher gab es
 * eine Zeile je Jahr; neu kann es beliebig viele geben, jede mit eigenem
 * Zielbetrag, eigenen Ausnahmen und eigenem Einreichen-Entscheid.
 *
 * - modus:         «ziel» = automatischer Ausgleich auf zielModus/zielBetrag
 *                  (schwarze Null usw.); «fest» = ein fester Betrag wird anteilig
 *                  verteilt.
 * - betrag:        der fest zu verteilende Betrag (nur bei modus «fest»).
 * - herkunft/antragsteller/begruendung: wie bei einem Antrag (F94).
 * - reihenfolge:   Reihenfolge der Pauschalanträge.
 */
class Version000042Date20260824100000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_verteilung')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_verteilung');
        if (!$t->hasColumn('modus')) {
            $t->addColumn('modus', Types::STRING, ['length' => 8, 'notnull' => true, 'default' => 'ziel']);
        }
        if (!$t->hasColumn('betrag')) {
            $t->addColumn('betrag', Types::BIGINT, ['notnull' => true, 'default' => 0]);
        }
        // Prozentuale Angabe (z.B. «10% einsparen») — bezieht sich IMMER auf den
        // ursprünglichen Aufwand, nie auf den bereits gekürzten.
        if (!$t->hasColumn('prozent')) {
            $t->addColumn('prozent', Types::FLOAT, ['notnull' => true, 'default' => 0]);
        }
        if (!$t->hasColumn('herkunft')) {
            $t->addColumn('herkunft', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'eigene']);
        }
        if (!$t->hasColumn('antragsteller')) {
            $t->addColumn('antragsteller', Types::STRING, ['length' => 255, 'notnull' => false, 'default' => '']);
        }
        if (!$t->hasColumn('begruendung')) {
            $t->addColumn('begruendung', Types::TEXT, ['notnull' => false, 'default' => '']);
        }
        if (!$t->hasColumn('reihenfolge')) {
            $t->addColumn('reihenfolge', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        }
        if (!$t->hasIndex('pw_budget_verteilung_jahr')) {
            $t->addIndex(['jahr'], 'pw_budget_verteilung_jahr');
        }
        return $schema;
    }
}
