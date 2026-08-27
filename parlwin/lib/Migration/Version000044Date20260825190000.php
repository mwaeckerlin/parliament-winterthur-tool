<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: die massgeblichen Totale der Erfolgsrechnung aus Teil A (gestufter
 * Erfolgsausweis) je Jahr — Gesamt-Aufwand, Gesamt-Ertrag und das offizielle
 * Gesamtergebnis. Sie sind die Basis der Summenzeile (nicht die Summe der
 * Produktegruppen, die interne Verrechnungen enthält).
 */
class Version000044Date20260825190000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_jahr')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_jahr');
        $geaendert = false;
        foreach (['total_aufwand', 'total_ertrag', 'total_aufwand_vorjahr', 'total_ertrag_vorjahr', 'gesamtergebnis'] as $spalte) {
            if (!$t->hasColumn($spalte)) {
                $t->addColumn($spalte, Types::BIGINT, ['notnull' => true, 'default' => 0]);
                $geaendert = true;
            }
        }
        if ($geaendert) {
            $output->info('V44: Budget-Jahr um Totale (Aufwand/Ertrag/Gesamtergebnis) ergänzt');
        }
        return $geaendert ? $schema : null;
    }
}
