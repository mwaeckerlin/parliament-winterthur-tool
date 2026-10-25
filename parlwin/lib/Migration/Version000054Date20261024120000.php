<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\DB\Types;

/**
 * Notizen hängen bisher an einem Objekt mit einer Zahl als Kennung — Geschäft,
 * Vorstoss, Traktandum, Sitzung, Budgetantrag, Frage. Notizen zu einem
 * übergeordneten Zusammenhang haben keine solche Zahl: das Budget eines Jahres
 * trägt sie am Jahr, ein Departement und ein Tab der Budgetansicht tragen einen
 * Namen. Diese Spalte nimmt diesen Namen auf; alles Bestehende führt den leeren
 * Schlüssel und bleibt unverändert.
 */
class Version000054Date20261024120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_geschaeft_aktionen')) {
            return null;
        }
        $table = $schema->getTable('pw_geschaeft_aktionen');
        if ($table->hasColumn('objekt_schluessel')) {
            return null;
        }
        // Die Spalte darf leer sein, und zwar als NULL: eine NOT-NULL-Spalte mit
        // leerem Standardwert bricht das Upgrade von Nextcloud ab und lässt die
        // Installation im Wartungsmodus stehen (Prüfung in MigrationSchemaTest).
        $table->addColumn('objekt_schluessel', Types::STRING, [
            'notnull' => false,
            'length' => 128,
            'default' => null,
        ]);
        $output->info('V54: pw_geschaeft_aktionen.objekt_schluessel ergänzt (Notizen am übergeordneten Zusammenhang)');
        return $schema;
    }
}
