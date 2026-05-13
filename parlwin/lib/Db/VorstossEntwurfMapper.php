<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<VorstossEntwurf>
 */
class VorstossEntwurfMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_vorstoss_entwuerfe', VorstossEntwurf::class);
    }

    public function findByExternId(string $externId): ?VorstossEntwurf {
        if ($externId === '') {
            return null;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)))
            ->setMaxResults(1);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if (!is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findOffenByTitelNormalisiert(string $titelNormalisiert, string $typ): ?VorstossEntwurf {
        if ($titelNormalisiert === '') {
            return null;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('titel_normalisiert', $qb->createNamedParameter($titelNormalisiert)))
            ->andWhere($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

        if ($typ !== '') {
            $qb->andWhere($qb->expr()->eq('typ', $qb->createNamedParameter($typ)));
        }

        $qb->orderBy('id', 'DESC')
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
