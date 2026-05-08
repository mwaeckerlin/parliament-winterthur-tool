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

    public function __construct(
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Lädt alle Geschäfte von der Parlamentswebseite.
     *
     * @return array[] Array von Geschäftsdaten
     */
    public function ladeGeschaefte(): array {
        return $this->ladeEntitaeten(self::URLS['geschaefte'], 'Geschäfte');
    }

    /**
     * Lädt alle Sitzungen von der Parlamentswebseite.
     *
     * @return array[] Array von Sitzungsdaten
     */
    public function ladeSitzungen(): array {
        return $this->ladeEntitaeten(self::URLS['sitzungen'], 'Sitzungen');
    }

    /**
     * Lädt die Traktanden einer Sitzung.
     *
     * @param string $sitzungUrl URL der Sitzungsdetailseite
     * @return array[] Array von Traktandumsdaten
     */
    public function ladeTraktanden(string $sitzungUrl): array {
        return $this->ladeEntitaeten($sitzungUrl, 'Traktanden');
    }

    /**
     * Lädt alle Parlamentsmitglieder.
     *
     * @return array[] Array von Mitgliederdaten
     */
    public function ladeMitglieder(): array {
        return $this->ladeEntitaeten(self::URLS['mitglieder'], 'Mitglieder');
    }

    /**
     * Lädt alle Kommissionen.
     *
     * @return array[] Array von Kommissionsdaten
     */
    public function ladeKommissionen(): array {
        return $this->ladeEntitaeten(self::URLS['kommissionen'], 'Kommissionen');
    }

    /**
     * Lädt alle Fraktionen und Parteien.
     *
     * @return array[] Array von Fraktionsdaten
     */
    public function ladeFraktionen(): array {
        return $this->ladeEntitaeten(self::URLS['fraktionen'], 'Fraktionen');
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
            $this->logger->debug("Parliament Winterthur: Lade {$label} von {$url}");

            $client = $this->clientService->newClient();
            $response = $client->get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'de-CH,de;q=0.9',
                ],
            ]);

            $html = $response->getBody();
            return $this->extrahiereEntitaeten($html, $label);
        } catch (\Throwable $e) {
            $this->logger->error(
                "Parliament Winterthur: Fehler beim Laden von {$label}: " . $e->getMessage(),
                ['url' => $url, 'exception' => $e]
            );
            return [];
        }
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
                        "Parliament Winterthur: Ungültiges JSON in data-entities für {$label}",
                        ['json_fehler' => json_last_error_msg(), 'roh' => substr($rohJson, 0, 200)]
                    );
                }
            }
        }

        $this->logger->info(
            "Parliament Winterthur: {$label}: " . count($entitaeten) . " Entitäten gefunden"
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
}
