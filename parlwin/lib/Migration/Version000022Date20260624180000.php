<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Tabelle für politische Vorstösse (Motionen, Postulate, Interpellationen …):
 * pw_vorstoesse. Manuell erfasst oder automatisch aus «Fraktion/40_Vorstösse».
 */
class Version000022Date20260624180000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?Schema
    {
        return null;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $tbl = $prefix . 'pw_vorstoesse';

        try {
            $vorhanden = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tbl]
            )->fetchOne();
            if ((int) $vorhanden === 0) {
                $this->connection->executeStatement(
                    "CREATE TABLE `{$tbl}` ("
                    . "`id` BIGINT AUTO_INCREMENT PRIMARY KEY, "
                    . "`titel` VARCHAR(512) NOT NULL DEFAULT '', "
                    . "`art` VARCHAR(64) NOT NULL DEFAULT '', "
                    . "`herkunft` VARCHAR(16) NOT NULL DEFAULT 'eigene', "
                    . "`status` VARCHAR(16) NOT NULL DEFAULT 'neu', "
                    . "`beschluss` VARCHAR(255) NOT NULL DEFAULT '', "
                    . "`zustaendigkeit` VARCHAR(255) NOT NULL DEFAULT '', "
                    . "`inhalt` LONGTEXT NULL, "
                    . "`dokument` VARCHAR(1024) NOT NULL DEFAULT '', "
                    . "`geloescht` SMALLINT NOT NULL DEFAULT 0, "
                    . "`erstellt_am` VARCHAR(32) NOT NULL DEFAULT '', "
                    . "`aktualisiert_am` VARCHAR(32) NOT NULL DEFAULT ''"
                    . ") DEFAULT CHARSET=utf8mb4"
                );
                $output->info("parlwin: Tabelle {$tbl} angelegt");
            } else {
                $output->info("parlwin: Tabelle {$tbl} bereits vorhanden");
            }
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl} Migration übersprungen: " . $e->getMessage());
        }
    }
}
