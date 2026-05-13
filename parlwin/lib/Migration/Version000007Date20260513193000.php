<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Macht lange Titelspalten robust gegen sehr grosse Quelltexte.
 *
 * Hintergrund:
 * In der Quelle können Titel deutlich länger als VARCHAR(255/1000) werden.
 * Deshalb werden diese Spalten auf TEXT umgestellt.
 */
class Version000007Date20260513193000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->setzeTextSpalte($schema, 'pw_geschaefte', 'titel');
        $this->setzeTextSpalte($schema, 'pw_sitzungen', 'titel');
        $this->setzeTextSpalte($schema, 'pw_traktanden', 'titel');
        $this->setzeTextSpalte($schema, 'pw_vorstoss_entwuerfe', 'titel');
        $this->setzeTextSpalte($schema, 'pw_geschaeft_aktionen', 'titel');

        return $schema;
    }

    private function setzeTextSpalte(ISchemaWrapper $schema, string $tabellenName, string $spaltenName): void {
        if (!$schema->hasTable($tabellenName)) {
            return;
        }
        $table = $schema->getTable($tabellenName);
        if (!$table->hasColumn($spaltenName)) {
            return;
        }

        $aktuell = $table->getColumn($spaltenName)->getType()->getName();
        if ($aktuell === Types::TEXT || $aktuell === 'text') {
            return;
        }

        $table->changeColumn($spaltenName, [
            'type' => Type::getType(Types::TEXT),
            'notnull' => false,
            'default' => '',
        ]);
    }
}

