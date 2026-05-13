<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurf;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Geschäfts-Service: Synchronisation und Verwaltung politischer Geschäfte.
 */
class GeschaeftService {
    public function __construct(
        private readonly GeschaeftMapper $mapper,
        private readonly VorstossEntwurfMapper $vorstossEntwurfMapper,
        private readonly GeschaeftEreignisMapper $geschaeftEreignisMapper,
        private readonly ScraperService $scraper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronisiert alle Geschäfte von der Parlamentswebseite.
     *
     * Neue Geschäfte werden eingefügt, bestehende aktualisiert.
     * Geschäfte, die nicht mehr auf der Webseite erscheinen, werden
     * als gelöscht markiert (aber nicht aus der Datenbank entfernt).
     *
     * @param array<string, mixed> $optionen
     * @return array{neu: int, aktualisiert: int, geloescht: int}
     */
    public function synchronisieren(?callable $fortschritt = null, array $optionen = []): array {
        $ladeGesamt = 0;
        $rohdaten = $this->scraper->ladeGeschaefte(function (array $event) use ($fortschritt, &$ladeGesamt): void {
            $total = max(0, (int) ($event['total'] ?? 0));
            $processed = max(0, (int) ($event['processed'] ?? 0));
            $cursor = trim((string) ($event['cursor'] ?? ''));
            if ($total > 0) {
                $processed = min($processed, $total);
            }
            $ladeGesamt = max($ladeGesamt, $total);
            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'geschaefte',
                    'processed' => $processed,
                    'total' => $total > 0 ? ($total * 2) : 0,
                    'cursor' => $cursor,
                    'final' => false,
                ]);
            }
        });
        $statistik = ['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0];
        $bekannteExternIds = [];
        $gesamt = count($rohdaten);
        $fortschrittGesamt = $gesamt > 0 ? ($gesamt * 2) : 0;
        $verarbeitet = 0;
        $resumeCursor = trim((string) ($optionen['resume_cursor'] ?? ''));
        $resumeAktiv = $this->istResumeCursorVorhanden($rohdaten, $resumeCursor);

        if ($fortschritt !== null && $gesamt > 0 && $ladeGesamt <= 0) {
            $fortschritt([
                'scope' => 'geschaefte',
                'processed' => 0,
                'total' => $fortschrittGesamt,
                'cursor' => '',
                'final' => false,
            ]);
        }

        foreach ($rohdaten as $daten) {
            $verarbeitet++;
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid', 'Guid']);
            $nummer = trim((string) ScraperService::wert($daten, ['number', 'Number', 'nummer', 'Nummer', 'geschaeftsnummer']));

            if (!empty($externId) && ctype_digit($externId) && $nummer !== '') {
                $bekannteExternIds[] = $externId;
            }

            if ($resumeAktiv) {
                if ($externId === $resumeCursor) {
                    $resumeAktiv = false;
                }
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'geschaefte',
                        'processed' => $gesamt + $verarbeitet,
                        'total' => $fortschrittGesamt,
                        'cursor' => $externId,
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            if (empty($externId)) {
                $this->logger->warning('Parlament Winterthur: Geschäft ohne ID übersprungen', ['daten' => $daten]);
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'geschaefte',
                        'processed' => $gesamt + $verarbeitet,
                        'total' => $fortschrittGesamt,
                        'cursor' => '',
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }
            if (!ctype_digit($externId)) {
                $this->logger->warning(
                    'Parlament Winterthur: Geschäft mit nicht-numerischer ID übersprungen',
                    ['externId' => $externId]
                );
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'geschaefte',
                        'processed' => $gesamt + $verarbeitet,
                        'total' => $fortschrittGesamt,
                        'cursor' => $externId,
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }
            $dbId = (int) $externId;

            if ($nummer === '') {
                $this->synchronisiereEntwurf($externId, $daten);
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'geschaefte',
                        'processed' => $gesamt + $verarbeitet,
                        'total' => $fortschrittGesamt,
                        'cursor' => $externId,
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            try {
                $geschaeft = $this->mapper->findByExternId($externId);
                $this->mapper->harmonisiereIdMitExternId($geschaeft, $dbId);
                if ($this->istAbgeschlossenStatus($geschaeft->getStatus())) {
                    if ($fortschritt !== null) {
                        $fortschritt([
                            'scope' => 'geschaefte',
                            'processed' => $gesamt + $verarbeitet,
                            'total' => $fortschrittGesamt,
                            'cursor' => $externId,
                            'final' => $verarbeitet >= $gesamt,
                        ]);
                    }
                    continue;
                }
                // Aktualisieren (nur öffentliche Felder, fraktionsinterne Felder bleiben erhalten)
                $this->aktualisiereOeffentlicheFelder($geschaeft, $daten);
                $this->mapper->update($geschaeft);
                $this->synchronisiereEreignisse($geschaeft, $daten);
                $this->verknuepfePassendenEntwurf($geschaeft, $daten);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                // Neues Geschäft anlegen
                $geschaeft = $this->erstelleAusRohdaten($externId, $dbId, $daten);
                $this->mapper->insert($geschaeft);
                $this->synchronisiereEreignisse($geschaeft, $daten);
                $this->verknuepfePassendenEntwurf($geschaeft, $daten);
                $statistik['neu']++;
            }

            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'geschaefte',
                    'processed' => $gesamt + $verarbeitet,
                    'total' => $fortschrittGesamt,
                    'cursor' => $externId,
                    'final' => $verarbeitet >= $gesamt,
                ]);
            }
        }

        // Geschäfte, die nicht mehr auf der Webseite erscheinen, als gelöscht markieren
        if (!empty($bekannteExternIds)) {
            $bekannteExternIds = array_values(array_unique($bekannteExternIds));
            $statistik['geloescht'] = $this->mapper->markiereNichtMehrVorhandeneAlsGeloescht($bekannteExternIds);
        }

        if ($fortschritt !== null && $verarbeitet >= $gesamt) {
            $fortschritt([
                'scope' => 'geschaefte',
                'processed' => $fortschrittGesamt,
                'total' => $fortschrittGesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        $this->logger->info(
            'Parlament Winterthur: Geschäfte synchronisiert',
            $statistik
        );

        return $statistik;
    }

    /**
     * Gibt alle nicht gelöschten Geschäfte zurück.
     *
     * @return Geschaeft[]
     */
    public function alle(int $limit = 100, int $offset = 0, bool $inklusiveErledigt = false): array {
        return $this->mapper->findAll($limit, $offset, $inklusiveErledigt);
    }

    /**
     * Gibt ein Geschäft anhand seiner ID zurück.
     *
     * @throws DoesNotExistException
     */
    public function eins(int $id): Geschaeft {
        return $this->mapper->find($id);
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Geschäfts.
     *
     * @param array{
     *   bemerkungen?: string,
     *   zustaendige_person?: string,
     *   antrag_fraktion?: string,
     *   entscheid_fraktion?: string,
     *   notizen?: string,
     * } $felder
     */
    public function aktualisiereInterneFelder(int $id, array $felder): Geschaeft {
        $geschaeft = $this->mapper->find($id);
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

        if (array_key_exists('bemerkungen', $felder)) {
            $geschaeft->setBemerkungen($felder['bemerkungen']);
        }
        if (array_key_exists('zustaendige_person', $felder)) {
            $geschaeft->setZustaendigePerson($felder['zustaendige_person']);
        }
        if (array_key_exists('antrag_fraktion', $felder)) {
            $geschaeft->setAntragFraktion($felder['antrag_fraktion']);
        }
        if (array_key_exists('entscheid_fraktion', $felder)) {
            $geschaeft->setEntscheidFraktion($felder['entscheid_fraktion']);
        }
        if (array_key_exists('notizen', $felder)) {
            $geschaeft->setNotizen($felder['notizen']);
        }
        $geschaeft->setAktualisiertAm($jetzt);

        return $this->mapper->update($geschaeft);
    }

    /**
     * Erstellt ein neues Geschäft-Objekt aus den Rohdaten der Webseite.
     */
    private function erstelleAusRohdaten(string $externId, int $dbId, array $daten): Geschaeft {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $geschaeft = new Geschaeft();
        $geschaeft->setId($dbId);
        $geschaeft->setExternId($externId);
        $geschaeft->setErstelltAm($jetzt);
        $geschaeft->setGeloescht(false);
        $this->aktualisiereOeffentlicheFelder($geschaeft, $daten);
        return $geschaeft;
    }

    /**
     * Aktualisiert die öffentlichen (von der Webseite stammenden) Felder eines Geschäfts.
     * Fraktionsinterne Felder werden nicht verändert.
     */
    private function aktualisiereOeffentlicheFelder(Geschaeft $geschaeft, array $daten): void {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

        $titel = (string) ScraperService::wert($daten, ['title', 'Title', 'bezeichnung', 'Bezeichnung', 'name', 'Name']);
        $nummer = (string) ScraperService::wert($daten, ['number', 'Number', 'nummer', 'Nummer', 'geschaeftsnummer']);
        $typ = (string) ScraperService::wert($daten, ['type', 'Type', 'typ', 'Typ', 'geschaeftstyp', 'businessType']);
        $status = (string) ScraperService::wert($daten, ['status', 'Status', 'state', 'State']);
        $datum = (string) ScraperService::wert($daten, ['date', 'Date', 'datum', 'Datum', 'eingangsdatum']);
        $url = (string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'link', 'Link', 'detailUrl']);

        $geschaeft->setTitel($titel);
        $geschaeft->setNummer($nummer);
        $geschaeft->setTyp($typ);
        $geschaeft->setStatus($status);
        $geschaeft->setDatum($datum);
        $geschaeft->setUrl($url);

        $quelleHash = $this->berechneQuellversion($daten);
        if ($quelleHash !== $geschaeft->getQuelleHash()) {
            $geschaeft->setQuelleHash($quelleHash);
            $geschaeft->setQuelleAktualisiertAm($jetzt);
        } elseif ($geschaeft->getQuelleAktualisiertAm() === '') {
            // Initialisierung bestehender Datensätze nach Migration.
            $geschaeft->setQuelleAktualisiertAm($jetzt);
        }

        $geschaeft->setGeloescht(false);
        $geschaeft->setAktualisiertAm($jetzt);
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function berechneQuellversion(array $daten): string {
        $events = ScraperService::wert($daten, ['events'], []);
        if (!is_array($events)) {
            $events = [];
        }

        $payload = [
            'title' => (string) ScraperService::wert($daten, ['title', 'Title', 'bezeichnung', 'Bezeichnung', 'name', 'Name']),
            'number' => (string) ScraperService::wert($daten, ['number', 'Number', 'nummer', 'Nummer', 'geschaeftsnummer']),
            'type' => (string) ScraperService::wert($daten, ['type', 'Type', 'typ', 'Typ', 'geschaeftstyp', 'businessType']),
            'status' => (string) ScraperService::wert($daten, ['status', 'Status', 'state', 'State']),
            'date' => (string) ScraperService::wert($daten, ['date', 'Date', 'datum', 'Datum', 'eingangsdatum']),
            'url' => (string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'link', 'Link', 'detailUrl']),
            'events' => $this->normalisiereEreignisseFuerHash($events),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return hash('sha256', serialize($payload));
        }

        return hash('sha256', $json);
    }

    /**
     * @param array<int, mixed> $events
     * @return array<int, array<string, string|int>>
     */
    private function normalisiereEreignisseFuerHash(array $events): array {
        $normalisiert = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $normalisiert[] = [
                'sequence' => (int) ($event['sequence'] ?? 0),
                'type' => trim((string) ($event['type'] ?? '')),
                'organ' => trim((string) ($event['organ'] ?? '')),
                'label' => trim((string) ($event['label'] ?? '')),
                'value' => trim((string) ($event['value'] ?? '')),
                'date' => trim((string) ($event['date'] ?? '')),
            ];
        }

        usort(
            $normalisiert,
            static function (array $a, array $b): int {
                $seqCmp = ($a['sequence'] <=> $b['sequence']);
                if ($seqCmp !== 0) {
                    return $seqCmp;
                }
                $labelCmp = strcmp((string) $a['label'], (string) $b['label']);
                if ($labelCmp !== 0) {
                    return $labelCmp;
                }
                return strcmp((string) $a['value'], (string) $b['value']);
            }
        );

        return $normalisiert;
    }

    /**
     * Entwurfs-/Einreichungsphase ohne Geschaeftsnummer.
     *
     * Diese Einträge werden getrennt von den offiziellen, nummerierten Geschäften geführt
     * und bei späterer Einreichung über Titel oder extern_id verknüpft.
     *
     * @param array<string, mixed> $daten
     */
    private function synchronisiereEntwurf(string $externId, array $daten): void {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $titel = (string) ScraperService::wert($daten, ['title', 'Title', 'bezeichnung', 'Bezeichnung', 'name', 'Name']);
        $typ = (string) ScraperService::wert($daten, ['type', 'Type', 'typ', 'Typ', 'geschaeftstyp', 'businessType']);
        $datum = (string) ScraperService::wert($daten, ['date', 'Date', 'datum', 'Datum', 'eingangsdatum']);
        $url = (string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'link', 'Link', 'detailUrl']);

        $entwurf = $this->vorstossEntwurfMapper->findByExternId($externId);
        $neu = false;
        if ($entwurf === null) {
            $entwurf = new VorstossEntwurf();
            $entwurf->setExternId($externId);
            $entwurf->setErstelltAm($jetzt);
            $neu = true;
        }

        $entwurf->setTitel($titel);
        $entwurf->setTitelNormalisiert($this->normalisiereTitel($titel));
        $entwurf->setTyp($typ);
        $entwurf->setEingangsdatum($datum);
        $entwurf->setUrl($url);
        if ($entwurf->getGeschaeftId() === 0) {
            $entwurf->setStatus('eingereicht_ohne_nummer');
            $entwurf->setMatchArt('');
        }
        $entwurf->setAktualisiertAm($jetzt);

        if ($neu) {
            $this->vorstossEntwurfMapper->insert($entwurf);
        } else {
            $this->vorstossEntwurfMapper->update($entwurf);
        }
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function synchronisiereEreignisse(Geschaeft $geschaeft, array $daten): void {
        $ereignisse = ScraperService::wert($daten, ['events'], []);
        if (!is_array($ereignisse)) {
            $ereignisse = [];
        }
        $this->geschaeftEreignisMapper->ersetzeFuerGeschaeft($geschaeft->getId(), $ereignisse);
    }

    /**
     * @param array<string, mixed> $daten
     */
    private function verknuepfePassendenEntwurf(Geschaeft $geschaeft, array $daten): void {
        $entwurf = $this->vorstossEntwurfMapper->findByExternId($geschaeft->getExternId());
        $matchArt = 'extern_id';

        if ($entwurf === null) {
            $titel = (string) ScraperService::wert($daten, ['title', 'Title', 'bezeichnung', 'Bezeichnung', 'name', 'Name']);
            $typ = (string) ScraperService::wert($daten, ['type', 'Type', 'typ', 'Typ', 'geschaeftstyp', 'businessType']);
            $entwurf = $this->vorstossEntwurfMapper->findOffenByTitelNormalisiert($this->normalisiereTitel($titel), $typ);
            $matchArt = 'titel';
        }

        if ($entwurf === null) {
            return;
        }

        $entwurf->setGeschaeftId($geschaeft->getId());
        $entwurf->setStatus('gematcht');
        $entwurf->setMatchArt($matchArt);
        $entwurf->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        $this->vorstossEntwurfMapper->update($entwurf);
    }

    /**
     * @param array<int, array<string, mixed>> $rohdaten
     */
    private function istResumeCursorVorhanden(array $rohdaten, string $resumeCursor): bool {
        if ($resumeCursor === '') {
            return false;
        }

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid', 'Guid']);
            if ($externId === $resumeCursor) {
                return true;
            }
        }

        return false;
    }

    private function normalisiereTitel(string $titel): string {
        $titel = ScraperService::bereinigeHtmlText($titel);
        if ($titel === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $titel = mb_strtolower($titel, 'UTF-8');
        } else {
            $titel = strtolower($titel);
        }
        $titel = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $titel);
        $titel = (string) preg_replace('/\s+/u', ' ', $titel);

        return trim($titel);
    }

    private function istAbgeschlossenStatus(string $status): bool {
        $status = trim($status);
        if ($status === '') {
            return false;
        }

        if (function_exists('mb_strtolower')) {
            $status = mb_strtolower($status, 'UTF-8');
        } else {
            $status = strtolower($status);
        }

        return str_contains($status, 'erledigt') || str_contains($status, 'abgeschlossen');
    }
}
