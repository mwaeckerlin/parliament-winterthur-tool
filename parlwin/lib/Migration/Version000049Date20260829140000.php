<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Budget: Investitionsprojekte um die Konten aus dem Anhang «Kontrolle der
 * Investitionskredite» erweitern (F87).
 *
 * Je Projekt führt das Buch dort die einzelnen Konten mit Teilbetrag,
 * Teilkredit und dem Datum, an dem der Kredit bewilligt wurde — die Angaben,
 * die das Detail eines Projekts zeigt. Sie liegen als JSON-Liste im Feld
 * «konten».
 */
class Version000049Date20260829140000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_budget_investition')) {
            return null;
        }
        $t = $schema->getTable('pw_budget_investition');
        if (!$t->hasColumn('konten')) {
            $t->addColumn('konten', Types::TEXT, ['notnull' => false, 'default' => '[]']);
        }
        return $schema;
    }
}
