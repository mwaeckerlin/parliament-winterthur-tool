<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

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

    /** Alle Pauschalverteilungen eines Jahres (F100), in Reihenfolge. */
    public function alleFuerJahr(int $jahr): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('jahr', $qb->createNamedParameter($jahr, IQueryBuilder::PARAM_INT)))
            ->orderBy('reihenfolge', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    public function findeVerteilung(int $id): BudgetVerteilung {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Die primäre Ziel-Verteilung (automatischer Ausglech auf schwarze Null usw.);
     * legt sie an, wenn noch keine existiert. Bestehende Datenbestände (eine Zeile
     * je Jahr) sind damit weiterhin die Ziel-Verteilung.
     */
    public function findeOderStandard(int $jahr): BudgetVerteilung {
        foreach ($this->alleFuerJahr($jahr) as $v) {
            if ($v->modusOderStandard() === 'ziel') {
                return $v;
            }
        }
        $v = new BudgetVerteilung();
        $v->setJahr($jahr);
        $v->setAutomatikEin(1);
        $v->setModus('ziel');
        $v->setZielModus('schwarze_null');
        $v->setZielBetrag(0);
        return $this->insert($v);
    }
}
