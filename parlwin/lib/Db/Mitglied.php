<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Parlamentsmitglied des Stadtparlaments Winterthur.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getName()
 * @method string getVorname()
 * @method string getPartei()
 * @method string getFraktion()
 * @method string getEmail()
 * @method string getFotoUrl()
 * @method string getNextcloudUid()
 * @method bool   getAktiv()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Mitglied extends Entity {
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Nachname */
    protected string $name = '';

    /** @var string Vorname */
    protected string $vorname = '';

    /** @var string Partei des Mitglieds (z.B. SP, Grüne) */
    protected string $partei = '';

    /** @var string Fraktion des Mitglieds */
    protected string $fraktion = '';

    /** @var string E-Mail-Adresse */
    protected string $email = '';

    /** @var string URL des Porträtfotos */
    protected string $fotoUrl = '';

    /** @var string Gemappter Nextcloud-Benutzername (manuell pflegbar) */
    protected string $nextcloudUid = '';

    /** @var bool Ist das Mitglied aktuell aktiv? */
    protected bool $aktiv = true;

    /** @var bool Wurde das Mitglied von der Webseite entfernt? */
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
            'vorname' => $this->getVorname(),
            'partei' => $this->getPartei(),
            'fraktion' => $this->getFraktion(),
            'email' => $this->getEmail(),
            'fotoUrl' => $this->getFotoUrl(),
            'nextcloudUid' => $this->getNextcloudUid(),
            'aktiv' => $this->getAktiv(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }

    /** Gibt den vollständigen Namen zurück. */
    public function getVollerName(): string {
        return trim($this->vorname . ' ' . $this->name);
    }
}
