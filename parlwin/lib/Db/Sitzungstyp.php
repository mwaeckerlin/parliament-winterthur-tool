<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Sitzungs-Vorlage / Sitzungstyp.
 *
 * Definiert eine wiederkehrende Sitzungsart (z. B. „Fraktionssitzung“) mit
 * Standard-Traktanden und Standard-Teilnehmern. Konkrete Sitzungen werden
 * aus der Vorlage instanziiert (Traktanden kopiert, Teilnehmer aufgelöst).
 *
 * @method int    getId()
 * @method string getName()
 * @method string getZweck()
 * @method bool   getKalenderAnlegen()
 * @method bool   getEinladungVersenden()
 * @method string getStandardOrt()
 * @method string getStandardZeitVon()
 * @method string getStandardZeitBis()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Sitzungstyp extends Entity
{
    protected string $name = '';
    protected string $zweck = '';
    protected bool $kalenderAnlegen = true;
    protected bool $einladungVersenden = false;
    protected string $standardOrt = '';
    protected string $standardZeitVon = '';
    protected string $standardZeitBis = '';
    protected bool $geloescht = false;
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('kalenderAnlegen', 'boolean');
        $this->addType('einladungVersenden', 'boolean');
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'zweck' => $this->getZweck(),
            'kalenderAnlegen' => $this->getKalenderAnlegen(),
            'einladungVersenden' => $this->getEinladungVersenden(),
            'standardOrt' => $this->getStandardOrt(),
            'standardZeitVon' => $this->getStandardZeitVon(),
            'standardZeitBis' => $this->getStandardZeitBis(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }
}
