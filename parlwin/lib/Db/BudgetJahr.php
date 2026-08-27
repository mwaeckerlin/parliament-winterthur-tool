<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Ein importiertes Budgetjahr: Steuerfuss, Steuerertrag, Personalsteuer und die
 * Herkunft (Weisung, Novemberbrief).
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method int    getSteuerfuss()
 * @method void   setSteuerfuss(int $v)
 * @method int    getSteuerertrag()
 * @method void   setSteuerertrag(int $v)
 * @method int    getPersonalsteuer()
 * @method void   setPersonalsteuer(int $v)
 * @method int    getTotalAufwand()
 * @method void   setTotalAufwand(int $v)
 * @method int    getTotalErtrag()
 * @method void   setTotalErtrag(int $v)
 * @method int    getTotalAufwandVorjahr()
 * @method void   setTotalAufwandVorjahr(int $v)
 * @method int    getTotalErtragVorjahr()
 * @method void   setTotalErtragVorjahr(int $v)
 * @method int    getGesamtergebnis()
 * @method void   setGesamtergebnis(int $v)
 * @method string getWeisungQuelle()
 * @method void   setWeisungQuelle(?string $v)
 * @method string getNovemberbriefQuelle()
 * @method void   setNovemberbriefQuelle(?string $v)
 * @method int    getNovemberbriefImportiert()
 * @method void   setNovemberbriefImportiert(int $v)
 * @method int    getErstelltAm()
 * @method void   setErstelltAm(?int $v)
 */
class BudgetJahr extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected int $steuerfuss = 0;
    protected int $steuerertrag = 0;
    protected int $personalsteuer = 0;
    protected int $totalAufwand = 0;
    protected int $totalErtrag = 0;
    protected int $totalAufwandVorjahr = 0;
    protected int $totalErtragVorjahr = 0;
    protected int $gesamtergebnis = 0;
    protected ?string $weisungQuelle = null;
    protected ?string $novemberbriefQuelle = null;
    protected int $novemberbriefImportiert = 0;
    protected ?int $erstelltAm = null;

    public function __construct() {
        $this->addType('jahr', 'integer');
        $this->addType('steuerfuss', 'integer');
        $this->addType('steuerertrag', 'integer');
        $this->addType('personalsteuer', 'integer');
        $this->addType('totalAufwand', 'integer');
        $this->addType('totalErtrag', 'integer');
        $this->addType('totalAufwandVorjahr', 'integer');
        $this->addType('totalErtragVorjahr', 'integer');
        $this->addType('gesamtergebnis', 'integer');
        $this->addType('novemberbriefImportiert', 'integer');
        $this->addType('erstelltAm', 'integer');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->getJahr(),
            'steuerfuss' => $this->getSteuerfuss(),
            'steuerertrag' => $this->getSteuerertrag(),
            'personalsteuer' => $this->getPersonalsteuer(),
            'totalAufwand' => $this->getTotalAufwand(),
            'totalErtrag' => $this->getTotalErtrag(),
            'totalAufwandVorjahr' => $this->getTotalAufwandVorjahr(),
            'totalErtragVorjahr' => $this->getTotalErtragVorjahr(),
            'gesamtergebnis' => $this->getGesamtergebnis(),
            'novemberbriefImportiert' => (bool) $this->getNovemberbriefImportiert(),
        ];
    }
}
