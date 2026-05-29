<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000014Date20260529120000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('pw_geschaefte')) {
            $table = $schema->getTable('pw_geschaefte');
            if (!$table->hasColumn('einreicher')) {
                $table->addColumn('einreicher', Types::TEXT, [
                    'notnull' => true,
                    'default' => '[]',
                ]);
                $output->info('V14: added pw_geschaefte.einreicher');
            } else {
                $output->info('V14: pw_geschaefte.einreicher already exists — skipping');
            }
        }

        return $schema;
    }
}
