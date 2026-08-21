<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Eine explizite Dokument-Verknüpfung: eine Datei (file_id + Pfad) gehört zu
 * einem Objekt (objekt_typ = vorstoss|geschaeft|sitzung, objekt_id). Unabhängig
 * vom Dateinamen und Ablageort — die Zuordnung ist explizit, nicht über einen
 * Namenspräfix.
 *
 * @method string getObjektTyp()
 * @method void   setObjektTyp(string $v)
 * @method int    getObjektId()
 * @method void   setObjektId(int $v)
 * @method int    getFileId()
 * @method void   setFileId(int $v)
 * @method string getPfad()
 * @method void   setPfad(string $v)
 * @method string getErstelltAm()
 * @method void   setErstelltAm(string $v)
 */
class DokumentLink extends Entity implements JsonSerializable
{
    protected string $objektTyp = '';
    protected int $objektId = 0;
    protected int $fileId = 0;
    protected string $pfad = '';
    protected string $erstelltAm = '';

    public function __construct()
    {
        $this->addType('objektId', 'integer');
        $this->addType('fileId', 'integer');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'objektTyp' => $this->getObjektTyp(),
            'objektId' => $this->getObjektId(),
            'fileId' => $this->getFileId(),
            'pfad' => $this->getPfad(),
            'erstelltAm' => $this->getErstelltAm(),
        ];
    }
}
