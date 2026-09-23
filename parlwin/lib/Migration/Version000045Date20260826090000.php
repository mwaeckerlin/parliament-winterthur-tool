<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: künstliche Produktegruppen (F89). Eine künstliche Produktegruppe trägt
 * die aus Teil A geparsten Posten, die in keiner echten Produktegruppe stehen
 * (interne Verrechnung, Abgrenzung), damit Σ aller Produktegruppen exakt dem vom
 * Stadtrat deklarierten Gesamtergebnis entspricht. Sie ist nicht antragbar.
 */
class Version000045Date20260826090000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_produktegruppe')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_produktegruppe');
        if ($t->hasColumn('kuenstlich')) {
            return null;
        }
        $t->addColumn('kuenstlich', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
        $output->info('V45: Produktegruppe um das Kennzeichen «kuenstlich» ergänzt');
        return $schema;
    }
}
