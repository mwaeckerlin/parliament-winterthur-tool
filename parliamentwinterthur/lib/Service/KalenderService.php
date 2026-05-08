<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Sitzung;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Kalender-Service: Erstellt und aktualisiert Nextcloud-Kalendereinträge
 * für Parlamentssitzungen.
 *
 * Verwendet Nextclouds CalDAV-Backend über die OCA\DAV-App.
 */
class KalenderService {
    /** Name des Kalenders, in dem die Sitzungen gespeichert werden */
    private const KALENDER_NAME = 'Parlament Winterthur';
    private const KALENDER_COLOR = '#1e6c9b';

    public function __construct(
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Aktualisiert alle Kalendereinträge für die gegebenen Sitzungen.
     *
     * Für jede Sitzung wird ein iCalendar-VEVENT erstellt oder aktualisiert.
     * Der Kalender wird pro konfigurierter Fraktion geführt.
     *
     * @param Sitzung[] $sitzungen
     */
    public function sitzungenAktualisieren(array $sitzungen): void {
        $kalenderNutzer = $this->config->getAppValue('parliamentwinterthur', 'kalender_nutzer', '');
        if (empty($kalenderNutzer)) {
            $this->logger->info('Parliament Winterthur: Kein Kalendernutzer konfiguriert, überspringe Kalenderaktualisierung');
            return;
        }

        try {
            // CalDAV-Backend über Nextcloud-DI verwenden
            $dav = \OC::$server->get(\OCA\DAV\CalDAV\CalDavBackend::class);

            $kalender = $this->sicherstelleKalender($dav, $kalenderNutzer);

            foreach ($sitzungen as $sitzung) {
                $this->erstelleOderAktualisiere($dav, $kalender, $sitzung);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parliament Winterthur: Fehler bei Kalenderaktualisierung: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Erstellt den Parlamentskalender wenn nötig und gibt seine URI zurück.
     */
    private function sicherstelleKalender(\OCA\DAV\CalDAV\CalDavBackend $dav, string $nutzer): array {
        $kalender = $dav->getCalendarByUri('principals/users/' . $nutzer, 'parliament-winterthur');
        if ($kalender === null) {
            $dav->createCalendar(
                'principals/users/' . $nutzer,
                'parliament-winterthur',
                [
                    '{DAV:}displayname' => self::KALENDER_NAME,
                    '{http://apple.com/ns/ical/}calendar-color' => self::KALENDER_COLOR,
                    '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'Sitzungen des Stadtparlaments Winterthur',
                ]
            );
            $kalender = $dav->getCalendarByUri('principals/users/' . $nutzer, 'parliament-winterthur');
            $this->logger->info("Parliament Winterthur: Kalender '{" . self::KALENDER_NAME . "}' für Nutzer '{$nutzer}' erstellt");
        }
        return $kalender;
    }

    /**
     * Erstellt oder aktualisiert einen Kalendereintrag für eine Sitzung.
     */
    private function erstelleOderAktualisiere(
        \OCA\DAV\CalDAV\CalDavBackend $dav,
        array $kalender,
        Sitzung $sitzung
    ): void {
        $uid = 'parliament-winterthur-sitzung-' . $sitzung->getExternId();
        $dateiname = $uid . '.ics';
        $ical = $this->erstelleIcal($sitzung, $uid);

        $vorhandeneObjekte = $dav->getCalendarObjects($kalender['id']);
        $vorhandeneUris = array_column($vorhandeneObjekte, 'uri');

        if (in_array($dateiname, $vorhandeneUris)) {
            $dav->updateCalendarObject($kalender['id'], $dateiname, $ical);
        } else {
            $dav->createCalendarObject($kalender['id'], $dateiname, $ical);
        }
    }

    /**
     * Erstellt einen iCalendar-String für eine Parlamentssitzung.
     */
    private function erstelleIcal(Sitzung $sitzung, string $uid): string {
        $datum = $sitzung->getDatum();
        $zeitVon = $sitzung->getZeitVon() ?: '09:00';
        $zeitBis = $sitzung->getZeitBis() ?: '12:00';

        // Datum-/Zeitformate für iCal
        $startDt = $this->formatiereDatumZeit($datum, $zeitVon);
        $endDt = $this->formatiereDatumZeit($datum, $zeitBis);
        $jetzt = gmdate('Ymd\THis\Z');

        $titel = addslashes($sitzung->getTitel() ?: 'Sitzung Stadtparlament Winterthur');
        $ort = addslashes($sitzung->getOrt() ?: '');
        $url = $sitzung->getUrl() ?: '';
        $beschreibung = '';
        if (!empty($url)) {
            $beschreibung = 'Weitere Informationen: ' . $url;
        }

        return "BEGIN:VCALENDAR\r\n" .
               "VERSION:2.0\r\n" .
               "PRODID:-//Parliament Winterthur Tool//Nextcloud//DE\r\n" .
               "CALSCALE:GREGORIAN\r\n" .
               "BEGIN:VEVENT\r\n" .
               "UID:{$uid}\r\n" .
               "DTSTAMP:{$jetzt}\r\n" .
               "DTSTART:{$startDt}\r\n" .
               "DTEND:{$endDt}\r\n" .
               "SUMMARY:{$titel}\r\n" .
               ($ort ? "LOCATION:{$ort}\r\n" : '') .
               ($beschreibung ? "DESCRIPTION:{$beschreibung}\r\n" : '') .
               ($url ? "URL:{$url}\r\n" : '') .
               "END:VEVENT\r\n" .
               "END:VCALENDAR\r\n";
    }

    /**
     * Formatiert Datum und Zeit für iCalendar (YYYYMMDDTHHMMSS).
     */
    private function formatiereDatumZeit(string $datum, string $zeit): string {
        $datumObj = \DateTime::createFromFormat('Y-m-d', $datum)
            ?: \DateTime::createFromFormat('d.m.Y', $datum)
            ?: new \DateTime($datum);

        [$stunde, $minute] = array_pad(explode(':', $zeit), 2, '00');
        $datumObj->setTime((int) $stunde, (int) $minute, 0);
        return $datumObj->format('Ymd\THis');
    }
}
