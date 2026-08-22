<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Eine Produktegruppe (Teil B) — die beschlussfähige Ebene. Trägt den
 * Globalkredit (Nettokosten), Aufwand/Ertrag (für Summen und Verteilbasis), den
 * Stellenplan, die Erläuterungstexte und die zugehörigen Produkte (JSON, nur
 * Information).
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method string getCode()
 * @method void   setCode(?string $v)
 * @method string getName()
 * @method void   setName(?string $v)
 * @method string getDepartement()
 * @method void   setDepartement(?string $v)
 * @method int    getReihenfolge()
 * @method void   setReihenfolge(int $v)
 */
class BudgetProduktegruppe extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected ?string $code = null;
    protected ?string $name = null;
    protected ?string $departement = null;
    protected int $reihenfolge = 0;
    protected int $globalkreditIst = 0;
    protected int $globalkreditSollVorjahr = 0;
    protected int $globalkreditSoll = 0;
    protected int $globalkreditPlan1 = 0;
    protected int $globalkreditPlan2 = 0;
    protected int $globalkreditPlan3 = 0;
    protected int $aufwandIst = 0;
    protected int $aufwandSollVorjahr = 0;
    protected int $aufwandSoll = 0;
    protected int $ertragIst = 0;
    protected int $ertragSollVorjahr = 0;
    protected int $ertragSoll = 0;
    protected float $stellenIst = 0.0;
    protected float $stellenSollVorjahr = 0.0;
    protected float $stellenSoll = 0.0;
    protected float $auszubildendeSoll = 0.0;
    protected ?string $auftrag = null;
    protected ?string $zielvorgaben = null;
    protected ?string $erlaeuterungStellen = null;
    protected ?string $begruendungAbweichung = null;
    protected ?string $begruendungFap = null;
    protected ?string $massnahmen = null;
    protected ?string $produkte = null;

    public function __construct() {
        foreach ([
            'jahr', 'reihenfolge',
            'globalkreditIst', 'globalkreditSollVorjahr', 'globalkreditSoll',
            'globalkreditPlan1', 'globalkreditPlan2', 'globalkreditPlan3',
            'aufwandIst', 'aufwandSollVorjahr', 'aufwandSoll',
            'ertragIst', 'ertragSollVorjahr', 'ertragSoll',
        ] as $f) {
            $this->addType($f, 'integer');
        }
        foreach (['stellenIst', 'stellenSollVorjahr', 'stellenSoll', 'auszubildendeSoll'] as $f) {
            $this->addType($f, 'float');
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'code' => $this->code,
            'name' => $this->name,
            'departement' => $this->departement,
            'reihenfolge' => $this->reihenfolge,
            'globalkredit' => [
                'ist' => $this->globalkreditIst,
                'sollVorjahr' => $this->globalkreditSollVorjahr,
                'soll' => $this->globalkreditSoll,
                'plan1' => $this->globalkreditPlan1,
                'plan2' => $this->globalkreditPlan2,
                'plan3' => $this->globalkreditPlan3,
            ],
            'aufwand' => ['ist' => $this->aufwandIst, 'sollVorjahr' => $this->aufwandSollVorjahr, 'soll' => $this->aufwandSoll],
            'ertrag' => ['ist' => $this->ertragIst, 'sollVorjahr' => $this->ertragSollVorjahr, 'soll' => $this->ertragSoll],
            'stellen' => ['ist' => $this->stellenIst, 'sollVorjahr' => $this->stellenSollVorjahr, 'soll' => $this->stellenSoll],
            'auszubildendeSoll' => $this->auszubildendeSoll,
            'auftrag' => $this->auftrag,
            'zielvorgaben' => $this->zielvorgaben,
            'erlaeuterungStellen' => $this->erlaeuterungStellen,
            'begruendungAbweichung' => $this->begruendungAbweichung,
            'begruendungFap' => $this->begruendungFap,
            'massnahmen' => $this->massnahmen,
            'produkte' => $this->produkte ? (json_decode($this->produkte, true) ?: []) : [],
        ];
    }
}
