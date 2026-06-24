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
 * @method string getBeschluss()
 * @method string getZustaendigkeit()
 * @method string getInhalt()
 * @method string getDokument()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Vorstoss extends Entity
{
    protected string $titel = '';
    protected string $art = '';
    protected string $herkunft = 'eigene';
    protected string $status = 'neu';
    protected string $beschluss = '';
    protected string $zustaendigkeit = '';
    protected string $inhalt = '';
    protected string $dokument = '';
    protected bool $geloescht = false;
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'titel' => $this->getTitel(),
            'art' => $this->getArt(),
            'herkunft' => $this->getHerkunft(),
            'status' => $this->getStatus(),
            'beschluss' => $this->getBeschluss(),
            'zustaendigkeit' => $this->getZustaendigkeit(),
            'inhalt' => $this->getInhalt(),
            'dokument' => $this->getDokument(),
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm(),
            'aktualisiertAm' => $this->getAktualisiertAm(),
        ];
    }
}
