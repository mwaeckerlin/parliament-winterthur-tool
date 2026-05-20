<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Fraktion des Stadtparlaments Winterthur.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getName()
 * @method string getBeschreibung()
 * @method string getMitglieder()
 * @method string getDatumVon()
 * @method string getDatumBis()
 * @method bool   getAktiv()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Fraktion extends Entity {
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Name der Fraktion */
    protected string $name = '';

    /** @var string Beschreibung der Fraktion */
    protected string $beschreibung = '';

    /** @var string Mitglieder-IDs als JSON-Array von extern_ids */
    protected string $mitglieder = '[]';

    /** @var string Amts-/Gültigkeitsbeginn (YYYY-MM-DD) */
    protected string $datumVon = '';

    /** @var string Amts-/Gültigkeitsende (YYYY-MM-DD, leer = offen) */
    protected string $datumBis = '';

    /** @var bool Ist die Fraktion aktuell aktiv? */
    protected bool $aktiv = true;

    /** @var bool Wurde die Fraktion von der Webseite entfernt? */
    protected bool $geloescht = false;

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('aktiv', 'boolean');
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'externId' => $this->getExternId(),
            'name' => $this->getName(),
            'beschreibung' => $this->getBeschreibung(),
            'mitglieder' => $this->getMitglieder(),
            'datumVon' => $this->getDatumVon(),
            'datumBis' => $this->getDatumBis(),
            'aktiv' => $this->getAktiv(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }

    /** Gibt die Mitglieder-IDs als Array zurück. */
    public function getMitgliederArray(): array {
        return json_decode($this->mitglieder, true) ?? [];
    }
}
