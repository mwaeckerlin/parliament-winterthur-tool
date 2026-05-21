<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SitzungstypTraktandum>
 */
class SitzungstypTraktandumMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_sitzungstyp_traktanden', SitzungstypTraktandum::class);
    }

    /**
     * @return SitzungstypTraktandum[]
     */
    public function findByTyp(int $typId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('typ_id', $qb->createNamedParameter($typId, IQueryBuilder::PARAM_INT)))
            ->orderBy('position', 'ASC');
        return $this->findEntities($qb);
    }

    public function deleteByTyp(int $typId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('typ_id', $qb->createNamedParameter($typId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
