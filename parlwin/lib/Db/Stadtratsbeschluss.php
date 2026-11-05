<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Ein Beschluss des Stadtrats (F144).
 *
 * Die Beschlüsse kommen von der Webseite der Stadt und werden nicht von Hand
 * erfasst; die Fraktion ergänzt ihre Notizen.
 *
 * @method int    getId()
 * @method string getNummer()
 * @method string getExternId()
 * @method string getTitel()
 * @method string getDatum()
 * @method string getDepartement()
 * @method string getSitzung()
 * @method string getPubliziertAm()
 * @method string getUrl()
 * @method string getDokumente()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Stadtratsbeschluss extends Entity implements \JsonSerializable
{
    // Alle Textspalten sind nullable, wie am Vorstoss: die Tabelle kann NULL
    // enthalten, und eine non-nullable Property würde beim Laden einen
    // TypeError werfen und damit die ganze Liste leeren.
    protected ?string $nummer = '';
    protected ?string $externId = '';
    protected ?string $titel = '';
    protected ?string $datum = '';
    protected ?string $departement = '';
    protected ?string $sitzung = '';
    protected ?string $publiziertAm = '';
    protected ?string $url = '';
    /** @var ?string JSON-Liste der Dokumente: [{titel, url}] */
    protected ?string $dokumente = '[]';
    protected ?string $erstelltAm = '';
    protected ?string $aktualisiertAm = '';

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'nummer' => $this->getNummer() ?? '',
            'externId' => $this->getExternId() ?? '',
            'titel' => $this->getTitel() ?? '',
            'datum' => $this->getDatum() ?? '',
            'departement' => $this->getDepartement() ?? '',
            'sitzung' => $this->getSitzung() ?? '',
            'publiziertAm' => $this->getPubliziertAm() ?? '',
            'url' => $this->getUrl() ?? '',
            'dokumente' => $this->getDokumenteArray(),
            'erstelltAm' => $this->getErstelltAm() ?? '',
            'aktualisiertAm' => $this->getAktualisiertAm() ?? '',
        ];
    }

    /**
     * Die Dokumente als Liste (NULL, leer oder ungültig ⇒ leere Liste).
     *
     * @return list<array{titel:string,url:string}>
     */
    public function getDokumenteArray(): array
    {
        $dekodiert = json_decode($this->dokumente ?? '[]', true);
        return is_array($dekodiert) ? $dekodiert : [];
    }

}
