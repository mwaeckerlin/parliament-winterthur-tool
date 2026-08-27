<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BudgetAntragEntscheid>
 */
class BudgetAntragEntscheidMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_budget_antrag_entscheid', BudgetAntragEntscheid::class);
    }

    public function findByAntrag(int $antragId): BudgetAntragEntscheid {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('antrag_id', $qb->createNamedParameter($antragId, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Löscht die Entscheide der angegebenen Anträge hart (Purge eines Budgets).
     *
     * @param int[] $antragIds
     */
    public function deleteByAntraege(array $antragIds): void {
        if ($antragIds === []) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->in('antrag_id', $qb->createNamedParameter($antragIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $qb->executeStatement();
    }

    /**
     * Entscheid-Status je Antrag-Id für ein ganzes Jahr (für die Live-Verfolgung).
     *
     * @param int[] $antragIds
     * @return array<int, string> antragId => status
     */
    public function statusFuer(array $antragIds): array {
        if ($antragIds === []) {
            return [];
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('antrag_id', 'status')
            ->from($this->getTableName())
            ->where($qb->expr()->in('antrag_id', $qb->createNamedParameter($antragIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $result = $qb->executeQuery();
        $map = [];
        while ($row = $result->fetch()) {
            $map[(int) $row['antrag_id']] = (string) $row['status'];
        }
        $result->closeCursor();
        return $map;
    }

    /** Setzt den Status (legt an oder aktualisiert). */
    public function setzeStatus(int $antragId, string $status, ?string $von, int $zeit): BudgetAntragEntscheid {
        try {
            $e = $this->findByAntrag($antragId);
            $e->setStatus($status);
            $e->setGeaendertVon($von);
            $e->setGeaendertAm($zeit);
            return $this->update($e);
        } catch (DoesNotExistException) {
            $e = new BudgetAntragEntscheid();
            $e->setAntragId($antragId);
            $e->setStatus($status);
            $e->setGeaendertVon($von);
            $e->setGeaendertAm($zeit);
            return $this->insert($e);
        }
    }
}
