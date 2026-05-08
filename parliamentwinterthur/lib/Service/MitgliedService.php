<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Fraktion;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\Kommission;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Mail\IMailer;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Mitglieder-Service: Synchronisation von Parlamentsmitgliedern, Fraktionen und Kommissionen.
 *
 * Ermöglicht zusätzlich die automatische Verwaltung von Nextcloud-Gruppen
 * für die konfigurierte Fraktion.
 */
class MitgliedService {
    public function __construct(
        private readonly MitgliedMapper $mitgliedMapper,
        private readonly FraktionMapper $fraktionMapper,
        private readonly KommissionMapper $kommissionMapper,
        private readonly ScraperService $scraper,
        private readonly IGroupManager $groupManager,
        private readonly IUserManager $userManager,
        private readonly IMailer $mailer,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronisiert Mitglieder, Fraktionen und Kommissionen.
     *
     * @return array{mitglieder: array, fraktionen: array, kommissionen: array}
     */
    public function synchronisieren(): array {
        $statistik = [
            'mitglieder' => $this->synchronisiereMitglieder(),
            'fraktionen' => $this->synchronisiereFraktionen(),
            'kommissionen' => $this->synchronisiereKommissionen(),
        ];

        // Nextcloud-Gruppe für konfigurierte Fraktion aktualisieren
        $this->aktualisiereNextcloudGruppe();

        return $statistik;
    }

    /**
     * Synchronisiert Parlamentsmitglieder.
     *
     * @return array{neu: int, aktualisiert: int, inaktiv: int}
     */
    public function synchronisiereMitglieder(): array {
        $rohdaten = $this->scraper->ladeMitglieder();
        $statistik = ['neu' => 0, 'aktualisiert' => 0, 'inaktiv' => 0];
        $bekannteExternIds = [];

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid', 'personId']);
            if (empty($externId)) {
                continue;
            }

            $bekannteExternIds[] = $externId;

            try {
                $mitglied = $this->mitgliedMapper->findByExternId($externId);
                $this->aktualisiereOeffentlicheFelder($mitglied, $daten);
                $this->mitgliedMapper->update($mitglied);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                $mitglied = $this->erstelleAusRohdaten($externId, $daten);
                $this->mitgliedMapper->insert($mitglied);
                $statistik['neu']++;
            }
        }

        // Mitglieder markieren, die nicht mehr auf der Webseite erscheinen
        if (!empty($bekannteExternIds)) {
            $statistik['inaktiv'] = $this->mitgliedMapper->markiereNichtMehrAktive($bekannteExternIds);
        }

        $this->logger->info('Parliament Winterthur: Mitglieder synchronisiert', $statistik);
        return $statistik;
    }

    /**
     * Synchronisiert Fraktionen.
     *
     * @return array{neu: int, aktualisiert: int}
     */
    public function synchronisiereFraktionen(): array {
        $rohdaten = $this->scraper->ladeFraktionen();
        $statistik = ['neu' => 0, 'aktualisiert' => 0];

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);
            if (empty($externId)) {
                continue;
            }

