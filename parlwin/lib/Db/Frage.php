<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Eine Frage der Fraktion für die Fragestunde des Parlaments (F114).
 *
 * Ein Mitglied bringt sie ein; die Fraktion bespricht sie in ihrer Sitzung, passt
 * sie an und teilt sie einem Mitglied zum Einreichen zu. Meist ist das der
 * Urheber — weil jedes Mitglied nur eine Frage einreichen darf, manchmal jemand
 * anderes.
 *
 * status: neu | besprochen | eingereicht | zurueckgezogen
 *
 * @method int    getId()
 * @method int    getFragestundeId()
 * @method string getUrheberKey()
 * @method string getUrheberName()
 * @method string getFrage()
 * @method string getKommentar()
 * @method string getEinreicherKey()
 * @method string getEinreicherName()
 * @method string getStatus()
 * @method bool   getGeloescht()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class Frage extends Entity implements \JsonSerializable
{
    /** Die Organisationsverordnung lässt höchstens 1'000 Zeichen zu (Art. 103 Abs. 2). */
    public const MAX_ZEICHEN = 1000;

    protected int $fragestundeId = 0;
    protected ?string $urheberKey = '';
    protected ?string $urheberName = '';
    protected ?string $frage = '';
    protected ?string $kommentar = '';
    protected ?string $einreicherKey = '';
    protected ?string $einreicherName = '';
    protected ?string $status = 'neu';
    protected bool $geloescht = false;
    protected ?string $erstelltAm = '';
    protected ?string $aktualisiertAm = '';

    public function __construct()
    {
        $this->addType('fragestundeId', 'integer');
        $this->addType('geloescht', 'boolean');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $frage = $this->getFrage() ?? '';
        return [
            'id' => $this->getId(),
            'fragestundeId' => $this->getFragestundeId(),
            'urheber' => [
                'key' => $this->getUrheberKey() ?? '',
                'name' => $this->getUrheberName() ?? '',
            ],
            'frage' => $frage,
            'zeichen' => mb_strlen($frage),
            'kommentar' => $this->getKommentar() ?? '',
            'einreicher' => [
                'key' => $this->getEinreicherKey() ?? '',
                'name' => $this->getEinreicherName() ?? '',
            ],
            'status' => $this->getStatus() ?? 'neu',
            'geloescht' => $this->getGeloescht(),
            'erstelltAm' => $this->getErstelltAm() ?? '',
            'aktualisiertAm' => $this->getAktualisiertAm() ?? '',
        ];
    }
}
