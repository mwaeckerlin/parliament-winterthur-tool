<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Eingeladene Person/Gruppe einer Sitzungs-Vorlage.
 *
 * `art` ist 'mitglied' | 'fraktion' | 'kommission' | 'rolle'.
 * `referenzId` zeigt auf die ID des Mitglieds/der Fraktion/der Kommission;
 * `referenzName` enthält bei Rollen den Rollennamen (z. B. „Fraktionspräsidium“)
 * oder bei Gruppen den Klartext-Namen.
 *
 * @method int    getId()
 * @method int    getTypId()
 * @method string getArt()
 * @method int    getReferenzId()
 * @method string getReferenzName()
 */
class SitzungstypTeilnehmer extends Entity
{
  protected int $typId = 0;
  protected string $art = 'mitglied';
  protected int $referenzId = 0;
  protected string $referenzName = '';

  public function __construct()
  {
    $this->addType('typId', 'integer');
    $this->addType('referenzId', 'integer');
  }

  /**
   * @return array<string, mixed>
   */
  public function jsonSerialize(): array
  {
    return [
      'id' => $this->getId(),
      'typId' => $this->getTypId(),
      'art' => $this->getArt(),
      'referenzId' => $this->getReferenzId(),
      'referenzName' => $this->getReferenzName(),
    ];
  }
}
