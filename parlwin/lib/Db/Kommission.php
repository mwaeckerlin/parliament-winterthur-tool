<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Kommission des Stadtparlaments Winterthur.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getName()
 * @method string getBeschreibung()
 * @method string getMitglieder()
 * @method bool   getAktiv()
 * @method string getDatumVon()
 * @method string getDatumBis()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Kommission extends Entity
{
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Name der Kommission */
    protected string $name = '';

    /** @var string Beschreibung/Aufgaben der Kommission */
    protected string $beschreibung = '';

    /** @var string Mitglieder-IDs als JSON-Array von extern_ids */
    protected string $mitglieder = '[]';

    /** @var bool Ist die Kommission aktuell aktiv? */
    protected bool $aktiv = true;

    /** @var string Mandatsbeginn (ISO YYYY-MM-DD) */
    protected string $datumVon = '';

    /** @var string Mandatsende (ISO YYYY-MM-DD, leer = offen) */
    protected string $datumBis = '';

    /** @var bool Wurde die Kommission von der Webseite entfernt? */
    protected bool $geloescht = false;

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('aktiv', 'boolean');
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'externId' => $this->getExternId(),
            'name' => $this->getName(),
            'beschreibung' => $this->getBeschreibung(),
            'mitglieder' => $this->getMitglieder(),
            'aktiv' => $this->getAktiv(),
            'datumVon' => $this->getDatumVon(),
            'datumBis' => $this->getDatumBis(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }

    /** Gibt die Mitglieder-IDs als Array zurück. */
    public function getMitgliederArray(): array
    {
        return json_decode($this->mitglieder, true) ?? [];
    }
}
