<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;

/**
 * CRUD für politische Vorstösse.
 */
class VorstossService
{
    public const HERKUENFTE = ['eigene', 'fremde'];
    public const STATUS = ['neu', 'entwurf', 'bereit', 'eingereicht', 'erledigt', 'pausiert'];

    /** Objekt-Typ für den geteilten Notiz-Code. */
    private const OBJEKT_TYP = 'vorstoss';

    public function __construct(
        private readonly VorstossMapper $mapper,
        private readonly GeschaeftService $geschaeftService,
        private readonly NotizService $notizService,
    ) {
    }

    private function jetzt(): string
    {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }

    /**
     * @return Vorstoss[]
     */
    public function alle(): array
    {
        return $this->mapper->findAll();
    }

    public function find(int $id): Vorstoss
    {
        return $this->mapper->find($id);
    }

    public function erstelle(array $daten): Vorstoss
    {
        $jetzt = $this->jetzt();
        $vorstoss = new Vorstoss();
        // ALLE Felder explizit setzen: der QBMapper schreibt beim INSERT nur
        // die per Setter markierten Felder — der INSERT darf nie davon
        // abhängen, welche Felder der Client mitschickt (NOT-NULL-Spalten
        // ohne DB-Default brächen ihn sonst).
        $this->uebernehmeFelder($vorstoss, array_merge([
            'titel' => '',
            'art' => '',
            'herkunft' => 'eigene',
            'status' => 'neu',
            'prioritaet' => '',
            'beschluss' => '',
            'zustaendigkeit' => '',
            'herkunftFraktion' => '',
            'ansprechpartner' => '',
            'inhalt' => '',
            'dokument' => '',
        ], $daten));
        $vorstoss->setNotizen('[]');
        $vorstoss->setGeschaeftId(0);
        $vorstoss->setGeloescht(false);
        $vorstoss->setErstelltAm($jetzt);
        $vorstoss->setAktualisiertAm($jetzt);
        return $this->mapper->insert($vorstoss);
    }

    public function aktualisiere(int $id, array $daten): Vorstoss
    {
        $vorstoss = $this->mapper->find($id);
        $this->uebernehmeFelder($vorstoss, $daten);
        $vorstoss->setAktualisiertAm($this->jetzt());
        return $this->mapper->update($vorstoss);
    }

    public function loesche(int $id): void
    {
        $vorstoss = $this->mapper->find($id);
        $vorstoss->setGeloescht(true);
        $vorstoss->setAktualisiertAm($this->jetzt());
        $this->mapper->update($vorstoss);
    }

