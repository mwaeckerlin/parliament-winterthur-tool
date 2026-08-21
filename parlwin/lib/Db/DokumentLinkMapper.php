<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<DokumentLink>
 */
class DokumentLinkMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_dokument_links', DokumentLink::class);
    }

    /**
     * Alle Datei-Verknüpfungen eines Objekts, in Reihenfolge des Hinzufügens.
     *
     * @return DokumentLink[]
     */
    public function findByObjekt(string $objektTyp, int $objektId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('objekt_id', $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    /** Gibt es die Datei bei diesem Objekt bereits? (verhindert Doppel-Verknüpfung) */
    public function existiert(string $objektTyp, int $objektId, int $fileId): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('objekt_id', $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();
        return is_array($row);
    }

    /** Löst eine Verknüpfung; die Datei selbst bleibt bestehen. */
    public function entferne(string $objektTyp, int $objektId, int $fileId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('objekt_id', $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
