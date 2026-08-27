<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Ereignis>
 */
class EreignisMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_ereignis', Ereignis::class);
    }

    /**
     * Die neuesten Ereignisse zuerst.
     *
     * @return Ereignis[]
     */
    public function neueste(int $limit = 200): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('zeitpunkt', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults(max(1, $limit));
        return $this->findEntities($qb);
    }

    /**
     * Ereignisse älter als der Grenz-Zeitpunkt löschen (Aufräumen, damit das
     * Protokoll nicht unbegrenzt wächst).
     */
    public function loescheAelterAls(int $zeitpunkt): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt('zeitpunkt', $qb->createNamedParameter($zeitpunkt, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
