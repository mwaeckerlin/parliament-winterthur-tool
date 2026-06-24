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

    public function __construct(
        private readonly VorstossMapper $mapper,
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
        $this->uebernehmeFelder($vorstoss, $daten);
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
        if (array_key_exists('beschluss', $daten)) {
            $vorstoss->setBeschluss(trim((string) $daten['beschluss']));
        }
        if (array_key_exists('zustaendigkeit', $daten)) {
            $vorstoss->setZustaendigkeit(trim((string) $daten['zustaendigkeit']));
        }
        if (array_key_exists('inhalt', $daten)) {
            $vorstoss->setInhalt((string) $daten['inhalt']);
        }
        if (array_key_exists('dokument', $daten)) {
            $vorstoss->setDokument(trim((string) $daten['dokument']));
        }
    }
}
