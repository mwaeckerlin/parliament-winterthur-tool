<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<BudgetVerteilung>
 */
class BudgetVerteilungMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_budget_verteilung', BudgetVerteilung::class);
    }

    public function findByJahr(int $jahr): BudgetVerteilung {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /** Liefert die Verteilung des Jahres oder legt den Standard (schwarze Null, Automatik ein) an. */
    public function findeOderStandard(int $jahr): BudgetVerteilung {
        try {
            return $this->findByJahr($jahr);
        } catch (DoesNotExistException) {
            $v = new BudgetVerteilung();
            $v->setJahr($jahr);
            $v->setAutomatikEin(1);
            $v->setZielModus('schwarze_null');
            $v->setZielBetrag(0);
            return $this->insert($v);
        }
    }
}
