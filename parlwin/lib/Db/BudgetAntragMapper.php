<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BudgetAntrag>
 */
class BudgetAntragMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_budget_antrag', BudgetAntrag::class);
    }

    /** Alle Anträge eines Jahres, in Antragsreihenfolge (Steuerfuss zuletzt). */
    public function findByJahr(int $jahr): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)))
            ->orderBy('reihenfolge', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    public function findeAntrag(int $id): BudgetAntrag {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /** Automatisch erzeugte Anträge einer Verteilrechnung. */
    public function findByVerteilung(int $verteilungId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('verteilung_id', $qb->createNamedParameter($verteilungId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    public function deleteByVerteilung(int $verteilungId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('verteilung_id', $qb->createNamedParameter($verteilungId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /** Alle Anträge eines Jahres entfernen (voller Clean-Slate beim Re-Import). */
    public function deleteByJahr(int $jahr): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /** Alle automatisch erzeugten Pauschal-Kinder eines Jahres entfernen. */
    public function deletePauschalByJahr(int $jahr): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('quelle', $qb->createNamedParameter('pauschal')));
        $qb->executeStatement();
    }
}
