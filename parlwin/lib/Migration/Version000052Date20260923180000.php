<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Inhalt der amtlichen Dokumente (F121): Jedes PDF, das an einem Geschäft
 * hängt, wird gelesen und hier abgelegt — als Struktur (JSON) und als Markdown,
 * das aus derselben Struktur entsteht. Damit steht der Text im Geschäft selbst,
 * die Suche findet ihn, und niemand muss ein PDF herunterladen, um zu lesen,
 * worum es geht.
 */
class Version000052Date20260923180000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('pw_geschaeft_dokumente')) {
            $t = $schema->createTable('pw_geschaeft_dokumente');
            $t->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $t->addColumn('geschaeft_id', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            // Die Nummer des Dokuments auf der Parlamentswebseite («/_doc/<id>»).
            $t->addColumn('extern_id', Types::STRING, ['length' => 64, 'notnull' => false]);
            $t->addColumn('titel', Types::STRING, ['length' => 500, 'notnull' => false]);
            // «Vorstoss», «Antrag Stadtrat», «Antwort Stadtrat», «Beilage», …
            $t->addColumn('kategorie', Types::STRING, ['length' => 128, 'notnull' => false]);
            $t->addColumn('datum', Types::STRING, ['length' => 10, 'notnull' => false]);
            $t->addColumn('url', Types::STRING, ['length' => 2000, 'notnull' => false]);
            // Die gelesene Struktur: Abschnitte mit Art (überschrift, absatz,
            // liste, tabelle), Seite und Text.
            $t->addColumn('struktur', Types::TEXT, ['notnull' => false]);
            // Dieselbe Struktur als Markdown, für Anzeige und Suche.
            $t->addColumn('markdown', Types::TEXT, ['notnull' => false]);
            // Reiner Text ohne Auszeichnung, damit die Suche einfach bleibt.
            $t->addColumn('volltext', Types::TEXT, ['notnull' => false]);
            $t->addColumn('seiten', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            // Prüfsumme über die gelesene Datei: Ein Dokument wird nur dann neu
            // gelesen, wenn es sich wirklich geändert hat.
            // 80 Zeichen: 64 für die Prüfsumme und Platz für die Fassung des
            // Lesers davor («v2:»), an der sich ein veralteter Inhalt erkennt.
            $t->addColumn('quelle_hash', Types::STRING, ['length' => 80, 'notnull' => false]);
            $t->addColumn('gelesen_am', Types::STRING, ['length' => 32, 'notnull' => false]);
            $t->addColumn('fehler', Types::STRING, ['length' => 500, 'notnull' => false]);
            $t->setPrimaryKey(['id']);
            $t->addIndex(['geschaeft_id'], 'pw_gdok_geschaeft');
            $t->addUniqueIndex(['extern_id'], 'pw_gdok_extern');
        }

        return $schema;
    }
}
