<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für Parlamentsmitglieder.
 *
 * @extends QBMapper<Mitglied>
 */
class MitgliedMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_mitglieder', Mitglied::class);
    }

    /**
     * Gibt alle aktiven, nicht gelöschten Mitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function findAlle(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('name', 'ASC')
            ->addOrderBy('vorname', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt alle aktiven Mitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function findAktive(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('name', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt alle Mitglieder einer Fraktion zurück.
     *
     * @return Mitglied[]
     */
    public function findByFraktion(string $fraktion): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('fraktion', $qb->createNamedParameter($fraktion)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('name', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt ein Mitglied anhand seiner ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Mitglied
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht ein Mitglied anhand der externen ID.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function findByExternId(string $externId): Mitglied
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)));
        return $this->findEntity($qb);
    }

    /**
     * Sucht ein Mitglied anhand der Nextcloud-UID.
     */
    public function findByNextcloudUid(string $uid): ?Mitglied
    {
        if ($uid === '') {
            return null;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('nextcloud_uid', $qb->createNamedParameter($uid)))
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * Markiert alle Mitglieder als nicht mehr aktiv, wenn sie nicht in $bekannteIds vorkommen.
     *
     * Chunkweise IN-Abfrage statt NOT IN, um Nextclouds Oracle-kompatibles
     * 1000-Element-Limit nicht zu überschreiten.
     *
     * @param string[] $bekannteIds
     */
    public function markiereNichtMehrAktive(array $bekannteIds): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('extern_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
        $result = $qb->executeQuery();
        $aktive = [];
        while ($row = $result->fetch()) {
            $aktive[] = (string) $row['extern_id'];
        }
        $result->closeCursor();

        $bekanntSet = array_flip(array_map('strval', $bekannteIds));
        $zuDeaktivieren = array_values(array_filter($aktive, static fn(string $id): bool => !isset($bekanntSet[$id])));
        if ($zuDeaktivieren === []) {
            return 0;
        }

        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $total = 0;
        foreach (array_chunk($zuDeaktivieren, 900) as $chunk) {
            $qbu = $this->db->getQueryBuilder();
            $qbu->update($this->getTableName())
                ->set('aktiv', $qbu->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
                ->set('aktualisiert_am', $qbu->createNamedParameter($jetzt))
                ->where($qbu->expr()->eq('aktiv', $qbu->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
                ->andWhere($qbu->expr()->in('extern_id', $qbu->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
            $total += $qbu->executeStatement();
        }
        return $total;
    }
}
