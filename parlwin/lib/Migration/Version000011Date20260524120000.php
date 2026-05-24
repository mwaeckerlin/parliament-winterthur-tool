<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Beschluss-Migration: Vorhandene Begründungen in das Text-Feld mergen.
 *
 * Für alle Beschluss-Aktionen mit nicht-leerem `titel` UND nicht-leerem `text`
 * wird der Inhalt zu «titel + Zeilenumbruch + text» zusammengeführt, damit das
 * Text-Feld künftig als alleiniges Anzeigefeld dient.
 */
class Version000011Date20260524120000 extends SimpleMigrationStep
{
    public function __construct(private readonly IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'titel', 'text')
            ->from('pw_geschaeft_aktionen')
            ->where($qb->expr()->eq('aktion_typ', $qb->createNamedParameter('beschluss')))
            ->andWhere($qb->expr()->neq('titel', $qb->createNamedParameter('')))
            ->andWhere($qb->expr()->neq('text', $qb->createNamedParameter('')));

        $result = $qb->executeQuery();
        $count = 0;
        while ($row = $result->fetch()) {
            $merged = trim((string) $row['titel']) . "\n" . trim((string) $row['text']);
            $upd = $this->db->getQueryBuilder();
            $upd->update('pw_geschaeft_aktionen')
                ->set('text', $upd->createNamedParameter($merged))
                ->where($upd->expr()->eq('id', $upd->createNamedParameter((int) $row['id'])));
            $upd->executeStatement();
            $count++;
        }
        $result->closeCursor();

        $output->info(sprintf('Beschluss-Migration: %d Einträge zusammengeführt.', $count));
    }
}
