<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
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
     * @throws DoesNotExistException
     */
    public function findById(int $id): GeschaeftAktion
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if (!is_array($row)) {
            throw new DoesNotExistException("GeschaeftAktion $id nicht gefunden");
        }

        return $this->mapRowToEntity($row);
    }

    public function loeschen(GeschaeftAktion $aktion): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($aktion->getId(), IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
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
            ->andWhere($qb->expr()->eq('objekt_typ', $qb->createNamedParameter('geschaeft')))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Verschiebt alle Notizen (reguläre und Sitzungsnotizen) eines Geschäfts an ein
     * anderes — bei der Verknüpfung eigenes→offizielles wandern die Notizen mit.
     * Die Notiz-Versionen (NotizRevision) hängen an der Aktions-ID und wandern
     * dadurch automatisch mit.
     *
     * @return int Anzahl verschobener Notizen
     */
    public function verschiebeNotizen(int $vonGeschaeftId, int $zuGeschaeftId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('geschaeft_id', $qb->createNamedParameter($zuGeschaeftId, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($vonGeschaeftId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('objekt_typ', $qb->createNamedParameter('geschaeft')))
            ->andWhere($qb->expr()->in('aktion_typ', $qb->createNamedParameter(['notiz', 'sitzungsnotiz'], IQueryBuilder::PARAM_STR_ARRAY)));
        return $qb->executeStatement();
    }

    /**
     * Alle Notiz-Aktionen (aktiv und gelöscht) eines Objekts — neueste zuerst,
     * identisch zur Reihenfolge von findByGeschaeft. Für den geteilten
     * NotizService (Geschäft wie Vorstoss).
     *
     * @return GeschaeftAktion[]
     */
    public function findNotizen(string $objektTyp, int $objektId, string $kategorie = 'notiz'): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('aktion_typ', $qb->createNamedParameter($kategorie)))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Notiz-Aktionen für mehrere Objekte in EINER Abfrage (kein N+1), neueste
     * zuerst. Leere ID-Liste ⇒ leeres Ergebnis.
     *
     * @param int[] $objektIds
     * @return GeschaeftAktion[]
     */
    public function findNotizenFuerObjekte(string $objektTyp, array $objektIds, string $kategorie = 'notiz'): array
    {
        if ($objektIds === []) {
            return [];
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('aktion_typ', $qb->createNamedParameter($kategorie)))
            ->andWhere($qb->expr()->in('geschaeft_id', $qb->createNamedParameter($objektIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->orderBy('erstellt_am', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Löscht ALLE Aktionen (Notizen usw.) der angegebenen Objekte hart und gibt
     * deren IDs zurück, damit der Aufrufer die zugehörigen Revisionen mitlöschen
     * kann. Für den vollständigen Purge eines Budgets (Frontend-Re-Import).
     *
     * @param int[] $objektIds
     * @return int[] die gelöschten Aktions-IDs
     */
    public function deleteFuerObjekte(string $objektTyp, array $objektIds): array
    {
        if ($objektIds === []) {
            return [];
        }
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->in('geschaeft_id', $qb->createNamedParameter($objektIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $ergebnis = $qb->executeQuery();
        $ids = array_map(static fn ($r): int => (int) $r['id'], $ergebnis->fetchAll());
        $ergebnis->closeCursor();

        $del = $this->db->getQueryBuilder();
        $del->delete($this->getTableName())
            ->where($del->expr()->eq('objekt_typ', $del->createNamedParameter($objektTyp)))
            ->andWhere($del->expr()->in('geschaeft_id', $del->createNamedParameter($objektIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $del->executeStatement();
        return $ids;
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
            ->andWhere($qb->expr()->eq('objekt_typ', $qb->createNamedParameter('geschaeft')))
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
