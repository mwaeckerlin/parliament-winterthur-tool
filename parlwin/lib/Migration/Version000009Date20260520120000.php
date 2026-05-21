<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Sitzungstypen / Sitzungs-Vorlagen (Greenfield).
 *
 * Tabellen:
 *  - pw_sitzungstypen           – Vorlage (Name, Zweck, Optionen)
 *  - pw_sitzungstyp_traktanden  – Standard-Traktanden je Vorlage
 *  - pw_sitzungstyp_teilnehmer  – Eingeladene Personen / Gruppen je Vorlage
 *
 * Zusätzlich: pw_sitzungen.typ_id verknüpft eine konkrete Sitzung
 * mit der Vorlage, aus der sie erstellt wurde.
 */
class Version000009Date20260520120000 extends SimpleMigrationStep
{
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('pw_sitzungstypen')) {
      $tabelle = $schema->createTable('pw_sitzungstypen');
      $tabelle->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
      $tabelle->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
      $tabelle->addColumn('zweck', Types::TEXT, ['notnull' => false, 'default' => '']);
      $tabelle->addColumn('kalender_anlegen', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
      $tabelle->addColumn('einladung_versenden', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
      $tabelle->addColumn('standard_ort', Types::STRING, ['notnull' => false, 'length' => 500, 'default' => '']);
      $tabelle->addColumn('standard_zeit_von', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => '']);
      $tabelle->addColumn('standard_zeit_bis', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => '']);
      $tabelle->addColumn('geloescht', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
      $tabelle->addColumn('erstellt_am', Types::DATETIME, ['notnull' => false]);
      $tabelle->addColumn('aktualisiert_am', Types::DATETIME, ['notnull' => false]);
      $tabelle->setPrimaryKey(['id']);
      $tabelle->addIndex(['geloescht'], 'pw_sitzungstypen_geloescht');
    }

    if (!$schema->hasTable('pw_sitzungstyp_traktanden')) {
      $tabelle = $schema->createTable('pw_sitzungstyp_traktanden');
      $tabelle->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
      $tabelle->addColumn('typ_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
      $tabelle->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $tabelle->addColumn('titel', Types::TEXT, ['notnull' => false, 'default' => '']);
      $tabelle->addColumn('beschreibung', Types::TEXT, ['notnull' => false, 'default' => '']);
      $tabelle->setPrimaryKey(['id']);
      $tabelle->addIndex(['typ_id'], 'pw_sitzungstyp_trakt_typ_id');
    }

    if (!$schema->hasTable('pw_sitzungstyp_teilnehmer')) {
      $tabelle = $schema->createTable('pw_sitzungstyp_teilnehmer');
      $tabelle->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
      $tabelle->addColumn('typ_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
      // art: 'mitglied' | 'fraktion' | 'kommission' | 'rolle'
      $tabelle->addColumn('art', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'mitglied']);
      // Bei art=mitglied/fraktion/kommission: Fremdschlüssel-ID (kann 0 sein, wenn Referenz über Name).
      $tabelle->addColumn('referenz_id', Types::BIGINT, ['notnull' => false, 'default' => 0]);
      // Bei art=rolle/fraktion/kommission auch als Klartext (z. B. 'Fraktionspräsidium').
      $tabelle->addColumn('referenz_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => '']);
      $tabelle->setPrimaryKey(['id']);
      $tabelle->addIndex(['typ_id'], 'pw_sitzungstyp_teiln_typ_id');
    }

    if ($schema->hasTable('pw_sitzungen')) {
      $sitzungen = $schema->getTable('pw_sitzungen');
      if (!$sitzungen->hasColumn('typ_id')) {
        $sitzungen->addColumn('typ_id', Types::BIGINT, [
          'notnull' => false,
          'default' => 0,
        ]);
        $sitzungen->addIndex(['typ_id'], 'pw_sitzungen_typ_id');
      }
      // Materialisierte Teilnehmerliste der konkreten Sitzung (JSON).
      if (!$sitzungen->hasColumn('teilnehmer')) {
        $sitzungen->addColumn('teilnehmer', Types::TEXT, [
          'notnull' => false,
          'default' => '[]',
        ]);
      }
    }

    return $schema;
  }
}
