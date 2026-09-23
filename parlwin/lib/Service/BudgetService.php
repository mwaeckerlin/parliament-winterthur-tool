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
    /** Pro Jahr gespeichert: ist die automatische Steuerfuss-Senkung an (F88)? Standard: ja. */
    private const CONFIG_STEUERFUSS_AUTOMATIK = 'budget_steuerfuss_automatik';
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

        // Die Summe kommt aus allen gezeigten Produktegruppen inklusive der
        // künstlichen (F89); ungefiltert ergibt das exakt das deklarierte Total.
        $gruppenRechnung = $this->gruppenFuerRechnung($gefiltert);
        $summen = BudgetRechnung::summen($gruppenRechnung, $summenAntraege);
        // Stadtratsbudget (F102): die Zahlen, wie der Stadtrat sie vorgelegt hat —
        // ohne unsere Anträge —, für den Vergleich mit dem Fraktionsbudget (mit Anträgen).
        $summenStadtrat = BudgetRechnung::summen($gruppenRechnung, []);

        return [
            // Der beantragte Steuerfuss ist der geparste Wert; das Vorjahr (falls
            // importiert) erlaubt die Differenz-Anzeige im Steuerfuss-Tab (F88).
            'jahr' => array_merge($jahrRow->jsonSerialize(), [
                'steuerfussVorjahr' => $this->steuerfussVorjahr($jahr),
                'steuerfussAutomatik' => $this->steuerfussAutomatikAn($jahr),
            ]),
            'departemente' => $this->departementListe($alleGruppen),
            'produktegruppen' => $this->gruppenMitFraktionswert($gefiltert, $summenAntraege),
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
            // Alle Pauschalanträge (F100) — die Liste startet leer, es gibt keinen
            // automatisch angelegten Standard mehr. Jeder trägt seinen Ziel-Typ.
            'pauschalantraege' => array_map(
                static fn ($v) => $v->jsonSerialize(),
                $this->verteilungen->alleFuerJahr($jahr)
            ),
            'summen' => $summen,
            'summenStadtrat' => $summenStadtrat,
            'standardBetragProStelle' => $this->standardBetragProStelle(),
            'kommissionZuordnung' => $zuordnung,
            // Die eigene Fraktion aus der Konfiguration (dieselbe Quelle wie das PDF,
            // F94): Antragsteller-Felder werden damit vorbelegt.
            'eigeneFraktion' => $this->eigeneFraktion(),
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
            'kuenstlich' => (int) $g->getKuenstlich(),
            'aufwandSoll' => (int) $g->getAufwandSoll(),
            'aufwandVorjahr' => (int) $g->getAufwandSollVorjahr(),
            'ertragSoll' => (int) $g->getErtragSoll(),
            'ertragVorjahr' => (int) $g->getErtragSollVorjahr(),
            'stellenSoll' => (float) $g->getStellenSoll(),
            'stellenVorjahr' => (float) $g->getStellenSollVorjahr(),
        ], $gruppen);
    }

    /** Steuerfuss des Vorjahres, falls importiert — sonst 0 (F88 Differenz-Anzeige). */
    private function steuerfussVorjahr(int $jahr): int {
        try {
            return (int) $this->jahre->findByJahr($jahr - 1)->getSteuerfuss();
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return 0;
        }
    }

    /** Ob die Produktegruppe mit diesem Code für das Jahr künstlich ist (F89). */
    private function istKuenstlicheGruppe(int $jahr, string $code): bool {
        if ($code === '') {
            return false;
        }
        foreach ($this->gruppen->findByJahr($jahr) as $g) {
            if ((string) $g->getCode() === $code) {
                return (int) $g->getKuenstlich() === 1;
            }
        }
        return false;
    }

    /**
     * Produktegruppen für die Ansicht, jede zusätzlich mit dem Wert der Fraktion
     * (F116): dem Betrag und den Stellen, die nach unseren Anträgen bleiben. Es
     * zählen dieselben Anträge wie in der Übersicht (F102) — in der Vorbereitung
     * die unterstützten, in der Sitzung die angenommenen —, weshalb hier die
     * bereits gefilterte Antragsliste der Summenzeile eingeht und keine zweite
     * Regel entsteht.
     *
     * @param list<BudgetProduktegruppe> $gruppen
     * @param list<array<string, float|int|string>> $summenAntraege
     * @return list<array<string, mixed>>
     */
    private function gruppenMitFraktionswert(array $gruppen, array $summenAntraege): array {
        $delta = [];
        foreach ($summenAntraege as $a) {
            $bereich = (string) ($a['bereich'] ?? '');
            // Beide Bereiche wirken auf den Globalkredit der Produktegruppe: ein
            // Personalantrag senkt neben den Stellen auch ihren Betrag.
            if ($bereich !== 'globalbudget' && $bereich !== 'personal') {
                continue;
            }
            $code = (string) ($a['zielRef'] ?? '');
            $delta[$code] ??= ['betrag' => 0, 'stellen' => 0.0];
            $delta[$code]['betrag'] += (int) ($a['betragDelta'] ?? 0);
            $delta[$code]['stellen'] += (float) ($a['stellenDelta'] ?? 0);
        }

        return array_map(static function ($g) use ($delta): array {
            $daten = $g->jsonSerialize();
            $eigen = $delta[(string) $g->getCode()] ?? ['betrag' => 0, 'stellen' => 0.0];
            $daten['globalkredit']['sollFraktion'] = (int) $daten['globalkredit']['soll'] + $eigen['betrag'];
            $daten['stellen']['sollFraktion'] = (float) $daten['stellen']['soll'] + $eigen['stellen'];
            return $daten;
        }, $gruppen);
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
        // «Anstieg» meint den Anstieg des Globalkredits (Nettokosten) — genau den
        // Wert, den die Karte neben dem Betrag zeigt (soll − sollVorjahr). Nicht den
        // Aufwand: der kann steigen, während der Globalkredit fällt (mehr Ertrag).
        $vorjahr = (int) $g->getGlobalkreditSollVorjahr();
        $soll = (int) $g->getGlobalkreditSoll();
        $absolut = $soll - $vorjahr;
        if ($minAbsolut !== null && $absolut < $minAbsolut) {
            return false;
        }
        if ($minProzent !== null) {
            // Prozentualer Anstieg relativ zum Betrag des Vorjahres (auch ein
            // negativer Globalkredit — Nettoertrag — hat einen sinnvollen Betrag).
            $basis = abs($vorjahr);
            $prozent = $basis > 0 ? ($absolut / $basis) * 100 : 0.0;
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
        // Künstliche Produktegruppen (F89) sind nicht antragbar — server-seitig gesperrt.
        $zielBereich = (string) ($daten['bereich'] ?? 'globalbudget');
        if (in_array($zielBereich, ['globalbudget', 'personal'], true)
            && $this->istKuenstlicheGruppe($jahr, (string) ($daten['zielRef'] ?? ''))) {
            throw new \RuntimeException('Auf eine künstliche Produktegruppe sind keine Anträge möglich');
        }
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
        // F109: fehlt oben ein Betrag, wird die Summe der Aufteilung eingesetzt.
        $this->aufteilungSummeAnwenden($a);
        // Phase (F93): «fraktion» (Vorbereitung) oder «sitzung» (offizielle
        // Sitzungsanträge). Sie steht VOR der Kürzungsprüfung, denn diese
        // vergleicht die Phase — mit einer noch leeren verglich sie ins Leere.
        $phase = (string) ($daten['phase'] ?? 'fraktion');
        $a->setPhase($phase === 'sitzung' ? 'sitzung' : 'fraktion');
        $this->kuerzungPruefen($a, $jahr, null);
        // Ein Steuerfussantrag ist ein Antrag der eigenen Fraktion (F88/F96): fehlt
        // der Antragsteller, tragen wir den eigenen Fraktionsnamen ein, damit er im
        // Antrags-PDF nicht leer bleibt.
        if ($a->getBereich() === 'steuerfuss' && trim((string) $a->getAntragsteller()) === '') {
            $a->setAntragsteller($this->eigeneFraktion());
        }
        $a->setReihenfolge((int) ($daten['reihenfolge'] ?? $this->naechsteReihenfolge($jahr)));
        $a->setErstelltVon($this->aktuellerNutzer());
        $a->setErstelltAm($this->time->getTime());
        $gespeichert = $this->antraege->insert($a);
        $this->nachAenderung($jahr);
        return $gespeichert;
    }

    /**
     * Übernimmt die aus dem Drehbuch der Budgetsitzung gelesenen Sitzungsanträge
     * (F90) in die Datenbank — als offizielle Sitzungsanträge (Phase «sitzung»,
     * Herkunft «fremde», Quelle «sitzung»). Bereits vorhandene, gleich lautende
     * Sitzungsanträge (gleiche Produktegruppe, Antragsteller und Betrag) werden
     * NICHT dupliziert; ein erneutes Einlesen ergänzt nur Neues und lässt von Hand
     * gesetzte Haltungen und Entscheide unberührt. Liefert die Zahl der neu
     * angelegten Anträge.
     *
     * @param list<array<string, mixed>> $geparst
     */
    public function sitzungsantraegeEinlesen(int $jahr, array $geparst): int {
        $vorhanden = [];
        foreach ($this->antraege->findByJahr($jahr) as $a) {
            if ((string) $a->getQuelle() === 'sitzung') {
                $vorhanden[$this->sitzungsantragSchluessel(
                    (string) $a->getZielRef(),
                    (string) $a->getAntragsteller(),
                    (int) $a->getBetragDelta()
                )] = true;
            }
        }
        $neu = 0;
        foreach ($geparst as $antrag) {
            $code = (string) ($antrag['code'] ?? '');
            $steller = (string) ($antrag['antragsteller'] ?? '');
            $betrag = (int) ($antrag['betragDelta'] ?? 0);
            if ($code === '' || $betrag === 0) {
                continue;
            }
            $schluessel = $this->sitzungsantragSchluessel($code, $steller, $betrag);
            if (isset($vorhanden[$schluessel])) {
                continue;
            }
            $vorhanden[$schluessel] = true;
            $ergebnis = trim((string) ($antrag['ergebnis'] ?? ''));
            $begruendung = trim((string) ($antrag['begruendung'] ?? ''));
            if ($ergebnis !== '') {
                $begruendung = trim($begruendung . ' (Kommission: ' . $ergebnis . ')');
            }
            $this->antragErstellen($jahr, [
                'bereich' => (string) ($antrag['bereich'] ?? 'globalbudget'),
                'zielTyp' => 'produktegruppe',
                'zielRef' => $code,
                'betragDelta' => $betrag,
                'quelle' => 'sitzung',
                'herkunft' => 'fremde',
                'phase' => 'sitzung',
                'antragsteller' => $steller,
                'begruendung' => $begruendung,
            ]);
            $neu++;
        }
        return $neu;
    }

    private function sitzungsantragSchluessel(string $code, string $antragsteller, int $betrag): string {
        return $code . '|' . mb_strtolower(trim($antragsteller)) . '|' . $betrag;
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
        // Betrag und Prozentsatz sind zwei Schreibweisen desselben Antrags und
        // dürfen einander nie widersprechen. Der CHF-Betrag ist führend: wo er
        // mitgeschickt wurde, folgt der Prozentsatz ihm, auch wenn zugleich ein
        // Prozentsatz ankommt — sonst bliebe nach einer Betragsänderung der alte
        // Prozentsatz stehen und machte sie beim nächsten Speichern rückgängig
        // (F117).
        if ($basis > 0 && ($hatChf || !$hatProzent)) {
            $prozent = $chf / $basis * 100;
        }
        $a->setBetragDelta($chf);
        $a->setProzentDelta($prozent);
    }

    /**
     * Ein Budget lässt sich höchstens auf null kürzen — was nicht ausgegeben wird,
     * kann nicht gespart werden. Die Grenze gilt für die SUMME aller Anträge auf
     * dasselbe Ziel: drei Anträge zu je 40% kürzen zusammen um 120% und sind damit
     * unmöglich, auch wenn jeder für sich passt. Nach oben gibt es keine Grenze.
     *
     * Geprüft wird beim Anlegen und beim Ändern; beim Ändern zählt der eigene
     * bisherige Betrag nicht mit (er wird ja ersetzt).
     */
    private function kuerzungPruefen(BudgetAntrag $a, int $jahr, ?int $eigeneId): void {
        $neu = (int) $a->getBetragDelta();
        if ($neu >= 0 || (string) $a->getBereich() === 'steuerfuss') {
            return;
        }
        // Gezählt wird nur, was ZUSAMMEN WIRKT: die eigenen Anträge und die
        // fremden, die die Fraktion unterstützt. In einer Sitzung stellen mehrere
        // Fraktionen Anträge auf dieselbe Produktegruppe; zusammengezählt kürzen
        // sie weit über das Budget hinaus, ohne dass davon je mehr als einer
        // angenommen würde. Ein fremder, offener Antrag hält nur fest, was im Rat
        // gestellt wurde — an ihm scheiterte das Einlesen des ganzen Drehbuchs.
        if (!$a->wirdUnterstuetzt()) {
            return;
        }
        $budget = $this->basisFuerPosition((string) $a->getBereich(), (string) $a->getZielRef(), $jahr);
        if ($budget <= 0) {
            return; // ohne bekannten Budgetwert gibt es keine Grenze zu prüfen
        }
        $bereits = 0;
        foreach ($this->antraege->findByJahr($jahr) as $vorhanden) {
            if ((int) $vorhanden->getId() === $eigeneId
                || !$vorhanden->wirdUnterstuetzt()
                || (string) $vorhanden->getBereich() !== (string) $a->getBereich()
                || (string) $vorhanden->getZielRef() !== (string) $a->getZielRef()
                || (string) $vorhanden->getPhase() !== (string) $a->getPhase()) {
                continue;
            }
            $bereits += min(0, (int) $vorhanden->getBetragDelta());
        }
        if ($bereits + $neu >= -$budget) {
            return;
        }
        throw new \RuntimeException(sprintf(
            'Es kann nicht mehr gekürzt werden, als budgetiert ist: %s stehen zur Verfügung, '
            . 'davon sind %s bereits beantragt, und dieser Antrag kürzt um weitere %s. '
            . 'Höchstens auf null — also noch %s.',
            $this->franken($budget),
            $this->franken(-$bereits),
            $this->franken(-$neu),
            $this->franken($budget + $bereits),
        ));
    }

    /** Ein Betrag in Schweizer Schreibweise, für Meldungen an den Nutzer. */
    private function franken(int $betrag): string {
        return number_format($betrag, 0, '.', "'") . ' CHF';
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
        if (array_key_exists('zielAenderungen', $daten)) {
            $a->setZielAenderungen((string) json_encode($this->zielAenderungenBereinigt($daten['zielAenderungen'])));
        }
        if (array_key_exists('aufteilung', $daten)) {
            $a->setAufteilung((string) json_encode($this->aufteilungBereinigt($daten['aufteilung'])));
        }
    }

    /**
     * Normalisiert die Einsparungsverteilung eines Antrags (F109) zu
     * [{ebene, ref, produkt?, betrag?, prozent?}]. Erlaubte Ebenen: «pg-kosten»
     * (Kostenzeile im Informationsteil), «produkt», «produkt-kosten» (Kostenzeile
     * innerhalb eines Produkts). Betrag und Prozent sind je optional.
     *
     * @param mixed $roh
     * @return list<array<string, mixed>>
     */
    private function aufteilungBereinigt($roh): array {
        if (!is_array($roh)) {
            return [];
        }
        $erlaubt = ['pg-kosten', 'produkt', 'produkt-kosten'];
        $out = [];
        foreach ($roh as $e) {
            if (!is_array($e)) {
                continue;
            }
            $ebene = (string) ($e['ebene'] ?? '');
            $ref = trim((string) ($e['ref'] ?? ''));
            if (!in_array($ebene, $erlaubt, true) || $ref === '') {
                continue;
            }
            $eintrag = ['ebene' => $ebene, 'ref' => $ref];
            if ($ebene === 'produkt-kosten') {
                $eintrag['produkt'] = trim((string) ($e['produkt'] ?? ''));
            }
            if (isset($e['betrag']) && is_numeric($e['betrag'])) {
                $eintrag['betrag'] = (int) $e['betrag'];
            }
            if (isset($e['prozent']) && is_numeric($e['prozent'])) {
                $eintrag['prozent'] = (float) $e['prozent'];
            }
            $out[] = $eintrag;
        }
        return $out;
    }

    /**
     * F109: Ist auf Ebene Produktegruppe kein Betrag/Prozent gesetzt, weiter unten
     * aber schon, wird oben die Summe der unteren Beträge eingesetzt. Ist oben ein
     * Wert gesetzt, wird nichts gerechnet — die Aufteilung dient dann nur der
     * Begründung.
     */
    private function aufteilungSummeAnwenden(BudgetAntrag $a): void {
        if ((int) $a->getBetragDelta() !== 0 || (float) $a->getProzentDelta() !== 0.0) {
            return;
        }
        $summe = 0;
        $hat = false;
        foreach ($a->getAufteilungArray() as $e) {
            if (isset($e['betrag'])) {
                $summe += (int) $e['betrag'];
                $hat = true;
            }
        }
        if ($hat) {
            $a->setBetragDelta($summe);
        }
    }

    /**
     * Normalisiert die Zielvorgaben-Änderungen eines Antrags (F109) zu
     * [{zielNummer, messgroesse, neuerWert}]. Verworfen wird alles ohne Ziel-Nummer,
     * Messgrösse oder Wert.
     *
     * @param mixed $roh
     * @return list<array{zielNummer:int,messgroesse:string,neuerWert:string}>
     */
    private function zielAenderungenBereinigt($roh): array {
        if (!is_array($roh)) {
            return [];
        }
        $out = [];
        foreach ($roh as $e) {
            if (!is_array($e)) {
                continue;
            }
            $nr = (int) ($e['zielNummer'] ?? 0);
            $mg = trim((string) ($e['messgroesse'] ?? ''));
            $wert = trim((string) ($e['neuerWert'] ?? ''));
            if ($nr > 0 && $mg !== '' && $wert !== '') {
                $out[] = ['zielNummer' => $nr, 'messgroesse' => $mg, 'neuerWert' => $wert];
            }
        }
        return $out;
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
        // F109: fehlt oben ein Betrag, wird die Summe der Aufteilung eingesetzt.
        $this->aufteilungSummeAnwenden($a);
        $this->kuerzungPruefen($a, $jahr, $id);
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

    /**
     * Löscht ALLE Fraktionsdaten zu einem Budgetjahr unwiederbringlich: Anträge
     * samt Notizen und Entscheiden sowie die Pauschalanträge. Dient dem Re-Import
     * über das Frontend, der ausdrücklich alles Bestehende zu diesem Budget
     * verwirft (nur nach doppelter Bestätigung im UI ausgelöst).
     */
    public function budgetFraktionsdatenLeeren(int $jahr): void {
        $antragIds = array_map(static fn ($a) => (int) $a->getId(), $this->antraege->findByJahr($jahr));
        $this->notizService->alleLoeschen(self::NOTIZ_OBJEKT_TYP, $antragIds);
        $this->entscheide->deleteByAntraege($antragIds);
        $this->antraege->deleteByJahr($jahr);
        $this->verteilungen->deleteByJahr($jahr);
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
     * Rechnet die automatischen Verteilungen und die Steuerfuss-Senkung neu —
     * öffentlich für den Aufruf nach einem Import (Weisung oder Novemberbrief),
     * damit die Automatik schon beim ersten Laden greift, nicht erst nach der
     * ersten Bearbeitung.
     */
    public function automatikNeuBerechnen(int $jahr): void {
        $this->nachAenderung($jahr);
    }

    /**
     * Ist die automatische Steuerfuss-Senkung für dieses Jahr eingeschaltet (F88)?
     * Standard: ja. Der Schalter wird pro Budgetjahr gespeichert, damit der Zustand
     * einen Reload überlebt (früher nur clientseitig, darum kam der Auto-Antrag
     * nach dem Neuladen zurück).
     */
    public function steuerfussAutomatikAn(int $jahr): bool {
        return $this->config->getAppValue(
            Application::APP_ID,
            self::CONFIG_STEUERFUSS_AUTOMATIK . '_' . $jahr,
            '1'
        ) === '1';
    }

    /**
     * Schaltet die automatische Steuerfuss-Senkung für ein Jahr ein oder aus (F88)
     * und rechnet neu. Beim Einschalten wird ein etwaiger manueller Steuerfussantrag
     * entfernt, damit die Automatik greift; beim Ausschalten fällt der Steuerfuss
     * auf den Stadtratsantrag zurück (der automatische Antrag wird nicht mehr erzeugt).
     */
    public function steuerfussAutomatikSetzen(int $jahr, bool $an): void {
        if ($an) {
            foreach ($this->antraege->findByJahr($jahr) as $a) {
                if ((string) $a->getBereich() === 'steuerfuss' && (string) $a->getQuelle() !== 'pauschal') {
                    $this->antraege->delete($a);
                }
            }
        }
        $this->config->setAppValue(
            Application::APP_ID,
            self::CONFIG_STEUERFUSS_AUTOMATIK . '_' . $jahr,
            $an ? '1' : '0'
        );
        $this->nachAenderung($jahr);
    }

    /** Name der eigenen Fraktion (Config «fraktion») — Antragsteller unserer eigenen Anträge. */
    public function eigeneFraktion(): string {
        return trim((string) $this->config->getAppValue(Application::APP_ID, 'fraktion', ''));
    }

    /**
     * Rechnet nach jeder Änderung die automatische Pauschalverteilung und die
     * Steuerfuss-Senkung neu (idempotent), publiziert ein Realtime-Event.
     */
    private function nachAenderung(int $jahr): void {
        $this->pauschalNeu($jahr);
        $this->steuerfussAutomatikNeu($jahr);
        $this->verknuepfungenAktualisieren($jahr);
        $this->realtime->publish('budget.updated', ['jahr' => $jahr]);
    }

    /**
     * Automatische Steuerfuss-Senkung (F88): Steht am Ende (auf dem bereits
     * pauschal gekürzten Stand) ein Überschuss, wird der Steuerfuss in ganzen
     * Prozent-Schritten gesenkt, bis der Überschuss aufgebraucht ist — der
     * Gesamtertrag geht damit auf (nahe) null. Die Senkung ist ein echter Antrag
     * (quelle «pauschal», damit sie wie die Pauschalkürzungen jeden Zyklus neu
     * gerechnet und in deletePauschalByJahr mitgelöscht wird): sie erscheint in
     * der Antragsliste und im Antrags-PDF und reduziert die Einnahmen.
     *
     * Verhältnis zur Ziel-Pauschalverteilung (Lösung B): Beide Automatiken dürfen
     * gleichzeitig aktiv sein. Die Ziel-Verteilung verteilt ausschliesslich
     * Defizite als Kürzungen (F84); einen Überschuss rührt sie nicht an. Ein
     * Überschuss — auch ein als Zielbetrag gewünschter Ertrag — wird stattdessen
     * hier über die Steuerfuss-Senkung ausgeglichen, sodass der Gesamtertrag
     * wieder null ist. So gibt es keinen Widerspruch zwischen «gewünschter Ertrag»
     * und «Überschuss senkt den Steuerfuss».
     *
     * Ein manuell gestellter Steuerfuss-Antrag (quelle ≠ «pauschal») schaltet die
     * Automatik aus — dann bestimmt der manuelle Antrag den Steuerfuss.
     */
    private function steuerfussAutomatikNeu(int $jahr): void {
        // Vom Nutzer für dieses Jahr ausgeschaltet (F88): keine automatische Senkung,
        // der Steuerfuss bleibt beim Stadtratsantrag.
        if (!$this->steuerfussAutomatikAn($jahr)) {
            return;
        }
        $alle = $this->antraege->findByJahr($jahr);
        foreach ($alle as $a) {
            if ((string) $a->getBereich() === 'steuerfuss' && (string) $a->getQuelle() !== 'pauschal') {
                return; // manuelle Kontrolle → keine Automatik
            }
        }
        $jahrRow = $this->jahre->findByJahr($jahr);
        $steuerfuss = (int) $jahrRow->getSteuerfuss();
        $steuerertrag = (int) $jahrRow->getSteuerertrag();
        // Überschuss auf dem laufenden (bereits pauschal gekürzten) Stand, ohne
        // einen etwaigen früheren Steuerfuss-Antrag.
        $laufend = array_map(fn ($a) => $this->antragFuerRechnung($a), array_values(array_filter(
            $alle,
            static fn ($a) => ((string) ($a->getPhase() ?? 'fraktion')) === 'fraktion'
                && $a->wirdUnterstuetzt()
                && (string) $a->getBereich() !== 'steuerfuss'
        )));
        $summen = BudgetRechnung::summen($this->gruppenFuerRechnung($this->gruppen->findByJahr($jahr)), $laufend);
        $senkung = BudgetRechnung::steuerfussSenkung((int) $summen['ergebnis'], $steuerertrag, $steuerfuss);
        if ($senkung['gesenkteProzent'] <= 0) {
            return;
        }
        $a = new BudgetAntrag();
        $a->setJahr($jahr);
        $a->setBereich('steuerfuss');
        $a->setZielTyp('steuerfuss');
        $a->setZielRef('');
        $a->setProzentDelta(-1.0 * $senkung['gesenkteProzent']);
        $a->setBetragDelta(-1 * $senkung['reduktion']);
        $a->setStellenDelta(0.0);
        $a->setBetragProStelle(0);
        $a->setQuelle('pauschal');
        $a->setHerkunft('eigene');
        $a->setHaltung('einreichen');
        // Die automatische Senkung ist ein Antrag der eigenen Fraktion (F88) — ihr
        // Name (Config) ist der Antragsteller, sonst bliebe er im Antrags-PDF leer.
        $a->setAntragsteller($this->eigeneFraktion());
        $a->setBegruendung('Automatische Steuerfusssenkung um ' . $senkung['gesenkteProzent'] . ' Prozentpunkte (Überschuss ausgeglichen)');
        $a->setReihenfolge(9500); // ganz am Ende der Antragsliste (F88)
        $a->setErstelltVon($this->aktuellerNutzer());
        $a->setErstelltAm($this->time->getTime());
        $this->antraege->insert($a);
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
        // Das absolute Ziel (Ausgleich) danach — auf dem bereits durch die
        // Einsparungen gekürzten Stand (F100). Es gibt höchstens eines (F84/F85).
        foreach ($verteilungen as $v) {
            if ($v->modusOderStandard() !== 'ziel') {
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
        // Pauschalkürzungen verteilen nur auf die echten, operativen Produktegruppen
        // — nie auf die künstliche (F89) und nicht auf ausgenommene Positionen (F101).
        $verteilbar = array_values(array_filter(
            $alleFuerRechnung,
            static fn ($g) => empty($g['kuenstlich']) && !in_array((string) $g['code'], $ausnahmen, true)
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
        // Vorbelegung: eine Einsparung (relativ). Ein absolutes Ziel wählt der
        // Nutzer danach; die Ein-absolutes-Ziel-Regel greift dabei (F84/F85).
        $daten['zielModus'] = $daten['zielModus'] ?? 'einsparungen';
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

    /**
     * Absolute Ziel-Typen: sie legen ein Gesamtergebnis fest (schwarze Null,
     * fester Ertrag, festes Defizit). Davon darf immer nur EINER aktiv sein
     * (F84/F85); «Einsparungen» (relativ) sind beliebig oft möglich.
     *
     * @var list<string>
     */
    private const ABSOLUTE_ZIELE = ['schwarze_null', 'fester_ertrag', 'festes_defizit'];

    /** @param array<string, mixed> $daten */
    private function pauschalFelderSetzen(BudgetVerteilung $v, array $daten): void {
        // Ziel-Typ (F84/F85/F100): «einsparungen» (relativ, beliebig oft) oder ein
        // absolutes Ziel (schwarze Null / fester Ertrag / festes Defizit, nur eines).
        // Der Diskriminator «modus» folgt daraus: fest = Einsparungen, ziel = absolut.
        if (array_key_exists('zielModus', $daten)) {
            $ziel = (string) $daten['zielModus'];
            if (!in_array($ziel, self::ABSOLUTE_ZIELE, true) && $ziel !== 'einsparungen') {
                $ziel = 'einsparungen';
            }
            $v->setZielModus($ziel === 'einsparungen' ? 'schwarze_null' : $ziel);
            $v->setModus($ziel === 'einsparungen' ? 'fest' : 'ziel');
            $v->setAutomatikEin(1);
            // Nur ein absolutes Ziel je Jahr: ein bestehendes anderes wird zur
            // Einsparung 0 herabgestuft (der ältere weicht, F84/F85).
            if (in_array($ziel, self::ABSOLUTE_ZIELE, true)) {
                $this->absolutesZielFreiraeumen((int) $v->getJahr(), (int) $v->getId());
            }
        }
        if (array_key_exists('zielBetrag', $daten)) {
            $v->setZielBetrag((int) $daten['zielBetrag']);
        }
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

    /**
     * Stuft ein bereits bestehendes absolutes Ziel (ausser $ausser) auf eine
     * Einsparung 0 herab, damit je Jahr nur ein absolutes Ziel aktiv ist (F84/F85).
     */
    private function absolutesZielFreiraeumen(int $jahr, int $ausser): void {
        foreach ($this->verteilungen->alleFuerJahr($jahr) as $andere) {
            if ((int) $andere->getId() === $ausser || $andere->modusOderStandard() !== 'ziel') {
                continue;
            }
            $andere->setModus('fest');
            $andere->setZielModus('schwarze_null');
            $andere->setBetrag(0);
            $andere->setProzent(0.0);
            $this->verteilungen->update($andere);
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
