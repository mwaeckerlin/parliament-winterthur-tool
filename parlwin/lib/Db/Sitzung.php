<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Parlamentssitzung des Stadtparlaments Winterthur.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getTitel()
 * @method string getDatum()
 * @method string getZeitVon()
 * @method string getZeitBis()
 * @method string getOrt()
 * @method string getUrl()
 * @method bool   getGeloescht()
 * @method string getBemerkungen()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Sitzung extends Entity {
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Titel der Sitzung */
    protected string $titel = '';

    /** @var string Datum der Sitzung (ISO 8601) */
    protected string $datum = '';

    /** @var string Beginn der Sitzung (HH:MM) */
    protected string $zeitVon = '';

    /** @var string Ende der Sitzung (HH:MM) */
    protected string $zeitBis = '';

    /** @var string Sitzungsort */
    protected string $ort = '';

    /** @var string Direkter Link auf der Parlamentswebseite */
    protected string $url = '';

    /** @var bool Wurde die Sitzung von der Webseite entfernt? */
    protected bool $geloescht = false;

    /** @var string Fraktionsinterne Bemerkungen */
    protected string $bemerkungen = '';

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'externId' => $this->getExternId(),
            'titel' => $this->getTitel(),
            'datum' => $this->getDatum(),
            'zeitVon' => $this->getZeitVon(),
            'zeitBis' => $this->getZeitBis(),
            'ort' => $this->getOrt(),
            'url' => $this->getUrl(),
            'geloescht' => $this->getGeloescht(),
            'bemerkungen' => $this->getBemerkungen(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }
}
