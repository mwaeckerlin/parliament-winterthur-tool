<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Parlamentssitzung des Stadtparlaments Winterthur.
 *
 * @method int     getId()
 * @method ?string getExternId()
 * @method string getTitel()
 * @method string getDatum()
 * @method string getZeitVon()
 * @method string getZeitBis()
 * @method string getOrt()
 * @method string getUrl()
 * @method bool   getGeloescht()
 * @method string getBemerkungen()
 * @method string getNotizen()
 * @method int    getTypId()
 * @method string getTeilnehmer()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Sitzung extends Entity
{
    /** @var ?string ID auf der Parlamentswebseite (NULL für interne Sitzungen) */
    protected ?string $externId = null;

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

    /** @var string Fraktionsinterne Bemerkungen (Altbestand, ersetzt durch Notizen) */
    protected string $bemerkungen = '';

    /** @var string Beliebig viele Notizen als JSON-Array (Datum/Autor/Text) */
    protected string $notizen = '[]';

    /** @var int Optionaler Verweis auf eine Sitzungs-Vorlage (0 = keine) */
    protected int $typId = 0;

    /**
     * @var string Materialisierte Teilnehmerliste (JSON-Array von
     * {mitgliedId,name,email,rolle}). Wird beim Erstellen aus einer
     * Vorlage befüllt.
     */
    protected string $teilnehmer = '[]';

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('geloescht', 'boolean');
        $this->addType('typId', 'integer');
    }

    // Explizite Setter für Felder, deren Entity-Default mit dem Leer-Wert übereinstimmt.
    // Nextcloud's setter() übergeht markFieldUpdated wenn sich der Wert nicht ändert —
    // bei neuen Entities (alle Properties auf Default) würde dann kein INSERT-Feld erzeugt.
    public function setExternId(?string $externId): void
    {
        $this->markFieldUpdated('externId');
        $this->externId = $externId;
    }

    public function setUrl(string $url): void
    {
        $this->markFieldUpdated('url');
        $this->url = $url;
    }

    public function setGeloescht(bool $geloescht): void
    {
        $this->markFieldUpdated('geloescht');
        $this->geloescht = $geloescht;
    }

    public function setNotizen(string $notizen): void
    {
        $this->markFieldUpdated('notizen');
        $this->notizen = $notizen;
    }

    public function setTeilnehmer(string $teilnehmer): void
    {
        $this->markFieldUpdated('teilnehmer');
        $this->teilnehmer = $teilnehmer;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
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
            'notizen' => $this->getNotizen(),
            'typId' => $this->getTypId(),
            'teilnehmer' => $this->getTeilnehmerArray(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }

    /**
     * Gibt die materialisierten Teilnehmer als Array zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTeilnehmerArray(): array
    {
        $entschluesselt = json_decode($this->teilnehmer ?: '[]', true);
        return is_array($entschluesselt) ? $entschluesselt : [];
    }

    /**
     * Gibt die Notizen als Array zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNotizenArray(): array
    {
        $entschluesselt = json_decode($this->notizen ?: '[]', true);
        return is_array($entschluesselt) ? $entschluesselt : [];
    }
}
