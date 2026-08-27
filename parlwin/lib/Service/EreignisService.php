<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Ereignis;
use OCA\ParliamentWinterthur\Db\EreignisMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;

/**
 * Schreibt und liest das Ereignis-Protokoll (F105): jede Synchronisation und jeder
 * Budget-Import wird mit Zeitpunkt, Art, Bereich, Erfolg und Meldung festgehalten —
 * inklusive der Parsing-Probleme (art='fehler'). So ist nachvollziehbar, wann was
 * neu eingelesen oder geändert wurde und wo ein Import scheiterte.
 */
class EreignisService {
    /** Ereignisse älter als so viele Tage werden beim Protokollieren entfernt. */
    private const AUFBEWAHRUNG_TAGE = 180;

    public function __construct(
        private readonly EreignisMapper $ereignisse,
        private readonly ITimeFactory $time,
        private readonly IUserSession $userSession,
    ) {
    }

    /**
     * Hält ein Ereignis fest. `ausgeloestVon` leer → automatisch die aktuelle
     * Nutzer-ID bzw. «auto» im Hintergrundjob.
     */
    public function protokolliere(
        string $art,
        string $bereich,
        bool $erfolg,
        string $titel,
        string $meldung = '',
        string $ausgeloestVon = '',
    ): Ereignis {
        $e = new Ereignis();
        $e->setZeitpunkt($this->time->getTime());
        $e->setArt($art);
        $e->setBereich($bereich);
        $e->setErfolg($erfolg ? 1 : 0);
        $e->setTitel(mb_substr($titel, 0, 255));
        $e->setMeldung($meldung !== '' ? $meldung : null);
        $e->setAusgeloestVon($ausgeloestVon !== '' ? $ausgeloestVon : $this->aktuellerNutzer());
        $gespeichert = $this->ereignisse->insert($e);
        $this->ereignisse->loescheAelterAls($this->time->getTime() - self::AUFBEWAHRUNG_TAGE * 86400);
        return $gespeichert;
    }

    /**
     * Das Protokoll, neueste zuerst.
     *
     * @return Ereignis[]
     */
    public function liste(int $limit = 200): array {
        return $this->ereignisse->neueste($limit);
    }

    private function aktuellerNutzer(): string {
        $user = $this->userSession->getUser();
        return $user !== null ? $user->getUID() : 'auto';
    }
}
