<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;

/**
 * Importiert ein Budgetjahr in die Datenbank. Datenquelle ist **immer** das echte
 * städtische Budgetbuch (Teil B + Teil A), das **live von der Parlamentswebseite**
 * geladen (aus den Beilagen des Budget-Geschäfts) und über BudgetBuchParser
 * (smalot/pdfparser) geparst wird. Das Ergebnis geht direkt als SQL in die Datenbank.
 * Es gibt **kein gebündeltes Budgetbuch und kein JSON im Image** — weder produktiv
 * noch als Laufzeitquelle (F89). Die committeten PDFs unter parlwin/tests/Fixtures/
 * dienen ausschliesslich dem Parser-Test (Vergleich) und liegen via .dockerignore
 * nicht im Image.
 */
class BudgetImportService {
    private const BASE_URL = 'https://parlament.winterthur.ch';

    public function __construct(
        private readonly BudgetJahrMapper $jahre,
        private readonly BudgetProduktegruppeMapper $gruppen,
        private readonly BudgetInvestitionMapper $investitionen,
        private readonly BudgetAntragMapper $antraege,
        private readonly BudgetBuchParser $parser,
        private readonly ITimeFactory $time,
        private readonly GeschaeftMapper $geschaefte,
        private readonly IClientService $clientService,
        private readonly TraktandumMapper $traktanden,
        private readonly SitzungMapper $sitzungen,
        private readonly BudgetDrehbuchParser $drehbuchParser,
    ) {
    }

    /**
     * Zwischenspeicher der je Jahr live geladenen und geparsten Drehbuch-Struktur
     * (Sitzungsanträge + Novemberbrief), damit derselbe Abruf innerhalb eines
     * Requests nicht mehrfach über das Netz geht.
     *
     * @var array<int, array{antraege: list<array<string, mixed>>, novemberbrief: array{produktegruppen: list<array<string, mixed>>}}|null>
     */
    private array $drehbuchCache = [];

    /**
     * Herkunft eines Budgetjahres: das gescrapte Budget-Geschäft (die Weisung) mit
     * Nummer und Link zur Parlamentswebseite. Wo ein Budget existiert, existiert
     * damit auch der Link (F89). Liefert null, wenn keine Weisung gefunden wird.
     *
     * @return array{url:string, nummer:string, titel:string}|null
     */
    public function weisungLink(int $jahr): ?array {
        $g = $this->geschaefte->findeBudgetWeisung($jahr);
        if ($g === null) {
            return null;
        }
        $url = (string) $g->getUrl();
        if ($url === '') {
            return null;
        }
        return ['url' => $url, 'nummer' => (string) $g->getNummer(), 'titel' => (string) $g->getTitel()];
    }

