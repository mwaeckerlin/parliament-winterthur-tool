<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Traktandum einer Parlamentssitzung.
 *
 * Ein Traktandum gehört zu einer Sitzung und ist in der Regel mit einem
 * politischen Geschäft aus der Geschäftsliste verknüpft.
 *
 * @method int    getId()
 * @method int    getSitzungId()
 * @method int    getGeschaeftId()
 * @method int    getNummer()
 * @method string getTitel()
 * @method string getBeschreibung()
 * @method bool   getGeloescht()
 * @method string getBemerkungen()
 * @method string getNotizen()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Traktandum extends Entity {
    /** @var int Fremdschlüssel zur zugehörigen Sitzung */
    protected int $sitzungId = 0;

    /** @var int Fremdschlüssel zum verknüpften Geschäft (0 = kein Geschäft verknüpft) */
    protected int $geschaeftId = 0;

    /** @var int Traktandumsnummer innerhalb der Sitzung */
    protected int $nummer = 0;

    /** @var string Titel des Traktandums */
    protected string $titel = '';

    /** @var string Kurzbeschreibung */
    protected string $beschreibung = '';

    /** @var bool Wurde das Traktandum entfernt? */
    protected bool $geloescht = false;

    /** @var string Fraktionsinterne Bemerkungen zum Traktandum */
    protected string $bemerkungen = '';

    /** @var string Beliebig viele Notizen als JSON-Array */
    protected string $notizen = '[]';

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('sitzungId', 'integer');
        $this->addType('geschaeftId', 'integer');
        $this->addType('nummer', 'integer');
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'sitzungId' => $this->getSitzungId(),
            'geschaeftId' => $this->getGeschaeftId(),
            'nummer' => $this->getNummer(),
            'titel' => $this->getTitel(),
            'beschreibung' => $this->getBeschreibung(),
            'geloescht' => $this->getGeloescht(),
            'bemerkungen' => $this->getBemerkungen(),
            'notizen' => $this->getNotizen(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }

    /** Gibt die Notizen als Array zurück. */
    public function getNotizenArray(): array {
        return json_decode($this->notizen, true) ?? [];
    }

    /** Fügt eine Notiz hinzu. */
    public function addNotiz(string $notiz): void {
        $notizen = $this->getNotizenArray();
        $notizen[] = [
            'text' => $notiz,
            'datum' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
        $this->notizen = json_encode($notizen);
    }
}
