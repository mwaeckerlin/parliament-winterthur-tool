<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GeschaeftEreignis>
 */
class GeschaeftEreignisMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_geschaeft_ereignisse', GeschaeftEreignis::class);
    }

    /**
     * @return GeschaeftEreignis[]
     */
    public function findByGeschaeft(int $geschaeftId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->orderBy('reihenfolge', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @param array<int, array<string, mixed>> $ereignisse
     */
    public function ersetzeFuerGeschaeft(int $geschaeftId, array $ereignisse): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();

        if ($ereignisse === []) {
            return;
        }

        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $laufnummer = 0;

        foreach ($ereignisse as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $laufnummer++;
            $ereignis = new GeschaeftEreignis();
            $ereignis->setGeschaeftId($geschaeftId);
            $ereignis->setReihenfolge((int) ($eintrag['sequence'] ?? $laufnummer));
            $ereignis->setTyp((string) ($eintrag['type'] ?? 'info'));
            $ereignis->setOrgan((string) ($eintrag['organ'] ?? ''));
            $ereignis->setLabel((string) ($eintrag['label'] ?? ''));
            $ereignis->setWert((string) ($eintrag['value'] ?? ''));
            $ereignis->setDatum((string) ($eintrag['date'] ?? ''));
            $ereignis->setErstelltAm($jetzt);
            $ereignis->setAktualisiertAm($jetzt);
            $this->insert($ereignis);
        }
    }
}
