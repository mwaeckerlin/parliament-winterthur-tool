<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\PropPatch;

/**
 * Kalender-Service: Erstellt und aktualisiert Nextcloud-Kalendereinträge
 * für die in den App-Einstellungen aktive Fraktion.
 *
 * Der Kalender ist bewusst nicht auf einen einzelnen Sitzungstyp beschränkt –
 * es werden später weitere Typen (z. B. Fraktionssitzungen, Kommissionssitzungen)
 * im gleichen Kalender geführt.
 *
 * Verwendet Nextclouds CalDAV-Backend über die OCA\DAV-App.
 */
class KalenderService
{
    /**
     * Stabile URI des Fraktionskalenders im Principal des konfigurierten Nutzers.
     * Wird nicht aus der Fraktion abgeleitet, damit ein Wechsel der aktiven
     * Fraktion vorhandene Einträge nicht verwaist und der Kalender nur umbenannt
     * werden muss.
     */
    private const KALENDER_URI = 'parlwin-fraktion-kalender';
    private const KALENDER_COLOR = '#1e6c9b';

    public function __construct(
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
        private readonly ?IUserManager $userManager = null,
    ) {
    }

    /**
     * Aktualisiert alle Kalendereinträge für die gegebenen Sitzungen.
     *
     * Für jede Sitzung wird ein iCalendar-VEVENT erstellt oder aktualisiert.
     * Der Kalender ist der gemeinsame Fraktionskalender (siehe Klassendoc).
     *
     * @param Sitzung[] $sitzungen
     */
    public function sitzungenAktualisieren(array $sitzungen): void
    {
        $kalenderNutzer = $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', '');
        if (empty($kalenderNutzer)) {
            $this->logger->info('Parlament Winterthur: Kein Kalendernutzer konfiguriert, überspringe Kalenderaktualisierung');
            return;
        }

        try {
            // CalDAV-Backend über Nextcloud-DI verwenden
            $dav = \OC::$server->get(\OCA\DAV\CalDAV\CalDavBackend::class);

            $kalender = $this->sicherstelleKalender($dav, $kalenderNutzer);

            foreach ($sitzungen as $sitzung) {
                $this->erstelleOderAktualisiere($dav, $kalender, $sitzung, null);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler bei Kalenderaktualisierung: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Erstellt den Fraktionskalender wenn nötig und gibt seine Metadaten zurück.
     * Aktualisiert zusätzlich Anzeigename/Beschreibung, falls die aktive
     * Fraktion seit dem letzten Lauf gewechselt hat.
     */
    private function sicherstelleKalender(\OCA\DAV\CalDAV\CalDavBackend $dav, string $nutzer): array
    {
        $principal = 'principals/users/' . $nutzer;
        $displayname = $this->kalenderAnzeigename();
        $beschreibung = $this->kalenderBeschreibung();

        $kalender = $dav->getCalendarByUri($principal, self::KALENDER_URI);
        if ($kalender === null) {
            $dav->createCalendar(
                $principal,
                self::KALENDER_URI,
                [
                    '{DAV:}displayname' => $displayname,
                    '{http://apple.com/ns/ical/}calendar-color' => self::KALENDER_COLOR,
                    '{urn:ietf:params:xml:ns:caldav}calendar-description' => $beschreibung,
                ]
            );
            $kalender = $dav->getCalendarByUri($principal, self::KALENDER_URI);
            $this->logger->info(
                sprintf('Parlament Winterthur: Fraktionskalender "%s" für Nutzer "%s" erstellt', $displayname, $nutzer)
            );
            return $kalender;
        }

        // Falls sich die aktive Fraktion geändert hat, Metadaten nachziehen.
        $aktuellerName = (string) ($kalender['{DAV:}displayname'] ?? '');
        $aktuelleBeschreibung = (string) ($kalender['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? '');
        if ($aktuellerName !== $displayname || $aktuelleBeschreibung !== $beschreibung) {
            $propPatch = new PropPatch([
                '{DAV:}displayname' => $displayname,
                '{urn:ietf:params:xml:ns:caldav}calendar-description' => $beschreibung,
            ]);
            $dav->updateCalendar((int) $kalender['id'], $propPatch);
            $propPatch->commit();
            $kalender = $dav->getCalendarByUri($principal, self::KALENDER_URI);
            $this->logger->info(
                sprintf('Parlament Winterthur: Fraktionskalender umbenannt zu "%s" für Nutzer "%s"', $displayname, $nutzer)
            );
        }

        return $kalender;
    }

    /**
     * Anzeigename des Kalenders, abgeleitet aus der aktiv konfigurierten Fraktion.
     */
    private function kalenderAnzeigename(): string
    {
        $fraktion = trim((string) $this->config->getAppValue(Application::APP_ID, 'fraktion', ''));
        return $fraktion !== '' ? 'Fraktion ' . $fraktion : 'Fraktion';
    }

    /**
     * Beschreibung des Kalenders, abgeleitet aus der aktiv konfigurierten Fraktion.
     */
    private function kalenderBeschreibung(): string
    {
        $fraktion = trim((string) $this->config->getAppValue(Application::APP_ID, 'fraktion', ''));
        return $fraktion !== ''
            ? sprintf('Termine und Sitzungen der Fraktion %s – synchronisiert vom Stadtparlament Winterthur Tool', $fraktion)
            : 'Termine und Sitzungen der Fraktion – synchronisiert vom Stadtparlament Winterthur Tool';
    }

    /**
     * Erstellt oder aktualisiert einen Kalendereintrag für eine einzelne
     * interne Sitzung. Die Beschreibung wird explizit übergeben (Zweck +
     * Traktanden-Liste, zusammengestellt durch den aufrufenden Service).
     */
    public function erstelleInterneSitzung(Sitzung $sitzung, string $beschreibung): void
    {
        $kalenderNutzer = $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', '');
        if (empty($kalenderNutzer)) {
            return;
        }
        try {
            $dav = \OC::$server->get(\OCA\DAV\CalDAV\CalDavBackend::class);
            $kalender = $this->sicherstelleKalender($dav, $kalenderNutzer);
            $this->erstelleOderAktualisiere($dav, $kalender, $sitzung, $beschreibung);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Parlament Winterthur: Fehler beim Anlegen des internen Kalendereintrags: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Erstellt oder aktualisiert einen Kalendereintrag für eine Sitzung.
     */
    private function erstelleOderAktualisiere(
        \OCA\DAV\CalDAV\CalDavBackend $dav,
        array $kalender,
        Sitzung $sitzung,
        ?string $beschreibungOverride = null
    ): void {
        $uid = $sitzung->getTypId() > 0
            ? 'parliament-winterthur-sitzung-intern-' . $sitzung->getId()
            : 'parliament-winterthur-sitzung-' . $sitzung->getExternId();
        $dateiname = $uid . '.ics';
        $ical = $this->erstelleIcal($sitzung, $uid, $beschreibungOverride);

        $vorhandeneObjekte = $dav->getCalendarObjects($kalender['id']);
        $vorhandeneUris = array_column($vorhandeneObjekte, 'uri');

        if (in_array($dateiname, $vorhandeneUris)) {
            $dav->updateCalendarObject($kalender['id'], $dateiname, $ical);
        } else {
            $dav->createCalendarObject($kalender['id'], $dateiname, $ical);
        }
    }

    /**
     * Erstellt einen iCalendar-String für eine Sitzung.
     * $beschreibungOverride wird bei internen Sitzungen übergeben (Zweck + Traktanden).
     */
    private function erstelleIcal(Sitzung $sitzung, string $uid, ?string $beschreibungOverride = null): string
    {
        $datum = $sitzung->getDatum();
        $zeitVon = $sitzung->getZeitVon() ?: '09:00';
        $zeitBis = $sitzung->getZeitBis() ?: '12:00';

        $startDt = $this->formatiereDatumZeit($datum, $zeitVon);
        $endDt = $this->formatiereDatumZeit($datum, $zeitBis);
        $jetzt = gmdate('Ymd\THis\Z');

        $titel = $this->icalEscape($sitzung->getTitel() ?: 'Sitzung Stadtparlament Winterthur');
        $ort = $this->icalEscape($sitzung->getOrt() ?: '');
        $url = $sitzung->getUrl() ?: '';
        $beschreibung = '';
        if ($beschreibungOverride !== null) {
            $beschreibung = $this->icalEscape($beschreibungOverride);
        } elseif (!empty($url)) {
            $beschreibung = 'Weitere Informationen: ' . $url;
        }

        $teilnehmerZeilen = $this->teilnehmerZeilen($sitzung);

        $ical = "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//Parlament Winterthur Tool//Nextcloud//DE\r\n" .
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
            $teilnehmerZeilen .
            "END:VEVENT\r\n" .
            "END:VCALENDAR\r\n";

        return $this->foldIcal($ical);
    }

    /** RFC 5545 text escaping: backslash, newlines, commas, semicolons. */
    private function icalEscape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\n', $value);
        $value = str_replace(',', '\,', $value);
        $value = str_replace(';', '\;', $value);
        return $value;
    }

    /** RFC 5545 line folding: fold at 75 octets with CRLF + space. */
    private function foldIcal(string $ical): string
    {
        $lines = explode("\r\n", rtrim($ical, "\r\n"));
        $folded = [];
        foreach ($lines as $line) {
            while (strlen($line) > 75) {
                $folded[] = substr($line, 0, 75);
                $line = ' ' . substr($line, 75);
            }
            $folded[] = $line;
        }
        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * Gibt ORGANIZER und ATTENDEE-Zeilen für eine Sitzung zurück, sofern
     * die Sitzung aus einer Vorlage erstellt wurde und Teilnehmer
     * materialisiert sind.
     */
    private function teilnehmerZeilen(Sitzung $sitzung): string
    {
        $teilnehmer = $sitzung->getTeilnehmerArray();
        if ($teilnehmer === []) {
            return '';
        }

        $zeilen = '';
        $organizerEmail = $this->organizerEmail();
        if ($organizerEmail !== '') {
            $zeilen .= 'ORGANIZER:mailto:' . $organizerEmail . "\r\n";
        }

        foreach ($teilnehmer as $eintrag) {
            $gruppe = trim((string) ($eintrag['gruppe'] ?? ''));
            if ($gruppe !== '') {
                $cn = ';CN="' . addcslashes($gruppe, '"\\') . '"';
                $zeilen .= 'ATTENDEE;CUTYPE=GROUP' . $cn
                    . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:'
                    . 'principal:principals/groups/' . rawurlencode($gruppe) . "\r\n";
                continue;
            }
            $email = trim((string) ($eintrag['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $name = (string) ($eintrag['name'] ?? '');
            $cn = $name !== '' ? ';CN="' . addcslashes($name, '"\\') . '"' : '';
            $zeilen .= 'ATTENDEE' . $cn
                . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:'
                . $email . "\r\n";
        }
        return $zeilen;
    }

    private function organizerEmail(): string
    {
        $uid = (string) $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', '');
        if ($uid === '' || $this->userManager === null) {
            return '';
        }
        $user = $this->userManager->get($uid);
        if ($user === null) {
            return '';
        }
        return (string) ($user->getEMailAddress() ?? '');
    }

    /**
     * Formatiert Datum und Zeit für iCalendar (YYYYMMDDTHHMMSS).
     */
    private function formatiereDatumZeit(string $datum, string $zeit): string
    {
        $datumObj = \DateTime::createFromFormat('Y-m-d', $datum)
            ?: \DateTime::createFromFormat('d.m.Y', $datum)
            ?: new \DateTime($datum);

        [$stunde, $minute] = array_pad(explode(':', $zeit), 2, '00');
        $datumObj->setTime((int) $stunde, (int) $minute, 0);
        return $datumObj->format('Ymd\THis');
    }
}
