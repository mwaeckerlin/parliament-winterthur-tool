<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Zeitlich gültige Fraktionsrolle.
 *
 * @method int    getId()
 * @method string getUid()
 * @method string getName()
 * @method string getRolleCode()
 * @method string|null getGueltigVon()
 * @method string|null getGueltigBis()
 * @method string getGesetztVonUid()
 * @method string getGesetztVonName()
 * @method bool   getAktiv()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Fraktionsrolle extends Entity {
    protected string $uid = '';
    protected string $name = '';
    protected string $rolleCode = '';
    protected ?string $gueltigVon = null;
    protected ?string $gueltigBis = null;
    protected string $gesetztVonUid = '';
    protected string $gesetztVonName = '';
    protected bool $aktiv = true;
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('aktiv', 'boolean');
    }
}
