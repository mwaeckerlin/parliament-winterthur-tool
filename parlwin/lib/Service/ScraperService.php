<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Scrapt Daten von der Parlamentswebseite Winterthur.
 *
 * Die Webseite https://parlament.winterthur.ch/ stellt Daten als
 * HTML-Attribute bereit, vor allem in data-entities="[...]" Attributen,
 * die JSON-kodierte Arrays mit den Entitätsdaten enthalten.
 */
class ScraperService {
    private const BASE_URL = 'https://parlament.winterthur.ch';

    /** URLs der verschiedenen Datenquellen */
    private const URLS = [
        'geschaefte'  => self::BASE_URL . '/politbusiness',
        'sitzungen'   => self::BASE_URL . '/sitzung',
        'mitglieder'  => self::BASE_URL . '/stadtparlament/27428',
        'kommissionen' => self::BASE_URL . '/kommissionen',
        'fraktionen'  => self::BASE_URL . '/fraktionen',
    ];

    /**
     * In-memory Cache fuer bereits geladene Geschaeft-Detailseiten.
     *
     * @var array<string, array<string, string>>
     */
    private array $geschaeftDetailCache = [];

    /**
     * Vorgepufferte Listen-Einträge je Bereich.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $prefetchedListen = [];

    public function __construct(
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Lädt die Hauptlisten (Geschäfte, Sitzungen, Mitglieder, Kommissionen, Fraktionen)
     * parallel vor und legt sie im In-Memory-Cache ab.
     *
     * @param array<int, string> $bereiche
     */
    public function prefetchTopLevelListen(array $bereiche = ['geschaefte', 'sitzungen', 'mitglieder', 'kommissionen', 'fraktionen']): void {
        $ziele = [];
        foreach ($bereiche as $bereich) {
            if (!isset(self::URLS[$bereich])) {
                continue;
            }
            if (isset($this->prefetchedListen[$bereich])) {
                continue;
            }
            $ziele[$bereich] = self::URLS[$bereich];
        }

        if ($ziele === []) {
            return;
        }

        $antworten = $this->ladeHtmlFuerBereicheParallel($ziele, $this->leseParallelitaetAusEnv('PARLWIN_SYNC_SECTION_PARALLEL', 6, 1, 12));
        foreach ($antworten as $bereich => $html) {
            if (!isset(self::URLS[$bereich])) {
                continue;
            }
            $label = ucfirst($bereich);
            $entitaeten = $this->extrahiereEntitaeten($html, $label);
            if ($bereich === 'geschaefte') {
                $this->prefetchedListen[$bereich] = $this->normalisiereGeschaeftslistenEntitaeten($entitaeten);
            } else {
                $this->prefetchedListen[$bereich] = $this->normalisiereListenEntitaeten($entitaeten);
            }
        }
    }

    /**
     * Liefert vorab bekannte Gesamtmengen für die Sync-Fortschrittsanzeige.
     *
     * @return array<string, int>
     */
    public function vorabTotalsFuerSync(): array {
        $totals = [];
        foreach ($this->prefetchedListen as $bereich => $eintraege) {
            $begrenzt = $this->wendeSyncLimitAn($bereich, $eintraege);
            $total = count($begrenzt);
            if ($bereich === 'geschaefte') {
                // Phase 1 = externe Details laden, Phase 2 = Datenbank-Upsert.
                $total *= 2;
            }
            $totals[$bereich] = $total;
        }
        return $totals;
    }

