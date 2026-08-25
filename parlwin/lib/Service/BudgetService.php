<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetVerteilung;
use OCA\ParliamentWinterthur\Db\BudgetAntragEntscheidMapper;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\BudgetVerteilungMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserSession;

/**
 * Orchestriert das Budget-Modul: liefert die gefilterte Ansicht mit Summen,
 * verwaltet Anträge und rechnet die automatische Pauschalverteilung sowie die
 * Steuerfuss-Senkung neu. Die reine Rechenlogik liegt in BudgetRechnung.
 */
class BudgetService {
    private const CONFIG_BETRAG_PRO_STELLE = 'budget_betrag_pro_stelle';
    private const DEFAULT_BETRAG_PRO_STELLE = 200000;
    /** Objekttyp für die geteilten Notizen (F103), wie 'vorstoss' beim Vorstoss. */
    private const NOTIZ_OBJEKT_TYP = 'budget-antrag';

    public function __construct(
        private readonly BudgetJahrMapper $jahre,
        private readonly BudgetProduktegruppeMapper $gruppen,
        private readonly BudgetInvestitionMapper $investitionen,
        private readonly BudgetAntragMapper $antraege,
        private readonly BudgetVerteilungMapper $verteilungen,
        private readonly BudgetAntragEntscheidMapper $entscheide,
        private readonly IConfig $config,
        private readonly ITimeFactory $time,
        private readonly IUserSession $userSession,
        private readonly RealtimePublisherService $realtime,
        private readonly NotizService $notizService,
    ) {
    }

    // ── Notizen an Anträgen (F103): geteilter NotizService wie beim Vorstoss ────

    /** Alle Notizen eines Antrags (aktiv und gelöscht) — für die shared Komponente. */
    public function notizen(int $antragId): array {
        $this->antraege->findeAntrag($antragId);
        return $this->notizService->liste(self::NOTIZ_OBJEKT_TYP, $antragId);
    }

    public function notizHinzufuegen(int $antragId, string $text): array {
        $this->antraege->findeAntrag($antragId);
        return $this->notizService->hinzufuegen(self::NOTIZ_OBJEKT_TYP, $antragId, $text);
    }

    public function notizAktualisieren(int $antragId, int $aktionId, string $text): array {
        return $this->notizService->aktualisieren(self::NOTIZ_OBJEKT_TYP, $antragId, $aktionId, $text);
    }

    public function notizLoeschen(int $antragId, int $aktionId): void {
        $this->notizService->loeschen(self::NOTIZ_OBJEKT_TYP, $antragId, $aktionId);
    }

    public function notizWiederherstellen(int $antragId, int $aktionId): array {
        return $this->notizService->wiederherstellen(self::NOTIZ_OBJEKT_TYP, $antragId, $aktionId);
    }

    public function notizRevisionen(int $antragId, int $aktionId): array {
        return $this->notizService->revisionen(self::NOTIZ_OBJEKT_TYP, $antragId, $aktionId);
    }

