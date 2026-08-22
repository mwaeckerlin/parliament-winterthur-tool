<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Ein Investitionsprojekt (Teil A, Investitionsplan) — gegliedert nach
 * Departement/Cluster/Projekt, nicht nach Produktegruppe.
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method string getDepartement()
 * @method void   setDepartement(?string $v)
 * @method string getCluster()
 * @method void   setCluster(?string $v)
 * @method string getProjekt()
 * @method void   setProjekt(?string $v)
 * @method int    getReihenfolge()
 * @method void   setReihenfolge(int $v)
 */
class BudgetInvestition extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected ?string $departement = null;
    protected ?string $cluster = null;
    protected ?string $projekt = null;
    protected int $bu = 0;
    protected int $fap1 = 0;
    protected int $fap2 = 0;
    protected int $fap3 = 0;
    protected int $gesamtkosten = 0;
    protected int $bereitsGetaetigt = 0;
    protected int $planungskosten = 0;
    protected int $reihenfolge = 0;

    public function __construct() {
        foreach (['jahr', 'bu', 'fap1', 'fap2', 'fap3', 'gesamtkosten', 'bereitsGetaetigt', 'planungskosten', 'reihenfolge'] as $f) {
            $this->addType($f, 'integer');
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'departement' => $this->departement,
            'cluster' => $this->cluster,
            'projekt' => $this->projekt,
            'bu' => $this->bu,
            'fap1' => $this->fap1,
            'fap2' => $this->fap2,
            'fap3' => $this->fap3,
            'gesamtkosten' => $this->gesamtkosten,
            'bereitsGetaetigt' => $this->bereitsGetaetigt,
            'planungskosten' => $this->planungskosten,
            'reihenfolge' => $this->reihenfolge,
        ];
    }
}
