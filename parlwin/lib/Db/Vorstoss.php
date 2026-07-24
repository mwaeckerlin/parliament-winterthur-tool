<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Politischer Vorstoss (Motion, Postulat, Interpellation, Anfrage …).
 *
 * Vorstösse werden manuell erfasst oder automatisch aus den Dokumenten im
 * Ordner «Fraktion/40_Vorstösse» übernommen.
 *
 * - herkunft: «eigene» (von der eigenen Fraktion eingereicht) oder «fremde»
 *   (von einer anderen Fraktion – dann ist ein Beschluss zur Haltung relevant).
 * - status: neu | entwurf | bereit | eingereicht | erledigt | pausiert
 *
 * @method int    getId()
 * @method string getTitel()
 * @method string getArt()
 * @method string getHerkunft()
 * @method string getStatus()
 * @method string getPrioritaet()
 * @method string getBeschluss()
 * @method string getZustaendigkeit()
 * @method string getHerkunftFraktion()
 * @method string getAnsprechpartner()
 * @method string getInhalt()
 * @method string getNotizen()
 * @method string getDokument()
 * @method int    getGeschaeftId()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Vorstoss extends Entity implements \JsonSerializable
{
    // ALLE Textspalten sind nullable: die Tabelle kann NULL enthalten (nullable
    // Spalten + nur «dirty» Felder im INSERT). Eine non-nullable Property würde
    // beim Laden einen TypeError werfen und die GANZE Liste leeren; die API
    // liefert NULL als Leerwert (jsonSerialize).
    protected ?string $titel = '';
    protected ?string $art = '';
    protected ?string $herkunft = 'eigene';
    protected ?string $status = 'neu';
    /** @var ?string Priorität: '' (undefiniert), 'hoch', 'mittel', 'tief' */
    protected ?string $prioritaet = '';
    protected ?string $beschluss = '';
    /** @var ?string JSON-Liste der zuständigen Personen: [{key, name}] */
    protected ?string $zustaendigkeit = '';
    /** @var ?string Name der Herkunftsfraktion (nur bei fremdem Vorstoss) */
    protected ?string $herkunftFraktion = '';
    /** @var ?string JSON-Liste der Ansprechpartner in der fremden Fraktion: [{key, name}] */
    protected ?string $ansprechpartner = '';
    protected ?string $inhalt = '';
    /** @var ?string JSON-Liste der Notizen: [{id, text, autorUid, autorName, erstelltAm}] */
    protected ?string $notizen = '[]';
    protected ?string $dokument = '';
    /** @var int Verknüpftes Geschäft (0 = nicht verknüpft) */
    protected int $geschaeftId = 0;
    protected bool $geloescht = false;
    protected ?string $erstelltAm = '';
    protected ?string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('geloescht', 'boolean');
        $this->addType('geschaeftId', 'integer');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'titel' => $this->getTitel() ?? '',
            'art' => $this->getArt() ?? '',
            'herkunft' => $this->getHerkunft() ?? 'eigene',
            'status' => $this->getStatus() ?? 'neu',
            'prioritaet' => $this->getPrioritaet() ?? '',
            'beschluss' => $this->getBeschluss() ?? '',
            'zustaendigkeit' => self::alsListe($this->getZustaendigkeit()),
            'herkunftFraktion' => $this->getHerkunftFraktion() ?? '',
            'ansprechpartner' => self::alsListe($this->getAnsprechpartner()),
            'inhalt' => $this->getInhalt() ?? '',
            'notizen' => $this->getNotizenArray(),
            'dokument' => $this->getDokument() ?? '',
            'geschaeftId' => $this->getGeschaeftId(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm() ?? '',
            'aktualisiertAm' => $this->getAktualisiertAm() ?? '',
        ];
    }

    /**
     * Wandelt einen gespeicherten Personen-Wert in eine Liste [{key, name}] um.
     * Neu ist der Wert JSON; ein alter Plain-String wird als Ein-Personen-Liste
     * geliefert, damit Bestandsdaten nicht verloren gehen.
     *
     * @return list<array{key:string,name:string}>
     */
    private static function alsListe(?string $roh): array
    {
        $roh = trim($roh ?? '');
        if ($roh === '') {
            return [];
        }
        $dekodiert = json_decode($roh, true);
        if (is_array($dekodiert)) {
            return $dekodiert;
        }
        return [['key' => '', 'name' => $roh]];
    }

    /** Gibt die Notizen als Array zurück (NULL/leer/ungültig ⇒ leere Liste). */
    public function getNotizenArray(): array
    {
        $dekodiert = json_decode($this->notizen ?? '[]', true);
        return is_array($dekodiert) ? $dekodiert : [];
    }

    /** Fügt eine Notiz hinzu (neueste zuletzt). */
    public function addNotiz(string $text, string $autorUid, string $autorName): void
    {
        $notizen = $this->getNotizenArray();
        $maxId = 0;
        foreach ($notizen as $n) {
            $maxId = max($maxId, (int) ($n['id'] ?? 0));
        }
        $notizen[] = [
            'id' => $maxId + 1,
            'text' => $text,
            'autorUid' => $autorUid,
            'autorName' => $autorName,
            'erstelltAm' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
        $this->setNotizen((string) json_encode($notizen));
    }
}
