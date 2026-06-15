<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * No-op: Die eigentliche AUTO_INCREMENT-Migration wurde nach Version000016
 * verschoben, weil changeSchema() in NC33 den falschen Rückgabetyp liefert.
 */
class Version000015Date20260615120000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?Schema
    {
        return null;
    }
}
