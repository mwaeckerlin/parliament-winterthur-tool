<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<NotizRevision>
 */
class NotizRevisionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_notiz_revisionen', NotizRevision::class);
    }

    /**
     * Alle archivierten Vorversionen einer Notiz, älteste zuerst.
     *
     * @return NotizRevision[]
     */
    public function findByAktion(int $aktionId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('aktion_id', $qb->createNamedParameter($aktionId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Löscht die Revisionen der angegebenen Aktionen hart (Purge eines Budgets).
     *
     * @param int[] $aktionIds
     */
    public function deleteByAktionen(array $aktionIds): void {
        if ($aktionIds === []) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->in('aktion_id', $qb->createNamedParameter($aktionIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $qb->executeStatement();
    }
}
