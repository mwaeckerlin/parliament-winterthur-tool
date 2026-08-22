<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Importiert ein Budgetjahr in die Datenbank. Datenquelle je Jahr:
 *   1. eine bereits strukturierte `budget.json` (gemessene Echtdaten, im Repo) —
 *      bevorzugt, damit Tests reproduzierbar ohne PDF-Bibliothek laufen;
 *   2. sonst die städtischen PDF (Teil A + Teil B), geparst über BudgetBuchParser
 *      (nutzt zur Laufzeit die PHP-Bibliothek smalot/pdfparser).
 *
 * Die Struktur beider Quellen ist identisch (siehe BudgetBuchParser::struktur()),
 * sodass der PDF-Parser dieselbe DB-Befüllung speist wie die JSON-Quelle.
 */
class BudgetImportService {
    public function __construct(
        private readonly BudgetJahrMapper $jahre,
        private readonly BudgetProduktegruppeMapper $gruppen,
        private readonly BudgetInvestitionMapper $investitionen,
        private readonly BudgetAntragMapper $antraege,
        private readonly BudgetBuchParser $parser,
        private readonly ITimeFactory $time,
    ) {
    }

    /**
     * Basisverzeichnis der ausgelieferten Budget-Quellen. Liegt unter parlwin/
     * (reference/budget) und wird darum ins Image kopiert — anders als
     * tests/fixtures, das im Image-Build entfernt wird. Je Jahr eine
     * strukturierte budget.json (und optional die PDF für den Parser).
     */
    private function basisVerzeichnis(): string {
        // __DIR__ = parlwin/lib/Service → zwei Ebenen hoch ist parlwin/.
        return \dirname(__DIR__, 2) . '/reference/budget';
    }

    private function quelleVerzeichnis(int $jahr): string {
        return $this->basisVerzeichnis() . '/' . $jahr;
    }

    /**
     * Jahre, für die eine Budgetquelle vorliegt (budget.json oder teil-b.pdf),
     * neuestes zuerst — die Grundlage für die «vergangenes Budgetjahr
     * importieren»-Auswahl. Aus dem Bestand ist damit klar, welche Jahre sich
     * importieren lassen.
     *
     * @return int[]
     */
    public function verfuegbareJahre(): array {
        $basis = $this->basisVerzeichnis();
        if (!is_dir($basis)) {
            return [];
        }
        $jahre = [];
        foreach (scandir($basis) ?: [] as $eintrag) {
            if (!preg_match('/^\d{4}$/', $eintrag)) {
                continue;
            }
            $dir = $basis . '/' . $eintrag;
            if (is_file($dir . '/budget.json') || is_file($dir . '/teil-b.pdf')) {
                $jahre[] = (int) $eintrag;
            }
        }
        rsort($jahre);
        return $jahre;
    }

    /**
     * Automatischer Auslöser (F89): sobald die Weisung eines neuen Budgetjahres
     * vorliegt (eine Quelle im Bestand, neuestes Jahr) und es noch nicht
     * importiert ist, wird es eingelesen; ein vorhandener, noch nicht
     * eingelesener Novemberbrief wird nachgezogen. Vergangene Jahre bleiben dem
     * manuellen Import («+ Neu») vorbehalten. Läuft im geplanten Hintergrundjob.
     *
     * @return array{jahr:int, importiert:bool, novemberbrief:bool}|null null, wenn keine Quelle vorliegt
     */
    public function automatischerImport(): ?array {
        $jahre = $this->verfuegbareJahre();
        if ($jahre === []) {
            return null;
        }
        $neuestes = $jahre[0];
        $importiert = false;
        if (!$this->jahre->existiert($neuestes)) {
            $this->importiereJahr($neuestes);
            $importiert = true;
        }
        $novemberbrief = false;
        $pfad = $this->quelleVerzeichnis($neuestes);
        $hatBrief = is_file($pfad . '/novemberbrief.json') || is_file($pfad . '/novemberbrief.pdf');
        if ($hatBrief) {
            try {
                $row = $this->jahre->findByJahr($neuestes);
                if (!$row->getNovemberbriefImportiert()) {
                    $this->importiereNovemberbrief($neuestes);
                    $novemberbrief = true;
                }
            } catch (DoesNotExistException) {
                // Jahr (noch) nicht vorhanden — nichts nachzuziehen.
            }
        }
        return ['jahr' => $neuestes, 'importiert' => $importiert, 'novemberbrief' => $novemberbrief];
    }

    /**
     * Importiert (oder re-importiert) ein Budgetjahr. Bestehende Daten des Jahres
     * werden ersetzt; manuelle Anträge bleiben erhalten.
     */
    public function importiereJahr(int $jahr, bool $mitNovemberbrief = false): void {
        $struktur = $this->ladeStruktur($jahr);
        $this->schreibeStruktur($jahr, $struktur);
        if ($mitNovemberbrief) {
            $this->importiereNovemberbrief($jahr);
        }
    }

