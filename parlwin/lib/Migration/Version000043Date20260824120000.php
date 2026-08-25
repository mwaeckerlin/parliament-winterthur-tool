<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Vereinheitlicht alle Notizen auf den geteilten NotizService: bisher lagen die
 * Notizen geschäftsloser Traktanden UND die Sitzungs-Notizen als JSON auf dem
 * jeweiligen Objekt (eigener Editor `SitzungNotizen`), während Geschäft-Traktanden
 * bereits den geteilten NotizService nutzten. Diese Migration überführt die
 * bestehenden JSON-Notizen beider Objekte in den geteilten NotizService
 * (`pw_geschaeft_aktionen`, objekt_typ «traktandum» bzw. «sitzung»), damit überall
 * dieselbe Notiz-Komponente mit demselben Aussehen greift. Idempotent: bereits
 * migrierte Objekte (es existieren schon Aktionen) werden übersprungen; die alte
 * JSON-Spalte bleibt unangetastet (kein Datenverlust).
 */
class Version000043Date20260824120000 extends SimpleMigrationStep
{
    public function __construct(private readonly IDBConnection $db)
    {
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('pw_geschaeft_aktionen')) {
            return;
        }
        $migriert = 0;
        // Beide notiz-tragenden Objekte: geschäftslose Traktanden und Sitzungen.
        foreach ([['pw_traktanden', 'traktandum'], ['pw_sitzungen', 'sitzung']] as [$tabelle, $objektTyp]) {
            if (!$schema->hasTable($tabelle)) {
                continue;
            }
            $migriert += $this->migriereTabelle($tabelle, $objektTyp);
        }
        if ($migriert > 0) {
            $output->info('parlwin: ' . $migriert . ' Notizen in den geteilten NotizService übernommen (einheitliche Notiz-Komponente)');
        }
    }

    private function migriereTabelle(string $tabelle, string $objektTyp): int
    {
        $sel = $this->db->getQueryBuilder();
        $sel->select('id', 'notizen')->from($tabelle);
        $res = $sel->executeQuery();
        $migriert = 0;
        while ($row = $res->fetch()) {
            $oid = (int) $row['id'];
            $notizen = json_decode((string) ($row['notizen'] ?? '[]'), true);
            if (!is_array($notizen) || $notizen === []) {
                continue;
            }
            if ($this->hatBereitsAktionen($objektTyp, $oid)) {
                continue;
            }
            foreach ($notizen as $n) {
                if (!is_array($n)) {
                    continue;
                }
                $text = trim((string) ($n['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $this->fuegeAktionEin($objektTyp, $oid, $text, (string) ($n['uid'] ?? ''), (string) ($n['displayName'] ?? ($n['uid'] ?? '')), self::konvertiereDatum((string) ($n['datum'] ?? '')));
                $migriert++;
            }
        }
        $res->closeCursor();
        return $migriert;
    }

    private function hatBereitsAktionen(string $objektTyp, int $objektId): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from('pw_geschaeft_aktionen')
            ->where($qb->expr()->eq('objekt_typ', $qb->createNamedParameter($objektTyp)))
            ->andWhere($qb->expr()->eq('geschaeft_id', $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT)));
        $anz = (int) $qb->executeQuery()->fetchOne();
        return $anz > 0;
    }

    private function fuegeAktionEin(string $objektTyp, int $objektId, string $text, string $uid, string $name, string $erstelltAm): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('pw_geschaeft_aktionen')->values([
            'objekt_typ' => $qb->createNamedParameter($objektTyp),
            'geschaeft_id' => $qb->createNamedParameter($objektId, IQueryBuilder::PARAM_INT),
            'aktion_typ' => $qb->createNamedParameter('notiz'),
            'aktion_code' => $qb->createNamedParameter(''),
            'titel' => $qb->createNamedParameter('Notiz'),
            'text' => $qb->createNamedParameter($text),
            'entscheid_gueltig' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
            'autor_uid' => $qb->createNamedParameter($uid),
            'autor_name' => $qb->createNamedParameter($name !== '' ? $name : $uid),
            'erstellt_am' => $qb->createNamedParameter($erstelltAm),
            'geloescht' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
        ]);
        $qb->executeStatement();
    }

    /**
     * Wandelt ein gespeichertes Notiz-Datum in das MySQL-Format «Y-m-d H:i:s».
     *
     * Die Alt-Daten stehen in wechselnden Anzeigeformaten — u.a. im Schweizer
     * `toLocaleString`-Format «21.5.2026, 10:34:56» (Tag/Monat ohne führende
     * Null, Komma, Sekunden), im früheren «d.m.Y H:i» und in ISO. Ein nicht
     * parsbarer Rohstring darf NIE in die `datetime`-Spalte geschrieben werden
     * (SQLSTATE 22007 bricht die ganze Migration und damit das Upgrade ab):
     * scheitern alle Formate, ist der sichere Rückfall die aktuelle Zeit.
     *
     * Öffentlich-statisch, damit die Konvertierung direkt testbar ist.
     */
    public static function konvertiereDatum(string $roh): string
    {
        $roh = trim($roh);
        if ($roh !== '') {
            // Das führende «!» setzt nicht im Format enthaltene Komponenten auf
            // Null (statt auf die aktuelle Zeit) — sonst wären die Sekunden bei
            // sekundenlosen Formaten nichtdeterministisch.
            $formate = [
                '!j.n.Y, H:i:s',
                '!j.n.Y, G:i:s',
                '!d.m.Y, H:i:s',
                '!d.m.Y H:i:s',
                '!d.m.Y H:i',
                '!d.m.Y, H:i',
                '!Y-m-d H:i:s',
                '!Y-m-d\TH:i:sP',
                '!Y-m-d\TH:i:s',
            ];
            foreach ($formate as $format) {
                $dt = \DateTime::createFromFormat($format, $roh);
                if ($dt !== false) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            $ts = strtotime($roh);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts);
            }
        }
        return (new \DateTime())->format('Y-m-d H:i:s');
    }
}
