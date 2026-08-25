<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Ein Budget-Antrag (eigen oder fremd). Bereich: globalbudget|personal|
 * investition|steuerfuss. Quelle: manuell|pauschal|steuerfuss. Automatisch aus
 * einer Pauschalverteilung erzeugte Anträge tragen eine verteilungId.
 *
 * Herkunft/Haltung folgen dem Vorstoss-Muster (F94/F97): «eigene» Anträge
 * reichen wir ein oder nicht, «fremde» unterstützen wir, nicht oder offen. Der
 * Betrag steht in CHF (betragDelta) UND in Prozent (prozentDelta); beim
 * Steuerfuss sind es Prozentpunkte (F95/F96). unterstuetzer und notizen sind
 * JSON-Listen (F98/F103).
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method string getBereich()
 * @method void   setBereich(?string $v)
 * @method string getZielTyp()
 * @method void   setZielTyp(?string $v)
 * @method string getZielRef()
 * @method void   setZielRef(?string $v)
 * @method int    getBetragDelta()
 * @method void   setBetragDelta(int $v)
 * @method float  getProzentDelta()
 * @method void   setProzentDelta(float $v)
 * @method float  getStellenDelta()
 * @method void   setStellenDelta(float $v)
 * @method int    getBetragProStelle()
 * @method void   setBetragProStelle(int $v)
 * @method string getQuelle()
 * @method void   setQuelle(?string $v)
 * @method string getHerkunft()
 * @method void   setHerkunft(?string $v)
 * @method string getHaltung()
 * @method void   setHaltung(?string $v)
 * @method string getUnterstuetzer()
 * @method void   setUnterstuetzer(?string $v)
 * @method int    getPauschalAusnahme()
 * @method void   setPauschalAusnahme(int $v)
 * @method int    getVerknuepftMitId()
 * @method void   setVerknuepftMitId(int $v)
 * @method string getAntragsteller()
 * @method void   setAntragsteller(?string $v)
 * @method string getBegruendung()
 * @method void   setBegruendung(?string $v)
 * @method int    getVerteilungId()
 * @method void   setVerteilungId(?int $v)
 * @method int    getReihenfolge()
 * @method void   setReihenfolge(int $v)
 * @method string getErstelltVon()
 * @method void   setErstelltVon(?string $v)
 * @method int    getErstelltAm()
 * @method void   setErstelltAm(?int $v)
 * @method string getPhase()
 * @method void   setPhase(?string $v)
 */
class BudgetAntrag extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected ?string $bereich = null;
    protected ?string $zielTyp = null;
    protected ?string $zielRef = null;
    protected int $betragDelta = 0;
    protected float $prozentDelta = 0.0;
    protected float $stellenDelta = 0.0;
    protected int $betragProStelle = 0;
    protected ?string $quelle = null;
    /** Herkunft: «eigene» oder «fremde» (F94). */
    protected ?string $herkunft = 'eigene';
    /** Unsere Haltung, getrennt vom Sitzungs-Beschluss (F97). */
    protected ?string $haltung = '';
    /** JSON-Liste der unterstützenden Fraktionen (F98). */
    protected ?string $unterstuetzer = '[]';
    /** Position vom Pauschalantrag ausgenommen (F101). */
    protected int $pauschalAusnahme = 0;
    /** Verknüpfter Antrag der jeweils anderen Phase (F104), 0 = nicht verknüpft. */
    protected int $verknuepftMitId = 0;
    protected ?string $antragsteller = null;
    protected ?string $begruendung = null;
    protected ?int $verteilungId = null;
    protected int $reihenfolge = 0;
    protected ?string $erstelltVon = null;
    protected ?int $erstelltAm = null;
    /** Phase: «fraktion» (interne Vorbereitung) oder «sitzung» (offizielle Sitzungsanträge). */
    protected ?string $phase = 'fraktion';

    public function __construct() {
        foreach (['jahr', 'betragDelta', 'betragProStelle', 'verteilungId', 'reihenfolge', 'erstelltAm', 'pauschalAusnahme', 'verknuepftMitId'] as $f) {
            $this->addType($f, 'integer');
        }
        $this->addType('stellenDelta', 'float');
        $this->addType('prozentDelta', 'float');
    }

    /**
     * Standard-Haltung nach Herkunft: eigene Anträge reichen wir ein, fremde sind
     * zunächst offen (F97).
     */
    public function haltungOderStandard(): string {
        $h = trim((string) ($this->haltung ?? ''));
        if ($h !== '') {
            return $h;
        }
        return ((string) ($this->herkunft ?? 'eigene')) === 'fremde' ? 'offen' : 'einreichen';
    }

    /**
     * Zählt dieser Antrag zur korrigierten Summe der Fraktion? Nur, was die
     * Fraktion unterstützt: eigene «einreichen», fremde «unterstuetzen» (F102).
     */
    public function wirdUnterstuetzt(): bool {
        $h = $this->haltungOderStandard();
        return $h === 'einreichen' || $h === 'unterstuetzen';
    }

    /** @return list<array{key:string,name:string}> */
    public function getUnterstuetzerArray(): array {
        return self::alsListe($this->unterstuetzer);
    }

    /**
     * Wandelt einen gespeicherten Listen-Wert in [{key, name}] um (JSON;
     * Altwert als Ein-Element-Liste), wie beim Vorstoss.
     *
     * @return list<array{key:string,name:string}>
     */
    private static function alsListe(?string $roh): array {
        $roh = trim($roh ?? '');
        if ($roh === '') {
            return [];
        }
        $dekodiert = json_decode($roh, true);
        if (is_array($dekodiert)) {
            return $dekodiert;
        }
        return [['key' => '', 'name' => $roh]];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'bereich' => $this->bereich,
            'zielTyp' => $this->zielTyp,
            'zielRef' => $this->zielRef,
            'betragDelta' => $this->betragDelta,
            'prozentDelta' => $this->prozentDelta,
            'stellenDelta' => $this->stellenDelta,
            'betragProStelle' => $this->betragProStelle,
            'quelle' => $this->quelle,
            'herkunft' => $this->herkunft ?? 'eigene',
            'haltung' => $this->haltungOderStandard(),
            'unterstuetzer' => $this->getUnterstuetzerArray(),
            'pauschalAusnahme' => (bool) $this->pauschalAusnahme,
            'verknuepftMitId' => $this->verknuepftMitId,
            'antragsteller' => $this->antragsteller,
            'begruendung' => $this->begruendung,
            'verteilungId' => $this->verteilungId,
            'automatisch' => $this->verteilungId !== null,
            'reihenfolge' => $this->reihenfolge,
            'erstelltVon' => $this->erstelltVon,
            'phase' => $this->phase ?? 'fraktion',
        ];
    }
}
