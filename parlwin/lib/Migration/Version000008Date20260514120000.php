<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt pw_kommissionen um Lebenszyklus-Felder (aktiv, datumVon, datumBis),
 * damit aufgelöste Spezialkommissionen korrekt als inaktiv erkannt werden.
 */
class Version000008Date20260514120000 extends SimpleMigrationStep
{
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('pw_kommissionen')) {
      return null;
    }

    $tabelle = $schema->getTable('pw_kommissionen');

    if (!$tabelle->hasColumn('aktiv')) {
      $tabelle->addColumn('aktiv', Types::BOOLEAN, [
        'notnull' => true,
        'default' => true,
      ]);
    }

    if (!$tabelle->hasColumn('datum_von')) {
      $tabelle->addColumn('datum_von', Types::STRING, [
        'notnull' => false,
        'length' => 20,
        'default' => '',
      ]);
    }

    if (!$tabelle->hasColumn('datum_bis')) {
      $tabelle->addColumn('datum_bis', Types::STRING, [
        'notnull' => false,
        'length' => 20,
        'default' => '',
      ]);
    }

    return $schema;
  }
}
