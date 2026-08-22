<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Ein Budget-Antrag (eigen oder fremd). Bereich: globalbudget|personal|
 * investition|steuerfuss. Quelle: manuell|pauschal|steuerfuss. Automatisch aus
 * einer Pauschalverteilung erzeugte Anträge tragen eine verteilungId.
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method string getBereich()
 * @method void   setBereich(?string $v)
 * @method string getZielTyp()
 * @method void   setZielTyp(?string $v)
 * @method string getZielRef()
 * @method void   setZielRef(?string $v)
 * @method int    getBetragDelta()
 * @method void   setBetragDelta(int $v)
 * @method float  getStellenDelta()
 * @method void   setStellenDelta(float $v)
 * @method int    getBetragProStelle()
 * @method void   setBetragProStelle(int $v)
 * @method string getQuelle()
 * @method void   setQuelle(?string $v)
 * @method string getAntragsteller()
 * @method void   setAntragsteller(?string $v)
 * @method string getBegruendung()
 * @method void   setBegruendung(?string $v)
 * @method int    getVerteilungId()
 * @method void   setVerteilungId(?int $v)
 * @method int    getReihenfolge()
 * @method void   setReihenfolge(int $v)
 * @method string getErstelltVon()
 * @method void   setErstelltVon(?string $v)
 * @method int    getErstelltAm()
 * @method void   setErstelltAm(?int $v)
 * @method string getPhase()
 * @method void   setPhase(?string $v)
 */
class BudgetAntrag extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected ?string $bereich = null;
    protected ?string $zielTyp = null;
    protected ?string $zielRef = null;
    protected int $betragDelta = 0;
    protected float $stellenDelta = 0.0;
    protected int $betragProStelle = 0;
    protected ?string $quelle = null;
    protected ?string $antragsteller = null;
    protected ?string $begruendung = null;
    protected ?int $verteilungId = null;
    protected int $reihenfolge = 0;
    protected ?string $erstelltVon = null;
    protected ?int $erstelltAm = null;
    /** Phase: «fraktion» (interne Vorbereitung) oder «sitzung» (offizielle Sitzungsanträge). */
    protected ?string $phase = 'fraktion';

    public function __construct() {
        foreach (['jahr', 'betragDelta', 'betragProStelle', 'verteilungId', 'reihenfolge', 'erstelltAm'] as $f) {
            $this->addType($f, 'integer');
        }
        $this->addType('stellenDelta', 'float');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'bereich' => $this->bereich,
            'zielTyp' => $this->zielTyp,
            'zielRef' => $this->zielRef,
            'betragDelta' => $this->betragDelta,
            'stellenDelta' => $this->stellenDelta,
            'betragProStelle' => $this->betragProStelle,
            'quelle' => $this->quelle,
            'antragsteller' => $this->antragsteller,
            'begruendung' => $this->begruendung,
            'verteilungId' => $this->verteilungId,
            'automatisch' => $this->verteilungId !== null,
            'reihenfolge' => $this->reihenfolge,
            'erstelltVon' => $this->erstelltVon,
            'phase' => $this->phase ?? 'fraktion',
        ];
    }
}
