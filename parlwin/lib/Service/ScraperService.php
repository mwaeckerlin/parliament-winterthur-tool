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
class ScraperService
{
    /** Die Quelle im Betrieb: die Webseite des Parlaments. */
    private const BASE_URL_STANDARD = 'https://parlament.winterthur.ch';

    /** So vielen Weiterleitungen folgt ein Abruf höchstens. */
    private const WEITERLEITUNGEN_HOECHSTENS = 5;

    /** Pfade der verschiedenen Datenquellen, unter der Basis-Adresse. */
    private const PFADE = [
        'geschaefte' => '/politbusiness',
        'sitzungen' => '/sitzung',
        'mitglieder' => '/stadtparlament/27428',
        'kommissionen' => '/kommissionen',
        'fraktionen' => '/fraktionen',
    ];

    /**
     * Die Adresse der Datenquelle.
     *
     * Im Betrieb die Webseite des Parlaments, im Testlauf eine lokale
     * Gegenstelle: `PARLWIN_QUELLE_BASE_URL` zeigt dorthin, und dann holt der
     * Abgleich die gespeicherten Seiten statt 1236 Detailseiten von der Stadt.
     * Ein Schrägstrich am Ende fällt weg, damit jede zusammengesetzte Adresse
     * genau einen trägt.
     */
    public static function basisUrl(): string
    {
        $wert = trim((string) getenv('PARLWIN_QUELLE_BASE_URL'));
        return $wert === '' ? self::BASE_URL_STANDARD : rtrim($wert, '/');
    }

    /** Die Adresse einer Liste, oder null für einen unbekannten Bereich. */
    private static function listenUrl(string $bereich): ?string
    {
        return isset(self::PFADE[$bereich]) ? self::basisUrl() . self::PFADE[$bereich] : null;
    }

