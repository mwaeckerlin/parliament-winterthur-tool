<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Verwaltet die n:m-Verknüpfung zwischen Sitzungen und Geschäften
 * (Tabelle pw_sitzung_geschaeft). Eine Verknüpfung kann manuell oder
 * automatisch (über die Kommissionen des Sitzungstyps) entstehen.
 */
class SitzungGeschaeftService {
    public function __construct(
        private readonly IDBConnection $db,
    ) {
    }

    /** Verknüpft ein Geschäft mit einer Sitzung (idempotent). */
    public function verlinke(int $sitzungId, int $geschaeftId, bool $automatisch = false): void {
        if ($this->verlinkt($sitzungId, $geschaeftId)) {
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert('pw_sitzung_geschaeft')
            ->values([
                'sitzung_id' => $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT),
                'geschaeft_id' => $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT),
                'automatisch' => $qb->createNamedParameter($automatisch ? 1 : 0, IQueryBuilder::PARAM_INT),
            ]);
        $qb->executeStatement();
    }

    /** Löst die Verknüpfung eines Geschäfts von einer Sitzung. */
    public function entlinke(int $sitzungId, int $geschaeftId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('pw_sitzung_geschaeft')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /** Ob ein Geschäft bereits mit einer Sitzung verknüpft ist. */
    public function verlinkt(int $sitzungId, int $geschaeftId): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('pw_sitzung_geschaeft')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $result = $qb->executeQuery();
        $vorhanden = $result->fetchOne();
        $result->closeCursor();
        return $vorhanden !== false;
    }

    /**
     * Gibt die IDs der mit einer Sitzung verknüpften Geschäfte zurück.
     *
     * @return int[]
     */
    public function geschaeftIdsFuerSitzung(int $sitzungId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('geschaeft_id')
            ->from('pw_sitzung_geschaeft')
            ->where($qb->expr()->eq('sitzung_id', $qb->createNamedParameter($sitzungId, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();
        return $ids;
    }
}
