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
class MitgliedService
{
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
     * @param array<string, mixed> $optionen
     * @return array{mitglieder: array, fraktionen: array, kommissionen: array}
     */
    public function synchronisieren(?callable $fortschritt = null, array $optionen = []): array
    {
        $statistik = [
            'mitglieder' => $this->synchronisiereMitglieder(
                $fortschritt,
                is_array($optionen['mitglieder'] ?? null) ? $optionen['mitglieder'] : []
            ),
            'fraktionen' => $this->synchronisiereFraktionen(
                $fortschritt,
                is_array($optionen['fraktionen'] ?? null) ? $optionen['fraktionen'] : []
            ),
            'kommissionen' => $this->synchronisiereKommissionen(
                $fortschritt,
                is_array($optionen['kommissionen'] ?? null) ? $optionen['kommissionen'] : []
            ),
        ];

        // Nextcloud-Gruppe für konfigurierte Fraktion aktualisieren
        $this->aktualisiereNextcloudGruppe();

        return $statistik;
    }

    /**
     * Synchronisiert Parlamentsmitglieder.
     *
     * @param array<string, mixed> $optionen
     * @return array{neu: int, aktualisiert: int, inaktiv: int}
     */
    public function synchronisiereMitglieder(?callable $fortschritt = null, array $optionen = []): array
    {
        $rohdaten = $this->scraper->ladeMitglieder();
        $statistik = ['neu' => 0, 'aktualisiert' => 0, 'inaktiv' => 0];
        $bekannteExternIds = [];
        $gesamt = count($rohdaten);
        $verarbeitet = 0;
        $resumeCursor = trim((string) ($optionen['resume_cursor'] ?? ''));
        $resumeAktiv = $this->istResumeCursorVorhanden($rohdaten, $resumeCursor, ['id', 'Id', 'ID', 'guid', 'personId']);

        if ($fortschritt !== null) {
            $fortschritt([
                'scope' => 'mitglieder',
                'processed' => 0,
                'total' => $gesamt,
                'cursor' => '',
                'final' => false,
            ]);
        }

        foreach ($rohdaten as $daten) {
            $verarbeitet++;
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid', 'personId']);

            if (!empty($externId)) {
                $bekannteExternIds[] = $externId;
            }

            if ($resumeAktiv) {
                if ($externId === $resumeCursor) {
                    $resumeAktiv = false;
                }
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'mitglieder',
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
                        'scope' => 'mitglieder',
                        'processed' => $verarbeitet,
                        'total' => $gesamt,
                        'cursor' => '',
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

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

            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'mitglieder',
                    'processed' => $verarbeitet,
                    'total' => $gesamt,
                    'cursor' => $externId,
                    'final' => $verarbeitet >= $gesamt,
                ]);
            }
        }

        // Mitglieder markieren, die nicht mehr auf der Webseite erscheinen
        if (!empty($bekannteExternIds)) {
            $bekannteExternIds = array_values(array_unique($bekannteExternIds));
            $statistik['inaktiv'] = $this->mitgliedMapper->markiereNichtMehrAktive($bekannteExternIds);
        }

        if ($fortschritt !== null && $verarbeitet >= $gesamt) {
            $fortschritt([
                'scope' => 'mitglieder',
                'processed' => $gesamt,
                'total' => $gesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        $this->logger->info('Parlament Winterthur: Mitglieder synchronisiert', $statistik);
        return $statistik;
    }

    /**
     * Synchronisiert Fraktionen.
     *
     * @param array<string, mixed> $optionen
     * @return array{neu: int, aktualisiert: int}
     */
    public function synchronisiereFraktionen(?callable $fortschritt = null, array $optionen = []): array
    {
        $rohdaten = $this->scraper->ladeFraktionen();
        $statistik = ['neu' => 0, 'aktualisiert' => 0];
        $gesamt = count($rohdaten);
        $verarbeitet = 0;
        $resumeCursor = trim((string) ($optionen['resume_cursor'] ?? ''));
        $resumeAktiv = $this->istResumeCursorVorhanden($rohdaten, $resumeCursor, ['id', 'Id', 'ID', 'guid']);

        if ($fortschritt !== null) {
            $fortschritt([
                'scope' => 'fraktionen',
                'processed' => 0,
                'total' => $gesamt,
                'cursor' => '',
                'final' => false,
            ]);
        }

        foreach ($rohdaten as $daten) {
            $verarbeitet++;
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);

            if ($resumeAktiv) {
                if ($externId === $resumeCursor) {
                    $resumeAktiv = false;
                }
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'fraktionen',
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
                        'scope' => 'fraktionen',
                        'processed' => $verarbeitet,
                        'total' => $gesamt,
                        'cursor' => '',
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            try {
                $fraktion = $this->fraktionMapper->findByExternId($externId);
                $this->aktualisiereFraktionsFelder($fraktion, $daten);
                $this->fraktionMapper->update($fraktion);
                $statistik['aktualisiert']++;
            } catch (DoesNotExistException) {
                $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
                $fraktion = new Fraktion();
                $fraktion->setExternId($externId);
                $fraktion->setGeloescht(false);
                $fraktion->setErstelltAm($jetzt);
                $this->aktualisiereFraktionsFelder($fraktion, $daten);
                $this->fraktionMapper->insert($fraktion);
                $statistik['neu']++;
            }

            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'fraktionen',
                    'processed' => $verarbeitet,
                    'total' => $gesamt,
                    'cursor' => $externId,
                    'final' => $verarbeitet >= $gesamt,
                ]);
            }
        }

        if ($fortschritt !== null && $verarbeitet >= $gesamt) {
            $fortschritt([
                'scope' => 'fraktionen',
                'processed' => $gesamt,
                'total' => $gesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        $this->bereinigePseudoFraktionen();

        return $statistik;
    }

    /**
     * Soft-löscht Fraktionen in der DB, deren Name kein echter Fraktionsname ist
     * (z.B. "Fraktionspräsident/innen" — eine Rollen-Sammlung von der Parlaments-Seite).
     */
    private function bereinigePseudoFraktionen(): void
    {
        foreach ($this->fraktionMapper->findAll() as $fraktion) {
            $name = (string) $fraktion->getName();
            if (!ScraperService::istPseudoFraktionsname($name)) {
                continue;
            }
            if ($fraktion->getGeloescht() === true && $fraktion->getAktiv() !== true) {
                continue;
            }
            $fraktion->setGeloescht(true);
            if (method_exists($fraktion, 'setAktiv')) {
                $fraktion->setAktiv(false);
            }
            $this->fraktionMapper->update($fraktion);
        }
    }

    /**
     * Aktualisiert die importierten Felder einer Fraktion.
     *
     * @param array<string, mixed> $daten
     */
    private function aktualisiereFraktionsFelder(Fraktion $fraktion, array $daten): void
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $fraktion->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
        $fraktion->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
        $mitgliederListe = ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], []);
        $fraktion->setMitglieder(json_encode($mitgliederListe));
        $fraktion->setDatumVon((string) ScraperService::wert($daten, ['datumVon', 'dateFrom', '_datumVon']));
        $fraktion->setDatumBis((string) ScraperService::wert($daten, ['datumBis', 'dateTo', '_datumBis']));
        $fraktion->setAktiv((bool) ScraperService::wert($daten, ['aktiv', 'active', 'isActive', 'is_active'], true));
        $fraktion->setAktualisiertAm($jetzt);

        $this->uebertrageFraktionAufMitglieder($fraktion->getName(), $mitgliederListe);
    }

    /**
     * Setzt bei allen in der Fraktions-Mitgliederliste aufgeführten Personen
     * das Mitglied->fraktion-Feld auf den Namen dieser Fraktion. Damit erhalten
     * auch Fraktionspräsident/innen, deren Funktion auf der Mitgliederliste
     * die echte Fraktion verdeckt, die korrekte Zuordnung.
     *
     * @param mixed $mitgliederListe
     */
    private function uebertrageFraktionAufMitglieder(string $fraktionName, $mitgliederListe): void
    {
        if ($fraktionName === '' || !is_array($mitgliederListe)) {
            return;
        }
        foreach ($mitgliederListe as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $externId = trim((string) ($eintrag['externId'] ?? $eintrag['id'] ?? ''));
            if ($externId === '') {
                continue;
            }
            try {
                $mitglied = $this->mitgliedMapper->findByExternId($externId);
            } catch (DoesNotExistException) {
                continue;
            }
            if ((string) $mitglied->getFraktion() === $fraktionName) {
                continue;
            }
            $mitglied->setFraktion($fraktionName);
            $mitglied->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
            $this->mitgliedMapper->update($mitglied);
        }
    }

    /**
     * Synchronisiert Kommissionen.
     *
     * @param array<string, mixed> $optionen
     * @return array{neu: int, aktualisiert: int}
     */
    public function synchronisiereKommissionen(?callable $fortschritt = null, array $optionen = []): array
    {
        $rohdaten = $this->scraper->ladeKommissionen();
        $statistik = ['neu' => 0, 'aktualisiert' => 0];
        $gesamt = count($rohdaten);
        $verarbeitet = 0;
        $resumeCursor = trim((string) ($optionen['resume_cursor'] ?? ''));
        $resumeAktiv = $this->istResumeCursorVorhanden($rohdaten, $resumeCursor, ['id', 'Id', 'ID', 'guid']);

        if ($fortschritt !== null) {
            $fortschritt([
                'scope' => 'kommissionen',
                'processed' => 0,
                'total' => $gesamt,
                'cursor' => '',
                'final' => false,
            ]);
        }

        foreach ($rohdaten as $daten) {
            $verarbeitet++;
            $externId = (string) ScraperService::wert($daten, ['id', 'Id', 'ID', 'guid']);

            if ($resumeAktiv) {
                if ($externId === $resumeCursor) {
                    $resumeAktiv = false;
                }
                if ($fortschritt !== null) {
                    $fortschritt([
                        'scope' => 'kommissionen',
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
                        'scope' => 'kommissionen',
                        'processed' => $verarbeitet,
                        'total' => $gesamt,
                        'cursor' => '',
                        'final' => $verarbeitet >= $gesamt,
                    ]);
                }
                continue;
            }

            try {
                $kommission = $this->kommissionMapper->findByExternId($externId);
                $kommission->setName((string) ScraperService::wert($daten, ['name', 'Name', 'bezeichnung']));
                $kommission->setBeschreibung((string) ScraperService::wert($daten, ['description', 'beschreibung']));
                $kommission->setMitglieder(json_encode(ScraperService::wert($daten, ['members', 'mitglieder', 'persons'], [])));
                $kommission->setDatumVon((string) ScraperService::wert($daten, ['datumVon', '_datumVon', 'dateFrom', 'von']));
                $kommission->setDatumBis((string) ScraperService::wert($daten, ['datumBis', '_datumBis', 'dateTo', 'bis']));
                $kommission->setAktiv((bool) ScraperService::wert($daten, ['aktiv', 'active', 'isActive', 'is_active'], true));
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
                $kommission->setDatumVon((string) ScraperService::wert($daten, ['datumVon', '_datumVon', 'dateFrom', 'von']));
                $kommission->setDatumBis((string) ScraperService::wert($daten, ['datumBis', '_datumBis', 'dateTo', 'bis']));
                $kommission->setAktiv((bool) ScraperService::wert($daten, ['aktiv', 'active', 'isActive', 'is_active'], true));
                $kommission->setGeloescht(false);
                $kommission->setErstelltAm($jetzt);
                $kommission->setAktualisiertAm($jetzt);
                $this->kommissionMapper->insert($kommission);
                $statistik['neu']++;
            }

            if ($fortschritt !== null) {
                $fortschritt([
                    'scope' => 'kommissionen',
                    'processed' => $verarbeitet,
                    'total' => $gesamt,
                    'cursor' => $externId,
                    'final' => $verarbeitet >= $gesamt,
                ]);
            }
        }

        if ($fortschritt !== null && $verarbeitet >= $gesamt) {
            $fortschritt([
                'scope' => 'kommissionen',
                'processed' => $gesamt,
                'total' => $gesamt,
                'cursor' => '',
                'final' => true,
            ]);
        }

        return $statistik;
    }

    /**
     * @param array<int, array<string, mixed>> $rohdaten
     * @param array<int, string> $idFelder
     */
    private function istResumeCursorVorhanden(array $rohdaten, string $resumeCursor, array $idFelder): bool
    {
        if ($resumeCursor === '') {
            return false;
        }

        foreach ($rohdaten as $daten) {
            $externId = (string) ScraperService::wert($daten, $idFelder);
            if ($externId === $resumeCursor) {
                return true;
            }
        }

        return false;
    }

    /**
     * Erstellt oder aktualisiert die Nextcloud-Gruppe für die konfigurierte Fraktion.
     *
     * - Neue Mitglieder werden per E-Mail eingeladen.
     * - Bestehende Mitglieder bleiben in der Gruppe.
     * - Entfernen aus der Gruppe erfolgt ausschliesslich über «Ausgewählte abgleichen» im Admin-UI.
     */
    public function aktualisiereNextcloudGruppe(): void
    {
        $konfigFraktion = $this->config->getAppValue('parlwin', 'fraktion', '');
        $konfigGruppe = $this->config->getAppValue('parlwin', 'nextcloud_gruppe', '');

        if (empty($konfigFraktion) || empty($konfigGruppe)) {
            $this->logger->debug('Parlament Winterthur: Keine Fraktion/Gruppe konfiguriert, überspringe Gruppenaktualisierung');
            return;
        }

        // Mitglieder der konfigurierten Fraktion laden
        $fraktionsmitglieder = $this->mitgliedMapper->findByFraktion($konfigFraktion);

        // Nextcloud-Gruppe erstellen wenn nicht vorhanden
        if (!$this->groupManager->groupExists($konfigGruppe)) {
            $this->groupManager->createGroup($konfigGruppe);
            $this->logger->info("Parlament Winterthur: Nextcloud-Gruppe '{$konfigGruppe}' erstellt");
        }

        $gruppe = $this->groupManager->get($konfigGruppe);
        if ($gruppe === null) {
            $this->logger->error("Parlament Winterthur: Gruppe '{$konfigGruppe}' konnte nicht geladen werden");
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
                        "Parlament Winterthur: Benutzer '{$user->getUID()}' zur Gruppe '{$konfigGruppe}' hinzugefügt"
                    );
                }
            } else {
                // Benutzer existiert nicht → Einladungs-E-Mail senden
                $this->sendeEinladung($email, $mitglied->getVollerName(), $konfigGruppe);
            }
        }

    }

    /**
     * Gibt alle Parlamentsmitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function alle(): array
    {
        return $this->mitgliedMapper->findAlle();
    }

    /**
     * Gibt alle aktiven Parlamentsmitglieder zurück.
     *
     * @return Mitglied[]
     */
    public function aktive(): array
    {
        return $this->mitgliedMapper->findAktive();
    }

    /**
     * Gibt ein Mitglied anhand seiner ID zurück.
     *
     * @throws DoesNotExistException
     */
    public function eins(int $id): Mitglied
    {
        return $this->mitgliedMapper->find($id);
    }

    /**
     * Gibt aktive Mitglieder einer Fraktion zurück.
     *
     * Bevorzugte Quelle ist die in der Fraktions-Entität gespeicherte Mitglieder-
     * liste (mit externId). Damit funktioniert das Matching auch dann zuverlässig,
     * wenn die Schreibweise des Fraktionsnamens im Mitgliedsdatensatz von der
     * offiziellen Bezeichnung abweicht (z.B. "Die Mitte (Die Mitte)" auf der
     * Fraktionsliste vs. "Die Mitte / EVP" im Tätig-in-Feld des Mitglieds).
     *
     * @return Mitglied[]
     */
    public function aktiveDerFraktion(string $fraktion): array
    {
        $name = trim($fraktion);
        if ($name === '') {
            return [];
        }

        try {
            $fraktionEntity = $this->fraktionMapper->findByName($name);
            $mitglieder = $this->mitgliederAusFraktionsEntitaet($fraktionEntity);
            if ($mitglieder !== []) {
                return $mitglieder;
            }
        } catch (DoesNotExistException) {
            // Fallback unten.
        }

        return $this->mitgliedMapper->findByFraktion($name);
    }

    /**
     * Prüft, ob ein Mitglied zu der gegebenen Fraktion gehört.
     *
     * Berücksichtigt sowohl die in der Fraktions-Entität gespeicherte Mitglieder-
     * liste (externId-basiert) als auch den `fraktion`-String am Mitglied selbst.
     */
    public function gehoertZurFraktion(Mitglied $mitglied, string $fraktion): bool
    {
        $name = trim($fraktion);
        if ($name === '') {
            return false;
        }

        $externId = trim((string) $mitglied->getExternId());
        if ($externId !== '') {
            try {
                $fraktionEntity = $this->fraktionMapper->findByName($name);
                if ($this->externIdInFraktion($externId, $fraktionEntity)) {
                    return true;
                }
            } catch (DoesNotExistException) {
                // Fallback unten.
            }
        }

        return strcasecmp(trim((string) $mitglied->getFraktion()), $name) === 0;
    }

    /**
     * @return Mitglied[]
     */
    private function mitgliederAusFraktionsEntitaet(Fraktion $fraktion): array
    {
        $eintraege = $this->dekodiereFraktionsmitglieder($fraktion);
        $mitglieder = [];
        $gesehen = [];

        foreach ($eintraege as $eintrag) {
            $externId = trim((string) ($eintrag['externId'] ?? $eintrag['id'] ?? ''));
            if ($externId === '' || isset($gesehen[$externId])) {
                continue;
            }
            $aktiv = (bool) ($eintrag['aktiv'] ?? true);
            if (!$aktiv) {
                continue;
            }
            try {
                $mitglied = $this->mitgliedMapper->findByExternId($externId);
            } catch (DoesNotExistException) {
                continue;
            }
            if ($mitglied->getGeloescht() === true) {
                continue;
            }
            if ($mitglied->getAktiv() !== true) {
                continue;
            }
            $gesehen[$externId] = true;
            $mitglieder[] = $mitglied;
        }

        usort(
            $mitglieder,
            static fn (Mitglied $a, Mitglied $b): int => strcmp((string) $a->getName(), (string) $b->getName())
        );

        return $mitglieder;
    }

    private function externIdInFraktion(string $externId, Fraktion $fraktion): bool
    {
        foreach ($this->dekodiereFraktionsmitglieder($fraktion) as $eintrag) {
            $kandidat = trim((string) ($eintrag['externId'] ?? $eintrag['id'] ?? ''));
            if ($kandidat !== '' && $kandidat === $externId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dekodiereFraktionsmitglieder(Fraktion $fraktion): array
    {
        $roh = (string) $fraktion->getMitglieder();
        if ($roh === '') {
            return [];
        }
        $liste = json_decode($roh, true);
        if (!is_array($liste)) {
            return [];
        }
        $ergebnis = [];
        foreach ($liste as $eintrag) {
            if (is_array($eintrag)) {
                $ergebnis[] = $eintrag;
            }
        }
        return $ergebnis;
    }

    /**
     * Persistiert das Mapping eines Mitglieds auf einen Nextcloud-Benutzernamen.
     *
     * @throws DoesNotExistException
     */
    public function setzeNextcloudUid(int $mitgliedId, string $nextcloudUid): Mitglied
    {
        $mitglied = $this->mitgliedMapper->find($mitgliedId);
        $mitglied->setNextcloudUid($nextcloudUid);
        $mitglied->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->mitgliedMapper->update($mitglied);
    }

    /**
     * Sendet eine Einladungs-E-Mail an eine Person, die noch kein Nextcloud-Konto hat.
     */
    private function sendeEinladung(string $email, string $name, string $gruppe): void
    {
        try {
            $absenderEmail = $this->config->getAppValue('parlwin', 'absender_email', 'noreply@example.com');
            $absenderName = $this->config->getAppValue('parlwin', 'absender_name', 'Parlament Winterthur Tool');
            $nextcloudUrl = rtrim($this->config->getSystemValue('overwrite.cli.url', ''), '/');

            $message = $this->mailer->createMessage();
            $message->setFrom([$absenderEmail => $absenderName]);
            $message->setTo([$email => $name]);
            $message->setSubject('Einladung zum Parlament Winterthur Tool');
            $message->setPlainBody(
                "Hallo {$name},\n\n" .
                "Sie wurden als Mitglied der Fraktion/Gruppe '{$gruppe}' im Parlament Winterthur Tool eingetragen.\n\n" .
                "Um Zugang zu erhalten, erstellen Sie bitte ein Nextcloud-Konto unter:\n" .
                "{$nextcloudUrl}\n\n" .
                "Verwenden Sie dabei diese E-Mail-Adresse: {$email}\n\n" .
                "Mit freundlichen Grüssen\n" .
                "Parlament Winterthur Tool"
            );

            $this->mailer->send($message);
            $this->logger->info("Parlament Winterthur: Einladung an {$email} gesendet");
        } catch (\Throwable $e) {
            $this->logger->error(
                "Parlament Winterthur: Fehler beim Senden der Einladung an {$email}: " . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    private function erstelleAusRohdaten(string $externId, array $daten): Mitglied
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $mitglied = new Mitglied();
        $mitglied->setExternId($externId);
        $mitglied->setErstelltAm($jetzt);
        $mitglied->setGeloescht(false);
        $this->aktualisiereOeffentlicheFelder($mitglied, $daten);
        return $mitglied;
    }

    private function aktualisiereOeffentlicheFelder(Mitglied $mitglied, array $daten): void
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $mitglied->setName((string) ScraperService::wert($daten, ['lastName', 'name', 'Name', 'nachname', 'Nachname']));
        $mitglied->setVorname((string) ScraperService::wert($daten, ['firstName', 'vorname', 'Vorname', 'firstname']));
        $mitglied->setPartei((string) ScraperService::wert($daten, ['party', 'Party', 'partei', 'Partei']));
        $fraktionWert = (string) ScraperService::wert($daten, ['faction', 'Faction', 'fraktion', 'Fraktion', 'group']);
        if (ScraperService::istPseudoFraktionsname($fraktionWert)) {
            $fraktionWert = '';
        }
        $mitglied->setFraktion($fraktionWert);
        $mitglied->setEmail((string) ScraperService::wert($daten, ['email', 'Email', 'mail', 'Mail']));
        $mitglied->setFotoUrl((string) ScraperService::wert($daten, ['photo', 'Photo', 'photoUrl', 'foto', 'image', 'imageUrl']));
        $mitglied->setAktiv($this->normalisiereBool(
            ScraperService::wert($daten, ['aktiv', 'active', 'isActive', 'is_active', 'status'], true),
            true
        ));
        $mitglied->setGeloescht(false);
        $mitglied->setAktualisiertAm($jetzt);
    }

    private function normalisiereBool(mixed $wert, bool $standard): bool
    {
        if (is_bool($wert)) {
            return $wert;
        }
        if (is_int($wert) || is_float($wert)) {
            return (int) $wert !== 0;
        }
        if (is_string($wert)) {
            $normalisiert = strtolower(trim($wert));
            if (in_array($normalisiert, ['1', 'true', 'aktiv', 'active', 'yes', 'ja'], true)) {
                return true;
            }
            if (in_array($normalisiert, ['0', 'false', 'inaktiv', 'inactive', 'no', 'nein'], true)) {
                return false;
            }
        }
        return $standard;
    }
}