            try {
                $fraktion = $this->fraktionMapper->findByExternId($externId);
                $fraktion->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
                $fraktion->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
                $fraktion->setMitglieder(json_encode(ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], [])));
                $fraktion->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
                $this->fraktionMapper->update($fraktion);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
                $fraktion = new Fraktion();
                $fraktion->setExternId($externId);
                $fraktion->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
                $fraktion->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
                $fraktion->setMitglieder(json_encode(ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], [])));
                $fraktion->setGeloescht(false);
                $fraktion->setErstelltAm($jetzt);
                $fraktion->setAktualisiertAm($jetzt);
                $this->fraktionMapper->insert($fraktion);
                $statistik['neu']++;
            }
        }

        return $statistik;
    }

    /**
     * Synchronisiert Kommissionen.
     *
     * @return array{neu: int, aktualisiert: int}
     */
    public function synchronisiereKommissionen(): array {
        $rohdaten = $this->scraper->ladeKommissionen();
        $statistik = ['neu' => 0, 'aktualisiert' => 0];

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);
            if (empty($externId)) {
                continue;
            }

            try {
                $kommission = $this->kommissionMapper->findByExternId($externId);
                $kommission->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
                $kommission->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
                $kommission->setMitglieder(json_encode(ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], [])));
                $kommission->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
                $this->kommissionMapper->update($kommission);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
                $kommission = new Kommission();
                $kommission->setExternId($externId);
                $kommission->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
                $kommission->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
                $kommission->setMitglieder(json_encode(ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], [])));
                $kommission->setGeloescht(false);
                $kommission->setErstelltAm($jetzt);
                $kommission->setAktualisiertAm($jetzt);
                $this->kommissionMapper->insert($kommission);
                $statistik['neu']++;
            }
        }

        return $statistik;
    }

    /**
     * Erstellt oder aktualisiert die Nextcloud-Gruppe für die konfigurierte Fraktion.
     *
     * - Neue Mitglieder werden per E-Mail eingeladen.
     * - Bestehende Mitglieder bleiben in der Gruppe.
     * - Mitglieder, die nicht mehr zur Fraktion gehören, werden aus der Gruppe entfernt.
     */
    public function aktualisiereNextcloudGruppe(): void {
        $konfigFraktion = $this->config->getAppValue('parliamentwinterthur', 'fraktion', '');
        $konfigGruppe = $this->config->getAppValue('parliamentwinterthur', 'nextcloud_gruppe', '');

        if (empty($konfigFraktion) || empty($konfigGruppe)) {
            $this->logger->debug('Parliament Winterthur: Keine Fraktion/Gruppe konfiguriert, überspringe Gruppenaktualisierung');
            return;
        }

        // Mitglieder der konfigurierten Fraktion laden
        $fraktionsmitglieder = $this->mitgliedMapper->findByFraktion($konfigFraktion);

        // Nextcloud-Gruppe erstellen wenn nicht vorhanden
        if (!$this->groupManager->groupExists($konfigGruppe)) {
            $this->groupManager->createGroup($konfigGruppe);
            $this->logger->info("Parliament Winterthur: Nextcloud-Gruppe '{$konfigGruppe}' erstellt");
        }

        $gruppe = $this->groupManager->get($konfigGruppe);
        if ($gruppe === null) {
            $this->logger->error("Parliament Winterthur: Gruppe '{$konfigGruppe}' konnte nicht geladen werden");
            return;
        }

        // Aktuelle Nextcloud-Gruppenmitglieder
        $aktuelleUsers = array_map(fn($u) => $u->getUID(), $gruppe->getUsers());

        // Fraktionsmitglieder nach E-Mail-Adresse suchen und Gruppe aktualisieren
        $sollMitglieder = [];
        foreach ($fraktionsmitglieder as $mitglied) {
            if (empty($mitglied->getEmail())) {
                continue;
            }

            $email = $mitglied->getEmail();
            $sollMitglieder[] = $email;

            // Benutzer in Nextcloud suchen
            $usersByEmail = $this->userManager->getByEmail($email);
            if (!empty($usersByEmail)) {
                $user = $usersByEmail[0];
                if (!in_array($user->getUID(), $aktuelleUsers)) {
                    $gruppe->addUser($user);
                    $this->logger->info(
                        "Parliament Winterthur: Benutzer '{$user->getUID()}' zur Gruppe '{$konfigGruppe}' hinzugefügt"
                    );
                }
            } else {
                // Benutzer existiert nicht → Einladungs-E-Mail senden
                $this->sendeEinladung($email, $mitglied->getVollerName(), $konfigGruppe);
            }
        }

        // Nicht mehr zur Fraktion gehörende Mitglieder aus der Gruppe entfernen
        foreach ($gruppe->getUsers() as $user) {
            if (!in_array($user->getEMailAddress(), $sollMitglieder)) {
                $gruppe->removeUser($user);
                $this->logger->info(
                    "Parliament Winterthur: Benutzer '{$user->getUID()}' aus Gruppe '{$konfigGruppe}' entfernt"
                );
            }
        }
    }

    /**
     * Gibt alle Parlamentsmitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function alle(): array {
        return $this->mitgliedMapper->findAlle();
    }

    /**
     * Gibt alle aktiven Parlamentsmitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function aktive(): array {
        return $this->mitgliedMapper->findAktive();
    }

    /**
     * Gibt ein Mitglied anhand seiner ID zurück.
     *
     * @throws DoesNotExistException
     */
    public function eins(int $id): Mitglied {
        return $this->mitgliedMapper->find($id);
    }

    /**
     * Sendet eine Einladungs-E-Mail an eine Person, die noch kein Nextcloud-Konto hat.
     */
    private function sendeEinladung(string $email, string $name, string $gruppe): void {
        try {
            $absenderEmail = $this->config->getAppValue('parliamentwinterthur', 'absender_email', 'noreply@example.com');
            $absenderName = $this->config->getAppValue('parliamentwinterthur', 'absender_name', 'Parliament Winterthur Tool');
            $nextcloudUrl = rtrim($this->config->getSystemValue('overwrite.cli.url', ''), '/');

            $message = $this->mailer->createMessage();
            $message->setFrom([$absenderEmail => $absenderName]);
            $message->setTo([$email => $name]);
            $message->setSubject('Einladung zum Parliament Winterthur Tool');
            $message->setPlainBody(
                "Hallo {$name},\n\n" .
                "Sie wurden als Mitglied der Fraktion/Gruppe '{$gruppe}' im Parliament Winterthur Tool eingetragen.\n\n" .
                "Um Zugang zu erhalten, erstellen Sie bitte ein Nextcloud-Konto unter:\n" .
                "{$nextcloudUrl}\n\n" .
                "Verwenden Sie dabei diese E-Mail-Adresse: {$email}\n\n" .
                "Mit freundlichen Grüssen\n" .
                "Parliament Winterthur Tool"
            );

            $this->mailer->send($message);
            $this->logger->info("Parliament Winterthur: Einladung an {$email} gesendet");
        } catch (\Throwable $e) {
            $this->logger->error(
                "Parliament Winterthur: Fehler beim Senden der Einladung an {$email}: " . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    private function erstelleAusRohdaten(string $externId, array $daten): Mitglied {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $mitglied = new Mitglied();
        $mitglied->setExternId($externId);
        $mitglied->setErstelltAm($jetzt);
        $mitglied->setGeloescht(false);
        $this->aktualisiereOeffentlicheFelder($mitglied, $daten);
        return $mitglied;
    }

    private function aktualisiereOeffentlicheFelder(Mitglied $mitglied, array $daten): void {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $mitglied->setName((string) ScraperService::wert($daten, ['lastName', 'name', 'Name', 'nachname', 'Nachname']));
        $mitglied->setVorname((string) ScraperService::wert($daten, ['firstName', 'vorname', 'Vorname', 'firstname']));
        $mitglied->setPartei((string) ScraperService::wert($daten, ['party', 'Party', 'partei', 'Partei']));
        $mitglied->setFraktion((string) ScraperService::wert($daten, ['faction', 'Faction', 'fraktion', 'Fraktion', 'group']));
        $mitglied->setEmail((string) ScraperService::wert($daten, ['email', 'Email', 'mail', 'Mail']));
        $mitglied->setFotoUrl((string) ScraperService::wert($daten, ['photo', 'Photo', 'photoUrl', 'foto', 'image', 'imageUrl']));
        $mitglied->setAktiv(true);
        $mitglied->setAktualisiertAm($jetzt);
    }
}
