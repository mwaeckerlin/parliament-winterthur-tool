<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GeschaeftAktion>
 */
class GeschaeftAktionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_geschaeft_aktionen', GeschaeftAktion::class);
    }

    /**
     * @return GeschaeftAktion[]
     */
    public function findByGeschaeft(int $geschaeftId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    public function findLetzterGueltigerBeschluss(int $geschaeftId): ?GeschaeftAktion
    {
        return $this->findAktuelleAktionVom($geschaeftId, 'beschluss');
    }

    /**
     * Liefert das aktuell aktive (noch nicht archivierte) Votum zu einem
     * Geschäft, falls vorhanden. Aktiv = entscheid_gueltig = true.
     */
    public function findAktuellesVotum(int $geschaeftId): ?GeschaeftAktion
    {
        return $this->findAktuelleAktionVom($geschaeftId, 'votum');
    }

    private function findAktuelleAktionVom(int $geschaeftId, string $aktionTyp): ?GeschaeftAktion
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('aktion_typ', $qb->createNamedParameter($aktionTyp)))
            ->andWhere($qb->expr()->eq('entscheid_gueltig', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC')
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
