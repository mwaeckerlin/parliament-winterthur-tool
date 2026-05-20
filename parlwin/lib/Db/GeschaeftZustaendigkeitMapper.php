<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GeschaeftZustaendigkeit>
 */
class GeschaeftZustaendigkeitMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'pw_geschaeft_zustaendigkeiten', GeschaeftZustaendigkeit::class);
    }

    /**
     * @return GeschaeftZustaendigkeit[]
     */
    public function findAktiveByGeschaeft(int $geschaeftId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->orderBy('ist_haupt', 'DESC')
            ->addOrderBy('person_name', 'ASC');

        return $this->findEntities($qb);
    }

    public function findHauptByGeschaeft(int $geschaeftId): ?GeschaeftZustaendigkeit {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('aktiv', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->eq('ist_haupt', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->setMaxResults(1);

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        if (!is_array($row)) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    /**
     * @param array<int, array{person_key: string, mitglied_extern_id: string, person_name: string, ist_haupt: bool}> $personen
     */
    public function ersetzeAktive(int $geschaeftId, array $personen): void {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();

        foreach ($personen as $person) {
            $entity = new GeschaeftZustaendigkeit();
            $entity->setGeschaeftId($geschaeftId);
            $entity->setPersonKey($person['person_key']);
            $entity->setMitgliedExternId($person['mitglied_extern_id']);
            $entity->setPersonName($person['person_name']);
            $entity->setIstHaupt($person['ist_haupt']);
            $entity->setAktiv(true);
            $entity->setErstelltAm($jetzt);
            $entity->setAktualisiertAm($jetzt);
            $this->insert($entity);
        }
    }
}
