<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Frage>
 */
class FrageMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_fragestunde_fragen', Frage::class);
    }

    /**
     * Die Fragen einer Fragestunde in der Reihenfolge, in der sie eingebracht
     * wurden — so wie sie der Parlamentsdienst später nummeriert.
     *
     * @return Frage[]
     */
    public function findByFragestunde(int $fragestundeId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('fragestunde_id', $qb->createNamedParameter($fragestundeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('erstellt_am', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * ALLE Fragen, die neueste zuerst — zugeteilte wie freie. Die Fraktion
     * sammelt jederzeit; eine Fragestunde braucht es dafür nicht.
     *
     * @return Frage[]
     */
    public function findAlle(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC');
        return $this->findEntities($qb);
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): Frage
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }
}
