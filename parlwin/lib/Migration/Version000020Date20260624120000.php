<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Sitzungs-Verknüpfung:
 *  - pw_sitzungen.verknuepfung_id gruppiert verknüpfte Sitzungen. Sitzungen mit
 *    derselben Verknüpfungs-ID zeigen eine aggregierte Sicht auf Notizen,
 *    Beschlüsse, To-dos und Dokumente. Die Daten bleiben physisch bei der
 *    Sitzung, bei der sie erfasst wurden (Entkoppeln lässt sie dort).
 *  - pw_sitzungstypen.verknuepfen markiert Typen, bei denen beim Anlegen einer
 *    Sitzung ein «Verknüpfen mit»-Dialog erscheint.
 */
class Version000020Date20260624120000 extends SimpleMigrationStep
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

        $this->spalteHinzufuegen(
            $output,
            $prefix . 'pw_sitzungen',
            'verknuepfung_id',
            'BIGINT NULL'
        );
        $this->spalteHinzufuegen(
            $output,
            $prefix . 'pw_sitzungstypen',
            'verknuepfen',
            'SMALLINT NOT NULL DEFAULT 0'
        );
    }

    private function spalteHinzufuegen(IOutput $output, string $tbl, string $spalte, string $definition): void
    {
        try {
            $vorhanden = $this->connection->executeQuery(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tbl, $spalte]
            )->fetchOne();

            if ((int) $vorhanden > 0) {
                $output->info("parlwin: {$tbl}.{$spalte} bereits vorhanden");
                return;
            }

            $this->connection->executeStatement(
                "ALTER TABLE `{$tbl}` ADD COLUMN `{$spalte}` {$definition}"
            );
            $output->info("parlwin: {$tbl}.{$spalte} hinzugefügt");
        } catch (\Throwable $e) {
            $output->info("parlwin: {$tbl}.{$spalte} Migration übersprungen: " . $e->getMessage());
        }
    }
}
