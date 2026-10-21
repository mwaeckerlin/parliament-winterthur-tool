<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Ein amtliches Dokument eines Geschäfts (F121) — Antrag, Antwort des
 * Stadtrats, Beilage, Pressemitteilung — samt seinem gelesenen Inhalt.
 *
 * Die Struktur hält die Abschnitte des PDF fest (Art, Seite, Text); das
 * Markdown entsteht aus derselben Struktur und wird angezeigt, der Volltext
 * dient der Suche.
 *
 * @method int    getGeschaeftId()
 * @method void   setGeschaeftId(int $v)
 * @method string getExternId()
 * @method void   setExternId(string $v)
 * @method string getTitel()
 * @method void   setTitel(string $v)
 * @method string getKategorie()
 * @method void   setKategorie(string $v)
 * @method string getDatum()
 * @method void   setDatum(string $v)
 * @method string getUrl()
 * @method void   setUrl(string $v)
 * @method string getStruktur()
 * @method void   setStruktur(string $v)
 * @method string getMarkdown()
 * @method void   setMarkdown(string $v)
 * @method string getVolltext()
 * @method void   setVolltext(string $v)
 * @method int    getSeiten()
 * @method void   setSeiten(int $v)
 * @method string getQuelleHash()
 * @method void   setQuelleHash(string $v)
 * @method string getGelesenAm()
 * @method void   setGelesenAm(string $v)
 * @method string getFehler()
 * @method void   setFehler(string $v)
 */
class GeschaeftDokument extends Entity implements JsonSerializable
{
    protected int $geschaeftId = 0;
    protected ?string $externId = '';
    protected ?string $titel = '';
    protected ?string $kategorie = '';
    protected ?string $datum = '';
    protected ?string $url = '';
    protected ?string $struktur = '';
    protected ?string $markdown = '';
    protected ?string $volltext = '';
    protected int $seiten = 0;
    protected ?string $quelleHash = '';
    protected ?string $gelesenAm = '';
    protected ?string $fehler = '';

    public function __construct()
    {
        $this->addType('geschaeftId', 'integer');
        $this->addType('seiten', 'integer');
    }

    /**
     * Die Abschnitte als Feld, wie sie gelesen wurden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function abschnitte(): array
    {
        $roh = json_decode((string) ($this->getStruktur() ?? ''), true);
        return \is_array($roh) ? $roh : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'geschaeftId' => $this->getGeschaeftId(),
            'externId' => $this->getExternId() ?? '',
            'titel' => $this->getTitel() ?? '',
            'kategorie' => $this->getKategorie() ?? '',
            'datum' => $this->getDatum() ?? '',
            'url' => $this->getUrl() ?? '',
            'abschnitte' => $this->abschnitte(),
            'markdown' => $this->getMarkdown() ?? '',
            'seiten' => $this->getSeiten(),
            'gelesenAm' => $this->getGelesenAm() ?? '',
            'fehler' => $this->getFehler() ?? '',
        ];
    }
}
