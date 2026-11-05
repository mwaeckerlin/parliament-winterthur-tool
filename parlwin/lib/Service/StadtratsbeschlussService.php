<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Stadtratsbeschluss;
use OCA\ParliamentWinterthur\Db\StadtratsbeschlussMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Die Stadtratsbeschlüsse (F144, F146): einlesen, lesen und mit Notizen der
 * Fraktion versehen.
 *
 * Der Abgleich holt die Beschlüsse von der Webseite der Stadt. Ein Beschluss
 * wird über seine Nummer wiedererkannt: ein zweiter Lauf aktualisiert den
 * vorhandenen Eintrag und legt keinen zweiten an. Die Notizen der Fraktion
 * bleiben dabei stehen, weil der Abgleich nur die Felder der Quelle schreibt.
 */
class StadtratsbeschlussService
{
    /** Der Objekttyp im geteilten Notiz-Code. */
    private const OBJEKT_TYP = 'stadtratsbeschluss';

    public function __construct(
        private readonly StadtratsbeschlussMapper $mapper,
        private readonly ScraperService $scraper,
        private readonly LoggerInterface $logger,
        private readonly RealtimePublisherService $realtime,
        private readonly NotizService $notizService,
    ) {
    }

    /**
     * @return Stadtratsbeschluss[]
     */
    public function alle(int $limit = 500, int $offset = 0): array
    {
        return $this->mapper->findAll($limit, $offset);
    }

    /**
     * @throws DoesNotExistException
     */
    public function einer(int $id): Stadtratsbeschluss
    {
        return $this->mapper->find($id);
    }

