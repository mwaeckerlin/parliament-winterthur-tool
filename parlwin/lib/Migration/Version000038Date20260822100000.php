<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget-Modul: Datenmodell für den städtischen Budgetprozess.
 *
 * Tabellen (alle mit Präfix pw_budget_):
 * - jahr             ein importiertes Budgetjahr (Steuerfuss, Steuerertrag, Quellen)
 * - departement      Departemente in Buchreihenfolge je Jahr
 * - produktegruppe   die beschlussfähige Ebene (Globalkredit/Nettokosten) je Jahr
 * - produkt          Informationsteil unter einer Produktegruppe
 * - investition      Investitionsprojekte je Jahr (Departement/Cluster/Projekt)
 * - antrag           eigene und fremde Anträge (Globalbudget/Personal/Investition/Steuerfuss)
 * - verteilung       Zustand der automatischen Pauschalverteilung je Jahr
 * - antrag_entscheid Live-Verfolgung des Beschlusses je Antrag (sitzungsübergreifend)
 *
 * Konvention: NOT-NULL-Spalten haben keinen leeren/null-Default (Zahlen Default 0,
 * Texte nullable), damit der Migrations-Konventionstest grün bleibt.
 */
class Version000038Date20260822100000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $geaendert = false;

        if (!$schema->hasTable('pw_budget_jahr')) {
            $t = $schema->createTable('pw_budget_jahr');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('jahr', Types::INTEGER, ['notnull' => true]);
            $t->addColumn('steuerfuss', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->addColumn('steuerertrag', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('personalsteuer', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->addColumn('weisung_quelle', Types::STRING, ['length' => 1024, 'notnull' => false]);
            $t->addColumn('novemberbrief_quelle', Types::STRING, ['length' => 1024, 'notnull' => false]);
            $t->addColumn('novemberbrief_importiert', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('erstellt_am', Types::BIGINT, ['notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['jahr'], 'pw_budget_jahr_uniq');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_budget_produktegruppe')) {
            $t = $schema->createTable('pw_budget_produktegruppe');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('jahr', Types::INTEGER, ['notnull' => true]);
            $t->addColumn('code', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('name', Types::STRING, ['length' => 512, 'notnull' => false]);
            $t->addColumn('departement', Types::STRING, ['length' => 512, 'notnull' => false]);
            $t->addColumn('reihenfolge', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            // Beschlussfähiger Globalkredit (Nettokosten) je Buchspalte.
            $t->addColumn('globalkredit_ist', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('globalkredit_soll_vorjahr', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('globalkredit_soll', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('globalkredit_plan1', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('globalkredit_plan2', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('globalkredit_plan3', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            // Total effektive Kosten (Aufwand — Verteilbasis) und Erlöse.
            $t->addColumn('aufwand_ist', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('aufwand_soll_vorjahr', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('aufwand_soll', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('ertrag_ist', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('ertrag_soll_vorjahr', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('ertrag_soll', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            // Stellenplan.
            $t->addColumn('stellen_ist', Types::FLOAT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('stellen_soll_vorjahr', Types::FLOAT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('stellen_soll', Types::FLOAT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('auszubildende_soll', Types::FLOAT, ['notnull' => true, 'default' => 0]);
            // Texte (Informationsteil).
            $t->addColumn('auftrag', Types::TEXT, ['notnull' => false]);
            $t->addColumn('zielvorgaben', Types::TEXT, ['notnull' => false]);
            $t->addColumn('erlaeuterung_stellen', Types::TEXT, ['notnull' => false]);
            $t->addColumn('begruendung_abweichung', Types::TEXT, ['notnull' => false]);
            $t->addColumn('begruendung_fap', Types::TEXT, ['notnull' => false]);
            $t->addColumn('massnahmen', Types::TEXT, ['notnull' => false]);
            // Produkte (Informationsteil) als JSON — nur Anzeige, nie einzeln
            // abgefragt oder beantragt: [{nummer,name,leistungen,nettokostenSoll,…}].
            $t->addColumn('produkte', Types::TEXT, ['notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['jahr'], 'pw_budget_pg_jahr');
            $t->addUniqueIndex(['jahr', 'code'], 'pw_budget_pg_uniq');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_budget_investition')) {
            $t = $schema->createTable('pw_budget_investition');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('jahr', Types::INTEGER, ['notnull' => true]);
            $t->addColumn('departement', Types::STRING, ['length' => 512, 'notnull' => false]);
            $t->addColumn('cluster', Types::STRING, ['length' => 512, 'notnull' => false]);
            $t->addColumn('projekt', Types::STRING, ['length' => 1024, 'notnull' => false]);
            $t->addColumn('bu', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('fap1', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('fap2', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('fap3', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('gesamtkosten', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('bereits_getaetigt', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('planungskosten', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('reihenfolge', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['jahr'], 'pw_budget_inv_jahr');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_budget_antrag')) {
            $t = $schema->createTable('pw_budget_antrag');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('jahr', Types::INTEGER, ['notnull' => true]);
            $t->addColumn('bereich', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('ziel_typ', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('ziel_ref', Types::STRING, ['length' => 64, 'notnull' => false]);
            $t->addColumn('betrag_delta', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('stellen_delta', Types::FLOAT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('betrag_pro_stelle', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->addColumn('quelle', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('antragsteller', Types::STRING, ['length' => 512, 'notnull' => false]);
            $t->addColumn('begruendung', Types::TEXT, ['notnull' => false]);
            $t->addColumn('verteilung_id', Types::BIGINT, ['notnull' => false]);
            $t->addColumn('reihenfolge', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $t->addColumn('erstellt_von', Types::STRING, ['length' => 128, 'notnull' => false]);
            $t->addColumn('erstellt_am', Types::BIGINT, ['notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['jahr'], 'pw_budget_antrag_jahr');
            $t->addIndex(['verteilung_id'], 'pw_budget_antrag_vert');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_budget_verteilung')) {
            $t = $schema->createTable('pw_budget_verteilung');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('jahr', Types::INTEGER, ['notnull' => true]);
            $t->addColumn('automatik_ein', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
            $t->addColumn('ziel_modus', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'schwarze_null']);
            $t->addColumn('ziel_betrag', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['jahr'], 'pw_budget_vert_uniq');
            $geaendert = true;
        }

        if (!$schema->hasTable('pw_budget_antrag_entscheid')) {
            $t = $schema->createTable('pw_budget_antrag_entscheid');
            $t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('antrag_id', Types::BIGINT, ['notnull' => true]);
            $t->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'offen']);
            $t->addColumn('geaendert_von', Types::STRING, ['length' => 128, 'notnull' => false]);
            $t->addColumn('geaendert_am', Types::BIGINT, ['notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addUniqueIndex(['antrag_id'], 'pw_budget_entsch_uniq');
            $geaendert = true;
        }

        if ($geaendert) {
            $output->info('V38: Budget-Tabellen (pw_budget_*) angelegt');
        }

        return $geaendert ? $schema : null;
    }
}