    /** Monatsnamen, wie sie in den Dokumenttiteln der Parlamentswebseite stehen */
    private const MONATE = [
        'januar' => 1,
        'februar' => 2,
        'märz' => 3,
        'maerz' => 3,
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

    /**
     * Die Adresse nach den Weiterleitungen, je angefragter Adresse, nur wo sie
     * von der angefragten abweicht.
     *
     * @var array<string, string>
     */
    private array $weitergeleitet = [];

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
    public function prefetchTopLevelListen(array $bereiche = ['geschaefte', 'sitzungen', 'mitglieder', 'kommissionen', 'fraktionen']): void
    {
        $ziele = [];
        foreach ($bereiche as $bereich) {
            $url = self::listenUrl($bereich);
            if ($url === null) {
                continue;
            }
            if (isset($this->prefetchedListen[$bereich])) {
                continue;
            }
            $ziele[$bereich] = $url;
        }

        if ($ziele === []) {
            return;
        }

        $antworten = $this->ladeHtmlFuerBereicheParallel($ziele, $this->leseParallelitaetAusEnv('PARLWIN_SYNC_SECTION_PARALLEL', 6, 1, 12));
        foreach ($antworten as $bereich => $html) {
            if (self::listenUrl($bereich) === null) {
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
    public function vorabTotalsFuerSync(): array
    {
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
    public function ladeGeschaefte(?callable $fortschritt = null): array
    {
        $eintraege = $this->holePrefetchedListenEintraege('geschaefte');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::basisUrl() . self::PFADE['geschaefte'], 'Geschäfte');
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
    public function ladeSitzungen(): array
    {
        $eintraege = $this->holePrefetchedListenEintraege('sitzungen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::basisUrl() . self::PFADE['sitzungen'], 'Sitzungen');
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
    public function ladeTraktanden(string $sitzungUrl): array
    {
        return $this->ladeSitzungsseite($sitzungUrl)['traktanden'];
    }

    /**
     * Lädt eine Sitzungsdetailseite und gibt beides zurück, was sie trägt:
     * die Traktanden und die Dokumente der Sitzung (Protokoll, Beschlüsse,
     * Traktandenliste).
     *
     * @return array{traktanden: array<int, array<string, mixed>>, dokumente: array<int, array{titel: string, url: string, datum: string}>}
     */
    public function ladeSitzungsseite(string $sitzungUrl): array
    {
        $url = self::absolutUrl($sitzungUrl);
        if ($url === '') {
            return ['traktanden' => [], 'dokumente' => []];
        }

        try {
            return $this->zerlegeSitzungsseite($this->ladeHtml($url));
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler beim Laden von Traktanden: ' . $e->getMessage(),
                ['url' => $url, 'exception' => $e]
            );
            return ['traktanden' => [], 'dokumente' => []];
        }
    }

    /**
     * Lädt mehrere Sitzungs-Detailseiten parallel und extrahiert je Sitzung
     * die normalisierten Traktanden.
     *
     * @param array<int, string> $sitzungsUrls
     * @param callable|null $onProgress Wird nach jedem geladenen Detail aufgerufen ($url, $erfolg, $erledigt, $gesamt)
     * @return array<string, array> Map: absolute URL → Traktanden-Array
     */
    public function ladeTraktandenJeUrlParallel(array $sitzungsUrls, ?callable $onProgress = null): array
    {
        return array_map(
            static fn (array $seite): array => $seite['traktanden'],
            $this->ladeSitzungsseitenJeUrlParallel($sitzungsUrls, $onProgress)
        );
    }

    /**
     * Lädt mehrere Sitzungs-Detailseiten parallel und zerlegt jede in
     * Traktanden und Dokumente der Sitzung.
     *
     * @param array<int, string> $sitzungsUrls
     * @param callable|null $onProgress Wird nach jedem geladenen Detail aufgerufen ($url, $erfolg, $erledigt, $gesamt)
     * @return array<string, array{traktanden: array, dokumente: array}> Map: absolute URL → Seitendaten
     */
    public function ladeSitzungsseitenJeUrlParallel(array $sitzungsUrls, ?callable $onProgress = null): array
    {
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
        $htmlJeUrl = $this->ladeHtmlParallel($urls, $parallel, true, $onProgress);

        $ergebnisse = [];
        foreach ($urls as $url) {
            $html = $htmlJeUrl[$url] ?? '';
            if ($html === '') {
                $ergebnisse[$url] = ['traktanden' => [], 'dokumente' => []];
                continue;
            }
            try {
                $ergebnisse[$url] = $this->zerlegeSitzungsseite($html);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Parlament Winterthur: Fehler beim parallelen Laden von Traktanden: ' . $e->getMessage(),
                    ['url' => $url, 'exception' => $e]
                );
                $ergebnisse[$url] = ['traktanden' => [], 'dokumente' => []];
            }
        }

        return $ergebnisse;
    }

    /**
     * @return array{traktanden: array<int, array<string, mixed>>, dokumente: array<int, array{titel: string, url: string, datum: string}>}
     */
    private function zerlegeSitzungsseite(string $html): array
    {
        $entitaeten = $this->extrahiereEntitaeten($html, 'Traktanden');
        $eintraege = $this->normalisiereListenEntitaeten($entitaeten);
        $traktanden = $this->normalisiereTraktandenEntitaeten($eintraege);
        if ($traktanden === []) {
            $traktanden = $this->extrahiereTraktandenAusHtml($html);
        }

        return [
            'traktanden' => $traktanden,
            'dokumente' => $this->extrahiereSitzungsdokumenteAusHtml($html),
        ];
    }

    /**
     * Lädt alle Parlamentsmitglieder.
     *
     * @return array[] Array von Mitgliederdaten
     */
    public function ladeMitglieder(): array
    {
        $eintraege = $this->holePrefetchedListenEintraege('mitglieder');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::basisUrl() . self::PFADE['mitglieder'], 'Mitglieder');
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
    public function ladeKommissionen(): array
    {
        $eintraege = $this->holePrefetchedListenEintraege('kommissionen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::basisUrl() . self::PFADE['kommissionen'], 'Kommissionen');
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
                $urlJeIndex[$idx] = self::basisUrl() . '/_rte/behoerde/' . rawurlencode($externId);
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
    public function ladeBehoerdenMitglieder(string $externId): array
    {
        $externId = trim($externId);
        if ($externId === '') {
            return [];
        }
        $url = self::basisUrl() . '/_rte/behoerde/' . rawurlencode($externId);
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
    public function extrahiereBehoerdenMitgliederAusHtml(string $html, string $label = 'Behoerde'): array
    {
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
    private function normalisiereBehoerdenMitgliedszeile(array $daten): ?array
    {
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
    public function ladeFraktionen(): array
    {
        $eintraege = $this->holePrefetchedListenEintraege('fraktionen');
        if ($eintraege === null) {
            $roh = $this->ladeEntitaeten(self::basisUrl() . self::PFADE['fraktionen'], 'Fraktionen');
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
    private function istKeineEchteFraktion(string $name): bool
    {
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
    public static function istPseudoFraktionsname(string $name): bool
    {
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
    private function ladeEntitaeten(string $url, string $label): array
    {
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

    private function ladeHtml(string $url): string
    {
        $client = $this->clientService->newClient();
        $connectTimeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_CONNECT_TIMEOUT', 8, 1, 60);
        $timeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_TIMEOUT', 25, 5, 180);
        $effektiv = $url;
        $response = $client->get($url, [
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => [
                'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'de-CH,de;q=0.9',
            ],
            // Guzzle ruft `on_redirect` vor jeder Weiterleitung; eine Adresse
            // ausserhalb der Basis bricht den Abruf ab, bevor sie angefragt wird.
            'allow_redirects' => [
                'max' => self::WEITERLEITUNGEN_HOECHSTENS,
                'protocols' => ['http', 'https'],
                'on_redirect' => static function ($anfrage, $antwort, $ziel) use ($url): void {
                    if (!self::darfWeiterleiten($url, (string) $ziel)) {
                        throw new \RuntimeException('Die Weiterleitung von ' . $url . ' nach ' . $ziel . ' verlässt die Quelle.');
                    }
                },
            ],
            // Guzzle meldet hier die Adresse nach den Weiterleitungen.
            'on_stats' => static function (object $stats) use (&$effektiv): void {
                if (method_exists($stats, 'getEffectiveUri')) {
                    $effektiv = (string) $stats->getEffectiveUri();
                }
            },
        ]);
        $this->merkeEffektiveAdresse($url, $effektiv);
        return $response->getBody();
    }

    /**
     * Ob der Abruf von `$url` der Weiterleitung nach `$ziel` folgen darf: nur
     * innerhalb der Basis, zu der `$url` gehört, also der Stadt-Webseite, der
     * Parlamentswebseite oder, für jede andere Adresse, ihres eigenen Rechners.
     */
    private static function darfWeiterleiten(string $url, string $ziel): bool
    {
        $basis = self::basisVon($url);
        return $basis !== '' && str_starts_with($ziel, $basis . '/');
    }

    /** Die Basis, unter der eine Adresse liegt, ohne Schrägstrich am Ende. */
    private static function basisVon(string $url): string
    {
        try {
            if (self::liegtUnterStadtBasis($url)) {
                return self::stadtBasisUrl();
            }
        } catch (\RuntimeException) {
            // Eine ungültige Basis der Stadt gilt für keine Adresse.
        }
        if (str_starts_with($url, self::basisUrl() . '/')) {
            return self::basisUrl();
        }
        $teile = parse_url($url);
        if (!is_array($teile) || !in_array(strtolower((string) ($teile['scheme'] ?? '')), ['http', 'https'], true) || ($teile['host'] ?? '') === '') {
            return '';
        }
        return strtolower((string) $teile['scheme']) . '://' . $teile['host'] . (isset($teile['port']) ? ':' . $teile['port'] : '');
    }

    private function merkeEffektiveAdresse(string $url, string $effektiv): void
    {
        if ($effektiv !== '' && $effektiv !== $url) {
            $this->weitergeleitet[$url] = $effektiv;
        } else {
            unset($this->weitergeleitet[$url]);
        }
    }

    /** Die Adresse, unter der die Antwort auf `$url` zuletzt tatsächlich kam. */
    private function effektiveAdresse(string $url): string
    {
        return $this->weitergeleitet[$url] ?? $url;
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
    public function extrahiereEntitaeten(string $html, string $label = ''): array
    {
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
    public static function wert(array $daten, array $schluessel, mixed $standard = ''): mixed
    {
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
    private function normalisiereGeschaeftslistenEntitaeten(array $entitaeten): array
    {
        return $this->normalisiereListenEntitaeten($entitaeten);
    }

    /**
     * @param array<int, array<string, mixed>> $entitaeten
     * @return array<int, array<string, mixed>>
     */
    private function normalisiereListenEntitaeten(array $entitaeten): array
    {
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
    private function normalisiereSitzungszeile(array $daten): array
    {
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
    private function normalisiereMitgliedszeile(array $daten): array
    {
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
    private function normalisiereBehoerdenzeile(array $daten): array
    {
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
    private function normalisiereTraktandenEntitaeten(array $eintraege): array
    {
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
    public function extrahiereTraktandenAusHtml(string $html): array
    {
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
            $titelHtml = $dom->saveHTML($zellen->item(1)) ?: '';
            // Metadaten-Suffix (Dokumentdatum, Kategorie, Download-Link) ab erstem <br> abschneiden
            $titelVorBr = preg_split('/<br\s*\/?>/ui', $titelHtml)[0] ?? $titelHtml;
            $titel = self::bereinigeHtmlText($titelVorBr);
            if ($titel === '') {
                $titel = self::bereinigeHtmlText($titelHtml);
            }
            // Dokument-URL aus Titel-Zelle extrahieren (für Protokolle, Beschlüsse etc. ohne Geschäft-Link)
            $titelLink = self::extrahiereLinkAusHtml($titelHtml);
            // Nur externe Dokument-Links speichern – Geschäft-Links (/_rte/information/ID) werden via businessId abgedeckt
            $istDokumentLink = $titelLink['externId'] === '' && $titelLink['url'] !== '';
            $traktandumUrl = $istDokumentLink ? $titelLink['url'] : '';
            // Der Titel des Dokuments nennt beim Protokoll die protokollierte Sitzung
            // («Protokoll Stadtparlament vom 10. November 2025 - Entwurf»).
            $dokumentTitel = $istDokumentLink ? $titelLink['titel'] : '';
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
                'traktandumUrl' => $traktandumUrl,
                'dokumentTitel' => $dokumentTitel,
            ];
        }

        return $traktanden;
    }

    /**
     * Extrahiert die Dokumente, die an der Sitzung selbst hängen (Protokoll,
     * Beschlüsse, Traktandenliste). Dokumente einzelner Traktanden gehören dem
     * Traktandum und bleiben hier aussen vor.
     *
     * @return array<int, array{titel: string, url: string, datum: string}>
     */
    public function extrahiereSitzungsdokumenteAusHtml(string $html): array
    {
        $dom = new \DOMDocument();
        $vorherigeErrors = libxml_use_internal_errors(true);
        $geladen = $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($vorherigeErrors);

        if ($geladen === false) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $links = $xpath->query("//a[contains(@href, '/_doc')][not(ancestor::tr[starts-with(@id, 'traktanden_')])]");
        if ($links === false) {
            return [];
        }

        $dokumente = [];
        foreach ($links as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            // Der Titel steht im title-Attribut; der Text ist beim zweiten Link
            // der Zeile nur «Download».
            $titel = self::bereinigeHtmlText($link->getAttribute('title'));
            if ($titel === '') {
                $titel = self::bereinigeHtmlText($link->textContent);
            }
            if ($titel === '' || strcasecmp($titel, 'Download') === 0) {
                continue;
            }
            $url = self::absolutUrl((string) $link->getAttribute('href'));
            if ($url === '') {
                continue;
            }
            $dokumente[$url . '|' . $titel] = [
                'titel' => $titel,
                'url' => $url,
                'datum' => self::datumAusText($titel),
            ];
        }

        return array_values($dokumente);
    }

    /**
     * Wählt aus den Dokumenten einer Sitzung ihr eigenes Protokoll: es trägt
     * «Protokoll» im Titel und nennt darin das Datum dieser Sitzung. Das
     * abgenommene Protokoll geht dem Entwurf vor.
     *
     * @param array<int, array<string, mixed>> $dokumente
     * @return array{titel: string, url: string, datum: string}|null
     */
    public static function waehleProtokoll(array $dokumente, string $sitzungsDatum): ?array
    {
        $datum = substr(trim($sitzungsDatum), 0, 10);
        if ($datum === '') {
            return null;
        }

        $treffer = [];
        foreach ($dokumente as $dokument) {
            if (!is_array($dokument)) {
                continue;
            }
            $titel = (string) ($dokument['titel'] ?? '');
            if (!self::istProtokollTitel($titel) || (string) ($dokument['datum'] ?? '') !== $datum) {
                continue;
            }
            $treffer[] = [
                'titel' => $titel,
                'url' => (string) ($dokument['url'] ?? ''),
                'datum' => $datum,
            ];
        }

        foreach ($treffer as $dokument) {
            if (!self::istEntwurfTitel($dokument['titel'])) {
                return $dokument;
            }
        }

        return $treffer[0] ?? null;
    }

    /** Nennt der Titel ein Protokoll? */
    public static function istProtokollTitel(string $titel): bool
    {
        return str_contains(self::kleinbuchstaben($titel), 'protokoll');
    }

    /** Nennt der Titel einen Entwurf (das noch nicht abgenommene Protokoll)? */
    public static function istEntwurfTitel(string $titel): bool
    {
        return str_contains(self::kleinbuchstaben($titel), 'entwurf');
    }

    /**
     * Liest das Datum aus einem Dokumenttitel — «vom 10. November 2025» ebenso
     * wie «vom 02.03.2026». Ohne erkennbares Datum leer.
     */
    public static function datumAusText(string $text): string
    {
        if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u', $text, $treffer) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $treffer[3], (int) $treffer[2], (int) $treffer[1]);
        }

        if (preg_match('/(\d{1,2})\.\s*([A-Za-zÄÖÜäöü]+)\s+(\d{4})/u', $text, $treffer) === 1) {
            $monat = self::MONATE[self::kleinbuchstaben($treffer[2])] ?? 0;
            if ($monat > 0) {
                return sprintf('%04d-%02d-%02d', (int) $treffer[3], $monat, (int) $treffer[1]);
            }
        }

        return '';
    }

    private static function kleinbuchstaben(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function normalisiereGeschaeftszeile(array $daten): array
    {
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
    public static function extrahiereLinkAusHtml(string $html): array
    {
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

    public static function extrahiereExternIdAusUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (preg_match('#/_rte/[^/]+/(\d+)#', $url, $idMatch) === 1) {
            return $idMatch[1];
        }
        return '';
    }

    /**
     * Text aus HTML, ohne Auszeichnung und mit einfachen Leerzeichen.
     *
     * `\s` trifft unter `/u` auch das geschützte Leerzeichen (U+00A0), mit dem
     * die Webseite der Stadt ihre Daten schreibt: «28.&nbsp;Oktober&nbsp;2026».
     */
    public static function bereinigeHtmlText(string $text): string
    {
        $dekodiert = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($dekodiert)));
    }

    public static function absolutUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return self::basisUrl() . $url;
        }
        return self::basisUrl() . '/' . ltrim($url, '/');
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function extrahiereSitzungsDatumZeit(string $datumHtml): array
    {
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
    private function normalisiereMitgliedsName(string $name): array
    {
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

    private function extrahiereFraktionAusTaetigIn(string $html): string
    {
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

    private function extrahiereMailAusHtml(string $html): string
    {
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
    private function holePrefetchedListenEintraege(string $bereich): ?array
    {
        if (!isset($this->prefetchedListen[$bereich])) {
            return null;
        }
        return $this->prefetchedListen[$bereich];
    }

    private function leseParallelitaetAusEnv(string $envKey, int $standard, int $min, int $max): int
    {
        return $this->leseIntAusEnv($envKey, $standard, $min, $max);
    }

    private function leseIntAusEnv(string $envKey, int $standard, int $min, int $max): int
    {
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
    private function ladeHtmlFuerBereicheParallel(array $ziele, int $parallel): array
    {
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
     * @param callable|null $onComplete Wird nach jedem fertigen Download aufgerufen ($url, $erfolg, $erledigt, $gesamt)
     * @return array<string, string>
     */
    protected function ladeHtmlParallel(array $urls, int $parallel, bool $mitSequenziellemFallback = true, ?callable $onComplete = null): array
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

        // curl folgt keiner Weiterleitung selbst: Jede Location wird erst gegen
        // die Basis der angefragten Adresse geprüft und dann neu angefragt.
        $starteHandle = function (string $url, string $ziel, int $schritte) use (&$mh, &$aktiv): void {
            $connectTimeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_CONNECT_TIMEOUT', 8, 1, 60);
            $timeout = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_TIMEOUT', 25, 5, 180);
            $lowSpeedTime = min($timeout, $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_LOW_SPEED_TIME', 12, 3, 120));
            $lowSpeedLimit = $this->leseIntAusEnv('PARLWIN_SYNC_HTTP_LOW_SPEED_LIMIT', 1, 1, 1024);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $ziel,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
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
            $aktiv[(int) $ch] = ['handle' => $ch, 'url' => $url, 'ziel' => $ziel, 'schritte' => $schritte];
        };

        while ($next < count($queue) || $aktiv !== []) {
            while ($next < count($queue) && count($aktiv) < $parallel) {
                $starteHandle($queue[$next], $queue[$next], 0);
                $next++;
            }

            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while (($info = curl_multi_info_read($mh)) !== false) {
                $ch = $info['handle'];
                $id = (int) $ch;
                $url = (string) ($aktiv[$id]['url'] ?? '');
                $ziel = (string) ($aktiv[$id]['ziel'] ?? $url);
                $schritte = (int) ($aktiv[$id]['schritte'] ?? 0);
                $body = (string) curl_multi_getcontent($ch);
                $errno = curl_errno($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $weiter = $httpCode >= 300 && $httpCode < 400 ? (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL) : '';
                curl_multi_remove_handle($mh, $ch);
                unset($aktiv[$id]);
                if ($url !== '' && $errno === 0 && $weiter !== '') {
                    if ($schritte < self::WEITERLEITUNGEN_HOECHSTENS && self::darfWeiterleiten($url, $weiter)) {
                        $starteHandle($url, $weiter, $schritte + 1);
                        continue;
                    }
                    $this->logger->warning('Parlament Winterthur: Weiterleitung nicht gefolgt', [
                        'url' => $url,
                        'nach' => $weiter,
                        'schritte' => $schritte,
                    ]);
                }
                $erfolg = false;
                if ($url !== '' && $errno === 0 && $weiter === '' && $httpCode >= 200 && $httpCode < 300 && $body !== '') {
                    $ergebnisse[$url] = $body;
                    $this->merkeEffektiveAdresse($url, $ziel);
                    $erfolg = true;
                } elseif ($url !== '' && $weiter === '') {
                    $this->logger->warning('Parlament Winterthur: Parallel-Download fehlgeschlagen', [
                        'url' => $url,
                        'errno' => $errno,
                        'httpCode' => $httpCode,
                    ]);
                }

                // Kein curl_close(): Seit PHP 8.0 wirkt es nicht mehr, und seit
                // 8.5 ist es verworfen. Der Handle verschwindet mit $aktiv.

                if ($onComplete !== null && $url !== '') {
                    try {
                        $onComplete($url, $erfolg, count($ergebnisse), count($urls));
                    } catch (\Throwable $e) {
                        $this->logger->warning('Parlament Winterthur: onComplete-Callback fehlgeschlagen: ' . $e->getMessage());
                    }
                }
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
    private function ladeHtmlSequenziell(array $urls): array
    {
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
    /**
     * Liest die amtlichen Dokumente aus der Seite eines Geschäfts.
     *
     * Die Seite führt sie in einer Tabelle; jede Zeile trägt den Verweis auf das
     * PDF («/_doc/<id>»), seine Bezeichnung, Grösse, Dokumentdatum und
     * Kategorie («Vorstoss», «Antrag Stadtrat», «Antwort Stadtrat», «Beilage»,
     * «Pressemitteilung»). Die zweite Zelle derselben Zeile wiederholt den
     * Verweis als Download-Knopf und trägt keine Kategorie — daran werden die
     * beiden unterschieden.
     *
     * @return array<int, array{externId: string, titel: string, url: string, kategorie: string, datum: string}>
     */
    public function extrahiereGeschaeftDokumenteAusHtml(string $html): array
    {
        $dokumente = [];
        $gesehen = [];

        if (preg_match_all('#<td[^>]*>(.*?)</td>#is', $html, $zellen) === false) {
            return [];
        }

        foreach ($zellen[1] as $zelle) {
            if (!str_contains($zelle, '/_doc/') || !str_contains($zelle, 'Kategorie:')) {
                continue;
            }
            if (preg_match('#<a[^>]*href="([^"]*/_doc/(\d+))"[^>]*>(.*?)</a>#is', $zelle, $link) !== 1) {
                continue;
            }
            $externId = $link[2];
            if (isset($gesehen[$externId])) {
                continue;
            }
            $gesehen[$externId] = true;

            $kategorie = '';
            if (preg_match('/Kategorie:\s*([^<]*)/u', $zelle, $k) === 1) {
                $kategorie = self::bereinigeHtmlText($k[1]);
            }
            $datum = '';
            if (preg_match('/Dokumentdatum:\s*(?:<br\s*\/?>)?\s*([^<]*)/iu', $zelle, $d) === 1) {
                $datum = $this->normalisiereIsoDatumOderLeer(self::bereinigeHtmlText($d[1]));
            }

            $dokumente[] = [
                'externId' => $externId,
                'titel' => self::bereinigeHtmlText($link[3]),
                'url' => self::absolutUrl($link[1]),
                'kategorie' => $kategorie,
                'datum' => $datum,
            ];
        }

        return $dokumente;
    }

    /**
     * Parst das Feld «Verfasser/Beteiligte» aus dem rohen HTML einer Geschäfts-Detailseite.
     *
     * Rückgabe: Array von ['name' => string, 'rolle' => string, 'externId' => string]
     * «rolle» ist z.B. «Erstunterzeichner/-in» oder «Mitunterzeichner/-in».
     *
     * @return array<int, array{name: string, rolle: string, externId: string}>
     */
    private function parseVerfasserBeteiligte(string $rawHtml): array
    {
        $einreicher = [];

        // Persons with a link to /_rte/person/{id}
        if (preg_match_all(
            '/<a[^>]*href="([^"]*)"[^>]*>\s*([^<]*)<\/a>\s*\(([^)]+)\)/u',
            $rawHtml,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $match) {
                $externId = '';
                if (preg_match('#/person/(\d+)#', $match[1], $idMatch) === 1) {
                    $externId = $idMatch[1];
                }
                $einreicher[] = [
                    'name' => self::bereinigeHtmlText($match[2]),
                    'rolle' => self::entgendere(self::bereinigeHtmlText($match[3])),
                    'externId' => $externId,
                ];
            }
        }

        // Persons without a link: remove the linked portions, parse remaining plain text
        $remaining = (string) preg_replace('/<a[^>]*>.*?<\/a>\s*\([^)]*\)/su', '', $rawHtml);
        if (preg_match_all('/(?:^|,)\s*([^,<(]+?)\s*\(([^)]+)\)/u', $remaining, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $name = self::bereinigeHtmlText($match[1]);
                if ($name !== '') {
                    $einreicher[] = [
                        'name' => $name,
                        'rolle' => self::entgendere(self::bereinigeHtmlText($match[2])),
                        'externId' => '',
                    ];
                }
            }
        }

        return $einreicher;
    }

    /**
     * Reduziert Schrägstrich-Gendern auf das generische Maskulinum, z.B.
     * «Erstunterzeichner/-in» → «Erstunterzeichner», «Einreicher/innen» →
     * «Einreicher». Regel: nie gendern.
     */
    public static function entgendere(string $text): string
    {
        return preg_replace('#/-?in(?:nen)?\b#u', '', $text) ?? $text;
    }

    private function ladeGeschaeftDetailsParallel(array $urls, int $parallel, ?callable $beiErfolg = null): array
    {
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
    private function ladeGeschaeftDetails(string $url): array
    {
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
    public function extrahiereGeschaeftDetailsAusHtml(string $html): array
    {
        $details = [];
        $detailPaare = [];
        $detailFelder = [];
        $ereignisse = [];
        $pattern = '/<dt>(.*?)<\/dt>\s*<dd>(.*?)<\/dd>/is';
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $index = 0;
            foreach ($matches as $match) {
                $label = self::bereinigeHtmlText($match[1]);
                $rawWert = $match[2];
                $wert = self::bereinigeHtmlText($rawWert);
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
                } elseif ($label === 'Verfasser/Beteiligte' && $wert !== '') {
                    $details['einreicher'] = $this->parseVerfasserBeteiligte($rawWert);
                }

                $ereignis = $this->klassifiziereGeschaeftDetailEintrag($index, $label, $wert);
                if ($ereignis !== null) {
                    $ereignisse[] = $ereignis;
                }
            }
        }

        $dokumente = $this->extrahiereGeschaeftDokumenteAusHtml($html);
        if ($dokumente !== []) {
            $details['dokumente'] = $dokumente;
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
    private function uebernehmeDetailDaten(array $daten, array $details): array
    {
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
    private function klassifiziereGeschaeftDetailEintrag(int $index, string $label, string $wert): ?array
    {
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

    private function normalisiereDatum(string $datum): string
    {
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

    private function normalisiereIsoDatumOderLeer(string $datum): string
    {
        $normalisiert = $this->normalisiereDatum($datum);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalisiert) === 1) {
            return $normalisiert;
        }
        return '';
    }

    private function istEintragAktiv(string $datumVon, string $datumBis, mixed $aktivRoh): bool
    {
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
    private function wendeSyncLimitAn(string $bereich, array $eintraege): array
    {
        $limit = $this->liesSyncLimit($bereich);
        if ($limit === null) {
            return $eintraege;
        }
        return array_slice($eintraege, 0, $limit);
    }

    private function liesSyncLimit(string $bereich): ?int
    {
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

    // ------------------------------------------------------------------
    // Stadtratsbeschlüsse (F146)
    //
    // Die Beschlüsse des Stadtrats stehen auf der Webseite der Stadt und nicht
    // auf der des Parlaments, darum eine eigene Basis-Adresse. Der Aufbau ist
    // eine Suchliste mit zwölf Einträgen je Seite: ein Link je Beschluss, der
    // Nummer und Titel in einer Überschrift trägt, dazu Datum und Departement,
    // und eine Detailseite mit dem Departement, dem Datum der Veröffentlichung
    // und den PDF-Dokumenten.
    // ------------------------------------------------------------------

    /** Die Quelle im Betrieb: die Webseite der Stadt. */
    private const BASE_URL_STADT_STANDARD = 'https://stadt.winterthur.ch';

    /** Der Pfad der Liste der Stadtratsbeschlüsse, unter der Basis-Adresse der Stadt. */
    private const PFAD_STADTRATSBESCHLUESSE = '/stadtratsbeschluesse';

    /** So viele Beschlüsse führt eine Listenseite der Stadt. */
    private const BESCHLUESSE_JE_SEITE = 12;

    /**
     * Die Adresse der Stadt-Webseite.
     *
     * Im Testlauf zeigt `PARLWIN_STADT_BASE_URL` auf die lokale Gegenstelle,
     * gleich wie `PARLWIN_QUELLE_BASE_URL` für die Parlamentswebseite. Erlaubt
     * ist eine Adresse mit http oder https und einem Rechnernamen, ohne
     * Benutzer, Abfrage und Anker.
     *
     * @throws \RuntimeException wenn die Einstellung keine solche Adresse ist
     */
    public static function stadtBasisUrl(): string
    {
        $wert = trim((string) getenv('PARLWIN_STADT_BASE_URL'));
        if ($wert === '') {
            return self::BASE_URL_STADT_STANDARD;
        }
        $teile = parse_url($wert);
        $mangel = match (true) {
            !is_array($teile) => 'sie lässt sich nicht lesen',
            !in_array(strtolower((string) ($teile['scheme'] ?? '')), ['http', 'https'], true) => 'sie beginnt nicht mit http:// oder https://',
            trim((string) ($teile['host'] ?? '')) === '' => 'ihr fehlt der Rechnername',
            isset($teile['user']) || isset($teile['pass']) => 'sie enthält Benutzer oder Passwort',
            isset($teile['query']) => 'sie enthält eine Abfrage mit «?»',
            isset($teile['fragment']) => 'sie enthält einen Anker mit «#»',
            default => '',
        };
        if ($mangel !== '') {
            throw new \RuntimeException(
                'PARLWIN_STADT_BASE_URL ist keine gültige Adresse der Stadt-Webseite: ' . $mangel
                . '. Erlaubt ist etwa https://stadt.winterthur.ch.'
            );
        }
        return rtrim($wert, '/');
    }

    /** Ob eine Adresse unter der Basis der Stadt-Webseite liegt. */
    private static function liegtUnterStadtBasis(string $url): bool
    {
        return str_starts_with($url, self::stadtBasisUrl() . '/');
    }

    /** Macht eine Adresse der Stadt-Webseite absolut. */
    public static function stadtAbsolutUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return self::stadtBasisUrl() . '/' . ltrim($url, '/');
    }

    /**
     * Wie viele Listenseiten der Abgleich höchstens liest.
     *
     * Die Quelle führt 3160 Beschlüsse auf 264 Seiten. Eine Fraktion arbeitet
     * mit den jüngsten, darum liest der Abgleich standardmässig die ersten zehn
     * Seiten, also 120 Beschlüsse; `PARLWIN_SRB_SEITEN` setzt eine andere Zahl,
     * und 0 heisst: alle Seiten.
     */
    public function beschlussSeitenGrenze(): int
    {
        $standard = 10;
        $wert = getenv('PARLWIN_SRB_SEITEN');
        if ($wert === false || trim((string) $wert) === '') {
            return $standard;
        }
        $wert = trim((string) $wert);
        if (!ctype_digit($wert)) {
            // Ein Tippfehler im Deployment soll nicht lautlos zum Standard
            // führen: der Betreiber sucht sonst die Wirkung seiner Einstellung.
            $this->logger->warning(sprintf(
                'Parlament Winterthur: PARLWIN_SRB_SEITEN ist «%s» und keine Zahl; gelesen werden %d Seiten.',
                $wert,
                $standard
            ));
            return $standard;
        }
        return (int) $wert;
    }

    /**
     * Liest die Stadtratsbeschlüsse von der Webseite der Stadt.
     *
     * Eine unerreichbare Quelle und eine Listenseite ohne einen einzigen
     * Beschluss sind Fehler und keine leere Antwort: sonst meldete der Abgleich
     * «0 gelesen, 0 neu» als Erfolg, und niemand erfuhr, dass die Adresse falsch
     * ist oder die Quelle ihren Aufbau geändert hat.
     *
     * @param ?callable $fortschritt Wird je gelesener Seite gerufen: (int $gelesen, int $gesamt).
     *        Der Zähler läuft über Listen- und Detailseiten durch; bis die Zahl
     *        der Beschlüsse feststeht, rechnet er mit zwölf je Listenseite.
     * @return array<int, array<string, mixed>> Je Beschluss Nummer, Titel, Datum,
     *         Departement, Sitzung, Adresse, Datum der Veröffentlichung und Dokumente
     * @throws \RuntimeException wenn die Quelle nicht antwortet oder nichts hergibt
     */
    public function ladeStadtratsbeschluesse(?callable $fortschritt = null): array
    {
        $listenUrl = self::stadtBasisUrl() . self::PFAD_STADTRATSBESCHLUESSE;
        try {
            $ersteSeite = $this->ladeHtml($listenUrl);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Stadtratsbeschlüsse nicht erreichbar: ' . $e->getMessage(),
                ['url' => $listenUrl, 'exception' => $e]
            );
            throw new \RuntimeException(self::nichtErreichbar($listenUrl, $e), 0, $e);
        }
        $effektiv = $this->effektiveAdresse($listenUrl);
        if (!self::liegtUnterStadtBasis($effektiv)) {
            throw new \RuntimeException(
                'Die Liste der Stadtratsbeschlüsse unter ' . $listenUrl . ' leitet auf ' . $effektiv
                . ' weiter, und diese Adresse liegt nicht unter ' . self::stadtBasisUrl() . '/.'
            );
        }

        $seitenGesamt = $this->extrahiereLetzteSeitenzahl($ersteSeite);
        $grenze = $this->beschlussSeitenGrenze();
        $zuLesen = $grenze === 0 ? $seitenGesamt : min($grenze, $seitenGesamt);

        $eintraege = $this->extrahiereStadtratsbeschluesseAusHtml($ersteSeite);
        if ($eintraege === []) {
            throw new \RuntimeException(
                'Die Liste der Stadtratsbeschlüsse unter ' . $listenUrl
                . ' nennt keinen einzigen Beschluss. Die Quelle hat ihren Aufbau geändert,'
                . ' oder die Adresse zeigt auf die falsche Seite.'
            );
        }
        $geschaetzt = $zuLesen * (1 + self::BESCHLUESSE_JE_SEITE);
        if ($fortschritt !== null) {
            $fortschritt(1, $geschaetzt);
        }

        $weitereUrls = [];
        for ($seite = 2; $seite <= $zuLesen; $seite++) {
            $weitereUrls[] = $listenUrl . '?page=' . $seite;
        }
        if ($weitereUrls !== []) {
            $gelesen = 1;
            $seitenHtml = $this->ladeHtmlParallel(
                $weitereUrls,
                $this->leseParallelitaetAusEnv('PARLWIN_SYNC_PARALLEL_LISTEN', 4, 1, 16),
                true,
                function () use (&$gelesen, $geschaetzt, $fortschritt): void {
                    $gelesen++;
                    if ($fortschritt !== null) {
                        $fortschritt($gelesen, $geschaetzt);
                    }
                }
            );
            foreach ($weitereUrls as $url) {
                $html = $seitenHtml[$url] ?? '';
                if ($html !== '' && $this->kamVonDerStadt($url)) {
                    $eintraege = array_merge($eintraege, $this->extrahiereStadtratsbeschluesseAusHtml($html));
                }
            }
        }

        $eintraege = $this->wendeSyncLimitAn('stadtratsbeschluesse', $eintraege);
        return $this->reichereBeschluesseMitDetailsAn($eintraege, $fortschritt, $zuLesen);
    }

    /** Gründe von curl, nach seiner Fehlernummer, für die Meldung an die Verwaltung. */
    private const CURL_GRUENDE = [
        6 => 'Rechnername nicht gefunden',
        7 => 'keine Verbindung zum Rechner',
        28 => 'Zeitüberschreitung',
        35 => 'TLS-Verbindung gescheitert',
        52 => 'leere Antwort',
        56 => 'Verbindung abgebrochen',
        60 => 'Zertifikat nicht anerkannt',
    ];

    /**
     * Die Meldung für eine Liste der Stadtratsbeschlüsse, die nicht kam. Sie
     * nennt die Adresse und den HTTP-Status oder den Grund des Netzfehlers, nie
     * den Rumpf der Antwort: Die Ausnahme von Guzzle hängt dessen Anfang an
     * ihre Meldung, bei einer Fehlerseite also HTML.
     */
    private static function nichtErreichbar(string $url, \Throwable $e): string
    {
        $anfang = 'Die Liste der Stadtratsbeschlüsse unter ' . $url . ' ist nicht erreichbar';
        $antwort = method_exists($e, 'getResponse') ? $e->getResponse() : null;
        if (is_object($antwort) && method_exists($antwort, 'getStatusCode')) {
            return $anfang . ' (HTTP ' . (int) $antwort->getStatusCode() . ').';
        }
        $kontext = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];
        $nummer = (int) ($kontext['errno'] ?? 0);
        if ($nummer > 0) {
            return $anfang . ' (' . (self::CURL_GRUENDE[$nummer] ?? 'Netzfehler, curl-Fehler ' . $nummer) . ').';
        }
        $grund = trim(strip_tags((string) strtok($e->getMessage(), "\n")));
        return $anfang . ($grund === '' ? '.' : ' (' . $grund . ').');
    }

    /**
     * Ob die Antwort auf `$url` nach allen Weiterleitungen unter der Basis der
     * Stadt-Webseite lag. Eine Antwort von anderswo wird verworfen.
     */
    private function kamVonDerStadt(string $url): bool
    {
        $effektiv = $this->effektiveAdresse($url);
        if (self::liegtUnterStadtBasis($effektiv)) {
            return true;
        }
        $this->logger->warning(
            'Parlament Winterthur: Seite der Stadtratsbeschlüsse leitet aus der Quelle hinaus und wird verworfen',
            ['url' => $url, 'effektiv' => $effektiv, 'basis' => self::stadtBasisUrl()]
        );
        return false;
    }

    /**
     * Holt je Beschluss die Detailseite und übernimmt Departement, Datum der
     * Veröffentlichung und die Dokumente. `detailseiteGelesen` sagt je
     * Beschluss, ob das gelang. Der Fortschritt zählt nach den `$listenseiten`
     * weiter.
     *
     * @param array<int, array<string, mixed>> $eintraege
     * @return array<int, array<string, mixed>>
     */
    private function reichereBeschluesseMitDetailsAn(array $eintraege, ?callable $fortschritt = null, int $listenseiten = 0): array
    {
        $urls = [];
        foreach ($eintraege as $index => $eintrag) {
            $eintraege[$index]['detailseiteGelesen'] = false;
            $url = (string) ($eintrag['url'] ?? '');
            if ($url !== '' && self::liegtUnterStadtBasis($url)) {
                $urls[] = $url;
            } elseif ($url !== '') {
                $this->logger->warning(
                    'Parlament Winterthur: Stadtratsbeschluss mit fremder Adresse übersprungen',
                    ['url' => $url, 'basis' => self::stadtBasisUrl()]
                );
            }
        }
        if ($urls === []) {
            return $eintraege;
        }

        $urls = array_values(array_unique($urls));
        $gelesen = 0;
        $gesamt = $listenseiten + count($urls);
        $seiten = $this->ladeHtmlParallel(
            $urls,
            $this->leseParallelitaetAusEnv('PARLWIN_SYNC_PARALLEL_DETAILS', 8, 1, 32),
            true,
            function () use (&$gelesen, $gesamt, $listenseiten, $fortschritt): void {
                $gelesen++;
                if ($fortschritt !== null) {
                    $fortschritt($listenseiten + $gelesen, $gesamt);
                }
            }
        );

        foreach ($eintraege as $index => $eintrag) {
            $url = (string) ($eintrag['url'] ?? '');
            $html = $seiten[$url] ?? '';
            if ($html === '' || !$this->kamVonDerStadt($url)) {
                continue;
            }
            $eintraege[$index] = array_merge(
                $eintrag,
                $this->extrahiereStadtratsbeschlussDetailsAusHtml($html),
                ['detailseiteGelesen' => true]
            );
        }
        return $eintraege;
    }

    /**
     * Die Zahl der Listenseiten, aus den Seitenlinks der Paginierung.
     *
     * Die Quelle verlinkt die erste, die zweite, die dritte und die letzte
     * Seite; die grösste dieser Zahlen ist die Gesamtzahl.
     */
    public function extrahiereLetzteSeitenzahl(string $html): int
    {
        if (!preg_match_all('#\?page=(\d+)#', $html, $treffer)) {
            return 1;
        }
        $zahlen = array_map('intval', $treffer[1]);
        return $zahlen === [] ? 1 : max(1, max($zahlen));
    }

    /**
     * Zerlegt eine Listenseite der Stadtratsbeschlüsse.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extrahiereStadtratsbeschluesseAusHtml(string $html): array
    {
        $muster = '#<a[^>]+href="(' . preg_quote(self::PFAD_STADTRATSBESCHLUESSE, '#')
            . '/(\d{4}-\d{2}-\d{2})/(\d+))"[^>]*>(.*?)</a>#s';
        preg_match_all($muster, $html, $treffer, PREG_SET_ORDER);

        $eintraege = [];
        foreach ($treffer as $t) {
            [$_, $pfad, $datumAusPfad, $externId, $block] = $t;

            $titelRoh = '';
            if (preg_match('#<h3[^>]*>(.*?)</h3>#s', $block, $h3) === 1) {
                $titelRoh = self::bereinigeHtmlText($h3[1]);
            }
            [$nummer, $titel] = self::teileNummerUndTitel($titelRoh);

            $datum = $datumAusPfad;
            if (preg_match('#<time[^>]+datetime="(\d{4}-\d{2}-\d{2})#', $block, $zeit) === 1) {
                $datum = $zeit[1];
            }

            $departement = '';
            if (preg_match_all('#<li class="inline dot-list-item[^"]*">(.*?)</li>#s', $block, $punkte)) {
                foreach ($punkte[1] as $punkt) {
                    $text = self::bereinigeHtmlText($punkt);
                    // Der erste Punkt trägt das Datum, der zweite das Departement.
                    if ($text !== '' && !str_contains($punkt, '<time')) {
                        $departement = $text;
                        break;
                    }
                }
            }

            $sitzung = '';
            if (preg_match('#(Stadtratssitzung vom [^<]{3,60})#u', $block, $s) === 1) {
                $sitzung = self::bereinigeHtmlText($s[1]);
            }

            $eintraege[$externId] = [
                'externId' => $externId,
                'nummer' => $nummer,
                'titel' => $titel,
                'datum' => $datum,
                'departement' => $departement,
                'sitzung' => $sitzung,
                'url' => self::stadtAbsolutUrl($pfad),
                'publiziertAm' => '',
                'dokumente' => [],
            ];
        }
        return array_values($eintraege);
    }

    /**
     * Trennt «2026/1279 Festlegung …» in Nummer und Titel.
     *
     * @return array{0: string, 1: string}
     */
    public static function teileNummerUndTitel(string $text): array
    {
        $text = trim($text);
        if (preg_match('#^(\d{4}/\d+)\s+(.*)$#s', $text, $m) === 1) {
            return [$m[1], trim($m[2])];
        }
        return ['', $text];
    }

    /**
     * Zerlegt die Detailseite eines Stadtratsbeschlusses.
     *
     * @return array<string, mixed> Departement, Datum der Veröffentlichung,
     *         Sitzung und die Dokumente
     */
    public function extrahiereStadtratsbeschlussDetailsAusHtml(string $html): array
    {
        $details = [];

        if (preg_match_all('#<th[^>]*>(.*?)</th>\s*<td[^>]*>(.*?)</td>#s', $html, $zeilen, PREG_SET_ORDER)) {
            foreach ($zeilen as $zeile) {
                $label = self::kleinbuchstaben(self::bereinigeHtmlText($zeile[1]));
                $wert = self::bereinigeHtmlText($zeile[2]);
                if ($wert === '') {
                    continue;
                }
                if ($label === 'departement') {
                    $details['departement'] = $wert;
                } elseif ($label === 'datum beschluss') {
                    $details['sitzung'] = $wert;
                } elseif ($label === 'datum veröffentlichung') {
                    $details['publiziertAm'] = self::datumAusText($wert);
                }
            }
        }

        $dokumente = [];
        // Nur die Dokumente des Beschlusses: die Quelle führt sie unter
        // «/download/resolution-document/<id>/<name>.pdf». Ein anderer Verweis
        // auf «/download/» im Kopf oder im Fuss der Seite ist keines.
        $muster = '#<a[^>]+href="(/download/resolution-document/[^"]+)"[^>]*>(.*?)</a>#s';
        if (preg_match_all($muster, $html, $treffer, PREG_SET_ORDER)) {
            foreach ($treffer as $t) {
                $titel = '';
                if (preg_match('#<div class="font-bold"[^>]*>(.*?)</div>#s', $t[2], $fett) === 1) {
                    $titel = self::bereinigeHtmlText($fett[1]);
                }
                if ($titel === '') {
                    $titel = basename($t[1]);
                }
                $dokumente[] = [
                    'titel' => $titel,
                    'url' => self::stadtAbsolutUrl($t[1]),
                ];
            }
        }
        $details['dokumente'] = $dokumente;

        return $details;
    }
}
