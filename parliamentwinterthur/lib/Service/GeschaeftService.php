<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Geschäfts-Service: Synchronisation und Verwaltung politischer Geschäfte.
 */
class GeschaeftService {
    public function __construct(
        private readonly GeschaeftMapper $mapper,
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
     * @return array{neu: int, aktualisiert: int, geloescht: int}
     */
    public function synchronisieren(): array {
        $rohdaten = $this->scraper->ladeGeschaefte();
        $statistik = ['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0];
        $bekannteExternIds = [];

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid', 'Guid']);
            if (empty($externId)) {
                $this->logger->warning('Parliament Winterthur: Geschäft ohne ID übersprungen', ['daten' => $daten]);
                continue;
            }

            $bekannteExternIds[] = $externId;

            try {
                $geschaeft = $this->mapper->findByExternId($externId);
                // Aktualisieren (nur öffentliche Felder, fraktionsinterne Felder bleiben erhalten)
                $this->aktualisiereOeffentlicheFelder($geschaeft, $daten);
                $this->mapper->update($geschaeft);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                // Neues Geschäft anlegen
                $geschaeft = $this->erstelleAusRohdaten($externId, $daten);
                $this->mapper->insert($geschaeft);
                $statistik['neu']++;
            }
        }

        // Geschäfte, die nicht mehr auf der Webseite erscheinen, als gelöscht markieren
        if (!empty($bekannteExternIds)) {
            $statistik['geloescht'] = $this->mapper->markiereNichtMehrVorhandeneAlsGeloescht($bekannteExternIds);
        }

        $this->logger->info(
            'Parliament Winterthur: Geschäfte synchronisiert',
            $statistik
        );

        return $statistik;
    }

    /**
     * Gibt alle nicht gelöschten Geschäfte zurück.
     *
     * @return Geschaeft[]
     */
    public function alle(int $limit = 100, int $offset = 0): array {
        return $this->mapper->findAll($limit, $offset);
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
    private function erstelleAusRohdaten(string $externId, array $daten): Geschaeft {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $geschaeft = new Geschaeft();
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
        $geschaeft->setRohDaten(json_encode($daten));
        $geschaeft->setGeloescht(false);
        $geschaeft->setAktualisiertAm($jetzt);
    }
}
