<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Eine Pauschalverteilung (Pauschalantrag) je Budgetjahr — es kann beliebig
 * viele geben (F100). Jede verteilt einen Betrag anteilig zum Aufwand auf die
 * Produktegruppen und erzeugt je Position einen Einzelantrag.
 *
 * - modus «ziel»:  automatischer Ausgleich auf zielModus/zielBetrag (schwarze
 *                  Null, akzeptiertes Defizit, gewünschter Ertrag). Rechnet sich
 *                  fortlaufend neu.
 * - modus «fest»:  ein fester Betrag (CHF) oder Prozentsatz des ursprünglichen
 *                  Aufwands wird verteilt.
 * - haltung:       Einreichen-Entscheid, vererbt sich auf die erzeugten Kinder.
 * - ausnahmen:     ausgenommene Positionen (zielRef); der Betrag verteilt sich
 *                  neu auf die übrigen (F101).
 *
 * @method int    getJahr()
 * @method void   setJahr(int $v)
 * @method int    getAutomatikEin()
 * @method void   setAutomatikEin(int $v)
 * @method string getModus()
 * @method void   setModus(?string $v)
 * @method string getZielModus()
 * @method void   setZielModus(string $v)
 * @method int    getZielBetrag()
 * @method void   setZielBetrag(int $v)
 * @method int    getBetrag()
 * @method void   setBetrag(int $v)
 * @method float  getProzent()
 * @method void   setProzent(float $v)
 * @method string getHaltung()
 * @method void   setHaltung(?string $v)
 * @method string getHerkunft()
 * @method void   setHerkunft(?string $v)
 * @method string getAntragsteller()
 * @method void   setAntragsteller(?string $v)
 * @method string getBegruendung()
 * @method void   setBegruendung(?string $v)
 * @method string getAusnahmen()
 * @method void   setAusnahmen(?string $v)
 * @method int    getReihenfolge()
 * @method void   setReihenfolge(int $v)
 */
class BudgetVerteilung extends Entity implements JsonSerializable {
    protected int $jahr = 0;
    protected int $automatikEin = 1;
    /** «ziel» (Ausgleich auf ein Ziel) oder «fest» (fester Betrag/Prozentsatz). */
    protected ?string $modus = 'ziel';
    protected string $zielModus = 'schwarze_null';
    protected int $zielBetrag = 0;
    /** Fest zu verteilender Betrag (modus «fest»). */
    protected int $betrag = 0;
    /** Fest zu verteilender Prozentsatz des ursprünglichen Aufwands (modus «fest»). */
    protected float $prozent = 0.0;
    /** Einreichen-Entscheid des Pauschalantrags (F100). */
    protected ?string $haltung = 'einreichen';
    protected ?string $herkunft = 'eigene';
    protected ?string $antragsteller = '';
    protected ?string $begruendung = '';
    /** JSON-Liste der ausgenommenen Positionen (zielRef), F101. */
    protected ?string $ausnahmen = '[]';
    protected int $reihenfolge = 0;

    public function __construct() {
        foreach (['jahr', 'automatikEin', 'zielBetrag', 'betrag', 'reihenfolge'] as $f) {
            $this->addType($f, 'integer');
        }
        $this->addType('prozent', 'float');
    }

    /** @return list<string> Ausgenommene Positionen (zielRef). */
    public function getAusnahmenArray(): array {
        $dekodiert = json_decode($this->ausnahmen ?? '[]', true);
        if (!is_array($dekodiert)) {
            return [];
        }
        return array_values(array_map(static fn ($x) => (string) $x, $dekodiert));
    }

    /** Standard-Haltung: einreichen, wenn nichts gesetzt. */
    public function haltungOderStandard(): string {
        $h = trim((string) ($this->haltung ?? ''));
        return $h !== '' ? $h : 'einreichen';
    }

    public function modusOderStandard(): string {
        return ((string) ($this->modus ?? 'ziel')) === 'fest' ? 'fest' : 'ziel';
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'jahr' => $this->jahr,
            'automatikEin' => (bool) $this->automatikEin,
            'modus' => $this->modusOderStandard(),
            'zielModus' => $this->zielModus,
            'zielBetrag' => $this->zielBetrag,
            'betrag' => $this->betrag,
            'prozent' => $this->prozent,
            'haltung' => $this->haltungOderStandard(),
            'herkunft' => $this->herkunft ?? 'eigene',
            'antragsteller' => $this->antragsteller ?? '',
            'begruendung' => $this->begruendung ?? '',
            'ausnahmen' => $this->getAusnahmenArray(),
            'reihenfolge' => $this->reihenfolge,
        ];
    }
}