    /**
     * Liest die Beschlüsse von der Quelle und schreibt sie in die Tabelle.
     *
     * Der Fortschritt zählt über den ganzen Bereich: zuerst die Listenseiten,
     * dann die Detailseiten, dann das Schreiben. Jede Phase meldet ihren Stand
     * auf derselben Gesamtzahl, und «final» steht erst am Ende des Schreibens.
     *
     * @param ?callable $fortschritt Meldet den Stand, gleiche Form wie bei den
     *        übrigen Bereichen des Abgleichs
     * @return array{neu:int, aktualisiert:int, gelesen:int, fehler:int, fehlermeldung:string}
     */
    public function synchronisieren(?callable $fortschritt = null): array
    {
        // Das Lesen ist die erste Hälfte, das Schreiben die zweite. Die Zahl
        // der Schritte des Lesens meldet der Scraper; bis die Zahl der
        // Beschlüsse feststeht, ist sie geschätzt.
        $gesamt = 0;
        $erledigt = 0;
        $melde = function (int $schritte, string $cursor = '', bool $fertig = false) use (
            $fortschritt,
            &$gesamt,
            &$erledigt
        ): void {
            $erledigt = max($erledigt, $schritte);
            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'stadtratsbeschluesse',
                    'processed' => $erledigt,
                    'total' => max($gesamt, $erledigt),
                    'cursor' => $cursor,
                    'final' => $fertig,
                ]);
            }
        };

        $melde(0);
        $gelesen = 0;
        $rohdaten = $this->scraper->ladeStadtratsbeschluesse(
            function (int $schritt, int $schritteGesamt) use ($melde, &$gesamt, &$gelesen): void {
                $gesamt = $schritteGesamt * 2;
                $gelesen = $schritt;
                $melde($schritt);
            }
        );

        $statistik = [
            'neu' => 0,
            'aktualisiert' => 0,
            'gelesen' => count($rohdaten),
            'fehler' => 0,
            'fehlermeldung' => '',
        ];
        $haelfte = $gelesen > 0 ? $gelesen : max(1, count($rohdaten));
        $gesamt = 2 * $haelfte;
        $geschrieben = 0;
        $anzahl = max(1, count($rohdaten));

        foreach ($rohdaten as $daten) {
            $geschrieben++;
            $verarbeitet = $haelfte + intdiv($haelfte * $geschrieben, $anzahl);
            $externId = trim((string) ($daten['externId'] ?? ''));
            if ($externId === '') {
                $melde($verarbeitet);
                continue;
            }

            try {
                $vorhanden = $this->mapper->findByExternId($externId);
                if ($vorhanden === null) {
                    $beschluss = new Stadtratsbeschluss();
                    $beschluss->setExternId($externId);
                    $beschluss->setErstelltAm((new \DateTime())->format('Y-m-d H:i:s'));
                    $this->uebernehmeQuelldaten($beschluss, $daten, true);
                    $this->mapper->insert($beschluss);
                    $statistik['neu']++;
                } else {
                    $this->uebernehmeQuelldaten($vorhanden, $daten, false);
                    $this->mapper->update($vorhanden);
                    $statistik['aktualisiert']++;
                }
            } catch (\Throwable $e) {
                $statistik['fehler']++;
                if ($statistik['fehlermeldung'] === '') {
                    $statistik['fehlermeldung'] = $externId . ': ' . $e->getMessage();
                }
                $this->logger->error(
                    'Parlament Winterthur: Stadtratsbeschluss ' . $externId . ' nicht gespeichert: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
            $melde($verarbeitet, $externId);
        }

        $melde($gesamt, '', true);
        $this->realtime->publish('stadtratsbeschluesse.updated', ['aktion' => 'sync'] + $statistik);
        return $statistik;
    }

    /**
     * Wie `synchronisieren`, aber eine Quelle, die nicht antwortet oder nichts
     * hergibt, beendet nur diesen Bereich: Die Meldung mit der Adresse steht in
     * `fehlermeldung`, `ausgefallen` ist gesetzt, und die übrigen Bereiche der
     * Synchronisation laufen weiter. Ein Abbruch aus dem Fortschritt geht
     * unverändert an den Aufrufer.
     *
     * @return array{neu:int, aktualisiert:int, gelesen:int, fehler:int, fehlermeldung:string, ausgefallen?:bool}
     */
    public function synchronisierenMitFehlerInStatistik(?callable $fortschritt = null): array
    {
        $abbruch = null;
        $weitergeben = $fortschritt === null ? null : static function (array $ereignis) use ($fortschritt, &$abbruch): void {
            try {
                $fortschritt($ereignis);
            } catch (\Throwable $e) {
                $abbruch = $e;
                throw $e;
            }
        };
        try {
            return $this->synchronisieren($weitergeben);
        } catch (\Throwable $e) {
            if ($e === $abbruch) {
                throw $e;
            }
            $this->logger->error(
                'Parlament Winterthur: Stadtratsbeschlüsse nicht gelesen: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return [
                'neu' => 0,
                'aktualisiert' => 0,
                'gelesen' => 0,
                'fehler' => 0,
                'fehlermeldung' => $e->getMessage(),
                'ausgefallen' => true,
            ];
        }
    }

    /**
     * Schreibt die Felder der Quelle in den Beschluss. Fehlt die Detailseite,
     * behält ein vorhandener Beschluss sein Datum der Veröffentlichung und
     * seine Dokumente.
     *
     * @param array<string, mixed> $daten
     */
    private function uebernehmeQuelldaten(Stadtratsbeschluss $beschluss, array $daten, bool $neu): void
    {
        $beschluss->setNummer((string) ($daten['nummer'] ?? ''));
        // Die Spalte fasst 500 Zeichen. Ein längerer Titel soll den Beschluss
        // nicht verlieren, darum wird er gekürzt statt abgewiesen.
        $beschluss->setTitel(mb_substr((string) ($daten['titel'] ?? ''), 0, 500));
        $beschluss->setDatum((string) ($daten['datum'] ?? ''));
        $beschluss->setDepartement((string) ($daten['departement'] ?? ''));
        $beschluss->setSitzung((string) ($daten['sitzung'] ?? ''));
        $beschluss->setUrl((string) ($daten['url'] ?? ''));
        $ohneDetailseite = ($daten['detailseiteGelesen'] ?? true) === false;
        if ($neu || !$ohneDetailseite) {
            $beschluss->setPubliziertAm((string) ($daten['publiziertAm'] ?? ''));
            $dokumente = $daten['dokumente'] ?? [];
            $beschluss->setDokumente((string) json_encode(is_array($dokumente) ? $dokumente : []));
        }
        $beschluss->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
    }

    /**
     * Die Notizen laufen über den GETEILTEN Code (NotizService) — gleich wie am
     * Geschäft und am Vorstoss: Versionen, Löschen, Wiederherstellen. Kein
     * eigener Notiz-Code.
     *
     * @return array<int, array<string, mixed>>
     * @throws DoesNotExistException
     */
    public function notizen(int $id): array
    {
        $this->mapper->find($id);
        return $this->notizService->liste(self::OBJEKT_TYP, $id);
    }

    /**
     * Die Notizen mehrerer Beschlüsse gruppiert, für die Liste ohne N+1.
     *
     * @param int[] $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function notizenGruppiert(array $ids): array
    {
        return $this->notizService->listeGruppiert(self::OBJEKT_TYP, $ids);
    }

    /**
     * Reichert die Beschlüsse für die Schnittstelle mit ihren Notizen
     * («aktionen») an: die geteilte Komponente NotizenListe liest «aktionen».
     *
     * @param Stadtratsbeschluss[] $beschluesse
     * @return array<int, array<string, mixed>>
     */
    public function mitNotizen(array $beschluesse): array
    {
        $ids = array_map(static fn(Stadtratsbeschluss $b): int => (int) $b->getId(), $beschluesse);
        $notizen = $this->notizenGruppiert($ids);
        return array_map(
            static function (Stadtratsbeschluss $b) use ($notizen): array {
                $daten = $b->jsonSerialize();
                $daten['aktionen'] = $notizen[(int) $b->getId()] ?? [];
                return $daten;
            },
            $beschluesse
        );
    }

    /**
     * @return array<string, mixed>
     * @throws DoesNotExistException
     */
    public function notizHinzufuegen(int $id, string $text): array
    {
        $this->mapper->find($id);
        return $this->notizService->hinzufuegen(self::OBJEKT_TYP, $id, $text);
    }

    /**
     * @return array<string, mixed>
     * @throws DoesNotExistException
     */
    public function notizAktualisieren(int $id, int $aktionId, string $text): array
    {
        $this->mapper->find($id);
        return $this->notizService->aktualisieren(self::OBJEKT_TYP, $id, $aktionId, $text);
    }

    /** @throws DoesNotExistException */
    public function notizLoeschen(int $id, int $aktionId): void
    {
        $this->mapper->find($id);
        $this->notizService->loeschen(self::OBJEKT_TYP, $id, $aktionId);
    }

    /**
     * @return array<string, mixed>
     * @throws DoesNotExistException
     */
    public function notizWiederherstellen(int $id, int $aktionId): array
    {
        $this->mapper->find($id);
        return $this->notizService->wiederherstellen(self::OBJEKT_TYP, $id, $aktionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DoesNotExistException
     */
    public function notizRevisionen(int $id, int $aktionId): array
    {
        $this->mapper->find($id);
        return $this->notizService->revisionen(self::OBJEKT_TYP, $id, $aktionId);
    }
}
