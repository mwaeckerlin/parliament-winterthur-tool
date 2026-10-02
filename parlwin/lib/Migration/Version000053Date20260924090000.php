<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Platz für die Fassung vor der Prüfsumme eines amtlichen Dokuments (F121).
 *
 * Die Prüfsumme entscheidet, ob ein Dokument neu gelesen wird. Seit der Leser
 * seine Fassung davorschreibt («v2:» plus 64 Zeichen), passen 64 Zeichen nicht
 * mehr: Das Schreiben endete mit «Data too long for column 'quelle_hash'».
 */
class Version000053Date20260924090000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_geschaeft_dokumente')) {
            return $schema;
        }
        $tabelle = $schema->getTable('pw_geschaeft_dokumente');
        if (!$tabelle->hasColumn('quelle_hash')) {
            return $schema;
        }
        $spalte = $tabelle->getColumn('quelle_hash');
        if ($spalte->getLength() < 80) {
            $spalte->setLength(80);
        }
        return $schema;
    }
}
