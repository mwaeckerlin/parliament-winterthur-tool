<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Sitzung: Notizen-Spalte (JSON-Array) ergänzen.
 *
 * Auf Sitzungs-Ebene gab es bisher nur `bemerkungen` (freier Textbereich).
 * Analog zu Traktanden bekommen Sitzungen jetzt eine eigene Notizen-Liste
 * (mit Datum/Autor/Text), das alte `bemerkungen`-Feld bleibt für Migration
 * vorhandener Daten erhalten.
 */
class Version000010Date20260521120000 extends SimpleMigrationStep
{
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if ($schema->hasTable('pw_sitzungen')) {
      $tabelle = $schema->getTable('pw_sitzungen');
      if (!$tabelle->hasColumn('notizen')) {
        $tabelle->addColumn('notizen', Types::TEXT, [
          'notnull' => true,
          'default' => '[]',
        ]);
      }
    }

    return $schema;
  }
}
