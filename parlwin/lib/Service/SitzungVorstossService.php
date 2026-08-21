<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Verwaltet die n:m-Verknüpfung zwischen Sitzungen und Vorstössen
 * (Tabelle pw_sitzung_vorstoss) — eigene wie fremde Vorstösse lassen sich so
 * direkt an eine Sitzung traktandieren. Analog zu SitzungGeschaeftService.
 */
class SitzungVorstossService {
    public function __construct(
        private readonly IDBConnection $db,
    ) {
    }

    /** Verknüpft einen Vorstoss mit einer Sitzung (idempotent). */
    public function verlinke(int $sitzungId, int $vorstossId): void {
        if ($this->verlinkt($sitzungId, $vorstossId)) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert('pw_sitzung_vorstoss')
            ->values([
                'sitzung_id' => $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT),
                'vorstoss_id' => $qb->createNamedParameter($vorstossId, IQueryBuilder::PARAM_INT),
            ]);
        $qb->executeStatement();
    }

    /** Löst die Verknüpfung eines Vorstosses von einer Sitzung. */
    public function entlinke(int $sitzungId, int $vorstossId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('pw_sitzung_vorstoss')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('vorstoss_id', $qb->createNamedParameter($vorstossId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /** Ob ein Vorstoss bereits mit einer Sitzung verknüpft ist. */
    public function verlinkt(int $sitzungId, int $vorstossId): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('pw_sitzung_vorstoss')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('vorstoss_id', $qb->createNamedParameter($vorstossId, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $result = $qb->executeQuery();
        $vorhanden = $result->fetchOne();
        $result->closeCursor();
        return $vorhanden !== false;
    }

    /**
     * Gibt die IDs der mit einer Sitzung verknüpften Vorstösse zurück.
     *
     * @return int[]
     */
    public function vorstossIdsFuerSitzung(int $sitzungId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('vorstoss_id')
            ->from('pw_sitzung_vorstoss')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();
        return $ids;
    }
}