    /**
     * Lädt alle Geschäfte von der Parlamentswebseite.
     *
     * @return array[] Array von Geschäftsdaten
     */
    public function ladeGeschaefte(?callable $fortschritt = null): array {
        $eintraege = $this->holePrefetchedListenEintraege('geschaefte');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::URLS['geschaefte'], 'Geschäfte');
            $eintraege = $this->normalisiereGeschaeftslistenEntitaeten($roh);
        }
        $eintraege = $this->wendeSyncLimitAn('geschaefte', $eintraege);
        $normalisiert = [];
        $gesamt = count($eintraege);
        $phase1Verarbeitet = 0;
        $eintraegeJeUrl = [];
        $cursorJeUrl = [];
        $datenJeIndex = [];

        if ($fortschritt !== null) {
            $fortschritt([
                'processed' => 0,
                'total' => $gesamt,
                'cursor' => '',
                'final' => $gesamt === 0,
            ]);
        }

        foreach ($eintraege as $eintrag) {
            if (!is_array($eintrag)) {
                $phase1Verarbeitet++;
                if ($fortschritt !== null) {
                    $fortschritt([
                        'processed' => $phase1Verarbeitet,
                        'total' => $gesamt,
                        'cursor' => '',
                        'final' => $phase1Verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            $daten = $this->normalisiereGeschaeftszeile($eintrag);
            $datenJeIndex[] = $daten;
            $url = (string) ($daten['url'] ?? '');
            if ($url === '') {
                $phase1Verarbeitet++;
                if ($fortschritt !== null) {
                    $fortschritt([
                        'processed' => $phase1Verarbeitet,
                        'total' => $gesamt,
                        'cursor' => (string) ($daten['id'] ?? ''),
                        'final' => $phase1Verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            $eintraegeJeUrl[$url] ??= [];
            $eintraegeJeUrl[$url][] = count($datenJeIndex) - 1;
            if (!isset($cursorJeUrl[$url])) {
                $cursorJeUrl[$url] = (string) ($daten['id'] ?? '');
            }
        }

        if ($eintraegeJeUrl !== []) {
            $fertigJeUrl = [];
            $detailsJeUrl = $this->ladeGeschaeftDetailsParallel(
                array_keys($eintraegeJeUrl),
                $this->leseParallelitaetAusEnv('PARLWIN_SYNC_GESCHAEFTE_PARALLEL', 10, 1, 50),
                function (string $url) use (&$phase1Verarbeitet, $gesamt, $fortschritt, $eintraegeJeUrl, $cursorJeUrl, &$fertigJeUrl): void {
                    if (isset($fertigJeUrl[$url])) {
                        return;
                    }
                    $fertigJeUrl[$url] = true;
                    $phase1Verarbeitet += count($eintraegeJeUrl[$url] ?? []);
                    if ($phase1Verarbeitet > $gesamt) {
                        $phase1Verarbeitet = $gesamt;
                    }
                    if ($fortschritt !== null) {
                        $fortschritt([
                            'processed' => $phase1Verarbeitet,
                            'total' => $gesamt,
                            'cursor' => (string) ($cursorJeUrl[$url] ?? ''),
                            'final' => $phase1Verarbeitet >= $gesamt,
                        ]);
                    }
                }
            );

            foreach ($eintraegeJeUrl as $url => $indices) {
                $detail = $detailsJeUrl[$url] ?? [];
                foreach ($indices as $index) {
                    $datenJeIndex[$index] = $this->uebernehmeDetailDaten($datenJeIndex[$index], $detail);
                }
            }
        }

        if ($fortschritt !== null && $phase1Verarbeitet < $gesamt) {
            $fortschritt([
                'processed' => $gesamt,
                'total' => $gesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        foreach ($datenJeIndex as $daten) {
            $normalisiert[] = $daten;
        }

        return $normalisiert;
    }

    /**
     * Lädt alle Sitzungen von der Parlamentswebseite.
     *
     * @return array[] Array von Sitzungsdaten
     */
    public function ladeSitzungen(): array {
        $eintraege = $this->holePrefetchedListenEintraege('sitzungen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::URLS['sitzungen'], 'Sitzungen');
            $eintraege = $this->normalisiereListenEntitaeten($roh);
        }
        $eintraege = $this->wendeSyncLimitAn('sitzungen', $eintraege);
        $normalisiert = [];

        foreach ($eintraege as $daten) {
            if (!is_array($daten)) {
                continue;
            }
            $normalisiert[] = $this->normalisiereSitzungszeile($daten);
        }

        return $normalisiert;
    }

    /**
     * Lädt die Traktanden einer Sitzung.
     *
     * @param string $sitzungUrl URL der Sitzungsdetailseite
     * @return array[] Array von Traktandumsdaten
     */
    public function ladeTraktanden(string $sitzungUrl): array {
        $url = self::absolutUrl($sitzungUrl);
        if ($url === '') {
            return [];
        }

        try {
            $html = $this->ladeHtml($url);
            $entitaeten = $this->extrahiereEntitaeten($html, 'Traktanden');
            $eintraege = $this->normalisiereListenEntitaeten($entitaeten);
            $normalisiert = $this->normalisiereTraktandenEntitaeten($eintraege);
            if (!empty($normalisiert)) {
                return $normalisiert;
            }

            return $this->extrahiereTraktandenAusHtml($html);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler beim Laden von Traktanden: ' . $e->getMessage(),
                ['url' => $url, 'exception' => $e]
            );
            return [];
        }
    }

    /**
     * Lädt mehrere Sitzungs-Detailseiten parallel und extrahiert je Sitzung
     * die normalisierten Traktanden.
     *
     * @param array<int, string> $sitzungsUrls
     * @return array<string, array> Map: absolute URL → Traktanden-Array
     */
    public function ladeTraktandenJeUrlParallel(array $sitzungsUrls): array {
        $absoluteUrls = [];
        foreach ($sitzungsUrls as $roh) {
            $abs = self::absolutUrl((string) $roh);
            if ($abs !== '') {
                $absoluteUrls[$abs] = true;
            }
        }
        if ($absoluteUrls === []) {
            return [];
        }
        $urls = array_keys($absoluteUrls);
        $parallel = $this->leseParallelitaetAusEnv('PARLWIN_SYNC_SITZUNG_PARALLEL', 6, 1, 20);
        $htmlJeUrl = $this->ladeHtmlParallel($urls, $parallel);

        $ergebnisse = [];
        foreach ($urls as $url) {
            $html = $htmlJeUrl[$url] ?? '';
            if ($html === '') {
                $ergebnisse[$url] = [];
                continue;
            }
            try {
                $entitaeten = $this->extrahiereEntitaeten($html, 'Traktanden');
                $eintraege = $this->normalisiereListenEntitaeten($entitaeten);
                $normalisiert = $this->normalisiereTraktandenEntitaeten($eintraege);
                if ($normalisiert === []) {
                    $normalisiert = $this->extrahiereTraktandenAusHtml($html);
                }
                $ergebnisse[$url] = $normalisiert;
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Parlament Winterthur: Fehler beim parallelen Laden von Traktanden: ' . $e->getMessage(),
                    ['url' => $url, 'exception' => $e]
                );
                $ergebnisse[$url] = [];
            }
        }

        return $ergebnisse;
    }

    /**
     * Lädt alle Parlamentsmitglieder.
     *
     * @return array[] Array von Mitgliederdaten
     */
    public function ladeMitglieder(): array {
        $eintraege = $this->holePrefetchedListenEintraege('mitglieder');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::URLS['mitglieder'], 'Mitglieder');
            $eintraege = $this->normalisiereListenEntitaeten($roh);
        }
        $eintraege = $this->wendeSyncLimitAn('mitglieder', $eintraege);
        $normalisiert = [];

        foreach ($eintraege as $daten) {
            if (!is_array($daten)) {
                continue;
            }

            $personHtml = (string) self::wert($daten, ['_nameVorname', 'name', 'Name'], '');
            if ($personHtml === '' || !str_contains($personHtml, '/_rte/person/')) {
                continue;
            }

            $normalisiert[] = $this->normalisiereMitgliedszeile($daten);
        }

        return $normalisiert;
    }

    /**
     * Lädt alle Kommissionen inklusive ihrer Mitgliederliste (aktuelle und
     * ehemalige). Die Mitgliederlisten stammen aus der Behörden-Detailseite
     * `/_rte/behoerde/{externId}` und werden parallel geladen.
     *
     * @return array[] Array von Kommissionsdaten
     */
    public function ladeKommissionen(): array {
        $eintraege = $this->holePrefetchedListenEintraege('kommissionen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::URLS['kommissionen'], 'Kommissionen');
            $eintraege = $this->normalisiereListenEntitaeten($roh);
        }
        $eintraege = $this->wendeSyncLimitAn('kommissionen', $eintraege);
        $normalisiert = [];

        foreach ($eintraege as $daten) {
            if (!is_array($daten)) {
                continue;
            }
            $normalisiert[] = $this->normalisiereBehoerdenzeile($daten);
        }

        return $this->reichereBehoerdenMitMitgliedernAn(
            $normalisiert,
            'PARLWIN_SYNC_KOMMISSION_PARALLEL',
            'Kommission'
        );
    }

    /**
     * Reichert eine Liste normalisierter Behörden um deren Mitgliederlisten an,
     * indem die Detailseiten parallel geladen und ausgewertet werden.
     *
     * @param array<int, array<string, mixed>> $normalisiert
     * @return array<int, array<string, mixed>>
     */
    private function reichereBehoerdenMitMitgliedernAn(
        array $normalisiert,
        string $envName,
        string $labelPrefix
    ): array {
        $urlJeIndex = [];
        foreach ($normalisiert as $idx => $daten) {
            $externId = (string) ($daten['id'] ?? '');
            if ($externId !== '') {
                $urlJeIndex[$idx] = self::BASE_URL . '/_rte/behoerde/' . rawurlencode($externId);
            }
        }

        if ($urlJeIndex === []) {
            return $normalisiert;
        }

        $htmlJeUrl = $this->ladeHtmlParallel(
            array_values(array_unique($urlJeIndex)),
            $this->leseParallelitaetAusEnv($envName, 6, 1, 20)
        );
        foreach ($urlJeIndex as $idx => $url) {
            if (!isset($htmlJeUrl[$url])) {
                continue;
            }
            $normalisiert[$idx]['members'] = $this->extrahiereBehoerdenMitgliederAusHtml(
                $htmlJeUrl[$url],
                $labelPrefix . ' ' . ($normalisiert[$idx]['id'] ?? '')
            );
        }

        return $normalisiert;
    }

    /**
     * Lädt die Mitgliederliste einer einzelnen Behörde (Kommission, Fraktion,
     * Stadtparlament usw.) von der Detailseite `/_rte/behoerde/{externId}`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ladeBehoerdenMitglieder(string $externId): array {
        $externId = trim($externId);
        if ($externId === '') {
            return [];
        }
        $url = self::BASE_URL . '/_rte/behoerde/' . rawurlencode($externId);
        try {
            $html = $this->ladeHtml($url);
            return $this->extrahiereBehoerdenMitgliederAusHtml($html, "Behoerde {$externId}");
        } catch (\Throwable $e) {
            $this->logger->error(
                "Parlament Winterthur: Fehler beim Laden der Behörden-Mitglieder {$externId}: " . $e->getMessage(),
                ['url' => $url, 'exception' => $e]
            );
            return [];
        }
    }

    /**
     * Extrahiert aus der Behörden-Detailseite die Personenliste (aktive und
     * ehemalige Mitglieder). Pro Person wird genau ein Eintrag zurückgegeben;
     * falls die Person sowohl aktiv als auch ehemals gelistet ist, gewinnt
     * der aktive Eintrag.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extrahiereBehoerdenMitgliederAusHtml(string $html, string $label = 'Behoerde'): array {
        $entitaeten = $this->extrahiereEntitaeten($html, $label);
        $zeilen = $this->normalisiereListenEntitaeten($entitaeten);
        $mitglieder = [];
        $indexJeExternId = [];

        foreach ($zeilen as $zeile) {
            if (!is_array($zeile)) {
                continue;
            }
            if (!array_key_exists('_nameVorname', $zeile) && !array_key_exists('_mandatPersonDatumVon', $zeile)) {
                continue;
            }
            $mitglied = $this->normalisiereBehoerdenMitgliedszeile($zeile);
            if ($mitglied === null) {
                continue;
            }
            $externId = (string) $mitglied['externId'];
            if (isset($indexJeExternId[$externId])) {
                $vorhandenIdx = $indexJeExternId[$externId];
                if (!$mitglieder[$vorhandenIdx]['aktiv'] && $mitglied['aktiv']) {
                    $mitglieder[$vorhandenIdx] = $mitglied;
                }
                continue;
            }
            $indexJeExternId[$externId] = count($mitglieder);
            $mitglieder[] = $mitglied;
        }

        return $mitglieder;
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>|null
     */
    private function normalisiereBehoerdenMitgliedszeile(array $daten): ?array {
        $nameHtml = (string) self::wert($daten, ['_nameVorname', 'name', 'Name'], '');
        $link = self::extrahiereLinkAusHtml($nameHtml);
        $externId = $link['externId'];
        if ($externId === '') {
            $externId = (string) self::wert($daten, ['id', 'Id', 'ID', 'personId'], '');
        }
        if ($externId === '') {
            return null;
        }

        [$nachname, $vorname] = $this->normalisiereMitgliedsName($link['titel']);
        $funktionAktiv = self::bereinigeHtmlText((string) self::wert($daten, ['_funktionAktiv', 'funktionAktiv'], ''));
        $funktionInaktiv = self::bereinigeHtmlText((string) self::wert($daten, ['_funktionInaktiv', 'funktionInaktiv'], ''));
        $partei = self::bereinigeHtmlText((string) self::wert($daten, ['_partei', 'partei', 'party'], ''));
        $datumVon = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['_mandatPersonDatumVon', 'mandatPersonDatumVon', 'datumVon'], ''));
        $datumBis = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['_mandatPersonDatumBis', 'mandatPersonDatumBis', 'datumBis'], ''));
        $aktiv = $funktionAktiv !== '';

        $label = trim($vorname . ' ' . $nachname);
        if ($label === '') {
            $label = $link['titel'] !== '' ? $link['titel'] : self::bereinigeHtmlText($nameHtml);
        }

        return [
            'externId' => $externId,
            'name' => $nachname !== '' ? $nachname : $link['titel'],
            'vorname' => $vorname,
            'label' => $label,
            'funktion' => $aktiv ? $funktionAktiv : $funktionInaktiv,
            'partei' => $partei,
            'datumVon' => $datumVon,
            'datumBis' => $datumBis,
            'aktiv' => $aktiv,
        ];
    }

    /**
     * Lädt alle Fraktionen und Parteien.
     *
     * @return array[] Array von Fraktionsdaten
     */
    public function ladeFraktionen(): array {
        $eintraege = $this->holePrefetchedListenEintraege('fraktionen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::URLS['fraktionen'], 'Fraktionen');
            $eintraege = $this->normalisiereListenEntitaeten($roh);
        }
        $eintraege = $this->wendeSyncLimitAn('fraktionen', $eintraege);
        $normalisiert = [];

        foreach ($eintraege as $daten) {
            if (!is_array($daten)) {
                continue;
            }
            $zeile = $this->normalisiereBehoerdenzeile($daten);
            if (self::istPseudoFraktionsname((string) ($zeile['name'] ?? ''))) {
                continue;
            }
            $normalisiert[] = $zeile;
        }

        return $this->reichereBehoerdenMitMitgliedernAn(
            $normalisiert,
            'PARLWIN_SYNC_FRAKTION_PARALLEL',
            'Fraktion'
        );
    }

    /**
     * Erkennt Pseudo-Fraktionen (Rollen-Sammlungen wie "Fraktionspräsident/innen"),
     * die auf der Fraktionen-Seite gelistet sind, aber keine eigene Fraktion darstellen.
     */
    private function istKeineEchteFraktion(string $name): bool {
        $normalisiert = trim($name);
        if ($normalisiert === '') {
            return true;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($normalisiert, 'UTF-8') : strtolower($normalisiert);
        return str_contains($lower, 'präsident')
            || str_contains($lower, 'praesident')
            || str_contains($lower, 'protokoll');
    }

    /**
     * Erkennt Pseudo-Fraktionen (Rollen-Sammlungen wie "Fraktionspräsident/innen"),
     * die auf der Fraktionen-Seite gelistet sind, aber keine eigene Fraktion darstellen.
     */
    public static function istPseudoFraktionsname(string $name): bool {
        $normalisiert = trim($name);
        if ($normalisiert === '') {
            return true;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($normalisiert, 'UTF-8') : strtolower($normalisiert);
        return str_contains($lower, 'präsident')
            || str_contains($lower, 'praesident')
            || str_contains($lower, 'protokoll');
    }

    /**
     * Extrahiert Entitäten aus den data-entities-Attributen einer Seite.
     *
     * Die Parlamentswebseite bettet Daten als JSON in HTML-Attribute ein:
     * <div data-entities="[{...}, {...}]">
     *
     * @param string $url   URL der zu scrapenden Seite
     * @param string $label Bezeichnung für Log-Meldungen
     * @return array[]
     */
    private function ladeEntitaeten(string $url, string $label): array {
        try {
            $this->logger->debug("Parlament Winterthur: Lade {$label} von {$url}");

            $html = $this->ladeHtml($url);
            return $this->extrahiereEntitaeten($html, $label);
        } catch (\Throwable $e) {
            $this->logger->error(
                "Parlament Winterthur: Fehler beim Laden von {$label}: " . $e->getMessage(),
                ['url' => $url, 'exception' => $e]
            );
            return [];
        }
    }

    private function ladeHtml(string $url): string {
        $client = $this->clientService->newClient();
        $connectTimeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_CONNECT_TIMEOUT', 8, 1, 60);
        $timeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_TIMEOUT', 25, 5, 180);
        $response = $client->get($url, [
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => [
                'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'de-CH,de;q=0.9',
            ],
        ]);
        return $response->getBody();
    }

    /**
     * Parst HTML und extrahiert alle data-entities-Attribute.
     *
     * Sucht nach dem Muster data-entities="[...]" im HTML-Quelltext.
     * Da das Attribut HTML-kodiert sein kann, wird htmlspecialchars_decode
     * angewendet.
     *
     * @param string $html  HTML-Quelltext der Seite
     * @param string $label Bezeichnung für Log-Meldungen
     * @return array[]
     */
    public function extrahiereEntitaeten(string $html, string $label = ''): array {
        $entitaeten = [];

        // Suche nach data-entities="..." Attributen im HTML
        // Das Attribut kann HTML-enkodierte Anführungszeichen enthalten
        $muster = '/data-entities\s*=\s*"([^"]*(?:(?:&quot;|\\\\")[^"]*)*)"/i';

        if (preg_match_all($muster, $html, $treffer)) {
            foreach ($treffer[1] as $rohJson) {
                // HTML-Entitäten dekodieren (z.B. &quot; → ")
                $json = html_entity_decode($rohJson, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $daten = json_decode($json, true);
                if (is_array($daten)) {
                    // Kann ein einzelnes Objekt oder ein Array sein
                    if (isset($daten[0]) || empty($daten)) {
                        // Array von Entitäten
                        foreach ($daten as $entitaet) {
                            if (is_array($entitaet)) {
                                $entitaeten[] = $entitaet;
                            }
                        }
                    } else {
                        // Einzelne Entität
                        $entitaeten[] = $daten;
                    }
                } else {
                    $this->logger->warning(
                        "Parlament Winterthur: Ungültiges JSON in data-entities für {$label}",
                        ['json_fehler' => json_last_error_msg(), 'roh' => substr($rohJson, 0, 200)]
                    );
                }
            }
        }

        $this->logger->info(
            "Parlament Winterthur: {$label}: " . count($entitaeten) . " Entitäten gefunden"
        );

        return $entitaeten;
    }

    /**
     * Gibt den Wert eines Felds aus den Entitätsdaten zurück.
     * Unterstützt mehrere mögliche Schlüsselnamen.
     *
     * @param array    $daten    Entitätsdaten
     * @param string[] $schluessel Mögliche Schlüsselnamen (in Reihenfolge der Präferenz)
     * @param mixed    $standard Standardwert wenn nicht gefunden
     */
    public static function wert(array $daten, array $schluessel, mixed $standard = ''): mixed {
        foreach ($schluessel as $key) {
            if (isset($daten[$key]) && $daten[$key] !== null && $daten[$key] !== '') {
                return $daten[$key];
            }
        }
        return $standard;
    }

    /**
     * @param array<int, array<string, mixed>> $entitaeten
     * @return array<int, array<string, mixed>>
     */
    private function normalisiereGeschaeftslistenEntitaeten(array $entitaeten): array {
        return $this->normalisiereListenEntitaeten($entitaeten);
    }

    /**
     * @param array<int, array<string, mixed>> $entitaeten
     * @return array<int, array<string, mixed>>
     */
    private function normalisiereListenEntitaeten(array $entitaeten): array {
        $eintraege = [];

        foreach ($entitaeten as $entitaet) {
            if (isset($entitaet['data']) && is_array($entitaet['data'])) {
                foreach ($entitaet['data'] as $zeile) {
                    if (is_array($zeile)) {
                        $eintraege[] = $zeile;
                    }
                }
                continue;
            }

            $eintraege[] = $entitaet;
        }

        return $eintraege;
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function normalisiereSitzungszeile(array $daten): array {
        $nameHtml = (string) self::wert($daten, ['name', 'title', 'Name', 'Title'], '');
        $link = self::extrahiereLinkAusHtml($nameHtml);
        $url = $link['url'] !== ''
            ? $link['url']
            : self::absolutUrl((string) self::wert($daten, ['url', 'Url', 'URL', 'detailUrl', 'link'], ''));
        $externId = $link['externId'] !== ''
            ? $link['externId']
            : (string) self::wert($daten, ['id', 'Id', 'ID', 'guid'], '');

        $datumRaw = (string) self::wert($daten, ['_datum', 'date', 'Date', 'datum', 'Datum', 'startDate'], '');
        [$datum, $zeitVon, $zeitBis] = $this->extrahiereSitzungsDatumZeit($datumRaw);

        $normalisiert = $daten;
        $normalisiert['title'] = $link['titel'] !== '' ? $link['titel'] : self::bereinigeHtmlText($nameHtml);
        $normalisiert['url'] = $url;
        $normalisiert['id'] = $externId;
        $normalisiert['date'] = $datum !== '' ? $datum : $this->normalisiereDatum((string) self::wert($daten, ['date', 'Date', 'datum', 'Datum', 'startDate'], ''));
        $normalisiert['startTime'] = $zeitVon !== '' ? $zeitVon : (string) self::wert($daten, ['startTime', 'timeFrom', 'zeitVon', 'start'], '');
        $normalisiert['endTime'] = $zeitBis !== '' ? $zeitBis : (string) self::wert($daten, ['endTime', 'timeTo', 'zeitBis', 'end'], '');

        return $normalisiert;
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function normalisiereMitgliedszeile(array $daten): array {
        $nameHtml = (string) self::wert($daten, ['_nameVorname', 'name', 'Name'], '');
        $link = self::extrahiereLinkAusHtml($nameHtml);
        [$nachname, $vorname] = $this->normalisiereMitgliedsName($link['titel']);

        $partei = self::bereinigeHtmlText((string) self::wert($daten, ['_partei', 'partei', 'party', 'Party'], ''));
        $fraktion = (string) self::wert($daten, ['fraktion', 'Fraktion', 'faction', 'Faction', 'group'], '');
        if ($fraktion === '') {
            $fraktion = $this->extrahiereFraktionAusTaetigIn(
                (string) self::wert($daten, ['_taetigInAktiv', '_taetigInAlle', 'taetigIn'], '')
            );
        }

        $email = $this->extrahiereMailAusHtml((string) self::wert($daten, ['_kontakt', 'kontakt', 'contact'], ''));
        $fotoUrl = self::absolutUrl((string) self::wert($daten, ['_thumbnail', 'thumbnail', 'photo', 'photoUrl', 'image', 'imageUrl'], ''));
        $datumVon = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['_mandatPersonDatumVon', 'mandatPersonDatumVon', 'mandatVon', 'datumVon'], ''));
        $datumBis = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['_mandatPersonDatumBis', 'mandatPersonDatumBis', 'mandatBis', 'datumBis'], ''));
        $funktionAktiv = self::bereinigeHtmlText((string) self::wert($daten, ['_funktionAktiv', 'funktionAktiv'], ''));
        $funktionInaktiv = self::bereinigeHtmlText((string) self::wert($daten, ['_funktionInaktiv', 'funktionInaktiv'], ''));
        $taetigInAktiv = self::bereinigeHtmlText((string) self::wert($daten, ['_taetigInAktiv', 'taetigInAktiv'], ''));
        $aktivRoh = self::wert($daten, ['aktiv', 'active', 'isActive', 'is_active', 'status', '_status'], null);
        if ($funktionAktiv !== '' || $taetigInAktiv !== '') {
            $aktivRoh = 'aktiv';
        } elseif ($funktionInaktiv !== '') {
            $aktivRoh = 'inaktiv';
        }
        $aktiv = $this->istEintragAktiv($datumVon, $datumBis, $aktivRoh);

        $normalisiert = $daten;
        $normalisiert['id'] = $link['externId'] !== '' ? $link['externId'] : (string) self::wert($daten, ['id', 'Id', 'ID', 'personId'], '');
        $normalisiert['url'] = $link['url'];
        $normalisiert['name'] = $nachname !== '' ? $nachname : $link['titel'];
        $normalisiert['lastName'] = $nachname !== '' ? $nachname : $link['titel'];
        $normalisiert['vorname'] = $vorname;
        $normalisiert['firstName'] = $vorname;
        $normalisiert['party'] = $partei;
        $normalisiert['partei'] = $partei;
        $normalisiert['faction'] = $fraktion;
        $normalisiert['fraktion'] = $fraktion;
        $normalisiert['email'] = $email;
        $normalisiert['photo'] = $fotoUrl;
        $normalisiert['photoUrl'] = $fotoUrl;
        $normalisiert['datumVon'] = $datumVon;
        $normalisiert['datumBis'] = $datumBis;
        $normalisiert['aktiv'] = $aktiv;

        return $normalisiert;
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function normalisiereBehoerdenzeile(array $daten): array {
        $nameHtml = (string) self::wert($daten, ['name', 'title', 'Name', 'Title'], '');
        $link = self::extrahiereLinkAusHtml($nameHtml);
        $url = $link['url'] !== ''
            ? $link['url']
            : self::absolutUrl((string) self::wert($daten, ['url', 'Url', 'URL', 'detailUrl', 'link'], ''));
        $externId = $link['externId'] !== ''
            ? $link['externId']
            : (string) self::wert($daten, ['id', 'Id', 'ID', 'guid'], '');
        $datumVon = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['datumVon', '_datumVon', 'dateFrom', 'von'], ''));
        $datumBis = $this->normalisiereIsoDatumOderLeer((string) self::wert($daten, ['datumBis', '_datumBis', 'dateTo', 'bis'], ''));
        $aktiv = $this->istEintragAktiv($datumVon, $datumBis, self::wert($daten, ['aktiv', 'active', 'isActive', 'is_active', 'status'], null));

        $normalisiert = $daten;
        $normalisiert['id'] = $externId;
        $normalisiert['name'] = $link['titel'] !== '' ? $link['titel'] : self::bereinigeHtmlText($nameHtml);
        $normalisiert['url'] = $url;
        $normalisiert['datumVon'] = $datumVon;
        $normalisiert['datumBis'] = $datumBis;
        $normalisiert['aktiv'] = $aktiv;
        if (!isset($normalisiert['members']) && !isset($normalisiert['mitglieder']) && !isset($normalisiert['persons'])) {
            $normalisiert['members'] = [];
        }

        return $normalisiert;
    }

    /**
     * @param array<int, array<string, mixed>> $eintraege
     * @return array<int, array<string, mixed>>
     */
    private function normalisiereTraktandenEntitaeten(array $eintraege): array {
        $normalisiert = [];

        foreach ($eintraege as $daten) {
            if (!is_array($daten)) {
                continue;
            }

            $titelHtml = (string) self::wert($daten, ['title', 'Title', 'bezeichnung', 'name'], '');
            $titel = self::bereinigeHtmlText($titelHtml);
            $nummer = (int) self::wert($daten, ['number', 'Number', 'nummer', 'position'], 0);
            $beschreibung = (string) self::wert($daten, ['description', 'Description', 'beschreibung', 'text'], '');
            $typ = (string) self::wert($daten, ['type', 'Type', 'geschaeftsart', '_kategorieId'], '');
            $geschaeftUrl = self::absolutUrl((string) self::wert($daten, ['url', 'Url', 'URL', 'link', 'Link', 'geschaeftUrl'], ''));
            $geschaeftExternId = (string) self::wert($daten, ['businessId', 'geschaeftId', 'politBusinessId'], '');
            if ($geschaeftExternId === '' && $geschaeftUrl !== '') {
                $geschaeftExternId = self::extrahiereExternIdAusUrl($geschaeftUrl);
            }

            if ($titel === '' && $nummer === 0 && $geschaeftExternId === '') {
                continue;
            }

            $eintrag = $daten;
            $eintrag['title'] = $titel;
            $eintrag['number'] = $nummer;
            $eintrag['description'] = $beschreibung !== '' ? $beschreibung : $titel;
            $eintrag['type'] = $typ;
            if ($geschaeftUrl !== '') {
                $eintrag['url'] = $geschaeftUrl;
            }
            if ($geschaeftExternId !== '') {
                $eintrag['businessId'] = $geschaeftExternId;
            }

            $normalisiert[] = $eintrag;
        }

        return $normalisiert;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extrahiereTraktandenAusHtml(string $html): array {
        $dom = new \DOMDocument();
        $vorherigeErrors = libxml_use_internal_errors(true);
        $geladen = $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($vorherigeErrors);

        if ($geladen === false) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query("//tr[starts-with(@id, 'traktanden_')]");
        if ($rows === false) {
            return [];
        }

        $traktanden = [];

        foreach ($rows as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }

            $zellen = $xpath->query('./td', $row);
            if ($zellen === false || $zellen->length < 4) {
                continue;
            }

            $nummerText = self::bereinigeHtmlText($dom->saveHTML($zellen->item(0)) ?: '');
            $titel = self::bereinigeHtmlText($dom->saveHTML($zellen->item(1)) ?: '');
            $typ = self::bereinigeHtmlText($dom->saveHTML($zellen->item(2)) ?: '');
            $geschaeftLinkNode = $xpath->query(".//a[contains(@href, '/_rte/information/')]", $zellen->item(3))->item(0);
            $geschaeftUrl = '';
            $geschaeftNummer = '';
            if ($geschaeftLinkNode instanceof \DOMElement) {
                $geschaeftUrl = self::absolutUrl((string) $geschaeftLinkNode->getAttribute('href'));
                $geschaeftNummer = self::bereinigeHtmlText($geschaeftLinkNode->textContent);
            }
            $geschaeftExternId = self::extrahiereExternIdAusUrl($geschaeftUrl);
            $traktandumExternId = (string) preg_replace('/^traktanden_/', '', $row->getAttribute('id'));

            $traktanden[] = [
                'id' => $traktandumExternId,
                'number' => (int) preg_replace('/[^0-9]/', '', $nummerText),
                'title' => $titel,
                'description' => $titel,
                'type' => $typ,
                'businessId' => $geschaeftExternId,
                'businessNumber' => $geschaeftNummer,
                'url' => $geschaeftUrl,
            ];
        }

        return $traktanden;
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function normalisiereGeschaeftszeile(array $daten): array {
        $titelHtml = (string) self::wert($daten, ['title', 'Title', 'name', 'Name'], '');
        $link = self::extrahiereLinkAusHtml($titelHtml);
        $url = $link['url'] !== ''
            ? $link['url']
            : self::absolutUrl((string) self::wert($daten, ['url', 'Url', 'URL', 'link', 'Link', 'detailUrl'], ''));
        $externId = $link['externId'];
        if ($externId === '' && preg_match('#/_rte/information/(\d+)#', $url, $idMatch) === 1) {
            $externId = $idMatch[1];
        }
        $datum = (string) self::wert($daten, ['_geschaeftsdatum-sort', 'date', 'Date', '_geschaeftsdatum', 'datum', 'Datum'], '');

        $normalisiert = $daten;
        $normalisiert['title'] = $link['titel'] !== '' ? $link['titel'] : self::bereinigeHtmlText($titelHtml);
        $normalisiert['url'] = $url;
        $normalisiert['id'] = $externId !== '' ? $externId : (string) self::wert($daten, ['id', 'Id', 'ID'], '');
        $normalisiert['number'] = (string) self::wert($daten, ['number', 'Number', '_nummer', 'nummer'], '');
        $normalisiert['type'] = (string) self::wert($daten, ['type', 'Type', '_kategorieId', 'kategorieId'], '');
        $normalisiert['date'] = $this->normalisiereDatum($datum);

        return $normalisiert;
    }

    /**
     * @return array{titel: string, url: string, externId: string}
     */
    public static function extrahiereLinkAusHtml(string $html): array {
        $result = ['titel' => '', 'url' => '', 'externId' => ''];

        if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $match) === 1) {
            $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = self::bereinigeHtmlText($match[2]);
            $url = self::absolutUrl($href);
            $externId = self::extrahiereExternIdAusUrl($url);

            $result['titel'] = $text;
            $result['url'] = $url;
            $result['externId'] = $externId;
            return $result;
        }

        $result['titel'] = self::bereinigeHtmlText($html);
        return $result;
    }

    public static function extrahiereExternIdAusUrl(string $url): string {
        if ($url === '') {
            return '';
        }
        if (preg_match('#/_rte/[^/]+/(\d+)#', $url, $idMatch) === 1) {
            return $idMatch[1];
        }
        return '';
    }

    public static function bereinigeHtmlText(string $text): string {
        $dekodiert = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ohneTags = strip_tags($dekodiert);
        return trim((string) preg_replace('/\s+/u', ' ', $ohneTags));
    }

    public static function absolutUrl(string $url): string {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return self::BASE_URL . $url;
        }
        return self::BASE_URL . '/' . ltrim($url, '/');
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function extrahiereSitzungsDatumZeit(string $datumHtml): array {
        $datumText = self::bereinigeHtmlText($datumHtml);
        if (
            preg_match(
                '/(\d{1,2}\.\d{1,2}\.\d{4})(?:,\s*(\d{1,2}\.\d{2})\s*Uhr\s*-\s*(\d{1,2}\.\d{2})\s*Uhr)?/u',
                $datumText,
                $m
            ) === 1
        ) {
            $datum = $this->normalisiereDatum($m[1]);
            $zeitVon = isset($m[2]) ? str_replace('.', ':', $m[2]) : '';
            $zeitBis = isset($m[3]) ? str_replace('.', ':', $m[3]) : '';
            return [$datum, $zeitVon, $zeitBis];
        }

        return ['', '', ''];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalisiereMitgliedsName(string $name): array {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $teile = preg_split('/\s+/u', $name);
        if (!is_array($teile) || count($teile) < 2) {
            return [$name, ''];
        }

        $vorname = (string) array_pop($teile);
        $nachname = trim(implode(' ', $teile));
        return [$nachname, $vorname];
    }

    private function extrahiereFraktionAusTaetigIn(string $html): string {
        if ($html === '') {
            return '';
        }

        if (preg_match_all('/<a[^>]*>(.*?)<\/a>/is', $html, $treffer)) {
            foreach ($treffer[1] as $text) {
                $kandidat = self::bereinigeHtmlText((string) $text);
                if ($kandidat !== '' && str_contains($kandidat, 'Fraktion')) {
                    return $kandidat;
                }
            }
        }

        return '';
    }

    private function extrahiereMailAusHtml(string $html): string {
        if ($html === '') {
            return '';
        }

        if (preg_match('/mailto:([^"\'>\s]+)/i', $html, $match) === 1) {
            return trim($match[1]);
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function holePrefetchedListenEintraege(string $bereich): ?array {
        if (!isset($this->prefetchedListen[$bereich])) {
            return null;
        }
        return $this->prefetchedListen[$bereich];
    }

    private function leseParallelitaetAusEnv(string $envKey, int $standard, int $min, int $max): int {
        return $this->leseIntAusEnv($envKey, $standard, $min, $max);
    }

    private function leseIntAusEnv(string $envKey, int $standard, int $min, int $max): int {
        $wert = trim((string) getenv($envKey));
        if ($wert === '') {
            return $standard;
        }
        if (!ctype_digit($wert)) {
            return $standard;
        }
        $parsed = (int) $wert;
        if ($parsed < $min) {
            return $min;
        }
        if ($parsed > $max) {
            return $max;
        }
        return $parsed;
    }

    /**
     * @param array<string, string> $ziele
     * @return array<string, string>
     */
    private function ladeHtmlFuerBereicheParallel(array $ziele, int $parallel): array {
        if ($ziele === []) {
            return [];
        }

        $urls = array_values(array_unique(array_values($ziele)));
        $htmlJeUrl = $this->ladeHtmlParallel($urls, $parallel);
        $result = [];
        foreach ($ziele as $bereich => $url) {
            if (isset($htmlJeUrl[$url])) {
                $result[$bereich] = $htmlJeUrl[$url];
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, string>
     */
    private function ladeHtmlParallel(array $urls, int $parallel, bool $mitSequenziellemFallback = true): array
    {
        $urls = array_values(array_unique(array_filter($urls, static fn(string $u): bool => $u !== '')));
        if ($urls === []) {
            return [];
        }

        if ($parallel <= 1 || !$this->kannNativeParallelDownloads()) {
            return $this->ladeHtmlSequenziell($urls);
        }

        $ergebnisse = [];
        $queue = $urls;
        $mh = curl_multi_init();
        $aktiv = [];
        $next = 0;

        $starteHandle = function (string $url) use (&$mh, &$aktiv): void {
            $connectTimeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_CONNECT_TIMEOUT', 8, 1, 60);
            $timeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_TIMEOUT', 25, 5, 180);
            $lowSpeedTime = min($timeout, $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_LOW_SPEED_TIME', 12, 3, 120));
            $lowSpeedLimit = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_LOW_SPEED_LIMIT', 1, 1, 1024);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_LOW_SPEED_TIME => $lowSpeedTime,
                CURLOPT_LOW_SPEED_LIMIT => $lowSpeedLimit,
                CURLOPT_USERAGENT => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: de-CH,de;q=0.9',
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $aktiv[(int) $ch] = ['handle' => $ch, 'url' => $url];
        };

        while ($next < count($queue) || $aktiv !== []) {
            while ($next < count($queue) && count($aktiv) < $parallel) {
                $starteHandle($queue[$next]);
                $next++;
            }

            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while (($info = curl_multi_info_read($mh)) !== false) {
                $ch = $info['handle'];
                $id = (int) $ch;
                $url = (string) ($aktiv[$id]['url'] ?? '');
                $body = (string) curl_multi_getcontent($ch);
                $errno = curl_errno($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($url !== '' && $errno === 0 && $httpCode >= 200 && $httpCode < 400 && $body !== '') {
                    $ergebnisse[$url] = $body;
                } elseif ($url !== '') {
                    $this->logger->warning('Parlament Winterthur: Parallel-Download fehlgeschlagen', [
                        'url' => $url,
                        'errno' => $errno,
                        'httpCode' => $httpCode,
                    ]);
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($aktiv[$id]);
            }

            if ($running > 0) {
                curl_multi_select($mh, 1.0);
            }
        }

        curl_multi_close($mh);

        // Optionaler Fallback für einzelne fehlgeschlagene URLs.
        if ($mitSequenziellemFallback && count($ergebnisse) < count($urls)) {
            $fehlend = array_values(array_diff($urls, array_keys($ergebnisse)));
            $ergebnisse += $this->ladeHtmlSequenziell($fehlend);
        }

        return $ergebnisse;
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, string>
     */
    private function ladeHtmlSequenziell(array $urls): array {
        $ergebnisse = [];
        foreach ($urls as $url) {
            try {
                $ergebnisse[$url] = $this->ladeHtml($url);
            } catch (\Throwable $e) {
                $this->logger->warning('Parlament Winterthur: Download fehlgeschlagen', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $ergebnisse;
    }

    private function kannNativeParallelDownloads(): bool
    {
        if (!function_exists('curl_multi_init') || !function_exists('curl_multi_exec') || !function_exists('curl_init')) {
            return false;
        }
        if (
            interface_exists('\\PHPUnit\\Framework\\MockObject\\MockObject')
            && $this->clientService instanceof \PHPUnit\Framework\MockObject\MockObject
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, array<string, mixed>>
     */
    private function ladeGeschaeftDetailsParallel(array $urls, int $parallel, ?callable $beiErfolg = null): array {
        $result = [];
        $zuLaden = [];

        foreach ($urls as $url) {
            if (isset($this->geschaeftDetailCache[$url])) {
                $result[$url] = $this->geschaeftDetailCache[$url];
                if ($beiErfolg !== null) {
                    $beiErfolg($url);
                }
                continue;
            }
            $zuLaden[] = $url;
        }

        if ($zuLaden !== []) {
            if ($parallel <= 1 || !$this->kannNativeParallelDownloads()) {
                foreach ($zuLaden as $url) {
                    try {
                        $details = $this->extrahiereGeschaeftDetailsAusHtml($this->ladeHtml($url));
                    } catch (\Throwable $e) {
                        $this->logger->warning('Parlament Winterthur: Download fehlgeschlagen', [
                            'url' => $url,
                            'error' => $e->getMessage(),
                        ]);
                        $details = [];
                    }
                    $this->geschaeftDetailCache[$url] = $details;
                    $result[$url] = $details;
                    if ($beiErfolg !== null) {
                        $beiErfolg($url);
                    }
                }
            } else {
                // In kleinen Batches arbeiten, damit Fortschritt laufend sichtbar bleibt.
                $batchSize = max(1, $parallel);
                foreach (array_chunk($zuLaden, $batchSize) as $batchUrls) {
                    $htmlJeUrl = $this->ladeHtmlParallel($batchUrls, $parallel, false);
                    foreach ($batchUrls as $url) {
                        if (isset($htmlJeUrl[$url])) {
                            $details = $this->extrahiereGeschaeftDetailsAusHtml($htmlJeUrl[$url]);
                        } else {
                            try {
                                $details = $this->extrahiereGeschaeftDetailsAusHtml($this->ladeHtml($url));
                            } catch (\Throwable $e) {
                                $this->logger->warning('Parlament Winterthur: Download fehlgeschlagen', [
                                    'url' => $url,
                                    'error' => $e->getMessage(),
                                ]);
                                $details = [];
                            }
                        }
                        $this->geschaeftDetailCache[$url] = $details;
                        $result[$url] = $details;
                        if ($beiErfolg !== null) {
                            $beiErfolg($url);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function ladeGeschaeftDetails(string $url): array {
        if (isset($this->geschaeftDetailCache[$url])) {
            return $this->geschaeftDetailCache[$url];
        }

        try {
            $html = $this->ladeHtml($url);
            $details = $this->extrahiereGeschaeftDetailsAusHtml($html);
            $this->geschaeftDetailCache[$url] = $details;
            return $details;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Parlament Winterthur: Konnte Geschaeftsdetails nicht laden',
                ['url' => $url, 'error' => $e->getMessage()]
            );
            $this->geschaeftDetailCache[$url] = [];
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function extrahiereGeschaeftDetailsAusHtml(string $html): array {
        $details = [];
        $detailPaare = [];
        $detailFelder = [];
        $ereignisse = [];
        $pattern = '/<dt>(.*?)<\/dt>\s*<dd>(.*?)<\/dd>/is';
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $index = 0;
            foreach ($matches as $match) {
                $label = self::bereinigeHtmlText($match[1]);
                $wert = self::bereinigeHtmlText($match[2]);
                if ($label === '') {
                    continue;
                }

                $index++;
                $detailPaare[] = [
                    'sequence' => $index,
                    'label' => $label,
                    'value' => $wert,
                ];
                if (!isset($detailFelder[$label])) {
                    $detailFelder[$label] = $wert;
                } elseif (is_array($detailFelder[$label])) {
                    $detailFelder[$label][] = $wert;
                } else {
                    $detailFelder[$label] = [$detailFelder[$label], $wert];
                }

                if ($label === 'Nummer' && $wert !== '') {
                    $details['number'] = $wert;
                } elseif ($label === 'Geschäftsart' && $wert !== '') {
                    $details['type'] = $wert;
                } elseif ($label === 'Status' && $wert !== '') {
                    $details['status'] = $wert;
                } elseif ($label === 'Eingangsdatum' && $wert !== '') {
                    $details['date'] = $this->normalisiereDatum($wert);
                }

                $ereignis = $this->klassifiziereGeschaeftDetailEintrag($index, $label, $wert);
                if ($ereignis !== null) {
                    $ereignisse[] = $ereignis;
                }
            }
        }

        if ($detailPaare !== []) {
            $details['detailPairs'] = $detailPaare;
        }
        if ($detailFelder !== []) {
            $details['detailFields'] = $detailFelder;
        }
        if ($ereignisse !== []) {
            $details['events'] = $ereignisse;
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $daten
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function uebernehmeDetailDaten(array $daten, array $details): array {
        $mitDetails = $daten;

        foreach ($details as $key => $value) {
            if (in_array($key, ['detailPairs', 'detailFields', 'events'], true)) {
                $mitDetails[$key] = $value;
                continue;
            }

            if (is_string($value) && $value !== '') {
                $mitDetails[$key] = $value;
            } elseif (!array_key_exists($key, $mitDetails)) {
                $mitDetails[$key] = $value;
            }
        }

        return $mitDetails;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function klassifiziereGeschaeftDetailEintrag(int $index, string $label, string $wert): ?array {
        if ($label === '' && $wert === '') {
            return null;
        }

        $typ = 'info';
        $organ = '';
        $datum = $this->normalisiereDatum($wert);
        if ($datum === $wert) {
            $datum = '';
        }

        if ($label === 'Status') {
            $typ = 'status';
        } elseif ($label === 'Nummer') {
            $typ = 'nummer';
        } elseif ($label === 'Geschäftsart') {
            $typ = 'geschaeftsart';
        } elseif ($label === 'Eingangsdatum') {
            $typ = 'eingang';
            $datum = $this->normalisiereDatum($wert);
        } elseif (preg_match('/^Beschlussdatum (.+)$/u', $label, $m) === 1) {
            $typ = 'beschlussdatum';
            $organ = trim($m[1]);
            $datum = $this->normalisiereDatum($wert);
        } elseif (preg_match('/^Beschlussart (.+)$/u', $label, $m) === 1) {
            $typ = 'beschlussart';
            $organ = trim($m[1]);
        } elseif (preg_match('/^Beschluss (.+)$/u', $label, $m) === 1) {
            $typ = 'beschluss';
            $organ = trim($m[1]);
        } elseif (preg_match('/^Abstimmungsresultat (.+)$/u', $label, $m) === 1) {
            $typ = 'abstimmungsresultat';
            $organ = trim($m[1]);
        } elseif (preg_match('/^Frist /u', $label) === 1) {
            $typ = 'frist';
            $datum = $this->normalisiereDatum($wert);
            if ($datum === $wert) {
                $datum = '';
            }
        } elseif ($label === 'Antrag und Bericht vom' || $label === 'Antrag vom') {
            $typ = 'antrag_bericht';
            $datum = $this->normalisiereDatum($wert);
        } elseif ($label === 'Beantwortung durch Stadtrat vom') {
            $typ = 'beantwortung';
            $organ = 'Stadtrat';
            $datum = $this->normalisiereDatum($wert);
        } elseif ($label === 'Geschäft in Vorberatung bei') {
            $typ = 'vorberatung';
        } elseif ($label === 'Bemerkungen') {
            $typ = 'bemerkung';
        }

        return [
            'sequence' => $index,
            'type' => $typ,
            'organ' => $organ,
            'label' => $label,
            'value' => $wert,
            'date' => $datum,
        ];
    }

    private function normalisiereDatum(string $datum): string {
        $datum = trim($datum);
        if ($datum === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) === 1) {
            return $datum;
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $datum, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        $deMonate = [
            'januar' => 1,
            'februar' => 2,
            'märz' => 3,
            'april' => 4,
            'mai' => 5,
            'juni' => 6,
            'juli' => 7,
            'august' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'dezember' => 12,
        ];

        if (preg_match('/^(\d{1,2})\.\s*([[:alpha:]äöüÄÖÜ]+)\s+(\d{4})$/u', $datum, $m) === 1) {
            $tag = (int) $m[1];
            if (function_exists('mb_strtolower')) {
                $monatName = mb_strtolower($m[2]);
            } else {
                $monatName = strtolower(strtr($m[2], ['Ä' => 'ä', 'Ö' => 'ö', 'Ü' => 'ü']));
            }
            $jahr = (int) $m[3];
            if (isset($deMonate[$monatName])) {
                return sprintf('%04d-%02d-%02d', $jahr, $deMonate[$monatName], $tag);
            }
        }

        return $datum;
    }

    private function normalisiereIsoDatumOderLeer(string $datum): string {
        $normalisiert = $this->normalisiereDatum($datum);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalisiert) === 1) {
            return $normalisiert;
        }
        return '';
    }

    private function istEintragAktiv(string $datumVon, string $datumBis, mixed $aktivRoh): bool {
        if (is_bool($aktivRoh)) {
            return $aktivRoh;
        }

        if (is_string($aktivRoh)) {
            $status = strtolower(trim($aktivRoh));
            if (in_array($status, ['active', 'aktiv', 'true', '1'], true)) {
                return true;
            }
            if (in_array($status, ['inactive', 'inaktiv', 'false', '0'], true)) {
                return false;
            }
        }

        $heute = (new \DateTimeImmutable('today'))->format('Y-m-d');
        if ($datumVon !== '' && $datumVon > $heute) {
            return false;
        }
        if ($datumBis !== '' && $datumBis < $heute) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $eintraege
     * @return array<int, array<string, mixed>>
     */
    private function wendeSyncLimitAn(string $bereich, array $eintraege): array {
        $limit = $this->liesSyncLimit($bereich);
        if ($limit === null) {
            return $eintraege;
        }
        return array_slice($eintraege, 0, $limit);
    }

    private function liesSyncLimit(string $bereich): ?int {
        $bereich = strtoupper(trim($bereich));
        $bereichsLimit = getenv('PARLWIN_SYNC_LIMIT_' . $bereich);
        $globalesLimit = getenv('PARLWIN_SYNC_LIMIT_ALL');
        $wert = $bereichsLimit !== false && $bereichsLimit !== '' ? $bereichsLimit : $globalesLimit;
        if ($wert === false || $wert === '') {
            return null;
        }
        if (!ctype_digit((string) $wert)) {
            return null;
        }
        $limit = (int) $wert;
        if ($limit <= 0) {
            return null;
        }
        return $limit;
    }
}
