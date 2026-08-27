<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Ein protokolliertes Ereignis (F105): eine Synchronisation oder ein Budget-Import
 * mit Zeitpunkt, Art, Bereich, Erfolg, Titel und Meldung. Auch der Ort für
 * Parsing-Probleme (art='fehler').
 *
 * @method int    getZeitpunkt()
 * @method void   setZeitpunkt(int $v)
 * @method string getArt()
 * @method void   setArt(?string $v)
 * @method string getBereich()
 * @method void   setBereich(?string $v)
 * @method int    getErfolg()
 * @method void   setErfolg(int $v)
 * @method string getTitel()
 * @method void   setTitel(?string $v)
 * @method string getMeldung()
 * @method void   setMeldung(?string $v)
 * @method string getAusgeloestVon()
 * @method void   setAusgeloestVon(?string $v)
 */
class Ereignis extends Entity implements JsonSerializable {
    protected int $zeitpunkt = 0;
    protected ?string $art = '';
    protected ?string $bereich = '';
    protected int $erfolg = 1;
    protected ?string $titel = '';
    protected ?string $meldung = null;
    protected ?string $ausgeloestVon = '';

    public function __construct() {
        $this->addType('zeitpunkt', 'integer');
        $this->addType('erfolg', 'integer');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'zeitpunkt' => $this->zeitpunkt,
            'art' => $this->art,
            'bereich' => $this->bereich,
            'erfolg' => (bool) $this->erfolg,
            'titel' => $this->titel,
            'meldung' => $this->meldung ?? '',
            'ausgeloestVon' => $this->ausgeloestVon,
        ];
    }
}