    /**
     * Notizen laufen über den GETEILTEN Code (NotizService) — identisch zu den
     * Geschäfts-Notizen: Versionen, Soft-Delete, Undo. Kein eigener Notiz-Code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function notizen(int $id): array
    {
        $this->mapper->find($id); // 404 wenn der Vorstoss nicht existiert
        return $this->notizService->liste(self::OBJEKT_TYP, $id);
    }

    /**
     * Notizen mehrerer Vorstösse gruppiert (für die Listen-Anreicherung, kein N+1).
     *
     * @param int[] $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function notizenGruppiert(array $ids): array
    {
        return $this->notizService->listeGruppiert(self::OBJEKT_TYP, $ids);
    }

    /**
     * Reichert Vorstoss-Entities für die API mit ihren Notizen («aktionen») an —
     * die shared NotizenListe-Komponente liest, wie beim Geschäft, «aktionen».
     *
     * @param Vorstoss[] $vorstoesse
     * @return array<int, array<string, mixed>>
     */
    public function mitNotizen(array $vorstoesse): array
    {
        $ids = array_map(static fn(Vorstoss $v): int => (int) $v->getId(), $vorstoesse);
        $notizen = $this->notizenGruppiert($ids);
        return array_map(
            static function (Vorstoss $v) use ($notizen): array {
                $daten = $v->jsonSerialize();
                $daten['aktionen'] = $notizen[(int) $v->getId()] ?? [];
                return $daten;
            },
            $vorstoesse
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function notizHinzufuegen(int $id, string $text): array
    {
        $this->mapper->find($id);
        return $this->notizService->hinzufuegen(self::OBJEKT_TYP, $id, $text);
    }

    /**
     * @return array<string, mixed>
     */
    public function notizAktualisieren(int $id, int $aktionId, string $text): array
    {
        return $this->notizService->aktualisieren(self::OBJEKT_TYP, $id, $aktionId, $text);
    }

    public function notizLoeschen(int $id, int $aktionId): void
    {
        $this->notizService->loeschen(self::OBJEKT_TYP, $id, $aktionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function notizWiederherstellen(int $id, int $aktionId): array
    {
        return $this->notizService->wiederherstellen(self::OBJEKT_TYP, $id, $aktionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function notizRevisionen(int $id, int $aktionId): array
    {
        return $this->notizService->revisionen(self::OBJEKT_TYP, $id, $aktionId);
    }

    /**
     * Verknüpft den Vorstoss mit einem Geschäft (schliesst ihn als «erledigt» ab)
     * und übernimmt die Priorität des Vorstosses ins Geschäft.
     */
    public function verknuepfen(int $id, int $geschaeftId): Vorstoss
    {
        $vorstoss = $this->mapper->find($id);
        $prio = $vorstoss->getPrioritaet();
        $vorstoss->setGeschaeftId($geschaeftId);
        $vorstoss->setStatus('erledigt');
        $vorstoss->setAktualisiertAm($this->jetzt());
        $aktualisiert = $this->mapper->update($vorstoss);
        if ($geschaeftId > 0 && $prio !== '') {
            try {
                $this->geschaeftService->aktualisiereInterneFelder($geschaeftId, ['prioritaet' => $prio]);
            } catch (\Throwable) {
                // Geschäft evtl. nicht (mehr) vorhanden – die Verknüpfung bleibt bestehen.
            }
        }
        return $aktualisiert;
    }

    /**
     * @return Vorstoss[] Die mit einem Geschäft verknüpften Vorstösse.
     */
    public function fuerGeschaeft(int $geschaeftId): array
    {
        return $this->mapper->findByGeschaeft($geschaeftId);
    }

    /** Übernimmt nur erlaubte Felder; normalisiert Herkunft/Status auf gültige Werte. */
    private function uebernehmeFelder(Vorstoss $vorstoss, array $daten): void
    {
        if (array_key_exists('titel', $daten)) {
            $vorstoss->setTitel(trim((string) $daten['titel']));
        }
        if (array_key_exists('art', $daten)) {
            $vorstoss->setArt(trim((string) $daten['art']));
        }
        if (array_key_exists('herkunft', $daten)) {
            $herkunft = (string) $daten['herkunft'];
            $vorstoss->setHerkunft(in_array($herkunft, self::HERKUENFTE, true) ? $herkunft : 'eigene');
        }
        if (array_key_exists('status', $daten)) {
            $status = (string) $daten['status'];
            $vorstoss->setStatus(in_array($status, self::STATUS, true) ? $status : 'neu');
        }
        if (array_key_exists('prioritaet', $daten)) {
            $p = (string) $daten['prioritaet'];
            $vorstoss->setPrioritaet(in_array($p, ['', 'hoch', 'mittel', 'tief'], true) ? $p : '');
        }
        if (array_key_exists('beschluss', $daten)) {
            $vorstoss->setBeschluss(trim((string) $daten['beschluss']));
        }
        if (array_key_exists('zustaendigkeit', $daten)) {
            $vorstoss->setZustaendigkeit(self::personenJson($daten['zustaendigkeit']));
        }
        if (array_key_exists('herkunftFraktion', $daten)) {
            $vorstoss->setHerkunftFraktion(trim((string) $daten['herkunftFraktion']));
        }
        if (array_key_exists('ansprechpartner', $daten)) {
            $vorstoss->setAnsprechpartner(self::personenJson($daten['ansprechpartner']));
        }
        if (array_key_exists('inhalt', $daten)) {
            $vorstoss->setInhalt((string) $daten['inhalt']);
        }
        if (array_key_exists('dokument', $daten)) {
            $vorstoss->setDokument(trim((string) $daten['dokument']));
        }
    }

    /**
     * Normalisiert eine Personen-Liste zu JSON. Ein Array (vom Frontend) wird
     * JSON-kodiert; ein Plain-String (Legacy) wird als Ein-Personen-Liste
     * abgelegt; leer bleibt leer.
     */
    private static function personenJson(mixed $wert): string
    {
        if (is_array($wert)) {
            return $wert === [] ? '' : (string) json_encode(array_values($wert));
        }
        $text = trim((string) $wert);
        return $text === '' ? '' : (string) json_encode([['key' => '', 'name' => $text]]);
    }
}
