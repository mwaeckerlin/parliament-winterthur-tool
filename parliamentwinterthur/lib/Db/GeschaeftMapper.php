<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für politische Geschäfte.
 *
 * @extends QBMapper<Geschaeft>
 */
class GeschaeftMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_geschaefte', Geschaeft::class);
    }

    /**
     * Gibt alle nicht gelöschten Geschäfte zurück.
     *
     * @return Geschaeft[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('datum', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        return $this->findEntities($qb);
    }

    /**
     * Gibt ein Geschäft anhand seiner ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Geschaeft {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht ein Geschäft anhand der externen ID (von der Parlamentswebseite).
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function findByExternId(string $externId): Geschaeft {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)));
        return $this->findEntity($qb);
    }

    /**
     * Gibt alle externen IDs der bekannten Geschäfte zurück.
     *
     * @return string[]
     */
    public function findAllExternIds(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('extern_id')
            ->from($this->getTableName());
        $result = $qb->executeQuery();
        $ids = [];
        while ($row = $result->fetch()) {
            $ids[] = $row['extern_id'];
        }
        $result->closeCursor();
        return $ids;
    }

    /**
     * Markiert alle Geschäfte, deren externe IDs nicht in $bekannteIds sind, als gelöscht.
     *
     * @param string[] $bekannteIds Externe IDs, die noch auf der Webseite vorhanden sind
     */
    public function markiereNichtMehrVorhandeneAlsGeloescht(array $bekannteIds): int {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('geloescht', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->set('aktualisiert_am', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
        if (!empty($bekannteIds)) {
            $qb->andWhere($qb->expr()->notIn(
                'extern_id',
                $qb->createNamedParameter($bekannteIds, IQueryBuilder::PARAM_STR_ARRAY)
            ));
        }
        return $qb->executeStatement();
    }
}
