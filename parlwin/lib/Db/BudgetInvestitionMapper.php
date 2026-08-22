<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BudgetInvestition>
 */
class BudgetInvestitionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_budget_investition', BudgetInvestition::class);
    }

    /** Alle Investitionsprojekte eines Jahres in Buchreihenfolge. */
    public function findByJahr(int $jahr): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)))
            ->orderBy('reihenfolge', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    public function deleteByJahr(int $jahr): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
