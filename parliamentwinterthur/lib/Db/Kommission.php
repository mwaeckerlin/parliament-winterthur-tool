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
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Kommission extends Entity {
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Name der Kommission */
    protected string $name = '';

    /** @var string Beschreibung/Aufgaben der Kommission */
    protected string $beschreibung = '';

    /** @var string Mitglieder-IDs als JSON-Array von extern_ids */
    protected string $mitglieder = '[]';

    /** @var bool Wurde die Kommission von der Webseite entfernt? */
    protected bool $geloescht = false;

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('geloescht', 'boolean');
    }

    /** Gibt die Mitglieder-IDs als Array zurück. */
    public function getMitgliederArray(): array {
        return json_decode($this->mitglieder, true) ?? [];
    }
}
