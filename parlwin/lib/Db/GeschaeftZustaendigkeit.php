<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Aktuelle Zuständigkeit(en) zu einem Geschäft.
 *
 * @method int    getId()
 * @method int    getGeschaeftId()
 * @method string getPersonKey()
 * @method string getMitgliedExternId()
 * @method string getPersonName()
 * @method bool   getIstHaupt()
 * @method bool   getAktiv()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class GeschaeftZustaendigkeit extends Entity {
    protected int $geschaeftId = 0;
    protected string $personKey = '';
    protected string $mitgliedExternId = '';
    protected string $personName = '';
    protected bool $istHaupt = false;
    protected bool $aktiv = true;
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('istHaupt', 'boolean');
        $this->addType('aktiv', 'boolean');
    }
}
