<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Eine Fragestunde des Stadtparlaments (F114).
 *
 * Das Parlament hält sie zweimal im Jahr. Die Fragen müssen bis spätestens am
 * Donnerstag vor der Fragestunde schriftlich beim Parlamentsdienst eingereicht
 * werden und dürfen nicht mehr als 1'000 Zeichen umfassen (Art. 103 Abs. 2 der
 * Organisationsverordnung des Stadtparlaments).
 *
 * @method int    getId()
 * @method string getDatum()
 * @method string getTitel()
 * @method string getFrist()
 * @method int    getGeschaeftId()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Fragestunde extends Entity implements \JsonSerializable
{
    // Alle Textspalten sind nullable: Die Tabelle kann NULL enthalten (nur
    // «dirty» Felder landen im INSERT), und eine non-nullable Property würde
    // beim Laden einen TypeError werfen und die ganze Liste leeren.
    protected ?string $datum = '';
    protected ?string $titel = '';
    protected ?string $frist = '';
    /** @var int Verknüpftes Geschäft des Parlaments (0 = nicht verknüpft) */
    protected int $geschaeftId = 0;
    protected ?string $erstelltAm = '';
    protected ?string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('geschaeftId', 'integer');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'datum' => $this->getDatum() ?? '',
            'titel' => $this->getTitel() ?? '',
            'frist' => $this->getFrist() ?? '',
            'geschaeftId' => $this->getGeschaeftId(),
            'erstelltAm' => $this->getErstelltAm() ?? '',
            'aktualisiertAm' => $this->getAktualisiertAm() ?? '',
        ];
    }
}
