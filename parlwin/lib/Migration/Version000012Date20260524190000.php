<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Traktandum: URL-Feld für Dokument-Links (Protokolle, PDFs etc.)
 * die nicht über ein verknüpftes Geschäft zugänglich sind.
 */
class Version000012Date20260524190000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_traktanden')) {
            $table = $schema->getTable('pw_traktanden');
            if (!$table->hasColumn('url')) {
                $table->addColumn('url', Types::STRING, [
                    'notnull' => true,
                    'length' => 2048,
                    'default' => '',
                ]);
            }
        }

        return $schema;
    }
}
