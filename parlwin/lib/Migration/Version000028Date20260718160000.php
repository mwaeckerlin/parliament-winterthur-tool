<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Repariert Bestandsdaten: die per Migration als nullable ergänzten Spalten
 * (notizen «LONGTEXT NULL», ansprechpartner «LONGTEXT NULL», zustaendigkeit
 * «TEXT NULL») enthalten für bestehende Zeilen und nicht-dirty Inserts NULL. Das
 * Laden solcher Zeilen brach die gesamte Vorstoss-Liste (TypeError beim Mappen auf
 * die non-nullable Property). Das Entity lädt NULL nun tolerant; hier werden die
 * Altwerte zusätzlich dauerhaft auf ihren Leerwert normalisiert.
 */
class Version000028Date20260718160000 extends SimpleMigrationStep
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

        // Spalte => Leerwert, auf den ein NULL normalisiert wird.
        $leerwerte = ['notizen' => '[]', 'ansprechpartner' => '', 'zustaendigkeit' => ''];
        foreach ($leerwerte as $spalte => $leer) {
            try {
                if (!$this->hatSpalte($tbl, $spalte)) {
                    continue;
                }
                $betroffen = $this->connection->executeStatement(
                    "UPDATE `{$tbl}` SET `{$spalte}` = ? WHERE `{$spalte}` IS NULL",
                    [$leer]
                );
                if ($betroffen > 0) {
                    $output->info("parlwin: {$tbl}.{$spalte} für {$betroffen} Zeile(n) normalisiert");
                }
            } catch (\Throwable $e) {
                $output->info("parlwin: {$tbl}.{$spalte} V28 übersprungen: " . $e->getMessage());
            }
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
