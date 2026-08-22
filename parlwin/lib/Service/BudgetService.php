<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\BudgetAntrag;
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
    ) {
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
        $status = $this->entscheide->statusFuer(array_map(static fn ($a) => (int) $a->getId(), $alleAntraege));
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
            if ($phase === 'sitzung' && (($status[(int) $a->getId()] ?? 'offen') !== 'angenommen')) {
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
            'antraege' => array_map(function ($a) use ($status) {
                $d = $a->jsonSerialize();
                $d['entscheid'] = $status[(int) $a->getId()] ?? 'offen';
                return $d;
            }, $alleAntraege),
            'verteilung' => $this->verteilungen->findeOderStandard($jahr)->jsonSerialize(),
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
        // Bei Personalanträgen ergibt sich der Betrag aus Stellen × Betrag pro Stelle,
        // falls nicht ausdrücklich angegeben.
        $betrag = array_key_exists('betragDelta', $daten)
            ? (int) $daten['betragDelta']
            : ($a->getBereich() === 'personal' ? (int) round($a->getStellenDelta() * $proStelle) : 0);
        $a->setBetragDelta($betrag);
        $a->setQuelle((string) ($daten['quelle'] ?? 'manuell'));
        $a->setAntragsteller((string) ($daten['antragsteller'] ?? ''));
        $a->setBegruendung((string) ($daten['begruendung'] ?? ''));
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

    /** @param array<string, mixed> $daten */
    public function antragAendern(int $id, array $daten): BudgetAntrag {
        $a = $this->antraege->findeAntrag($id);
        if (array_key_exists('betragDelta', $daten)) {
            $a->setBetragDelta((int) $daten['betragDelta']);
        }
        if (array_key_exists('stellenDelta', $daten)) {
            $a->setStellenDelta((float) $daten['stellenDelta']);
        }
        if (array_key_exists('begruendung', $daten)) {
            $a->setBegruendung((string) $daten['begruendung']);
        }
        if (array_key_exists('antragsteller', $daten)) {
            $a->setAntragsteller((string) $daten['antragsteller']);
        }
        $gespeichert = $this->antraege->update($a);
        $this->nachAenderung((int) $a->getJahr());
        return $gespeichert;
    }

    public function antragLoeschen(int $id): void {
        $a = $this->antraege->findeAntrag($id);
        $jahr = (int) $a->getJahr();
        $this->antraege->delete($a);
        $this->nachAenderung($jahr);
    }

    // ── Verteilung / Steuerfuss ──────────────────────────────────────────────

    public function verteilungSetzen(int $jahr, bool $automatik, string $modus, int $betrag): void {
        $v = $this->verteilungen->findeOderStandard($jahr);
        $v->setAutomatikEin($automatik ? 1 : 0);
        $v->setZielModus($modus);
        $v->setZielBetrag($betrag);
        $this->verteilungen->update($v);
        $this->nachAenderung($jahr);
    }

    /**
     * Rechnet nach jeder Änderung die automatische Pauschalverteilung und die
     * Steuerfuss-Senkung neu (idempotent), publiziert ein Realtime-Event.
     */
    private function nachAenderung(int $jahr): void {
        $v = $this->verteilungen->findeOderStandard($jahr);
        if ((int) $v->getAutomatikEin() === 1) {
            $this->pauschalNeu($jahr, $v);
        }
        $this->realtime->publish('budget.updated', ['jahr' => $jahr]);
    }

    private function pauschalNeu(int $jahr, $v): void {
        // Alte automatische Anträge dieser Verteilung entfernen.
        $this->antraege->deleteByVerteilung((int) $v->getId());

        $gruppen = $this->gruppen->findByJahr($jahr);
        // Nur manuelle Anträge der Vorbereitungsphase steuern die Automatik;
        // offizielle Sitzungsanträge (F93) bleiben aussen vor.
        $manuelle = array_values(array_filter(
            $this->antraege->findByJahr($jahr),
            static fn ($a) => $a->getVerteilungId() === null && ((string) ($a->getPhase() ?? 'fraktion')) === 'fraktion'
        ));
        $summenAntraege = array_map(fn ($a) => $this->antragFuerRechnung($a), $manuelle);
        $summen = BudgetRechnung::summen($this->gruppenFuerRechnung($gruppen), $summenAntraege);

        $delta = BudgetRechnung::benoetigterDelta((int) $summen['ergebnis'], (string) $v->getZielModus(), (int) $v->getZielBetrag());
        // Automatik verteilt nur Kürzungen (Defizit), nie Mehrausgaben (F84).
        if ($delta < 0) {
            $verteilung = BudgetRechnung::verteileAnteiligAufwand($delta, $this->gruppenFuerRechnung($gruppen));
            foreach ($verteilung as $code => $betrag) {
                if ($betrag === 0) {
                    continue;
                }
                $a = new BudgetAntrag();
                $a->setJahr($jahr);
                $a->setBereich('globalbudget');
                $a->setZielTyp('produktegruppe');
                $a->setZielRef((string) $code);
                $a->setBetragDelta((int) $betrag);
                $a->setStellenDelta(0.0);
                $a->setBetragProStelle(0);
                $a->setQuelle('pauschal');
                $a->setAntragsteller('');
                $a->setBegruendung('Automatische Pauschalkürzung (anteilig zum Aufwand)');
                $a->setVerteilungId((int) $v->getId());
                $a->setReihenfolge(9000);
                $a->setErstelltVon($this->aktuellerNutzer());
                $a->setErstelltAm($this->time->getTime());
                $this->antraege->insert($a);
            }
        }
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
