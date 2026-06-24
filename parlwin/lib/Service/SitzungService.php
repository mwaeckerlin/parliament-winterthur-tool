<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Sitzungs-Service: Synchronisation und Verwaltung von Parlamentssitzungen
 * und deren Traktanden.
 */
class SitzungService
{
    public function __construct(
        private readonly SitzungMapper $sitzungMapper,
        private readonly TraktandumMapper $traktandumMapper,
        private readonly GeschaeftMapper $geschaeftMapper,
        private readonly ScraperService $scraper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronisiert alle Sitzungen und deren Traktanden.
     *
     * @param array<string, mixed> $optionen
     * @return array{neu: int, aktualisiert: int, geloescht: int}
     */
    public function synchronisieren(?callable $fortschritt = null, array $optionen = []): array
    {
        $rohdaten = $this->scraper->ladeSitzungen();
        $statistik = ['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0];
        $bekannteExternIds = [];
        $gesamt = count($rohdaten);
        $verarbeitet = 0;
        $resumeCursor = trim((string) ($optionen['resume_cursor'] ?? ''));
        $resumeAktiv = $this->istResumeCursorVorhanden($rohdaten, $resumeCursor);

        if ($fortschritt !== null) {
            $fortschritt([
                'scope' => 'sitzungen',
                'processed' => 0,
                'total' => $gesamt,
                'cursor' => '',
                'final' => false,
            ]);
        }

        // Detailseiten parallel vorladen, statt sie pro Sitzung sequenziell
        // nachzuziehen. Verhindert das wahrgenommene Hängen bei späten
        // Sitzungen, deren Server-Antwort langsam ist.
        $detailUrls = [];
        foreach ($rohdaten as $daten) {
            $hatInline = !empty(ScraperService::wert($daten, ['agenda', 'Agenda', 'traktanden', 'items'], []));
            if ($hatInline) {
                continue;
            }
            $url = (string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'detailUrl', 'link']);
            if ($url !== '') {
                $detailUrls[] = $url;
            }
        }
        $traktandenCache = [];
        if ($detailUrls !== []) {
            $prefetchGesamt = count(array_unique($detailUrls));
            $progressCallback = null;
            if ($fortschritt !== null) {
                $progressCallback = function (string $url, bool $erfolg, int $erledigt, int $total) use ($fortschritt, $gesamt): void {
                    $fortschritt([
                        'scope' => 'sitzungen',
                        'processed' => 0,
                        'total' => $gesamt,
                        'cursor' => 'Detailseiten laden: ' . $erledigt . '/' . $total,
                        'final' => false,
                    ]);
                };
            }
            $traktandenCache = $this->scraper->ladeTraktandenJeUrlParallel($detailUrls, $progressCallback);
        }

        $syncFehler = 0;

        foreach ($rohdaten as $daten) {
            $verarbeitet++;
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);

            if (!empty($externId)) {
                $bekannteExternIds[] = $externId;
            }

            if ($resumeAktiv) {
                if ($externId === $resumeCursor) {
                    $resumeAktiv = false;
                }
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'sitzungen',
                        'processed' => $verarbeitet,
                        'total' => $gesamt,
                        'cursor' => $externId,
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            if (empty($externId)) {
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'sitzungen',
                        'processed' => $verarbeitet,
                        'total' => $gesamt,
                        'cursor' => '',
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            try {
                try {
                    $sitzung = $this->sitzungMapper->findByExternId($externId);
                    $this->aktualisiereOeffentlicheFelder($sitzung, $daten);
                    $this->sitzungMapper->update($sitzung);
                    $statistik['aktualisiert']++;
                } catch (DoesNotExistException) {
                    $sitzung = $this->erstelleAusRohdaten($externId, $daten);
                    $this->sitzungMapper->insert($sitzung);
                    $statistik['neu']++;
                }

                // Traktanden laden, wenn eine Detail-URL vorhanden ist
                $detailUrl = (string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'detailUrl', 'link']);
                if (!empty($detailUrl)) {
                    $this->synchronisiereTraktanden($sitzung, $detailUrl, $daten, $traktandenCache);
                }
            } catch (\Throwable $e) {
                $syncFehler++;
                $this->logger->error(
                    'Parlament Winterthur: Fehler bei Sitzung ' . $externId . ': ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }

            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'sitzungen',
                    'processed' => $verarbeitet,
                    'total' => $gesamt,
                    'cursor' => $externId,
                    'final' => $verarbeitet >= $gesamt,
                ]);
            }
        }

        // Nur als gelöscht markieren wenn der Sync vollständig fehlerfrei war.
        // Bei Fehlern: lieber veraltete Daten behalten als korrekte Daten löschen.
        if ($syncFehler === 0 && !empty($bekannteExternIds)) {
            $bekannteExternIds = array_values(array_unique($bekannteExternIds));
            $statistik['geloescht'] = $this->sitzungMapper->markiereNichtMehrVorhandeneAlsGeloescht($bekannteExternIds);
        } elseif ($syncFehler > 0) {
            $this->logger->warning(
                "Parlament Winterthur: {$syncFehler} Sitzung(en) mit Fehler — markiereNichtMehrVorhandeneAlsGeloescht übersprungen"
            );
        }

        if ($fortschritt !== null && $verarbeitet >= $gesamt) {
            $fortschritt([
                'scope' => 'sitzungen',
                'processed' => $gesamt,
                'total' => $gesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        $this->logger->info('Parlament Winterthur: Sitzungen synchronisiert', $statistik);

        return $statistik;
    }

    /**
     * @param array<int, array<string, mixed>> $rohdaten
     */
    private function istResumeCursorVorhanden(array $rohdaten, string $resumeCursor): bool
    {
        if ($resumeCursor === '') {
            return false;
        }

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);
            if ($externId === $resumeCursor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Synchronisiert die Traktanden einer einzelnen Sitzung.
     *
     * @param array<string, array> $traktandenCache Map: absolute URL → vorab geladene Traktanden
     */
    private function synchronisiereTraktanden(Sitzung $sitzung, string $url, array $sitzungsDaten, array $traktandenCache = []): void
    {
        // Traktanden können direkt in den Sitzungsdaten enthalten sein
        $traktandenDaten = ScraperService::wert($sitzungsDaten, ['agenda', 'Agenda', 'traktanden', 'items'], []);

        // Oder von der Detailseite laden (bevorzugt aus dem parallelen Cache)
        if (empty($traktandenDaten) && !empty($url)) {
            $absUrl = ScraperService::absolutUrl($url);
            if ($absUrl !== '' && array_key_exists($absUrl, $traktandenCache)) {
                $traktandenDaten = $traktandenCache[$absUrl];
            } else {
                $traktandenDaten = $this->scraper->ladeTraktanden($url);
            }
        }

        if (empty($traktandenDaten)) {
            return;
        }

        // Erst alle neuen Daten aufbereiten — kein Schreiben vor dem ersten Fehler.
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $verarbeitetNummern = [];
        $nummer = 0;

        foreach ($traktandenDaten as $tDaten) {
            if (!is_array($tDaten)) {
                continue;
            }

            $nummer++;
            $titel = (string) ScraperService::wert($tDaten, ['title', 'Title', 'bezeichnung', 'name']);
            $beschreibung = (string) ScraperService::wert($tDaten, ['description', 'Description', 'beschreibung', 'text']);
            if ($beschreibung === '') {
                $beschreibung = $titel;
            }
            $tNummer = (int) ScraperService::wert($tDaten, ['number', 'Number', 'nummer', 'position'], $nummer);

            $geschaeftExternId = (string) ScraperService::wert($tDaten, ['businessId', 'geschaeftId', 'politBusinessId']);
            if ($geschaeftExternId === '') {
                $geschaeftUrl = (string) ScraperService::wert($tDaten, ['url', 'Url', 'URL', 'link', 'Link', 'geschaeftUrl'], '');
                $geschaeftExternId = ScraperService::extrahiereExternIdAusUrl($geschaeftUrl);
            }
            $geschaeftId = 0;
            if (!empty($geschaeftExternId)) {
                try {
                    $geschaeft = $this->geschaeftMapper->findByExternId($geschaeftExternId);
                    $geschaeftId = $geschaeft->getId();
                } catch (DoesNotExistException) {
                    // Verknüpfung kann nicht aufgelöst werden
                }
            }

            $traktandumUrl = (string) ScraperService::wert($tDaten, ['traktandumUrl'], '');

            // Bestehendes Traktandum suchen (auch gelöschte) — nie neu anlegen wenn eines existiert.
            $traktandum = $this->traktandumMapper->findErstesBySitzungUndNummer($sitzung->getId(), $tNummer);
            $neu = false;
            if ($traktandum === null) {
                $traktandum = new Traktandum();
                $traktandum->setSitzungId($sitzung->getId());
                $traktandum->setErstelltAm($jetzt);
                $neu = true;
            }

            $traktandum->setGeschaeftId($geschaeftId);
            $traktandum->setNummer($tNummer);
            $traktandum->setTitel($titel);
            $traktandum->setBeschreibung($beschreibung);
            $traktandum->setUrl($traktandumUrl);
            $traktandum->setGeloescht(false);
            $traktandum->setAktualisiertAm($jetzt);

            if ($neu) {
                $this->traktandumMapper->insert($traktandum);
            } else {
                $this->traktandumMapper->update($traktandum);
            }

            $verarbeitetNummern[] = $tNummer;
        }

        // Traktanden die nicht mehr in der Quelle erscheinen als gelöscht markieren.
        // Notizen bleiben im DB-Datensatz erhalten (nur geloescht=true).
        if (!empty($verarbeitetNummern)) {
            $this->traktandumMapper->markiereNichtMehrVorhandeneAlsGeloescht($sitzung->getId(), $verarbeitetNummern);
        }
    }

    /**
     * Gibt alle nicht gelöschten Sitzungen zurück.
     *
     * @return Sitzung[]
     */
    public function alle(int $limit = 100, int $offset = 0): array
    {
        return $this->sitzungMapper->findAll($limit, $offset);
    }

    /**
     * Gibt alle aktiven (nicht gelöschten) Sitzungen zurück.
     *
     * @return Sitzung[]
     */
    public function alleAktiven(): array
    {
        return $this->sitzungMapper->findAll(1000, 0);
    }

    /**
     * Gibt alle zukünftigen Sitzungen zurück.
     *
     * @return Sitzung[]
     */
    public function kuenftige(): array
    {
        return $this->sitzungMapper->findKuenftige();
    }

    /**
     * Gibt eine Sitzung anhand ihrer ID zurück.
     *
     * @throws DoesNotExistException
     */
    public function eins(int $id): Sitzung
    {
        return $this->sitzungMapper->find($id);
    }

    /**
     * Gibt alle Traktanden einer Sitzung zurück.
     *
     * @return Traktandum[]
     */
    public function traktanden(int $sitzungId): array
    {
        return $this->traktandumMapper->findBySitzung($sitzungId);
    }

    /**
     * Aktualisiert die fraktionsinternen Felder einer Sitzung.
     */
    public function aktualisiereInterneSitzung(int $id, array $felder): Sitzung
    {
        $sitzung = $this->sitzungMapper->find($id);
        if (array_key_exists('bemerkungen', $felder)) {
            $sitzung->setBemerkungen($felder['bemerkungen']);
        }
        if (array_key_exists('notizen', $felder)) {
            $sitzung->setNotizen($felder['notizen']);
        }
        $sitzung->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->sitzungMapper->update($sitzung);
    }

    /**
     * Verknüpft eine Sitzung mit einer Zielsitzung: beide teilen fortan eine
     * Verknüpfungs-Gruppe (gleiche verknuepfung_id) und zeigen eine aggregierte
     * Sicht. Gruppen-ID ist die bestehende der Zielsitzung oder deren eigene ID.
     */
    public function verknuepfe(int $sitzungId, int $zielSitzungId): Sitzung
    {
        $ziel = $this->sitzungMapper->find($zielSitzungId);
        $gruppe = $ziel->getVerknuepfungId() ?? $ziel->getId();
        if ($ziel->getVerknuepfungId() === null) {
            $ziel->setVerknuepfungId($gruppe);
            $this->sitzungMapper->update($ziel);
        }
        $sitzung = $this->sitzungMapper->find($sitzungId);
        $sitzung->setVerknuepfungId($gruppe);
        $sitzung->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->sitzungMapper->update($sitzung);
    }

    /**
     * Entkoppelt eine Sitzung aus ihrer Verknüpfungs-Gruppe. Die bei dieser
     * Sitzung erfassten Daten bleiben erhalten (bleiben an ihrem Platz).
     */
    public function entkopple(int $sitzungId): Sitzung
    {
        $sitzung = $this->sitzungMapper->find($sitzungId);
        $sitzung->setVerknuepfungId(null);
        $sitzung->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->sitzungMapper->update($sitzung);
    }

    /**
     * Alle Sitzungen der Verknüpfungs-Gruppe einer Sitzung (inkl. ihrer selbst).
     * Ohne Verknüpfung nur die Sitzung selbst.
     *
     * @return Sitzung[]
     */
    public function verknuepfteSitzungen(int $sitzungId): array
    {
        $sitzung = $this->sitzungMapper->find($sitzungId);
        $gruppe = $sitzung->getVerknuepfungId();
        if ($gruppe === null) {
            return [$sitzung];
        }
        return $this->sitzungMapper->findByVerknuepfungId($gruppe);
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Traktandums.
     *
     * Nur `notizen` ist relevant; `bemerkungen` wird nicht mehr von der UI
     * gesetzt, bleibt aber in der DB (Altbestand) und wird hier defensiv
     * gehandhabt, falls Tests/Altcode das Feld noch übergeben.
     */
    public function aktualisiereInternesTraktandum(int $id, array $felder): Traktandum
    {
        $traktandum = $this->traktandumMapper->find($id);
        if (array_key_exists('bemerkungen', $felder)) {
            $traktandum->setBemerkungen($felder['bemerkungen']);
        }
        if (array_key_exists('notizen', $felder)) {
            $traktandum->setNotizen($felder['notizen']);
        }
        $traktandum->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->traktandumMapper->update($traktandum);
    }

    private function erstelleAusRohdaten(string $externId, array $daten): Sitzung
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $sitzung = new Sitzung();
        $sitzung->setExternId($externId);
        $sitzung->setErstelltAm($jetzt);
        $sitzung->setGeloescht(false);
        $this->aktualisiereOeffentlicheFelder($sitzung, $daten);
        return $sitzung;
    }

    private function aktualisiereOeffentlicheFelder(Sitzung $sitzung, array $daten): void
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $sitzung->setTitel((string) ScraperService::wert($daten, ['title', 'Title', 'bezeichnung', 'name']));
        $sitzung->setDatum((string) ScraperService::wert($daten, ['date', 'Date', 'datum', 'Datum', 'startDate']));
        $sitzung->setZeitVon((string) ScraperService::wert($daten, ['timeFrom', 'zeitVon', 'startTime', 'start']));
        $sitzung->setZeitBis((string) ScraperService::wert($daten, ['timeTo', 'zeitBis', 'endTime', 'end']));
        $sitzung->setOrt((string) ScraperService::wert($daten, ['location', 'Location', 'ort', 'Ort', 'place']));
        $sitzung->setUrl((string) ScraperService::wert($daten, ['url', 'Url', 'URL', 'detailUrl', 'link']));
        $sitzung->setGeloescht(false);
        $sitzung->setAktualisiertAm($jetzt);
    }
}
