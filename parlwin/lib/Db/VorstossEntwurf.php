<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Vorstoss in Entwurfs- bzw. Einreichungsphase ohne Geschaeftsnummer.
 *
 * @method int    getId()
 * @method string getExternId()
 * @method string getTitel()
 * @method string getTitelNormalisiert()
 * @method string getTyp()
 * @method string getEingangsdatum()
 * @method string getUrl()
 * @method string getStatus()
 * @method string getMatchArt()
 * @method int    getGeschaeftId()
 * @method string getErstelltAm()
 * @method string getAktualisiertAm()
 */
class VorstossEntwurf extends Entity {
    protected string $externId = '';
    protected string $titel = '';
    protected string $titelNormalisiert = '';
    protected string $typ = '';
    protected string $eingangsdatum = '';
    protected string $url = '';
    protected string $status = 'offen';
    protected string $matchArt = '';
    protected int $geschaeftId = 0;
    protected string $erstelltAm = '';
    protected string $aktualisiertAm = '';
}
