<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Politisches Geschäft des Stadtparlaments Winterthur.
 *
 * Enthält sowohl die öffentlich zugänglichen Daten von der Parlamentswebseite
 * als auch fraktionsinterne Zusatzfelder.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getTitel()
 * @method string getNummer()
 * @method string getTyp()
 * @method string getStatus()
 * @method string getDatum()
 * @method string getUrl()
 * @method string getRohDaten()
 * @method bool   getGeloescht()
 * @method string getBemerkungen()
 * @method string getZustaendigePerson()
 * @method string getAntragFraktion()
 * @method string getEntscheidFraktion()
 * @method string getNotizen()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Geschaeft extends Entity {
    /** @var string ID auf der Parlamentswebseite */
    protected string $externId = '';

    /** @var string Titel/Bezeichnung des Geschäfts */
    protected string $titel = '';

    /** @var string Geschäftsnummer (z.B. 2024/05) */
    protected string $nummer = '';

    /** @var string Art des Geschäfts (z.B. Motion, Interpellation) */
    protected string $typ = '';

    /** @var string Aktueller Stand des Geschäfts */
    protected string $status = '';

    /** @var string Eingangsdatum (ISO 8601) */
    protected string $datum = '';

    /** @var string Direkter Link auf der Parlamentswebseite */
    protected string $url = '';

    /** @var string Alle data-entities-Felder als JSON-String */
    protected string $rohDaten = '{}';

    /** @var bool Wurde das Geschäft von der Webseite entfernt? */
    protected bool $geloescht = false;

    // --- Fraktionsinterne Zusatzfelder ---

    /** @var string Freie Bemerkungen der Fraktion */
    protected string $bemerkungen = '';

    /** @var string Name des zuständigen Fraktionsmitglieds */
    protected string $zustaendigePerson = '';

    /** @var string Antrag, den die Fraktion stellen will */
    protected string $antragFraktion = '';

    /** @var string Entscheid der Fraktion zu diesem Geschäft */
    protected string $entscheidFraktion = '';

    /** @var string Weitere interne Notizen (JSON-Array) */
    protected string $notizen = '[]';

    /** @var string Erstellungszeitpunkt (ISO 8601) */
    protected string $erstelltAm = '';

    /** @var string Letzter Aktualisierungszeitpunkt (ISO 8601) */
    protected string $aktualisiertAm = '';

    public function __construct() {
        $this->addType('geloescht', 'boolean');
    }

    /** Gibt die rohen Daten als assoziatives Array zurück. */
    public function getRohDatenArray(): array {
        return json_decode($this->rohDaten, true) ?? [];
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
