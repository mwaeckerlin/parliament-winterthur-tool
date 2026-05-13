<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Fraktionsrolle>
 */
class FraktionsrolleMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_fraktionsrollen', Fraktionsrolle::class);
    }

    /**
     * @return Fraktionsrolle[]
     */
    public function findAktiveByRolle(string $rolleCode, ?\DateTimeInterface $zeitpunkt = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('rolle_code', $qb->createNamedParameter($rolleCode)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

        $this->applyGueltigFilter($qb, $zeitpunkt);

        $qb->orderBy('gueltig_von', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    public function hasAktiveRolle(string $uid, string $rolleCode, ?\DateTimeInterface $zeitpunkt = null): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('rolle_code', $qb->createNamedParameter($rolleCode)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->setMaxResults(1);

        $this->applyGueltigFilter($qb, $zeitpunkt);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return is_array($row);
    }

    public function deaktiviereAktiveByRolle(string $rolleCode): int {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('aktiv', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
            ->set('aktualisiert_am', $qb->createNamedParameter($jetzt))
            ->where($qb->expr()->eq('rolle_code', $qb->createNamedParameter($rolleCode)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

        return $qb->executeStatement();
    }

    private function applyGueltigFilter(IQueryBuilder $qb, ?\DateTimeInterface $zeitpunkt): void {
        $jetzt = ($zeitpunkt ?? new \DateTime())->format('Y-m-d H:i:s');
        $expr = $qb->expr();

        $qb->andWhere($expr->orX(
            $expr->isNull('gueltig_von'),
            $expr->lte('gueltig_von', $qb->createNamedParameter($jetzt))
        ));

        $qb->andWhere($expr->orX(
            $expr->isNull('gueltig_bis'),
            $expr->gte('gueltig_bis', $qb->createNamedParameter($jetzt))
        ));
    }
}

