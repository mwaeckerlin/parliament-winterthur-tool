<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Bringt pw_geschaeft_aktionen ins Doctrine-Schema-Modell mit ALLEN für Notizen
 * nötigen Spalten:
 *
 * - `objekt_typ` (neu): trennt Geschäfts- von Vorstoss-Notizen, damit beide über
 *   denselben geteilten Code (NotizService) in dieser Tabelle liegen.
 * - `geloescht`: das Soft-Delete-Flag wurde in V23 per Raw-SQL
 *   (`postSchemaChange`) ergänzt. Raw-SQL-Spalten sind NICHT im Doctrine-Modell —
 *   in frischen Installationen wurde `geloescht` dadurch nie zuverlässig angelegt
 *   (der Soft-Delete/Undo warf dort HTTP 500). Beide Spalten werden hier über
 *   Doctrine deklariert (idempotent via hasColumn), sodass sie in frischen wie in
 *   bestehenden Installationen vorhanden sind. Doctrine legt fehlende Spalten an
 *   und fasst vorhandene nicht an — es wird nichts gedroppt.
 */
class Version000031Date20260720120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_geschaeft_aktionen')) {
            return null;
        }
        $table = $schema->getTable('pw_geschaeft_aktionen');
        $geaendert = false;

        if (!$table->hasColumn('objekt_typ')) {
            $table->addColumn('objekt_typ', Types::STRING, [
                'notnull' => true,
                'length' => 32,
                'default' => 'geschaeft',
            ]);
            $output->info('V31: pw_geschaeft_aktionen.objekt_typ ergänzt');
            $geaendert = true;
        }

        // geloescht ggf. nachziehen (V23 legte es nur per Raw-SQL an → in frischen
        // Installationen fehlte es). SMALLINT wie in V23, damit auf bestehenden
        // Installationen kein Typ-Diff entsteht.
        if (!$table->hasColumn('geloescht')) {
            $table->addColumn('geloescht', Types::SMALLINT, [
                'notnull' => true,
                'default' => 0,
            ]);
            $output->info('V31: pw_geschaeft_aktionen.geloescht ergänzt');
            $geaendert = true;
        }

        if (!$table->hasIndex('pw_aktion_objekt')) {
            $table->addIndex(['objekt_typ', 'geschaeft_id'], 'pw_aktion_objekt');
            $geaendert = true;
        }

        return $geaendert ? $schema : null;
    }
}
