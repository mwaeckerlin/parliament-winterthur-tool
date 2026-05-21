<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Sitzungstyp>
 */
class SitzungstypMapper extends QBMapper
{
  public function __construct(IDBConnection $db)
  {
    parent::__construct($db, 'pw_sitzungstypen', Sitzungstyp::class);
  }

  /**
   * @return Sitzungstyp[]
   */
  public function findAll(): array
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
      ->orderBy('name', 'ASC');
    return $this->findEntities($qb);
  }

  /**
   * @throws DoesNotExistException
   */
  public function find(int $id): Sitzungstyp
  {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
    return $this->findEntity($qb);
  }
}
