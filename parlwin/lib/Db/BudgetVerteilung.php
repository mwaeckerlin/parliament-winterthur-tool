<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Zustand der automatischen Pauschalverteilung je Budgetjahr.
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method int    getAutomatikEin()
 * @method void   setAutomatikEin(int $v)
 * @method string getZielModus()
 * @method void   setZielModus(string $v)
 * @method int    getZielBetrag()
 * @method void   setZielBetrag(int $v)
 */
class BudgetVerteilung extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected int $automatikEin = 1;
    protected string $zielModus = 'schwarze_null';
    protected int $zielBetrag = 0;

    public function __construct() {
        $this->addType('jahr', 'integer');
        $this->addType('automatikEin', 'integer');
        $this->addType('zielBetrag', 'integer');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'automatikEin' => (bool) $this->automatikEin,
            'zielModus' => $this->zielModus,
            'zielBetrag' => $this->zielBetrag,
        ];
    }
}
