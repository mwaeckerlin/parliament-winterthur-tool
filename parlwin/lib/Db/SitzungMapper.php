<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für Parlamentssitzungen.
 *
 * @extends QBMapper<Sitzung>
 */
class SitzungMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_sitzungen', Sitzung::class);
    }

    /**
     * Gibt alle nicht gelöschten Sitzungen zurück.
     *
     * @return Sitzung[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
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
     * Gibt alle zukünftigen Sitzungen zurück.
     *
     * @return Sitzung[]
     */
    public function findKuenftige(): array
    {
        $qb = $this->db->getQueryBuilder();
        $heute = (new \DateTime())->format('Y-m-d');
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->gte('datum', $qb->createNamedParameter($heute)))
            ->orderBy('datum', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt eine Sitzung anhand ihrer ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Sitzung
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht eine Sitzung anhand der externen ID.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function findByExternId(string $externId): Sitzung
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)));
        return $this->findEntity($qb);
    }

    /**
     * Gibt alle externen IDs der bekannten Parlamentssitzungen zurück.
     * Interne Sitzungen (extern_id IS NULL) werden ausgeschlossen.
     *
     * @return string[]
     */
    public function findAllExternIds(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('extern_id')
            ->from($this->getTableName())
            ->where($qb->expr()->isNotNull('extern_id'))
            ->andWhere($qb->expr()->neq('extern_id', $qb->createNamedParameter('')));
        $result = $qb->executeQuery();
        $ids = [];
        while ($row = $result->fetch()) {
            $ids[] = (string) $row['extern_id'];
        }
        $result->closeCursor();
        return $ids;
    }

    /**
     * Markiert alle Parlamentssitzungen, deren externe IDs nicht in $bekannteIds sind, als gelöscht.
     * Interne Sitzungen (extern_id IS NULL) werden nie angefasst.
     *
     * Chunkweise IN-Abfrage statt NOT IN, um Nextclouds Oracle-kompatibles
     * 1000-Element-Limit nicht zu überschreiten.
     *
     * @param string[] $bekannteIds
     */
    public function markiereNichtMehrVorhandeneAlsGeloescht(array $bekannteIds): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('extern_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->isNotNull('extern_id'))
            ->andWhere($qb->expr()->neq('extern_id', $qb->createNamedParameter('')));
        $result = $qb->executeQuery();
        $aktive = [];
        while ($row = $result->fetch()) {
            $aktive[] = (string) $row['extern_id'];
        }
        $result->closeCursor();

        $bekanntSet = array_flip(array_map('strval', $bekannteIds));
        $zuLoeschen = array_values(array_filter($aktive, static fn(string $id): bool => !isset($bekanntSet[$id])));
        if ($zuLoeschen === []) {
            return 0;
        }

        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $total = 0;
        foreach (array_chunk($zuLoeschen, 900) as $chunk) {
            $qbu = $this->db->getQueryBuilder();
            $qbu->update($this->getTableName())
                ->set('geloescht', $qbu->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
                ->set('aktualisiert_am', $qbu->createNamedParameter($jetzt))
                ->where($qbu->expr()->eq('geloescht', $qbu->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
                ->andWhere($qbu->expr()->in('extern_id', $qbu->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
            $total += $qbu->executeStatement();
        }
        return $total;
    }
}
