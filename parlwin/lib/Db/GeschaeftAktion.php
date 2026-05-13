<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Zeitlich protokollierte Fraktionsaktion zu einem Geschäft.
 *
 * @method int    getId()
 * @method int    getGeschaeftId()
 * @method string getAktionTyp()
 * @method string getAktionCode()
 * @method string getTitel()
 * @method string getText()
 * @method bool   getEntscheidGueltig()
 * @method string getAutorUid()
 * @method string getAutorName()
 * @method string getErstelltAm()
 */
class GeschaeftAktion extends Entity {
    protected int $geschaeftId = 0;
    protected string $aktionTyp = '';
    protected string $aktionCode = '';
    protected string $titel = '';
    protected string $text = '';
    protected bool $entscheidGueltig = false;
    protected string $autorUid = '';
    protected string $autorName = '';
    protected string $erstelltAm = '';

    public function __construct() {
        $this->addType('entscheidGueltig', 'boolean');
    }
}
