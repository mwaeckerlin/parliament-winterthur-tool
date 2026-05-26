<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für Traktanden.
 *
 * @extends QBMapper<Traktandum>
 */
class TraktandumMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_traktanden', Traktandum::class);
    }

    /**
     * Gibt alle Traktanden einer Sitzung zurück.
     *
     * @return Traktandum[]
     */
    public function findBySitzung(int $sitzungId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('nummer', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Gibt ein Traktandum anhand seiner ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Traktandum {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Gibt alle Traktanden zurück, die mit einem Geschäft verknüpft sind.
     *
     * @return Traktandum[]
     */
    public function findByGeschaeft(int $geschaeftId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
        return $this->findEntities($qb);
    }

    /**
     * Markiert Traktanden einer Sitzung als gelöscht, die nicht mehr in $bekannteNummern vorkommen.
     * Bestehende Traktanden (mit Notizen etc.) bleiben erhalten — nur geloescht=true.
     *
     * @param int[] $bekannteNummern Traktandum-Nummern die noch aktiv sind
     */
    public function markiereNichtMehrVorhandeneAlsGeloescht(int $sitzungId, array $bekannteNummern): int {
        if (empty($bekannteNummern)) {
            return 0;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('geloescht', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->set('aktualisiert_am', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->notIn('nummer', $qb->createNamedParameter($bekannteNummern, IQueryBuilder::PARAM_INT_ARRAY)));
        return $qb->executeStatement();
    }

    public function findErstesBySitzungUndNummer(int $sitzungId, int $nummer): ?Traktandum {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('nummer', $qb->createNamedParameter($nummer, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC')
            ->setMaxResults(1);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if (!is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }
}
