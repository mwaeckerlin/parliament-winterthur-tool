<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erweitert pw_vorstoesse: Zuständigkeit wird eine Liste (TEXT statt VARCHAR),
 * und ein fremder Vorstoss erhält Herkunftsfraktion + Ansprechpartner-Liste.
 */
class Version000025Date20260717130000 extends SimpleMigrationStep
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
                $output->info("parlwin: {$tbl} fehlt — V25 übersprungen");
                return;
            }

            // Zuständigkeit von VARCHAR(255) auf TEXT (Personen-Liste als JSON).
            $this->connection->executeStatement(
                "ALTER TABLE `{$tbl}` MODIFY `zustaendigkeit` TEXT NULL"
            );

            if (!$this->hatSpalte($tbl, 'herkunft_fraktion')) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tbl}` ADD COLUMN `herkunft_fraktion` VARCHAR(255) NOT NULL DEFAULT ''"
                );
                $output->info("parlwin: {$tbl}.herkunft_fraktion hinzugefügt");
            }
            if (!$this->hatSpalte($tbl, 'ansprechpartner')) {
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tbl}` ADD COLUMN `ansprechpartner` LONGTEXT NULL"
                );
                $output->info("parlwin: {$tbl}.ansprechpartner hinzugefügt");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} V25 übersprungen: " . $e->getMessage());
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
