<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Datenbankzugriff für politische Geschäfte.
 *
 * @extends QBMapper<Geschaeft>
 */
class GeschaeftMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_geschaefte', Geschaeft::class);
    }

    /**
     * Nächste freie Primärschlüssel-ID (max + 1, mindestens 1).
     *
     * Wird für selbst angelegte Geschäfte benötigt: die Tabelle wird nicht in
     * allen Installationen mit AUTO_INCREMENT geführt (die importierten
     * Geschäfte tragen die Geschäftsnummer als ID), deshalb muss beim Anlegen
     * ohne externe Nummer eine ID explizit vergeben werden.
     */
    public function naechsteId(): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('id'))->from($this->getTableName());
        $result = $qb->executeQuery();
        $max = (int) $result->fetchOne();
        $result->closeCursor();
        return $max + 1;
    }

    /**
     * Wandelt eine Datenbankzeile in eine Geschaeft-Entity um und ersetzt dabei
     * NULL-Spaltenwerte durch Leerstrings.
     *
     * Die String-Properties der Entity (z.B. quelleAktualisiertAm, das in der DB
     * eine nullable datetime-Spalte ist) sind nicht nullable typisiert. Eine
     * einzige NULL-Zeile würde sonst beim Mapping einen TypeError werfen und damit
     * die GESAMTE Geschäftsliste mit HTTP 500 abbrechen lassen («Keine Geschäfte
     * gefunden»). Nullable Spalten gibt es hier nur für String-Felder, daher ist
     * die Ersetzung NULL → '' unbedenklich.
     */
    protected function mapRowToEntity(array $row): Entity
    {
        foreach ($row as $spalte => $wert) {
            if ($wert === null) {
                $row[$spalte] = '';
            }
        }
        return parent::mapRowToEntity($row);
    }

    /**
     * Gibt alle nicht gelöschten Geschäfte zurück.
     *
     * @return Geschaeft[]
     */
    public function findAll(int $limit = 100, int $offset = 0, bool $inklusiveErledigt = false): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('datum', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (!$inklusiveErledigt) {
            $statusLower = $qb->func()->lower('status');
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('status'),
                $qb->expr()->notLike($statusLower, $qb->createNamedParameter('%erledigt%'))
            ));
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('status'),
                $qb->expr()->notLike($statusLower, $qb->createNamedParameter('%abgeschlossen%'))
            ));
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('status'),
                $qb->expr()->notLike($statusLower, $qb->createNamedParameter('%aufgehoben%'))
            ));
        }

        return $this->findEntities($qb);
    }

    /**
     * Volltext-Suche über Nummer und Titel (für Unified Search Provider).
     *
     * @return Geschaeft[]
     */
    public function searchByText(string $text, int $limit = 20): array
    {
        $qb = $this->db->getQueryBuilder();
        $like = '%' . $this->db->escapeLikeParameter($text) . '%';
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->iLike('titel', $qb->createNamedParameter($like)),
                $qb->expr()->iLike('nummer', $qb->createNamedParameter($like))
            ))
            ->orderBy('datum', 'DESC')
            ->setMaxResults($limit);
        return $this->findEntities($qb);
    }

    /**
     * Gibt ein Geschäft anhand seiner ID zurück.
     *
     * @throws DoesNotExistException wenn nicht gefunden
     */
    public function find(int $id): Geschaeft
    {
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
    public function findByExternId(string $externId): Geschaeft
    {
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
    public function findAllExternIds(): array
    {
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
     * Implementierung ohne `NOT IN (große-liste)`, da Nextclouds DBAL Oracle-kompatible
     * Limits durchsetzt (max. 1000 IN-Werte). Wir laden die aktuell ungelöschten
     * extern_ids, ermitteln in PHP, welche fehlen, und löschen sie chunkweise via `IN`.
     *
     * @param string[] $bekannteIds Externe IDs, die noch auf der Webseite vorhanden sind
     */
    public function markiereNichtMehrVorhandeneAlsGeloescht(array $bekannteIds): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('extern_id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geloescht', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
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

    /**
     * Stellt sicher, dass die interne Primary-ID der externen `_rte/information/{id}`-ID entspricht.
     *
     * Falls nötig, werden auch referenzierende Traktanden auf die neue ID umgehängt.
     */
    public function harmonisiereIdMitExternId(Geschaeft $geschaeft, int $externIdAlsInt): void
    {
        $aktuelleId = $geschaeft->getId();
        if ($aktuelleId === $externIdAlsInt) {
            return;
        }

        if ($this->idExistiert($externIdAlsInt)) {
            // Konfliktfall: Ziel-ID ist bereits belegt, keine automatische Umschlüsselung.
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('id', $qb->createNamedParameter($externIdAlsInt, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($aktuelleId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();

        $qb = $this->db->getQueryBuilder();
        $qb->update('pw_traktanden')
            ->set('geschaeft_id', $qb->createNamedParameter($externIdAlsInt, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($aktuelleId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();

        $geschaeft->setId($externIdAlsInt);
    }

    private function idExistiert(int $id): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return is_array($row);
    }
}
