<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Zeitlich protokollierte Fraktionsaktion zu einem Objekt (Geschäft oder
 * Vorstoss). Notizen (aktionTyp = «notiz») laufen für beide Objektarten über
 * denselben geteilten Code (NotizService); objektTyp unterscheidet, geschaeftId
 * hält die Objekt-ID.
 *
 * @method int    getId()
 * @method string getObjektTyp()
 * @method void   setObjektTyp(string $v)
 * @method int    getGeschaeftId()
 * @method void   setGeschaeftId(int $v)
 * @method string getAktionTyp()
 * @method void   setAktionTyp(string $v)
 * @method string getAktionCode()
 * @method void   setAktionCode(string $v)
 * @method string getTitel()
 * @method void   setTitel(string $v)
 * @method string getText()
 * @method bool   getEntscheidGueltig()
 * @method string getAutorUid()
 * @method void   setAutorUid(string $v)
 * @method string getAutorName()
 * @method void   setAutorName(string $v)
 * @method string getErstelltAm()
 * @method void   setErstelltAm(string $v)
 * @method bool   getGeloescht()
 * @method void   setText(string $text)
 * @method void   setEntscheidGueltig(bool $v)
 * @method void   setGeloescht(bool $v)
 */
class GeschaeftAktion extends Entity {
    /** «geschaeft» oder «vorstoss» — trennt Notizen der beiden Objektarten. */
    protected string $objektTyp = 'geschaeft';
    protected int $geschaeftId = 0;
    protected string $aktionTyp = '';
    protected string $aktionCode = '';
    protected string $titel = '';
    protected string $text = '';
    protected bool $entscheidGueltig = false;
    protected string $autorUid = '';
    protected string $autorName = '';
    protected string $erstelltAm = '';
    /** Gelöschte Notizen bleiben erhalten und werden nur ausgeblendet (Undo möglich). */
    protected bool $geloescht = false;

    public function __construct() {
        $this->addType('entscheidGueltig', 'boolean');
        $this->addType('geloescht', 'boolean');
    }
}