    /** Wendet die Novemberbrief-Anpassungen auf ein bereits importiertes Jahr an. */
    public function importiereNovemberbrief(int $jahr): void {
        $pfad = $this->quelleVerzeichnis($jahr);
        $anpassungen = null;
        if (is_file($pfad . '/novemberbrief.json')) {
            $anpassungen = json_decode((string) file_get_contents($pfad . '/novemberbrief.json'), true);
        } elseif (is_file($pfad . '/novemberbrief.pdf')) {
            $anpassungen = $this->parser->parseNovemberbrief($pfad . '/novemberbrief.pdf');
        }
        if (!is_array($anpassungen)) {
            throw new \RuntimeException('Kein Novemberbrief für Jahr ' . $jahr . ' vorhanden');
        }
        $vorhandene = [];
        foreach ($this->gruppen->findByJahr($jahr) as $g) {
            $vorhandene[(string) $g->getCode()] = $g;
        }
        foreach ($anpassungen['produktegruppen'] ?? [] as $a) {
            $code = (string) ($a['code'] ?? '');
            if (!isset($vorhandene[$code])) {
                continue;
            }
            $g = $vorhandene[$code];
            if (array_key_exists('globalkreditSoll', $a)) {
                $g->setGlobalkreditSoll((int) $a['globalkreditSoll']);
            }
            if (array_key_exists('aufwandSoll', $a)) {
                $g->setAufwandSoll((int) $a['aufwandSoll']);
            }
            if (array_key_exists('ertragSoll', $a)) {
                $g->setErtragSoll((int) $a['ertragSoll']);
            }
            if (array_key_exists('stellenSoll', $a)) {
                $g->setStellenSoll((float) $a['stellenSoll']);
            }
            $this->gruppen->update($g);
        }
        try {
            $jahrRow = $this->jahre->findByJahr($jahr);
            $jahrRow->setNovemberbriefImportiert(1);
            $this->jahre->update($jahrRow);
        } catch (DoesNotExistException) {
            // Jahr fehlt — nichts zu markieren.
        }
    }

    /** @return array<string, mixed> */
    private function ladeStruktur(int $jahr): array {
        $pfad = $this->quelleVerzeichnis($jahr);
        if (is_file($pfad . '/budget.json')) {
            $json = json_decode((string) file_get_contents($pfad . '/budget.json'), true);
            if (is_array($json)) {
                return $json;
            }
        }
        if (is_file($pfad . '/teil-b.pdf')) {
            return $this->parser->struktur(
                $pfad . '/teil-b.pdf',
                is_file($pfad . '/teil-a.pdf') ? $pfad . '/teil-a.pdf' : null,
                $jahr
            );
        }
        throw new \RuntimeException('Keine Budgetquelle für Jahr ' . $jahr . ' gefunden');
    }

    /** @param array<string, mixed> $struktur */
    private function schreibeStruktur(int $jahr, array $struktur): void {
        // Jahr-Metadaten.
        try {
            $jahrRow = $this->jahre->findByJahr($jahr);
        } catch (DoesNotExistException) {
            $jahrRow = new BudgetJahr();
            $jahrRow->setJahr($jahr);
            $jahrRow->setErstelltAm($this->time->getTime());
            $jahrRow = $this->jahre->insert($jahrRow);
        }
        $jahrRow->setSteuerfuss((int) ($struktur['steuerfuss'] ?? 0));
        $jahrRow->setSteuerertrag((int) ($struktur['steuerertrag'] ?? 0));
        $jahrRow->setPersonalsteuer((int) ($struktur['personalsteuer'] ?? 0));
        $this->jahre->update($jahrRow);

        // Produktegruppen und Investitionen ersetzen (Anträge bleiben).
        $this->gruppen->deleteByJahr($jahr);
        $reihenfolge = 0;
        foreach ($struktur['produktegruppen'] ?? [] as $g) {
            $this->gruppen->insert($this->baueGruppe($jahr, $g, $reihenfolge++));
        }
        $this->investitionen->deleteByJahr($jahr);
        $reihenfolge = 0;
        foreach ($struktur['investitionen'] ?? [] as $i) {
            $this->investitionen->insert($this->baueInvestition($jahr, $i, $reihenfolge++));
        }
    }

