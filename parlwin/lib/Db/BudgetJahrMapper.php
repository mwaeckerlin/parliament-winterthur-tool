<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BudgetJahr>
 */
class BudgetJahrMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_budget_jahr', BudgetJahr::class);
    }

    /** Alle vorhandenen Budgetjahre, neuestes zuerst (für das Jahr-Auswahlmenü). */
    public function alle(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->orderBy('jahr', 'DESC');
        return $this->findEntities($qb);
    }

    public function findByJahr(int $jahr): BudgetJahr {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function existiert(int $jahr): bool {
        try {
            $this->findByJahr($jahr);
            return true;
        } catch (DoesNotExistException) {
            return false;
        }
    }
}
