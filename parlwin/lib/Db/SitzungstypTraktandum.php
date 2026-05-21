<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Standard-Traktandum einer Sitzungs-Vorlage.
 *
 * @method int    getId()
 * @method int    getTypId()
 * @method int    getPosition()
 * @method string getTitel()
 * @method string getBeschreibung()
 */
class SitzungstypTraktandum extends Entity
{
    protected int $typId = 0;
    protected int $position = 0;
    protected string $titel = '';
    protected string $beschreibung = '';

    public function __construct()
    {
        $this->addType('typId', 'integer');
        $this->addType('position', 'integer');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'typId' => $this->getTypId(),
            'position' => $this->getPosition(),
            'titel' => $this->getTitel(),
            'beschreibung' => $this->getBeschreibung(),
        ];
    }
}