    /**
     * Jahre, für die auf der Parlamentswebseite ein Budget-Geschäft (Weisung)
     * vorliegt — neuestes zuerst. Grundlage für die «vergangenes Budgetjahr
     * importieren»-Auswahl. Das Budgetjahr wird aus dem Titel «Budget <Jahr>»
     * gelesen; nichts wird aus einem gebündelten Bestand abgeleitet.
     *
     * @return int[]
     */
    public function verfuegbareJahre(): array {
        $jahre = [];
        foreach ($this->geschaefte->alleBudgetWeisungen() as $g) {
            if (preg_match('/Budget\s+(\d{4})/u', (string) $g->getTitel(), $m)) {
                $jahre[(int) $m[1]] = true;
            }
        }
        $liste = array_keys($jahre);
        rsort($liste);
        return $liste;
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
        return ['jahr' => $neuestes, 'importiert' => $importiert, 'novemberbrief' => false];
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
        $anpassungen = $this->novemberbriefAnpassungen($jahr);
        if (!is_array($anpassungen)) {
            throw new \RuntimeException('Kein Novemberbrief für Jahr ' . $jahr . ' verfügbar');
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

    /**
     * Baut die Jahresstruktur durch **Live-Laden und Parsen des echten
     * Budgetbuchs** (Teil B + Teil A) von der Parlamentswebseite (F89). Die
     * heruntergeladenen PDFs landen nur in temporären Dateien für den Parser und
     * werden danach gelöscht — nichts wird abgelegt.
     *
     * @return array<string, mixed>
     */
    private function ladeStruktur(int $jahr): array {
        $urls = $this->buchUrls($jahr);
        if ($urls === null || $urls['teilB'] === '') {
            throw new \RuntimeException('Kein Budgetbuch (Teil B) für Jahr ' . $jahr . ' auf der Parlamentswebseite gefunden');
        }
        $teilB = $this->tempPdf($this->ladeDokument($urls['teilB']));
        $teilA = $urls['teilA'] !== '' ? $this->tempPdf($this->ladeDokument($urls['teilA'])) : null;
        try {
            $struktur = $this->parser->struktur($teilB, $teilA, $jahr);
        } finally {
            @unlink($teilB);
            if ($teilA !== null) {
                @unlink($teilA);
            }
        }
        // Ein gültiges Teil B trägt Dutzende Produktegruppen. Liefert der Parse keine,
        // ist das geladene Dokument nicht das erwartete Budgetbuch (falscher Link,
        // Fehlerseite, geänderte Struktur). Dann NICHT weiterschreiben: schreibeStruktur
        // löscht sonst die bestehenden Produktegruppen, und die künstliche Position
        // schluckt das ganze Total (das Budget stünde ohne Produktegruppen da). Lieber
        // laut scheitern und den bestehenden Stand unangetastet lassen.
        if (($struktur['produktegruppen'] ?? []) === []) {
            throw new \RuntimeException(
                'Teil B (geladen von ' . $urls['teilB'] . ') lieferte keine Produktegruppen — '
                . 'Budget ' . $jahr . ' bleibt unverändert'
            );
        }
        return $struktur;
    }

    /**
     * Die Download-URLs von Teil A und Teil B aus der Seite des Budget-Geschäfts
     * (Weisung). Die Bücher hängen als Beilagen «… Teil A …» / «… Teil B …» am
     * Geschäft. Liefert null, wenn kein Budget-Geschäft gefunden wird.
     *
     * @return array{teilA:string, teilB:string}|null
     */
    private function buchUrls(int $jahr): ?array {
        $g = $this->geschaefte->findeBudgetWeisung($jahr);
        if ($g === null || (string) $g->getUrl() === '') {
            return null;
        }
        $html = $this->ladeSeite((string) $g->getUrl());
        return [
            'teilA' => $this->findeDokumentLink($html, 'Teil A'),
            'teilB' => $this->findeDokumentLink($html, 'Teil B'),
        ];
    }

    /**
     * Sucht in der Geschäft-Seite den ersten Dokument-Link (`/_doc/…`), dessen
     * Beschriftung die Bezeichnung enthält (z.B. «Teil A»), und liefert die
     * absolute URL. Leerer String, wenn nicht gefunden.
     */
    private function findeDokumentLink(string $html, string $bezeichnung): string {
        $doc = new \DOMDocument();
        if (@$doc->loadHTML('<?xml encoding="utf-8"?>' . $html) !== true) {
            return '';
        }
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//a[contains(@href, "/_doc")]') ?: [] as $a) {
            if (!$a instanceof \DOMElement) {
                continue;
            }
            if (mb_stripos($a->textContent, $bezeichnung) !== false) {
                return $this->absolutUrl((string) $a->getAttribute('href'));
            }
        }
        return '';
    }

    private function absolutUrl(string $url): string {
        if ($url === '' || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        return self::BASE_URL . '/' . ltrim($url, '/');
    }

    /** Lädt eine HTML-Seite der Parlamentswebseite. */
    private function ladeSeite(string $url): string {
        $client = $this->clientService->newClient();
        return (string) $client->get($url, [
            'timeout' => 25,
            'connect_timeout' => 8,
            'headers' => [
                'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'de-CH,de;q=0.9',
            ],
        ])->getBody();
    }

    /** Lädt ein Dokument (PDF) als Rohbytes; prüft, dass die Antwort ein PDF ist. */
    private function ladeDokument(string $url): string {
        $client = $this->clientService->newClient();
        $inhalt = (string) $client->get($url, [
            'timeout' => 120,
            'connect_timeout' => 8,
            'headers' => [
                'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                'Accept' => 'application/pdf,*/*',
            ],
        ])->getBody();
        // Ein PDF trägt die «%PDF-»-Kennung am Anfang (ein paar Vorbytes sind laut
        // Spezifikation erlaubt, darum im ersten KB suchen — wie smalot). Kommt
        // stattdessen eine HTML-Fehlerseite oder ein leerer Body zurück, laut scheitern
        // statt ein Nicht-PDF an den Parser zu geben.
        if (!str_contains(substr($inhalt, 0, 1024), '%PDF-')) {
            throw new \RuntimeException('Kein PDF von ' . $url . ' erhalten (die Antwort ist kein PDF)');
        }
        return $inhalt;
    }

    /** Schreibt Rohbytes in eine temporäre PDF-Datei und liefert den Pfad. */
    private function tempPdf(string $inhalt): string {
        $pfad = (string) tempnam(sys_get_temp_dir(), 'parlwin-budget-');
        file_put_contents($pfad, $inhalt);
        return $pfad;
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
        $jahrRow->setTotalAufwand((int) ($struktur['totalAufwand'] ?? 0));
        $jahrRow->setTotalErtrag((int) ($struktur['totalErtrag'] ?? 0));
        $jahrRow->setTotalAufwandVorjahr((int) ($struktur['totalAufwandVorjahr'] ?? 0));
        $jahrRow->setTotalErtragVorjahr((int) ($struktur['totalErtragVorjahr'] ?? 0));
        $jahrRow->setGesamtergebnis((int) ($struktur['gesamtergebnis'] ?? 0));
        $this->jahre->update($jahrRow);

        // Produktegruppen und Investitionen ersetzen (Anträge bleiben).
        $this->gruppen->deleteByJahr($jahr);
        $reihenfolge = 0;
        $sumAufwand = 0;
        $sumErtrag = 0;
        $sumAufwandVorjahr = 0;
        $sumErtragVorjahr = 0;
        foreach ($struktur['produktegruppen'] ?? [] as $g) {
            $this->gruppen->insert($this->baueGruppe($jahr, $g, $reihenfolge++));
            $sumAufwand += (int) (($g['aufwand'] ?? [])['soll'] ?? 0);
            $sumErtrag += (int) (($g['ertrag'] ?? [])['soll'] ?? 0);
            $sumAufwandVorjahr += (int) (($g['aufwand'] ?? [])['sollVorjahr'] ?? 0);
            $sumErtragVorjahr += (int) (($g['ertrag'] ?? [])['sollVorjahr'] ?? 0);
        }
        // Künstliche Produktegruppe (F89): schliesst die Differenz zwischen der Summe
        // der operativen Produktegruppen (Teil B, brutto inkl. interner Verrechnung)
        // und dem vom Stadtrat deklarierten Ergebnis der Erfolgsrechnung. So ergibt
        // Σ aller Produktegruppen exakt das deklarierte Gesamtergebnis. Das ERGEBNIS
        // wird an die Schlagzeile (gesamtergebnis) gebunden — die zuverlässigste
        // Grösse —, die Aufwand-Seite an das geparste Total (interne Verrechnung).
        // Einmalig beim Import gebildet und fix; Anträge und Novemberbrief verschieben
        // die Summe danach wie bei jeder echten Produktegruppe.
        $totalAufwand = (int) ($struktur['totalAufwand'] ?? 0);
        $totalErtrag = (int) ($struktur['totalErtrag'] ?? 0);
        // Nur wenn operative Produktegruppen vorliegen — sonst würde die künstliche
        // Position das ganze Total schlucken (ladeStruktur fängt den leeren Parse zwar
        // schon ab, dies ist die zweite Sicherung).
        if ($totalAufwand > 0 && $totalErtrag > 0 && $reihenfolge > 0) {
            $gesamtergebnis = (int) ($struktur['gesamtergebnis'] ?? 0);
            $aufwandKuenstlich = $totalAufwand - $sumAufwand;
            // Ist die Schlagzeile geparst, ist sie massgeblich; sonst die Totale.
            $ertragKuenstlich = $gesamtergebnis !== 0
                ? $totalAufwand + $gesamtergebnis - $sumErtrag
                : $totalErtrag - $sumErtrag;
            $this->gruppen->insert($this->kuenstlicheGruppe(
                $jahr,
                $reihenfolge++,
                $aufwandKuenstlich,
                $ertragKuenstlich,
                (int) ($struktur['totalAufwandVorjahr'] ?? 0) - $sumAufwandVorjahr,
                (int) ($struktur['totalErtragVorjahr'] ?? 0) - $sumErtragVorjahr
            ));
        }
        $this->investitionen->deleteByJahr($jahr);
        $reihenfolge = 0;
        foreach ($struktur['investitionen'] ?? [] as $i) {
            $this->investitionen->insert($this->baueInvestition($jahr, $i, $reihenfolge++));
        }
    }

    /**
     * Die künstliche Produktegruppe «Interne Verrechnung / Abgrenzung» (Finanzen,
     * nicht antragbar). Ihr Aufwand/Ertrag ist die Differenz zwischen deklariertem
     * Total (Teil A) und der Summe der operativen Produktegruppen (F89).
     */
    private function kuenstlicheGruppe(int $jahr, int $reihenfolge, int $aufwand, int $ertrag, int $aufwandVorjahr, int $ertragVorjahr): BudgetProduktegruppe {
        $e = new BudgetProduktegruppe();
        $e->setJahr($jahr);
        $e->setCode('IV');
        $e->setName('Interne Verrechnung / Abgrenzung');
        $e->setDepartement('Finanzen');
        $e->setReihenfolge($reihenfolge);
        $e->setKuenstlich(1);
        $e->setAufwandSoll($aufwand);
        $e->setAufwandSollVorjahr($aufwandVorjahr);
        $e->setErtragSoll($ertrag);
        $e->setErtragSollVorjahr($ertragVorjahr);
        // Globalkredit (Nettokosten) = Aufwand − Ertrag, für die Anzeige der Karte.
        $e->setGlobalkreditSoll($aufwand - $ertrag);
        $e->setGlobalkreditSollVorjahr($aufwandVorjahr - $ertragVorjahr);
        return $e;
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
        $e->setZielvorgaben(isset($g['zielvorgaben']) ? (string) json_encode($g['zielvorgaben']) : null);
        $e->setKostenzeilen(isset($g['kostenzeilen']) ? (string) json_encode($g['kostenzeilen']) : null);
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
    public function antraegePdf(int $jahr, ?string $kommission, bool $mitFremden = false, string $eigeneFraktion = ''): array {
        $gruppenNachCode = [];
        foreach ($this->gruppen->findByJahr($jahr) as $g) {
            $gruppenNachCode[(string) $g->getCode()] = $g;
        }
        $invNachId = [];
        foreach ($this->investitionen->findByJahr($jahr) as $i) {
            $invNachId[(string) $i->getId()] = $i;
        }
        // Innerhalb eines Departements zuerst das Budget (Globalbudget, Personal),
        // dann die Investitionen (F92); der Steuerfuss steht in einem eigenen Abschnitt.
        $bereichOrder = ['globalbudget' => 0, 'personal' => 1, 'investition' => 2, 'steuerfuss' => 3];
        $eintraege = [];
        $depReihenfolge = [];
        foreach ($this->antraege->findByJahr($jahr) as $a) {
            // Eigene Anträge, die wir einreichen, stehen immer im PDF. Von uns
            // unterstützte fremde Anträge nur, wenn das ausdrücklich gewählt ist
            // (F92/F97). Ein nicht eingereichter/nicht unterstützter Antrag fehlt.
            $h = $a->haltungOderStandard();
            if ($h !== 'einreichen' && !($mitFremden && $h === 'unterstuetzen')) {
                continue;
            }
            $bereich = (string) $a->getBereich();
            $steuerfuss = $bereich === 'steuerfuss';
            if ($bereich === 'investition') {
                $inv = $invNachId[(string) $a->getZielRef()] ?? null;
                $dep = $inv !== null ? (string) $inv->getDepartement() : '';
                $bezeichnung = $inv !== null ? (string) $inv->getProjekt() : (string) $a->getZielRef();
            } elseif ($steuerfuss) {
                $dep = 'Steuerfuss';
                $bezeichnung = 'Steuerfuss';
            } else {
                $g = $gruppenNachCode[(string) $a->getZielRef()] ?? null;
                $dep = $g !== null ? (string) $g->getDepartement() : '';
                $bezeichnung = $g !== null ? (string) $g->getName() : (string) $a->getZielRef();
            }
            if ($kommission !== null && $kommission !== '' && !$steuerfuss && $dep !== $kommission) {
                continue;
            }
            if (!array_key_exists($dep, $depReihenfolge)) {
                $depReihenfolge[$dep] = count($depReihenfolge);
            }
            // Im PDF steht als Antragsteller die Fraktion, nicht die Person (im Rat
            // stellt die Fraktion die Anträge). Bei eigenen ersetzt die eigene
            // Fraktion die Person; bei fremden bleibt der eingetragene Antragsteller
            // (dort tragen wir Fraktion oder Person nach Bedarf ein).
            $antrag = $a->jsonSerialize();
            if (((string) $a->getHerkunft()) === 'eigene' && $eigeneFraktion !== '') {
                $antrag['antragsteller'] = $eigeneFraktion;
            }
            $eintraege[] = [
                'departement' => $dep,
                'produktegruppe' => $bezeichnung,
                'bereichOrder' => $bereichOrder[$bereich] ?? 9,
                'antrag' => $antrag,
            ];
        }
        // Departemente in Buchreihenfolge (erstes Auftreten), Steuerfuss ganz ans
        // Ende; innerhalb eines Departements Budget vor Investition (F92). Stabil.
        usort($eintraege, static function ($x, $y) use ($depReihenfolge) {
            $sx = $x['departement'] === 'Steuerfuss' ? 1 : 0;
            $sy = $y['departement'] === 'Steuerfuss' ? 1 : 0;
            if ($sx !== $sy) {
                return $sx <=> $sy;
            }
            $dx = $depReihenfolge[$x['departement']] ?? 0;
            $dy = $depReihenfolge[$y['departement']] ?? 0;
            if ($dx !== $dy) {
                return $dx <=> $dy;
            }
            return $x['bereichOrder'] <=> $y['bereichOrder'];
        });
        return ['jahr' => $jahr, 'kommission' => $kommission, 'fraktion' => $eigeneFraktion, 'eintraege' => $eintraege];
    }

    /**
     * Ob sich für ein Jahr ein Novemberbrief von Hand einlesen lässt (F91): Das
     * Budget des Jahres liegt in der Datenbank, es ist noch nicht als
     * Novemberbrief-eingelesen markiert, und das Drehbuch der Budgetsitzung führt
     * in der Spalte «NB» tatsächlich Korrekturen des Stadtrats. Andernfalls false
     * — der Knopf «Novemberbrief einlesen» erscheint dann nicht.
     */
    public function novemberbriefVerfuegbar(int $jahr): bool {
        if (!$this->jahre->existiert($jahr)) {
            return false;
        }
        try {
            if ((int) $this->jahre->findByJahr($jahr)->getNovemberbriefImportiert() === 1) {
                return false;
            }
        } catch (DoesNotExistException) {
            return false;
        }
        return $this->novemberbriefAnpassungen($jahr) !== null;
    }

    /**
     * Liest die Sitzungsanträge (F90) live aus dem Drehbuch der Budgetsitzung —
     * die je Produktegruppe behandelten Kommissions- und Fraktionsanträge (Quelle,
     * Richtung, Betrag, Begründung, Abstimmungsergebnis). Liefert eine leere Liste,
     * wenn kein Drehbuch gefunden wird.
     *
     * @return list<array<string, mixed>>
     */
    public function sitzungsantraege(int $jahr): array {
        $struktur = $this->drehbuchStruktur($jahr);
        return $struktur['antraege'] ?? [];
    }

    /**
     * Die Novemberbrief-Anpassungen (je Produktegruppe) aus der «NB»-Spalte des
     * Drehbuchs. Liefert null, wenn kein Drehbuch vorliegt oder die NB-Spalte leer
     * ist (kein Novemberbrief in dem Jahr).
     *
     * @return array<string, mixed>|null
     */
    private function novemberbriefAnpassungen(int $jahr): ?array {
        $struktur = $this->drehbuchStruktur($jahr);
        $nb = $struktur['novemberbrief']['produktegruppen'] ?? [];
        return $nb === [] ? null : ['produktegruppen' => $nb];
    }

    /**
     * Lädt das Drehbuch der Budgetsitzung live von der Parlamentswebseite und
     * parst es (Sitzungsanträge + Novemberbrief). Der Weg zum Drehbuch: Budget-
     * Geschäft → dessen Traktandum → die Budgetsitzung → deren Beilage
     * «… Drehbuch zur Budgetbehandlung». Das Ergebnis wird je Request
     * zwischengespeichert; die heruntergeladene PDF landet nur in einer
     * temporären Datei und wird danach gelöscht.
     *
     * @return array{antraege: list<array<string, mixed>>, novemberbrief: array{produktegruppen: list<array<string, mixed>>}}|null
     */
    private function drehbuchStruktur(int $jahr): ?array {
        if (array_key_exists($jahr, $this->drehbuchCache)) {
            return $this->drehbuchCache[$jahr];
        }
        $url = $this->drehbuchUrl($jahr);
        if ($url === null) {
            return $this->drehbuchCache[$jahr] = null;
        }
        $pfad = $this->tempPdf($this->ladeDokument($url));
        try {
            return $this->drehbuchCache[$jahr] = $this->drehbuchParser->parse($pfad, $jahr);
        } finally {
            @unlink($pfad);
        }
    }

    /**
     * Die Download-URL des Drehbuchs: über das Traktandum des Budget-Geschäfts zur
     * Budgetsitzung und von deren Seite die Beilage, deren Beschriftung «Drehbuch»
     * enthält. null, wenn kein Weg dorthin führt.
     */
    private function drehbuchUrl(int $jahr): ?string {
        $g = $this->geschaefte->findeBudgetWeisung($jahr);
        if ($g === null) {
            return null;
        }
        foreach ($this->traktanden->findByGeschaeft((int) $g->getId()) as $t) {
            try {
                $sitzung = $this->sitzungen->find((int) $t->getSitzungId());
            } catch (DoesNotExistException) {
                continue;
            }
            $seite = (string) $sitzung->getUrl();
            if ($seite === '') {
                continue;
            }
            $link = $this->findeDokumentLink($this->ladeSeite($seite), 'Drehbuch');
            if ($link !== '') {
                return $link;
            }
        }
        return null;
    }
}
