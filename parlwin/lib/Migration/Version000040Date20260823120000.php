<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: Anträge um Herkunft, Prozentwert, Haltung, unterstützende Fraktionen,
 * Pauschal-Ausnahme, Sitzungsverknüpfung und Notizen erweitern (F94–F104).
 *
 * - herkunft:           «eigene» | «fremde» (wie beim Vorstoss)
 * - prozent_delta:      Betrag in Prozent (vorzeichenbehaftet); beim Steuerfuss
 *                       sind es Prozentpunkte
 * - haltung:            unsere Haltung, getrennt vom Sitzungs-Beschluss —
 *                       «einreichen» | «nicht_einreichen» (eigene),
 *                       «unterstuetzen» | «nicht_unterstuetzen» | «offen» (fremde)
 * - unterstuetzer:      JSON-Liste der unterstützenden Fraktionen
 * - pauschal_ausnahme:  Position vom Pauschalantrag ausgenommen (F101)
 * - verknuepft_mit_id:  verknüpfter Antrag der jeweils anderen Phase (F104)
 * - notizen:            JSON-Liste der Notizen (wie beim Vorstoss, F103)
 */
class Version000040Date20260823120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_antrag')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_antrag');
        if (!$t->hasColumn('herkunft')) {
            $t->addColumn('herkunft', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'eigene']);
        }
        if (!$t->hasColumn('prozent_delta')) {
            $t->addColumn('prozent_delta', Types::FLOAT, ['notnull' => true, 'default' => 0]);
        }
        if (!$t->hasColumn('haltung')) {
            // nullable: die Standard-Haltung ergibt sich in der Anwendung aus der
            // Herkunft (F97); eine NOT-NULL-Spalte mit leerem Default würde das
            // occ-upgrade abbrechen.
            $t->addColumn('haltung', Types::STRING, ['length' => 24, 'notnull' => false, 'default' => '']);
        }
        if (!$t->hasColumn('unterstuetzer')) {
            $t->addColumn('unterstuetzer', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        }
        if (!$t->hasColumn('pauschal_ausnahme')) {
            $t->addColumn('pauschal_ausnahme', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
        }
        if (!$t->hasColumn('verknuepft_mit_id')) {
            $t->addColumn('verknuepft_mit_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
        }
        return $schema;
    }
}
