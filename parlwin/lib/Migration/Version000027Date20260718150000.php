<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erweitert pw_vorstoesse um Notizen (JSON-Liste) und die Verknüpfung mit einem
 * Geschäft (geschaeft_id, 0 = nicht verknüpft). Ein Vorstoss ist die Vorstufe
 * zum Geschäft und wird durch die Verknüpfung abgeschlossen.
 */
class Version000027Date20260718150000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $tbl = $prefix . 'pw_vorstoesse';

        try {
            $vorhanden = (int) $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tbl]
            )->fetchOne();
            if ($vorhanden === 0) {
                $output->info("parlwin: {$tbl} fehlt — V27 übersprungen");
                return;
            }

            if (!$this->hatSpalte($tbl, 'notizen')) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tbl}` ADD COLUMN `notizen` LONGTEXT NULL"
                );
                $output->info("parlwin: {$tbl}.notizen hinzugefügt");
            }
            if (!$this->hatSpalte($tbl, 'geschaeft_id')) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tbl}` ADD COLUMN `geschaeft_id` BIGINT NOT NULL DEFAULT 0"
                );
                $output->info("parlwin: {$tbl}.geschaeft_id hinzugefügt");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} V27 übersprungen: " . $e->getMessage());
        }
    }

    private function hatSpalte(string $tabelle, string $spalte): bool
    {
        $anzahl = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabelle, $spalte]
        )->fetchOne();
        return $anzahl > 0;
    }
}
