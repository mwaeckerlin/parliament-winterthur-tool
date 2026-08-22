<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Live-Verfolgung: der Beschluss-Status eines Antrags (offen|angenommen|
 * abgelehnt). Hängt am Antrag, nicht an einer Sitzung — erscheint darum in allen
 * Sitzungen, die dasselbe Budget traktandieren.
 *
 * @method int    getAntragId()
 * @method void   setAntragId(int $v)
 * @method string getStatus()
 * @method void   setStatus(string $v)
 * @method string getGeaendertVon()
 * @method void   setGeaendertVon(?string $v)
 * @method int    getGeaendertAm()
 * @method void   setGeaendertAm(?int $v)
 */
class BudgetAntragEntscheid extends Entity implements JsonSerializable {
    protected int $antragId = 0;
    protected string $status = 'offen';
    protected ?string $geaendertVon = null;
    protected ?int $geaendertAm = null;

    public function __construct() {
        $this->addType('antragId', 'integer');
        $this->addType('geaendertAm', 'integer');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'antragId' => $this->antragId,
            'status' => $this->status,
            'geaendertVon' => $this->geaendertVon,
        ];
    }
}