    /** Konfigurierter Standardbetrag pro Stelle (F86), initial 200'000 CHF. */
    public function standardBetragProStelle(): int {
        $wert = (int) $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_BETRAG_PRO_STELLE,
            (string) self::DEFAULT_BETRAG_PRO_STELLE
        );
        return $wert > 0 ? $wert : self::DEFAULT_BETRAG_PRO_STELLE;
    }

    /** Vorhandene Budgetjahre (neuestes zuerst) für das Auswahlmenü (F75). */
    public function jahre(): array {
        return array_map(static fn ($j) => $j->jsonSerialize(), $this->jahre->alle());
    }

    /**
     * Vollständige Ansicht eines Budgetjahres, gefiltert nach Departement und
     * Kostensteigerung (F76/F77). Enthält Produktegruppen, Investitionen,
     * Anträge (mit Entscheid-Status) und die Summenzeile (F79).
     *
     * @return array<string, mixed>
     */
    public function ansicht(int $jahr, ?string $departement = null, ?float $minProzent = null, ?int $minAbsolut = null, ?string $kommission = null, string $phase = 'fraktion'): array {
        $phase = $phase === 'sitzung' ? 'sitzung' : 'fraktion';
        $jahrRow = $this->jahre->findByJahr($jahr);
        $zuordnung = $this->kommissionZuordnung();
        // Departement- ODER Kommission-Filter (F76): eine gewählte Kommission wird
        // über die Zuordnung auf ihre Departemente aufgelöst.
        $erlaubteDepts = $this->erlaubteDepartemente($departement, $kommission, $zuordnung);
        $alleGruppen = $this->gruppen->findByJahr($jahr);
        $gefiltert = array_values(array_filter(
            $alleGruppen,
            fn ($g) => $this->trifftFilter($g, $erlaubteDepts, $minProzent, $minAbsolut)
        ));

        $alleAntraege = $this->antraege->findByJahr($jahr);
        $antragIds = array_map(static fn ($a) => (int) $a->getId(), $alleAntraege);
        $status = $this->entscheide->statusFuer($antragIds);
        // Notizen je Antrag über den geteilten Dienst (F103).
        $notizen = $this->notizService->listeGruppiert('budget-antrag', $antragIds);
        $notizen = is_array($notizen) ? $notizen : [];
        $codesGefiltert = array_map(static fn ($g) => (string) $g->getCode(), $gefiltert);
        $ohneFilter = $erlaubteDepts === null;

        // Anträge, die in die (gefilterte) Summe einfliessen. Nur Anträge der
        // betrachteten Phase (F93): in der Vorbereitung alle Fraktionsanträge, in
        // der Sitzung nur die ANGENOMMENEN offiziellen Sitzungsanträge — dann zählt
        // nur, was in der Debatte beschlossen wurde.
        $summenAntraege = [];
        foreach ($alleAntraege as $a) {
            if (((string) ($a->getPhase() ?? 'fraktion')) !== $phase) {
                continue;
            }
            // In der Sitzung zählt nur das vom Parlament Angenommene (F93); in der
            // Vorbereitung nur, was die Fraktion unterstützt (F102).
            if ($phase === 'sitzung') {
                if (($status[(int) $a->getId()] ?? 'offen') !== 'angenommen') {
                    continue;
                }
            } elseif (!$a->wirdUnterstuetzt()) {
                continue;
            }
            $bereich = (string) $a->getBereich();
            if ($bereich === 'steuerfuss') {
                if ($ohneFilter) {
                    $summenAntraege[] = $this->antragFuerRechnung($a);
                }
                continue;
            }
            if ($bereich === 'investition') {
                continue;
            }
            if ($ohneFilter || in_array((string) $a->getZielRef(), $codesGefiltert, true)) {
                $summenAntraege[] = $this->antragFuerRechnung($a);
            }
        }

        $summen = BudgetRechnung::summen($this->gruppenFuerRechnung($gefiltert), $summenAntraege);

        return [
            'jahr' => $jahrRow->jsonSerialize(),
            'departemente' => $this->departementListe($alleGruppen),
            'produktegruppen' => array_map(static fn ($g) => $g->jsonSerialize(), $gefiltert),
            'investitionen' => array_map(
                static fn ($i) => $i->jsonSerialize(),
                array_values(array_filter(
                    $this->investitionen->findByJahr($jahr),
                    static fn ($i) => $erlaubteDepts === null || in_array((string) $i->getDepartement(), $erlaubteDepts, true)
                ))
            ),
            'antraege' => array_map(function ($a) use ($status, $notizen) {
                $d = $a->jsonSerialize();
                $d['entscheid'] = $status[(int) $a->getId()] ?? 'offen';
                $d['aktionen'] = $notizen[(int) $a->getId()] ?? [];
                return $d;
            }, $alleAntraege),
            'verteilung' => $this->verteilungen->findeOderStandard($jahr)->jsonSerialize(),
            'pauschalantraege' => array_map(
                static fn ($v) => $v->jsonSerialize(),
                array_values(array_filter($this->verteilungen->alleFuerJahr($jahr), static fn ($v) => $v->modusOderStandard() === 'fest'))
            ),
            'summen' => $summen,
            'standardBetragProStelle' => $this->standardBetragProStelle(),
            'kommissionZuordnung' => $zuordnung,
        ];
    }

    /** Departement→Kommission-Zuordnung aus der Verwaltungseinstellung (F76). */
    private function kommissionZuordnung(): array {
        $raw = $this->config->getAppValue(Application::APP_ID, 'budget_kommission_zuordnung', '[]');
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * Auf welche Departemente die Ansicht eingeschränkt ist: ein gewähltes
     * Departement direkt, eine gewählte Kommission über die Zuordnung, sonst
     * null (keine Einschränkung).
     *
     * @return string[]|null
     */
    private function erlaubteDepartemente(?string $departement, ?string $kommission, array $zuordnung): ?array {
        if ($departement !== null && $departement !== '') {
            return [$departement];
        }
        if ($kommission !== null && $kommission !== '') {
            $depts = [];
            foreach ($zuordnung as $z) {
                if (is_array($z) && (string) ($z['kommission'] ?? '') === $kommission) {
                    $depts[] = (string) ($z['departement'] ?? '');
                }
            }
            return $depts;
        }
        return null;
    }

    /** @return array<int, array<string, float|int|string>> */
    private function gruppenFuerRechnung(array $gruppen): array {
        return array_map(static fn ($g) => [
            'code' => (string) $g->getCode(),
            'aufwandSoll' => (int) $g->getAufwandSoll(),
            'aufwandVorjahr' => (int) $g->getAufwandSollVorjahr(),
            'ertragSoll' => (int) $g->getErtragSoll(),
            'ertragVorjahr' => (int) $g->getErtragSollVorjahr(),
            'stellenSoll' => (float) $g->getStellenSoll(),
            'stellenVorjahr' => (float) $g->getStellenSollVorjahr(),
        ], $gruppen);
    }

    /** @return array<string, float|int|string> */
    private function antragFuerRechnung(BudgetAntrag $a): array {
        return [
            'bereich' => (string) $a->getBereich(),
            'zielRef' => (string) $a->getZielRef(),
            'betragDelta' => (int) $a->getBetragDelta(),
            'stellenDelta' => (float) $a->getStellenDelta(),
        ];
    }

    private function trifftFilter($g, ?array $erlaubteDepts, ?float $minProzent, ?int $minAbsolut): bool {
        if ($erlaubteDepts !== null && !in_array((string) $g->getDepartement(), $erlaubteDepts, true)) {
            return false;
        }
        $vorjahr = (int) $g->getAufwandSollVorjahr();
        $soll = (int) $g->getAufwandSoll();
        $absolut = $soll - $vorjahr;
        if ($minAbsolut !== null && $absolut < $minAbsolut) {
            return false;
        }
        if ($minProzent !== null) {
            $prozent = $vorjahr > 0 ? ($absolut / $vorjahr) * 100 : 0.0;
            if ($prozent < $minProzent) {
                return false;
            }
        }
        return true;
    }

    /** @return string[] */
    private function departementListe(array $gruppen): array {
        $seen = [];
        foreach ($gruppen as $g) {
            $d = (string) $g->getDepartement();
            if ($d !== '' && !in_array($d, $seen, true)) {
                $seen[] = $d;
            }
        }
        return $seen;
    }

    // ── Anträge ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $daten */
    public function antragErstellen(int $jahr, array $daten): BudgetAntrag {
        $a = new BudgetAntrag();
        $a->setJahr($jahr);
        $a->setBereich((string) ($daten['bereich'] ?? 'globalbudget'));
        $a->setZielTyp((string) ($daten['zielTyp'] ?? 'produktegruppe'));
        $a->setZielRef((string) ($daten['zielRef'] ?? ''));
        $a->setStellenDelta((float) ($daten['stellenDelta'] ?? 0));
        $proStelle = (int) ($daten['betragProStelle'] ?? 0);
        if ($a->getBereich() === 'personal' && $proStelle <= 0) {
            $proStelle = $this->standardBetragProStelle();
        }
        $a->setBetragProStelle($proStelle);
        $a->setQuelle((string) ($daten['quelle'] ?? 'manuell'));
        // Herkunft «eigene»/«fremde» und Standard-Haltung nach Herkunft (F94/F97).
        $herkunft = (string) ($daten['herkunft'] ?? 'eigene');
        $a->setHerkunft($herkunft === 'fremde' ? 'fremde' : 'eigene');
        // Betrag: CHF und Prozent (F95), Steuerfuss in Prozentpunkten (F96).
        $this->betragSetzen($a, $daten, $jahr, $proStelle);
        $this->felderSetzen($a, $daten);
        // Phase (F93): «fraktion» (Vorbereitung) oder «sitzung» (offizielle Sitzungsanträge).
        $phase = (string) ($daten['phase'] ?? 'fraktion');
        $a->setPhase($phase === 'sitzung' ? 'sitzung' : 'fraktion');
        $a->setReihenfolge((int) ($daten['reihenfolge'] ?? $this->naechsteReihenfolge($jahr)));
        $a->setErstelltVon($this->aktuellerNutzer());
        $a->setErstelltAm($this->time->getTime());
        $gespeichert = $this->antraege->insert($a);
        $this->nachAenderung($jahr);
        return $gespeichert;
    }

    /**
     * Setzt CHF- und Prozent-Betrag konsistent (F95/F96). Fehlt einer der beiden,
     * wird er aus dem anderen und der Bezugsbasis berechnet:
     *  - Steuerfuss: prozentDelta sind Prozentpunkte, CHF folgt aus
     *    Ertrag × Prozentpunkte / geltender Steuerfuss (F96).
     *  - sonst: Prozent bezieht sich auf den Budgetwert der Position (F95);
     *    Personal ohne CHF ergibt Stellen × Betrag pro Stelle.
     *
     * @param array<string, mixed> $daten
     */
    private function betragSetzen(BudgetAntrag $a, array $daten, int $jahr, int $proStelle): void {
        $hatChf = array_key_exists('betragDelta', $daten);
        $hatProzent = array_key_exists('prozentDelta', $daten);
        $chf = $hatChf ? (int) $daten['betragDelta'] : 0;
        $prozent = $hatProzent ? (float) $daten['prozentDelta'] : 0.0;

        if ($a->getBereich() === 'steuerfuss') {
            // Prozentpunkte sind führend; CHF ist der abgeleitete Ertrag-Effekt.
            $jahrRow = $this->jahre->findByJahr($jahr);
            $fuss = (int) $jahrRow->getSteuerfuss();
            $ertrag = (int) $jahrRow->getSteuerertrag();
            if (!$hatProzent && $hatChf && $ertrag !== 0) {
                $prozent = $fuss > 0 ? ($chf * $fuss / $ertrag) : 0.0;
            }
            $chf = $fuss > 0 ? (int) round($ertrag * $prozent / $fuss) : 0;
            $a->setProzentDelta($prozent);
            $a->setBetragDelta($chf);
            return;
        }

        $basis = $this->basisFuerPosition($a->getBereich(), (string) $a->getZielRef(), $jahr);
        if (!$hatChf && $a->getBereich() === 'personal') {
            $chf = (int) round($a->getStellenDelta() * $proStelle);
        } elseif (!$hatChf && $hatProzent && $basis > 0) {
            $chf = (int) round($basis * $prozent / 100);
        }
        if (!$hatProzent && $basis > 0) {
            $prozent = $chf / $basis * 100;
        }
        $a->setBetragDelta($chf);
        $a->setProzentDelta($prozent);
    }

    /**
     * Budgetwert einer Antragsposition als Basis für die Prozentrechnung (F95):
     * Globalkredit-Soll der Produktegruppe bzw. Budgetwert des Investitionsprojekts.
     */
    private function basisFuerPosition(?string $bereich, string $zielRef, int $jahr): int {
        if ($bereich === 'investition') {
            foreach ($this->investitionen->findByJahr($jahr) as $i) {
                if ((string) $i->getId() === $zielRef) {
                    return (int) $i->getBu();
                }
            }
            return 0;
        }
        foreach ($this->gruppen->findByJahr($jahr) as $g) {
            if ((string) $g->getCode() === $zielRef) {
                return (int) $g->getGlobalkreditSoll();
            }
        }
        return 0;
    }

    /**
     * Setzt die gemeinsamen, nicht-betragsbezogenen Felder aus dem Antrag: Haltung
     * (F97), unterstützende Fraktionen (F98), Antragsteller/Begründung, Pauschal-
     * Ausnahme (F101).
     *
     * @param array<string, mixed> $daten
     */
    private function felderSetzen(BudgetAntrag $a, array $daten): void {
        if (array_key_exists('haltung', $daten)) {
            $a->setHaltung($this->haltungBereinigt((string) $daten['haltung'], (string) $a->getHerkunft()));
        }
        if (array_key_exists('unterstuetzer', $daten)) {
            $a->setUnterstuetzer((string) json_encode($this->listeBereinigt($daten['unterstuetzer'])));
        }
        if (array_key_exists('antragsteller', $daten)) {
            $a->setAntragsteller((string) $daten['antragsteller']);
        }
        if (array_key_exists('begruendung', $daten)) {
            $a->setBegruendung((string) $daten['begruendung']);
        }
        if (array_key_exists('pauschalAusnahme', $daten)) {
            $a->setPauschalAusnahme($daten['pauschalAusnahme'] ? 1 : 0);
        }
    }

    /** Erlaubte Haltung je nach Herkunft (F97); sonst der Herkunfts-Standard. */
    private function haltungBereinigt(string $haltung, string $herkunft): string {
        $eigene = ['einreichen', 'nicht_einreichen'];
        $fremde = ['unterstuetzen', 'nicht_unterstuetzen', 'offen'];
        $erlaubt = $herkunft === 'fremde' ? $fremde : $eigene;
        if (in_array($haltung, $erlaubt, true)) {
            return $haltung;
        }
        return $herkunft === 'fremde' ? 'offen' : 'einreichen';
    }

    /**
     * Normalisiert eine Fraktionsliste zu [{key, name}] (F98). Akzeptiert Strings
     * oder {key?, name} und lässt leere Einträge weg.
     *
     * @param mixed $roh
     * @return list<array{key:string,name:string}>
     */
    private function listeBereinigt($roh): array {
        if (!is_array($roh)) {
            return [];
        }
        $out = [];
        foreach ($roh as $e) {
            if (is_string($e)) {
                $name = trim($e);
                if ($name !== '') {
                    $out[] = ['key' => $name, 'name' => $name];
                }
            } elseif (is_array($e)) {
                $name = trim((string) ($e['name'] ?? $e['value'] ?? ''));
                if ($name !== '') {
                    $out[] = ['key' => (string) ($e['key'] ?? $e['value'] ?? $name), 'name' => $name];
                }
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $daten */
    public function antragAendern(int $id, array $daten): BudgetAntrag {
        $a = $this->antraege->findeAntrag($id);
        $jahr = (int) $a->getJahr();
        if (array_key_exists('herkunft', $daten)) {
            $h = (string) $daten['herkunft'];
            $a->setHerkunft($h === 'fremde' ? 'fremde' : 'eigene');
        }
        if (array_key_exists('stellenDelta', $daten)) {
            $a->setStellenDelta((float) $daten['stellenDelta']);
        }
        if (array_key_exists('betragDelta', $daten) || array_key_exists('prozentDelta', $daten)) {
            $this->betragSetzen($a, $daten, $jahr, (int) $a->getBetragProStelle());
        }
        $this->felderSetzen($a, $daten);
        $gespeichert = $this->antraege->update($a);
        $this->nachAenderung($jahr);
        return $gespeichert;
    }

    public function antragLoeschen(int $id): void {
        $a = $this->antraege->findeAntrag($id);
        $jahr = (int) $a->getJahr();
        $this->antraege->delete($a);
        $this->nachAenderung($jahr);
    }

    // ── Verteilung / Steuerfuss ──────────────────────────────────────────────

    /**
     * @param list<string> $ausnahmen ausgenommene Positionen (zielRef), F101
     */
    public function verteilungSetzen(int $jahr, bool $automatik, string $modus, int $betrag, string $haltung = 'einreichen', array $ausnahmen = []): void {
        $v = $this->verteilungen->findeOderStandard($jahr);
        $v->setAutomatikEin($automatik ? 1 : 0);
        $v->setZielModus($modus);
        $v->setZielBetrag($betrag);
        $v->setHaltung($haltung === 'nicht_einreichen' ? 'nicht_einreichen' : 'einreichen');
        $v->setAusnahmen((string) json_encode(array_values(array_map(static fn ($x) => (string) $x, $ausnahmen))));
        $this->verteilungen->update($v);
        $this->nachAenderung($jahr);
    }

    /**
     * Rechnet nach jeder Änderung die automatische Pauschalverteilung und die
     * Steuerfuss-Senkung neu (idempotent), publiziert ein Realtime-Event.
     */
    private function nachAenderung(int $jahr): void {
        $this->pauschalNeu($jahr);
        $this->verknuepfungenAktualisieren($jahr);
        $this->realtime->publish('budget.updated', ['jahr' => $jahr]);
    }

    /**
     * Verknüpft Vorbereitungs- und Sitzungsanträge automatisch, wo es eindeutig
     * ist (F104): gleiche Position und gleicher CHF-Betrag, und auf beiden Seiten
     * je genau ein noch freier Kandidat. Beim Verknüpfen übernimmt der
     * Sitzungsantrag unsere Haltung aus dem Vorbereitungsantrag. Mehrdeutiges
     * bleibt unverknüpft; eine bereits (manuell) gesetzte Verknüpfung bleibt.
     */
    private function verknuepfungenAktualisieren(int $jahr): void {
        $alle = $this->antraege->findByJahr($jahr);
        $fraktion = array_values(array_filter($alle, static fn ($a) => ((string) ($a->getPhase() ?? 'fraktion')) === 'fraktion'));
        $sitzung = array_values(array_filter($alle, static fn ($a) => ((string) $a->getPhase()) === 'sitzung'));
        // Bereits belegte Fraktionsanträge (Gegenrichtung) nicht doppelt verknüpfen.
        $belegt = [];
        foreach ($sitzung as $s) {
            if ((int) $s->getVerknuepftMitId() > 0) {
                $belegt[(int) $s->getVerknuepftMitId()] = true;
            }
        }
        foreach ($sitzung as $s) {
            if ((int) $s->getVerknuepftMitId() > 0) {
                continue; // schon verknüpft (automatisch oder manuell)
            }
            $treffer = array_values(array_filter($fraktion, static fn ($f) =>
                (string) $f->getBereich() === (string) $s->getBereich()
                && (string) $f->getZielRef() === (string) $s->getZielRef()
                && (int) $f->getBetragDelta() === (int) $s->getBetragDelta()
                && (int) $f->getVerknuepftMitId() === 0
                && !isset($belegt[(int) $f->getId()])));
            if (count($treffer) !== 1) {
                continue; // nicht eindeutig → nicht verknüpfen
            }
            $f = $treffer[0];
            $s->setVerknuepftMitId((int) $f->getId());
            $s->setHaltung($f->haltungOderStandard());
            $f->setVerknuepftMitId((int) $s->getId());
            $this->antraege->update($s);
            $this->antraege->update($f);
            $belegt[(int) $f->getId()] = true;
        }
    }

    /**
     * Setzt oder löst eine Verknüpfung von Hand (F104). $zielId = 0 löst die
     * bestehende Verknüpfung beider Seiten.
     */
    public function verknuepfungSetzen(int $antragId, int $zielId): void {
        $a = $this->antraege->findeAntrag($antragId);
        // Alte Gegenseite lösen.
        $alt = (int) $a->getVerknuepftMitId();
        if ($alt > 0) {
            try {
                $altRow = $this->antraege->findeAntrag($alt);
                $altRow->setVerknuepftMitId(0);
                $this->antraege->update($altRow);
            } catch (\OCP\AppFramework\Db\DoesNotExistException) {
                // Gegenseite bereits weg — nichts zu lösen.
            }
        }
        $a->setVerknuepftMitId($zielId);
        $this->antraege->update($a);
        if ($zielId > 0) {
            $ziel = $this->antraege->findeAntrag($zielId);
            $ziel->setVerknuepftMitId($antragId);
            // Der Sitzungsantrag übernimmt die Haltung des Vorbereitungsantrags.
            if (((string) $ziel->getPhase()) === 'sitzung' && ((string) ($a->getPhase() ?? 'fraktion')) === 'fraktion') {
                $ziel->setHaltung($a->haltungOderStandard());
            } elseif (((string) ($a->getPhase() ?? 'fraktion')) === 'sitzung' && ((string) $ziel->getPhase()) === 'fraktion') {
                $a->setHaltung($ziel->haltungOderStandard());
                $this->antraege->update($a);
            }
            $this->antraege->update($ziel);
        }
        $this->realtime->publish('budget.updated', ['jahr' => (int) $a->getJahr()]);
    }

    /**
     * Rechnet ALLE Pauschalverteilungen des Jahres neu (F100/F101): erst die
     * festen (fester Betrag bzw. Prozent des ursprünglichen Aufwands), dann — auf
     * dem so bereits gekürzten Stand — die Ziel-Verteilungen (Ausgleich). Jede
     * erzeugte Kürzung folgt dem Einreichen-Entscheid ihrer Verteilung.
     */
    private function pauschalNeu(int $jahr): void {
        $this->antraege->deletePauschalByJahr($jahr);
        $verteilungen = $this->verteilungen->alleFuerJahr($jahr);
        $alleFuerRechnung = $this->gruppenFuerRechnung($this->gruppen->findByJahr($jahr));

        // Von der Fraktion unterstützte manuelle Anträge der Vorbereitung steuern
        // die Rechnung (F102); Sitzungsanträge (F93) bleiben aussen vor.
        $laufend = array_map(fn ($a) => $this->antragFuerRechnung($a), array_values(array_filter(
            $this->antraege->findByJahr($jahr),
            static fn ($a) => $a->getVerteilungId() === null
                && ((string) ($a->getPhase() ?? 'fraktion')) === 'fraktion'
                && $a->wirdUnterstuetzt()
        )));

        // Feste Pauschalverteilungen zuerst.
        foreach ($verteilungen as $v) {
            if ($v->modusOderStandard() !== 'fest') {
                continue;
            }
            $betrag = $this->festerBetrag($v, $alleFuerRechnung);
            if ($betrag !== 0) {
                $this->erzeugePauschalKinder($jahr, $v, $betrag, $alleFuerRechnung, $laufend);
            }
        }
        // Ziel-Verteilungen (Ausgleich) danach — auf dem bereits gekürzten Stand.
        foreach ($verteilungen as $v) {
            if ($v->modusOderStandard() !== 'ziel' || (int) $v->getAutomatikEin() !== 1) {
                continue;
            }
            $summen = BudgetRechnung::summen($alleFuerRechnung, $laufend);
            $delta = BudgetRechnung::benoetigterDelta((int) $summen['ergebnis'], (string) $v->getZielModus(), (int) $v->getZielBetrag());
            // Automatik verteilt nur Kürzungen (Defizit), nie Mehrausgaben (F84).
            if ($delta < 0) {
                $this->erzeugePauschalKinder($jahr, $v, $delta, $alleFuerRechnung, $laufend);
            }
        }
    }

    /**
     * Fester Verteilbetrag einer «fest»-Verteilung: aus Prozent (bezogen auf den
     * URSPRÜNGLICHEN Gesamt-Aufwand, nie auf den bereits gekürzten) oder aus dem
     * CHF-Betrag.
     *
     * @param array<int, array<string, float|int|string>> $gruppen
     */
    private function festerBetrag(BudgetVerteilung $v, array $gruppen): int {
        if ((float) $v->getProzent() !== 0.0) {
            $summeAufwand = 0;
            foreach ($gruppen as $g) {
                $summeAufwand += max(0, (int) ($g['aufwandSoll'] ?? 0));
            }
            return (int) round($summeAufwand * (float) $v->getProzent() / 100);
        }
        return (int) $v->getBetrag();
    }

    /**
     * Verteilt $betrag anteilig zum Aufwand auf die nicht ausgenommenen Positionen
     * (F101) und erzeugt je Position einen Pauschal-Antrag; die neuen Kürzungen
     * fliessen in $laufend ein, damit spätere Ziel-Verteilungen darauf aufbauen.
     *
     * @param array<int, array<string, float|int|string>> $alleFuerRechnung
     * @param array<int, array<string, float|int|string>> $laufend
     */
    private function erzeugePauschalKinder(int $jahr, BudgetVerteilung $v, int $betrag, array $alleFuerRechnung, array &$laufend): void {
        $ausnahmen = $v->getAusnahmenArray();
        $verteilbar = array_values(array_filter(
            $alleFuerRechnung,
            static fn ($g) => !in_array((string) $g['code'], $ausnahmen, true)
        ));
        $haltung = $v->haltungOderStandard();
        $herkunft = ((string) ($v->getHerkunft() ?? 'eigene')) === 'fremde' ? 'fremde' : 'eigene';
        $antragsteller = (string) ($v->getAntragsteller() ?? '');
        $begruendung = trim((string) ($v->getBegruendung() ?? '')) !== ''
            ? (string) $v->getBegruendung()
            : 'Pauschalkürzung (anteilig zum Aufwand)';
        $verteilung = BudgetRechnung::verteileAnteiligAufwand($betrag, $verteilbar);
        foreach ($verteilung as $code => $b) {
            if ($b === 0) {
                continue;
            }
            $a = new BudgetAntrag();
            $a->setJahr($jahr);
            $a->setBereich('globalbudget');
            $a->setZielTyp('produktegruppe');
            $a->setZielRef((string) $code);
            $a->setBetragDelta((int) $b);
            $a->setStellenDelta(0.0);
            $a->setBetragProStelle(0);
            $a->setQuelle('pauschal');
            $a->setHerkunft($herkunft);
            // F100: die erzeugten Einzelanträge folgen dem Einreichen-Entscheid
            // ihrer Pauschalverteilung.
            $a->setHaltung($haltung);
            $a->setAntragsteller($antragsteller);
            $a->setBegruendung($begruendung);
            $a->setVerteilungId((int) $v->getId());
            $a->setReihenfolge(9000);
            $a->setErstelltVon($this->aktuellerNutzer());
            $a->setErstelltAm($this->time->getTime());
            $this->antraege->insert($a);
            $laufend[] = ['bereich' => 'globalbudget', 'zielRef' => (string) $code, 'betragDelta' => (int) $b, 'stellenDelta' => 0.0];
        }
    }

    // ── Weitere Pauschalverteilungen (F100) ──────────────────────────────────

    /** @param array<string, mixed> $daten */
    public function pauschalErstellen(int $jahr, array $daten): BudgetVerteilung {
        $v = new BudgetVerteilung();
        $v->setJahr($jahr);
        $v->setModus('fest');
        $v->setAutomatikEin(1);
        $this->pauschalFelderSetzen($v, $daten);
        $v->setReihenfolge((int) ($daten['reihenfolge'] ?? $this->naechstePauschalReihenfolge($jahr)));
        $gespeichert = $this->verteilungen->insert($v);
        $this->nachAenderung($jahr);
        return $gespeichert;
    }

    /** @param array<string, mixed> $daten */
    public function pauschalAendern(int $id, array $daten): BudgetVerteilung {
        $v = $this->verteilungen->findeVerteilung($id);
        $this->pauschalFelderSetzen($v, $daten);
        $gespeichert = $this->verteilungen->update($v);
        $this->nachAenderung((int) $v->getJahr());
        return $gespeichert;
    }

    public function pauschalLoeschen(int $id): void {
        $v = $this->verteilungen->findeVerteilung($id);
        $jahr = (int) $v->getJahr();
        $this->antraege->deleteByVerteilung($id);
        $this->verteilungen->delete($v);
        $this->nachAenderung($jahr);
    }

    /** @param array<string, mixed> $daten */
    private function pauschalFelderSetzen(BudgetVerteilung $v, array $daten): void {
        if (array_key_exists('betrag', $daten)) {
            $v->setBetrag((int) $daten['betrag']);
        }
        if (array_key_exists('prozent', $daten)) {
            $v->setProzent((float) $daten['prozent']);
        }
        if (array_key_exists('haltung', $daten)) {
            $v->setHaltung(((string) $daten['haltung']) === 'nicht_einreichen' ? 'nicht_einreichen' : 'einreichen');
        }
        if (array_key_exists('herkunft', $daten)) {
            $v->setHerkunft(((string) $daten['herkunft']) === 'fremde' ? 'fremde' : 'eigene');
        }
        if (array_key_exists('antragsteller', $daten)) {
            $v->setAntragsteller((string) $daten['antragsteller']);
        }
        if (array_key_exists('begruendung', $daten)) {
            $v->setBegruendung((string) $daten['begruendung']);
        }
        if (array_key_exists('ausnahmen', $daten) && is_array($daten['ausnahmen'])) {
            $v->setAusnahmen((string) json_encode(array_values(array_map(static fn ($x) => (string) $x, $daten['ausnahmen']))));
        }
    }

    private function naechstePauschalReihenfolge(int $jahr): int {
        $max = 0;
        foreach ($this->verteilungen->alleFuerJahr($jahr) as $v) {
            $max = max($max, (int) $v->getReihenfolge());
        }
        return $max + 1;
    }

    public function entscheidSetzen(int $antragId, string $status): void {
        $a = $this->antraege->findeAntrag($antragId);
        $erlaubt = ['offen', 'angenommen', 'abgelehnt'];
        if (!in_array($status, $erlaubt, true)) {
            $status = 'offen';
        }
        $this->entscheide->setzeStatus($antragId, $status, $this->aktuellerNutzer(), $this->time->getTime());
        $this->realtime->publish('budget.updated', ['jahr' => (int) $a->getJahr()]);
    }

    private function naechsteReihenfolge(int $jahr): int {
        $max = 0;
        foreach ($this->antraege->findByJahr($jahr) as $a) {
            $max = max($max, (int) $a->getReihenfolge());
        }
        return $max + 1;
    }

    private function aktuellerNutzer(): string {
        $u = $this->userSession->getUser();
        return $u !== null ? $u->getUID() : '';
    }
}
