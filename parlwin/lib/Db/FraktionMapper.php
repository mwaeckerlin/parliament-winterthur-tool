<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für Fraktionen.
 *
 * @extends QBMapper<Fraktion>
 */
class FraktionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_fraktionen', Fraktion::class);
    }

    /**
     * Gibt alle nicht gelöschten Fraktionen zurück.
     *
     * @return Fraktion[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('name', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt eine Fraktion anhand ihrer ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Fraktion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht eine Fraktion anhand der externen ID.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function findByExternId(string $externId): Fraktion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht eine Fraktion anhand ihres Namens.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function findByName(string $name): Fraktion {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)));
        return $this->findEntity($qb);
    }
}
