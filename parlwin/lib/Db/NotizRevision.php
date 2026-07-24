<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Archivierte Vorversion einer Notiz.
 *
 * Beim Bearbeiten einer Notiz wird der bisherige Text hier abgelegt; die Notiz
 * selbst (GeschaeftAktion) trägt immer die aktuelle Fassung. Zusammen ergibt das
 * eine Versions-History, durch die vor- und zurückgeblättert werden kann.
 *
 * @method int    getAktionId()
 * @method string getText()
 * @method string getAutorUid()
 * @method string getAutorName()
 * @method string getErstelltAm()
 * @method void   setAktionId(int $id)
 * @method void   setText(string $text)
 * @method void   setAutorUid(string $uid)
 * @method void   setAutorName(string $name)
 * @method void   setErstelltAm(string $zeitpunkt)
 */
class NotizRevision extends Entity {
    protected int $aktionId = 0;
    protected string $text = '';
    protected string $autorUid = '';
    protected string $autorName = '';
    protected string $erstelltAm = '';
}
