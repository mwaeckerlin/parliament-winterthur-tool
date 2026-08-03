<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ergänzt die beiden Angaben, die ein selbst angelegtes Geschäft braucht:
 *
 *  - `inhalt`     – der Beschreibungstext des Geschäfts (Rich-Text wie beim
 *                   Vorstoss). Bei Geschäften von der Parlamentswebseite bleibt
 *                   er leer, dort steht der Text auf der Quellseite.
 *  - `kommission` – die zuständige Kommission; höchstens eine, keine ist
 *                   ebenfalls gültig.
 *
 * Beide Spalten sind nullable mit leerem Default: bestehende Zeilen bleiben
 * unangetastet, und Nextcloud lehnt NOT-NULL-Spalten mit leerem Default beim
 * Upgrade ab.
 */
class Version000034Date20260724160000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_geschaefte')) {
            return null;
        }

        $tabelle = $schema->getTable('pw_geschaefte');
        $geaendert = false;

        if (!$tabelle->hasColumn('inhalt')) {
            $tabelle->addColumn('inhalt', Types::TEXT, ['notnull' => false, 'default' => '']);
            $output->info('V34: pw_geschaefte.inhalt ergänzt');
            $geaendert = true;
        }

        if (!$tabelle->hasColumn('kommission')) {
            $tabelle->addColumn('kommission', Types::STRING, [
                'notnull' => false,
                'length' => 255,
                'default' => '',
            ]);
            $output->info('V34: pw_geschaefte.kommission ergänzt');
            $geaendert = true;
        }

        return $geaendert ? $schema : null;
    }
}
