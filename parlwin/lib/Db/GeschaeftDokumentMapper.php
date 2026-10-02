<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GeschaeftDokument>
 */
class GeschaeftDokumentMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'pw_geschaeft_dokumente', GeschaeftDokument::class);
    }

    /**
     * Die Dokumente eines Geschäfts in der Reihenfolge der Quelle: der Antrag
     * steht vor seinen Beilagen, und das Datum ordnet die Antworten dahinter.
     *
     * @return GeschaeftDokument[]
     */
    public function findByGeschaeft(int $geschaeftId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($geschaeftId, IQueryBuilder::PARAM_INT)))
            ->orderBy('datum', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Die Dokumente mehrerer Geschäfte auf einmal, nach Geschäft gruppiert —
     * die Übersicht zeigt sie zu jedem Geschäft an und darf dafür nicht je
     * Zeile eine Abfrage fahren.
     *
     * @param int[] $geschaeftIds
     * @return array<int, GeschaeftDokument[]>
     */
    public function findByGeschaefte(array $geschaeftIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $geschaeftIds)));
        if ($ids === []) {
            return [];
        }
        $gruppiert = [];
        // Die Datenbanken begrenzen die Zahl der Werte einer IN-Liste; in
        // Blöcken bleibt die Abfrage auch bei einer vollen Übersicht gültig.
        foreach (array_chunk($ids, 500) as $block) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from($this->getTableName())
                ->where($qb->expr()->in('geschaeft_id', $qb->createNamedParameter($block, IQueryBuilder::PARAM_INT_ARRAY)))
                ->orderBy('datum', 'ASC')
                ->addOrderBy('id', 'ASC');
            foreach ($this->findEntities($qb) as $dokument) {
                $gruppiert[$dokument->getGeschaeftId()][] = $dokument;
            }
        }
        return $gruppiert;
    }

    /**
     * Dokumente, die verzeichnet, aber noch nicht gelesen sind — der
     * Hintergrundauftrag holt sie Stück für Stück nach. Das jüngste zuerst:
     * Was jetzt beraten wird, ist zuerst da.
     *
     * @return GeschaeftDokument[]
     */
    public function findOhneInhalt(int $limit, string $fassung = ''): array
    {
        $qb = $this->db->getQueryBuilder();
        $offen = $qb->expr()->andX(
            $qb->expr()->orX(
                $qb->expr()->isNull('gelesen_am'),
                $qb->expr()->eq('gelesen_am', $qb->createNamedParameter(''))
            ),
            // Was einmal an seiner Grösse oder an einem Fehler gescheitert ist,
            // hält den Auftrag nicht auf: Es trägt seine Prüfsumme und ist damit
            // nicht mehr offen.
            $qb->expr()->orX(
                $qb->expr()->isNull('quelle_hash'),
                $qb->expr()->eq('quelle_hash', $qb->createNamedParameter(''))
            )
        );
        $bedingungen = [$offen];
        if ($fassung !== '') {
            // Dazu, was eine frühere Fassung des Lesers abgelegt hat: Die Datei
            // ist dieselbe, der abgelegte Inhalt trotzdem veraltet.
            $bedingungen[] = $qb->expr()->notLike(
                'quelle_hash',
                $qb->createNamedParameter($this->db->escapeLikeParameter($fassung) . '%')
            );
        }
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->orX(...$bedingungen))
            ->orderBy('datum', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults(max(1, $limit));
        return $this->findEntities($qb);
    }

    /**
     * Stellt jedes Dokument wieder auf offen, das keinen Inhalt trägt und einen
     * Grund nennt. Ein gescheitertes Dokument behält sonst seine Prüfsumme und
     * wird nie wieder angefasst — richtig, solange der Grund bleibt, und falsch,
     * sobald der Betrieb die Grenze angehoben oder der Leser sich geändert hat.
     *
     * @return int Zahl der Dokumente, die wieder offen sind
     */
    public function vergisseGescheiterte(): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('quelle_hash', $qb->createNamedParameter(''))
            ->where($qb->expr()->neq('fehler', $qb->createNamedParameter('')))
            ->andWhere($qb->expr()->isNotNull('fehler'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('markdown'),
                $qb->expr()->eq('markdown', $qb->createNamedParameter('')),
            ));

        return $qb->executeStatement();
    }

    /**
     * Das Dokument zu einer Nummer der Parlamentswebseite, falls es schon
     * gelesen wurde.
     */
    public function findByExternId(string $externId): ?GeschaeftDokument
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('extern_id', $qb->createNamedParameter($externId)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): GeschaeftDokument
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Die Geschäfte, in deren Dokumenten der Begriff vorkommt — die Suche
     * greift damit auf den Inhalt der PDF zu, nicht nur auf ihre Titel.
     *
     * @return int[]
     */
    public function findGeschaeftIdsMitText(string $begriff): array
    {
        $begriff = trim($begriff);
        if ($begriff === '') {
            return [];
        }
        $qb = $this->db->getQueryBuilder();
        $muster = '%' . $this->db->escapeLikeParameter($begriff) . '%';
        $qb->selectDistinct('geschaeft_id')
            ->from($this->getTableName())
            ->where($qb->expr()->iLike('volltext', $qb->createNamedParameter($muster)));
        $ergebnis = $qb->executeQuery();
        $ids = [];
        foreach ($ergebnis->fetchAll() as $zeile) {
            $ids[] = (int) $zeile['geschaeft_id'];
        }
        $ergebnis->closeCursor();
        return $ids;
    }
}