    /** @param array<string, mixed> $g */
    private function baueGruppe(int $jahr, array $g, int $reihenfolge): BudgetProduktegruppe {
        $e = new BudgetProduktegruppe();
        $e->setJahr($jahr);
        $e->setCode((string) ($g['code'] ?? ''));
        $e->setName((string) ($g['name'] ?? ''));
        $e->setDepartement((string) ($g['departement'] ?? ''));
        $e->setReihenfolge((int) ($g['reihenfolge'] ?? $reihenfolge));
        $gk = $g['globalkredit'] ?? [];
        $e->setGlobalkreditIst((int) ($gk['ist'] ?? 0));
        $e->setGlobalkreditSollVorjahr((int) ($gk['sollVorjahr'] ?? 0));
        $e->setGlobalkreditSoll((int) ($gk['soll'] ?? 0));
        $e->setGlobalkreditPlan1((int) ($gk['plan1'] ?? 0));
        $e->setGlobalkreditPlan2((int) ($gk['plan2'] ?? 0));
        $e->setGlobalkreditPlan3((int) ($gk['plan3'] ?? 0));
        $auf = $g['aufwand'] ?? [];
        $e->setAufwandIst((int) ($auf['ist'] ?? 0));
        $e->setAufwandSollVorjahr((int) ($auf['sollVorjahr'] ?? 0));
        $e->setAufwandSoll((int) ($auf['soll'] ?? 0));
        $ert = $g['ertrag'] ?? [];
        $e->setErtragIst((int) ($ert['ist'] ?? 0));
        $e->setErtragSollVorjahr((int) ($ert['sollVorjahr'] ?? 0));
        $e->setErtragSoll((int) ($ert['soll'] ?? 0));
        $st = $g['stellen'] ?? [];
        $e->setStellenIst((float) ($st['ist'] ?? 0));
        $e->setStellenSollVorjahr((float) ($st['sollVorjahr'] ?? 0));
        $e->setStellenSoll((float) ($st['soll'] ?? 0));
        $e->setAuszubildendeSoll((float) ($g['auszubildendeSoll'] ?? 0));
        $e->setAuftrag(isset($g['auftrag']) ? (string) $g['auftrag'] : null);
        $e->setZielvorgaben(isset($g['zielvorgaben']) ? (string) $g['zielvorgaben'] : null);
        $e->setErlaeuterungStellen(isset($g['erlaeuterungStellen']) ? (string) $g['erlaeuterungStellen'] : null);
        $e->setBegruendungAbweichung(isset($g['begruendungAbweichung']) ? (string) $g['begruendungAbweichung'] : null);
        $e->setBegruendungFap(isset($g['begruendungFap']) ? (string) $g['begruendungFap'] : null);
        $e->setMassnahmen(isset($g['massnahmen']) ? (string) $g['massnahmen'] : null);
        $e->setProdukte(isset($g['produkte']) ? (string) json_encode($g['produkte']) : null);
        return $e;
    }

    /** @param array<string, mixed> $i */
    private function baueInvestition(int $jahr, array $i, int $reihenfolge): BudgetInvestition {
        $e = new BudgetInvestition();
        $e->setJahr($jahr);
        $e->setDepartement((string) ($i['departement'] ?? ''));
        $e->setCluster((string) ($i['cluster'] ?? ''));
        $e->setProjekt((string) ($i['projekt'] ?? ''));
        $e->setBu((int) ($i['bu'] ?? 0));
        $e->setFap1((int) ($i['fap1'] ?? 0));
        $e->setFap2((int) ($i['fap2'] ?? 0));
        $e->setFap3((int) ($i['fap3'] ?? 0));
        $e->setGesamtkosten((int) ($i['gesamtkosten'] ?? 0));
        $e->setBereitsGetaetigt((int) ($i['bereitsGetaetigt'] ?? 0));
        $e->setPlanungskosten((int) ($i['planungskosten'] ?? 0));
        $e->setReihenfolge((int) ($i['reihenfolge'] ?? $reihenfolge));
        return $e;
    }

    /**
     * Daten für das Anträge-PDF (F92): alle Anträge der Fraktion, gruppiert je
     * Kommission/Departement. Die eigentliche PDF-Ausgabe erfolgt als Druckansicht
     * im Frontend (Browser-Druck), ohne Server-PDF-Bibliothek.
     *
     * @return array<string, mixed>
     */
    public function antraegePdf(int $jahr, ?string $kommission): array {
        $gruppenNachCode = [];
        foreach ($this->gruppen->findByJahr($jahr) as $g) {
            $gruppenNachCode[(string) $g->getCode()] = $g;
        }
        $eintraege = [];
        foreach ($this->antraege->findByJahr($jahr) as $a) {
            $g = $gruppenNachCode[(string) $a->getZielRef()] ?? null;
            $dep = $g !== null ? (string) $g->getDepartement() : '';
            if ($kommission !== null && $kommission !== '' && $dep !== $kommission) {
                continue;
            }
            $eintraege[] = [
                'departement' => $dep,
                'produktegruppe' => $g !== null ? (string) $g->getName() : (string) $a->getZielRef(),
                'antrag' => $a->jsonSerialize(),
            ];
        }
        return ['jahr' => $jahr, 'kommission' => $kommission, 'eintraege' => $eintraege];
    }
}
