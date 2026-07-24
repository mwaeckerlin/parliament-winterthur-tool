<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Überführt bestehende Vorstoss-Notizen aus der JSON-Spalte pw_vorstoesse.notizen
 * in die gemeinsame Aktionen-Tabelle (objekt_typ = «vorstoss»). Ab hier laufen
 * Vorstoss-Notizen über denselben Code wie Geschäfts-Notizen (Versionen,
 * Soft-Delete, Undo). Idempotent: nur Vorstösse ohne bereits migrierte
 * Notiz-Aktionen werden übernommen.
 */
class Version000032Date20260720130000 extends SimpleMigrationStep
{
    public function __construct(
        private readonly IDBConnection $connection,
        private readonly IConfig $config,
    ) {
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $vorstoesse = $prefix . 'pw_vorstoesse';
        $aktionen = $prefix . 'pw_geschaeft_aktionen';

        try {
            $rows = $this->connection->executeQuery(
                "SELECT `id`, `notizen` FROM `{$vorstoesse}`"
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            $output->info('parlwin: V32 übersprungen (pw_vorstoesse nicht lesbar): ' . $e->getMessage());
            return;
        }

        $migriert = 0;
        foreach ($rows as $row) {
            $vorstossId = (int) $row['id'];
            $notizen = json_decode((string) ($row['notizen'] ?? '[]'), true);
            if (!is_array($notizen) || $notizen === []) {
                continue;
            }

            $bereits = (int) $this->connection->executeQuery(
                "SELECT COUNT(*) FROM `{$aktionen}` "
                . "WHERE `objekt_typ` = ? AND `geschaeft_id` = ? AND `aktion_typ` = 'notiz'",
                ['vorstoss', $vorstossId]
            )->fetchOne();
            if ($bereits > 0) {
                continue;
            }

            foreach ($notizen as $notiz) {
                if (!is_array($notiz)) {
                    continue;
                }
                $text = trim((string) ($notiz['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $this->connection->executeStatement(
                    "INSERT INTO `{$aktionen}` "
                    . "(`objekt_typ`, `geschaeft_id`, `aktion_typ`, `aktion_code`, `titel`, `text`, "
                    . "`entscheid_gueltig`, `autor_uid`, `autor_name`, `erstellt_am`, `geloescht`) "
                    . "VALUES (?, ?, 'notiz', '', 'Notiz', ?, 0, ?, ?, ?, 0)",
                    [
                        'vorstoss',
                        $vorstossId,
                        $text,
                        (string) ($notiz['autorUid'] ?? ''),
                        (string) ($notiz['autorName'] ?? ''),
                        (string) ($notiz['erstelltAm'] ?? (new \DateTime())->format('Y-m-d H:i:s')),
                    ]
                );
                $migriert++;
            }
        }

        if ($migriert > 0) {
            $output->info("parlwin: V32 {$migriert} Vorstoss-Notiz(en) in die gemeinsame Tabelle überführt");
        }
    }
}
