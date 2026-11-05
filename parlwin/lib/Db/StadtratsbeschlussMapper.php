<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Stadtratsbeschluss>
 */
class StadtratsbeschlussMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_stadtratsbeschluesse', Stadtratsbeschluss::class);
    }

    /**
     * Die Beschlüsse, der jüngste zuerst.
     *
     * `$limit` begrenzt die Antwort: die Quelle führt 3160 Beschlüsse, und eine
     * Ansicht braucht nie alle auf einmal.
     *
     * @return Stadtratsbeschluss[]
     */
    public function findAll(int $limit = 500, int $offset = 0): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('datum', 'DESC')
            ->addOrderBy('nummer', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->setFirstResult(max(0, $offset));
        return $this->findEntities($qb);
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): Stadtratsbeschluss
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Der Beschluss zu einer Nummer der Quelle, oder null.
     *
     * Der Import ruft sie je Eintrag: steht der Beschluss schon da, wird er
     * aktualisiert, sonst angelegt.
     */
    public function findByExternId(string $externId): ?Stadtratsbeschluss
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)))
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

}
