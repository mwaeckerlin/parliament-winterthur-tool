<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Einzelnes Verfahrensereignis eines Geschaefts (aus Detailseite extrahiert).
 *
 * @method int    getId()
 * @method int    getGeschaeftId()
 * @method int    getReihenfolge()
 * @method string getTyp()
 * @method string getOrgan()
 * @method string getLabel()
 * @method string getWert()
 * @method string getDatum()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class GeschaeftEreignis extends Entity {
    protected int $geschaeftId = 0;
    protected int $reihenfolge = 0;
    protected string $typ = 'info';
    protected string $organ = '';
    protected string $label = '';
    protected string $wert = '';
    protected string $datum = '';
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';
}
