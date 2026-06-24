<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Stellt sicher, dass die gemeinsame Fraktions-Infrastruktur in Nextcloud
 * existiert: Ordnerstruktur (geteilt mit Fraktionsgruppe) und Kalender.
 *
 * Architektur: Ordner + Kalender werden fest im Admin-Account (ADMIN_USER)
 * angelegt und mit der Fraktionsgruppe geteilt.
 * Wenn Members bereits lokale "Fraktion"-Ordner haben, werden diese aufgelöst:
 * Daten verschieben → lokal löschen (Konflikt-Handler).
 */
class FraktionsraumService
{
    private const ORDNER_STRUKTUR = [
        'Fraktion',
        'Fraktion/00_Allgemein',
        'Fraktion/10_Sitzungen',
        'Fraktion/10_Sitzungen/2026',
        'Fraktion/20_Geschäfte',
        'Fraktion/30_Kommissionen',
        'Fraktion/30_Kommissionen/Aufsichtskommission',
        'Fraktion/30_Kommissionen/Sachkommission Bildung Sport Kultur',
        'Fraktion/40_Vorstösse',
        'Fraktion/40_Vorstösse/10_Eigene',
        'Fraktion/40_Vorstösse/20_Fremde',
        'Fraktion/50_Finanzen',
        'Fraktion/60_Wahlkampf',
        'Fraktion/70_Medien',
        'Fraktion/90_Archiv',
    ];

    /**
     * Umbenennungen bestehender Ordner (Move alter → neuer Name). Wird vor dem
     * Anlegen der Struktur ausgeführt, damit vorhandene Inhalte erhalten bleiben.
     */
    private const ORDNER_UMBENENNUNGEN = [
        'Fraktion/40_Wahlkampf' => 'Fraktion/60_Wahlkampf',
        'Fraktion/50_Medien' => 'Fraktion/70_Medien',
    ];

    private const ORDNER_PERMISSIONS = Constants::PERMISSION_READ
        | Constants::PERMISSION_CREATE
        | Constants::PERMISSION_UPDATE
        | Constants::PERMISSION_DELETE;

    private const ADMIN_USER = 'admin';

    public function __construct(
        private readonly IConfig $config,
        private readonly IRootFolder $rootFolder,
        private readonly IGroupManager $groupManager,
        private readonly IUserManager $userManager,
        private readonly IDBConnection $db,
        private readonly KalenderService $kalenderService,
        private readonly IShareManager $shareManager,
        private readonly LoggerInterface $logger,
        private readonly DeckService $deckService,
    ) {
    }

    /** @var array<string,string> Diagnosebericht des letzten sicherstellen()-Laufs. */
    private array $bericht = [];

    /** @return array<string,string> Bericht des letzten sicherstellen()-Laufs. */
    public function getBericht(): array
    {
        return $this->bericht;
    }

    public function sicherstellen(): void
    {
        $this->bericht = [];
        $gruppe = trim((string) $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', ''));

        if ($gruppe === '' || !$this->groupManager->groupExists($gruppe)) {
            $this->bericht['status'] = 'übersprungen: Gruppe fehlt oder existiert nicht (' . $gruppe . ')';
            return;
        }

        // 1. Offizielle Ordnerstruktur im Admin-Account anlegen (noch NICHT teilen):
        //    erst müssen bei den Mitgliedern eventuelle eigene "Fraktion"-Ordner aus
        //    dem Weg geräumt werden, sonst mountet Nextcloud den Share als "Fraktion (2)".
        $adminFraktion = $this->legeAdminOrdnerAn();
        if ($adminFraktion === null) {
            return;
        }

        // 2. Mitglieder-Konflikte auflösen: fremde Freigaben verlassen, eigene lokale
        //    Ordner als "Fraktion.bak" sichern und ihren Inhalt in den offiziellen
        //    Ordner übernehmen.
        $this->loeseMitgliedKonflikte($gruppe, $adminFraktion);

        // 3. Offiziellen Ordner mit der Gruppe teilen und für alle akzeptieren.
        $this->teileOrdnerMitGruppe($adminFraktion, self::ADMIN_USER, $gruppe);

        // 4. Kalender im Admin-Account anlegen und mit Gruppe teilen.
        $this->kalenderService->sicherstelleKalenderOeffentlich(self::ADMIN_USER, $gruppe);

        // 5. Gemeinsames Deck-Board anlegen und mit Gruppe teilen (falls Deck installiert).
        $this->deckService->sicherstellenBoard(self::ADMIN_USER, $gruppe);
    }

    /** Legt die offizielle Ordnerstruktur im Admin-Account an und liefert den Fraktion-Ordner. */
    private function legeAdminOrdnerAn(): ?Folder
    {
        try {
            $adminFolder = $this->rootFolder->getUserFolder(self::ADMIN_USER);

            // Bestehende Ordner auf neue Namen verschieben (idempotent: nur wenn
            // der alte Ordner existiert und der neue noch nicht). Erhält Inhalte.
            foreach (self::ORDNER_UMBENENNUNGEN as $alt => $neu) {
                if ($adminFolder->nodeExists($alt) && !$adminFolder->nodeExists($neu)) {
                    $adminFolder->get($alt)->move($adminFolder->getFullPath($neu));
                    $this->logger->info('parlwin: Ordner verschoben: ' . $alt . ' → ' . $neu);
                }
            }

            foreach (self::ORDNER_STRUKTUR as $pfad) {
                if (!$adminFolder->nodeExists($pfad)) {
                    $adminFolder->newFolder($pfad);
                }
            }

            $fraktionOrdner = $adminFolder->get('Fraktion');
            if (!$fraktionOrdner instanceof Folder) {
                $this->bericht['ordner_struktur'] = 'FEHLER: Fraktion ist kein Ordner';
                return null;
            }
            $this->bericht['ordner_existiert'] = 'ja (id=' . $fraktionOrdner->getId() . ')';
            $this->logger->info('parlwin: Admin-Ordnerstruktur sichergestellt');
            return $fraktionOrdner;
        } catch (\Throwable $e) {
            $this->bericht['ordner_struktur'] = 'FEHLER [' . get_class($e) . ']: ' . $e->getMessage();
            $this->logger->warning('parlwin: Admin-Ordnerstruktur fehlgeschlagen: ' . $e->getMessage());
            return null;
        }
    }

    private function teileOrdnerMitGruppe(Node $ordner, string $owner, string $gruppe): void
    {
        try {
            // Bestehenden Gruppen-Share suchen (eigene try/catch-Schicht, damit
            // eine fehlschlagende Abfrage das eigentliche Teilen nicht verhindert).
            $share = null;
            try {
                $bestehende = $this->shareManager->getSharesBy($owner, IShare::TYPE_GROUP, $ordner, false, 50);
                foreach ($bestehende as $vorhanden) {
                    if ($vorhanden->getSharedWith() === $gruppe) {
                        $share = $vorhanden;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('parlwin: Share-Prüfung fehlgeschlagen: ' . $e->getMessage());
            }

            if ($share === null) {
                // Über die Nextcloud-Share-API teilen. Ein direkter SQL-INSERT in
                // oc_share genügt nicht: Nextcloud mountet den Ordner dann nicht für
                // die Gruppenmitglieder. Die Share-API kümmert sich um Mount,
                // Akzeptanz und Cache.
                $share = $this->shareManager->newShare();
                $share->setNode($ordner);
                $share->setShareType(IShare::TYPE_GROUP);
                $share->setSharedWith($gruppe);
                $share->setSharedBy($owner);
                $share->setPermissions(self::ORDNER_PERMISSIONS);
                $share = $this->shareManager->createShare($share);
                $this->bericht['ordner_share'] = 'erstellt';
                $this->logger->info('parlwin: Ordner mit Gruppe geteilt: ' . $ordner->getName());
            } else {
                $this->bericht['ordner_share'] = 'bestehend';
            }

            // KERN: Ein Gruppen-Share muss für jedes Mitglied AKZEPTIERT sein,
            // sonst bleibt er STATUS_PENDING und Nextcloud mountet den Ordner nicht
            // – er erscheint dann bei den Mitgliedern gar nicht. Nextclouds
            // Auto-Accept-Listener greift nur einmalig beim Erstellen und nur für
            // die zu diesem Zeitpunkt vorhandenen Mitglieder, die Freigaben
            // automatisch annehmen. Ein bestehender, nie akzeptierter Share oder
            // später hinzugekommene Mitglieder bleiben sonst dauerhaft hängen.
            // Darum bei JEDEM Lauf für alle aktuellen Mitglieder explizit
            // akzeptieren – idempotent und unabhängig von Annahme-Einstellungen.
            $this->akzeptiereFuerAlleMitglieder($share, $gruppe);
        } catch (\Throwable $e) {
            $this->bericht['ordner_share'] = 'FEHLER [' . get_class($e) . ']: ' . $e->getMessage();
            $this->logger->warning('parlwin: Ordner-Sharing fehlgeschlagen [' . get_class($e) . ']: ' . $e->getMessage());
        }
    }

    /**
     * Akzeptiert den Gruppen-Share für jedes aktuelle Mitglied (ausser Admin),
     * damit Nextcloud den Ordner zuverlässig mountet. Idempotent: bereits
     * akzeptierte Shares bleiben akzeptiert.
     */
    private function akzeptiereFuerAlleMitglieder(IShare $share, string $gruppe): void
    {
        $group = $this->groupManager->get($gruppe);
        if ($group === null) {
            return;
        }

        $akzeptiert = 0;
        foreach ($group->getUsers() as $member) {
            $uid = $member->getUID();
            if ($uid === self::ADMIN_USER) {
                continue; // Admin ist Besitzer – kein eingehender Share.
            }
            try {
                $this->shareManager->acceptShare($share, $uid);
                // Mountpoint deterministisch auf den Ordnernamen setzen. Hatte ein
                // Mitglied beim Akzeptieren noch einen gleichnamigen Mount im
                // Request-Cache (z.B. die gerade abgelöste Freigabe eines anderen
                // Mitglieds), vergibt Nextcloud sonst "Fraktion (2)" und der Ordner
                // erscheint nicht unter "Fraktion". Der Konflikt ist hier bereits
                // aufgelöst, daher das Ziel korrigieren.
                $share->setTarget('/' . $share->getNode()->getName());
                $this->shareManager->moveShare($share, $uid);
                $akzeptiert++;
            } catch (\Throwable $e) {
                $this->logger->warning('parlwin: Share-Akzeptanz für ' . $uid . ' fehlgeschlagen: ' . $e->getMessage());
            }
        }
        $this->bericht['ordner_share_akzeptiert'] = (string) $akzeptiert;
    }

    private function loeseMitgliedKonflikte(string $gruppe, Folder $adminFraktion): void
    {
        try {
            $group = $this->groupManager->get($gruppe);
            if (!$group) {
                return;
            }

            foreach ($group->getUsers() as $member) {
                $uid = $member->getUID();
                if ($uid === self::ADMIN_USER) {
                    continue; // Admin ist Besitzer des offiziellen Ordners.
                }
                $this->loeseMitgliedFraktion($uid, $adminFraktion);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: Konflikt-Handler fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Räumt einen bei einem Mitglied vorhandenen "Fraktion"-Eintrag aus dem Weg,
     * damit der offizielle Admin-Ordner als "Fraktion" gemountet werden kann:
     *
     *  - Gehört "Fraktion" bereits dem Admin (offizieller Share) → nichts tun.
     *  - Ist es die Freigabe eines ANDEREN Mitglieds → diese Freigabe verlassen (A).
     *  - Ist das Mitglied selbst Besitzer → nach "Fraktion.bak" sichern, eigene
     *    Freigaben lösen, Inhalt in den offiziellen Ordner übernehmen, Sicherung
     *    behalten (B).
     */
    private function loeseMitgliedFraktion(string $uid, Folder $adminFraktion): void
    {
        try {
            $memberFolder = $this->rootFolder->getUserFolder($uid);
            if (!$memberFolder->nodeExists('Fraktion')) {
                return; // Kein Eintrag → der offizielle Share mountet automatisch als "Fraktion".
            }

            $fraktion = $memberFolder->get('Fraktion');
            $besitzer = $fraktion->getOwner();
            $besitzerUid = $besitzer?->getUID();

            if ($besitzerUid === self::ADMIN_USER) {
                return; // Bereits der offizielle, geteilte Ordner – sauber.
            }

            if ($besitzerUid !== $uid) {
                // A: Freigabe eines anderen Mitglieds → verlassen.
                $this->verlasseFremdeFreigabe($fraktion, $uid);
                $this->bericht['konflikt_' . $uid] = 'fremde Freigabe verlassen';
                return;
            }

            // B: eigener lokaler Ordner (eventuell selbst mit der Gruppe geteilt).
            // B0: eigene Gruppen-Freigaben lösen, sonst sähen die anderen Mitglieder
            //     weiterhin den (jetzt umbenannten) Ordner als "Fraktion.bak".
            $this->loescheEigeneGruppenShares($fraktion, $uid);

            // B1: lokalen Ordner als Sicherung umbenennen (eindeutiger Name).
            $bakName = $this->freierName($memberFolder, 'Fraktion.bak');
            $fraktion->move($memberFolder->getPath() . '/' . $bakName);
            $bak = $memberFolder->get($bakName);

            // B3+B4: Inhalt in den offiziellen Ordner übernehmen (rekursiv kopieren,
            //        bei Namenskonflikt als "*.migrated").
            $kopiert = 0;
            if ($bak instanceof Folder) {
                $kopiert = $this->migriereInhalte($bak, $adminFraktion);
            }

            // B5: Sicherung bleibt erhalten – kein Löschen.
            $this->bericht['konflikt_' . $uid] = $bakName . ' gesichert, ' . $kopiert . ' Dateien übernommen';
            $this->logger->info("parlwin: $uid - eigener Fraktion-Ordner nach $bakName gesichert, $kopiert Dateien übernommen");
        } catch (\Throwable $e) {
            $this->logger->warning("parlwin: Konflikt-Lösung für $uid fehlgeschlagen: " . $e->getMessage());
        }
    }

    /** Verlässt alle Gruppen-Freigaben, die das Mitglied auf diesem Knoten empfängt. */
    private function verlasseFremdeFreigabe(Node $node, string $uid): void
    {
        try {
            $shares = $this->shareManager->getSharedWith($uid, IShare::TYPE_GROUP, $node, 50);
            foreach ($shares as $share) {
                $this->shareManager->deleteFromSelf($share, $uid);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("parlwin: Freigabe verlassen für $uid fehlgeschlagen: " . $e->getMessage());
        }
    }

    /** Löscht die Gruppen-Freigaben, die das Mitglied selbst auf diesem Knoten erstellt hat. */
    private function loescheEigeneGruppenShares(Node $node, string $uid): void
    {
        try {
            $shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_GROUP, $node, false, 50);
            foreach ($shares as $share) {
                $this->shareManager->deleteShare($share);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("parlwin: Eigene Freigabe entfernen für $uid fehlgeschlagen: " . $e->getMessage());
        }
    }

    /**
     * Übernimmt den Inhalt von $quelle rekursiv in $ziel: fehlende Ordner werden
     * angelegt, Dateien kopiert. Existiert im Ziel bereits eine Datei gleichen
     * Namens, wird die Kopie als "<name>.migrated" abgelegt. Liefert die Anzahl
     * kopierter Dateien. Die Quelle bleibt unverändert.
     */
    private function migriereInhalte(Folder $quelle, Folder $ziel): int
    {
        $anzahl = 0;
        foreach ($quelle->getDirectoryListing() as $kind) {
            $name = $kind->getName();
            if ($kind instanceof Folder) {
                $zielKind = $ziel->nodeExists($name) ? $ziel->get($name) : $ziel->newFolder($name);
                if ($zielKind instanceof Folder) {
                    $anzahl += $this->migriereInhalte($kind, $zielKind);
                }
            } else {
                $zielName = $ziel->nodeExists($name) ? $this->freierName($ziel, $name . '.migrated') : $name;
                $kind->copy($ziel->getPath() . '/' . $zielName);
                $anzahl++;
            }
        }
        return $anzahl;
    }

    /** Liefert einen im Ordner noch nicht vergebenen Namen ("basis", "basis.1", "basis.2", …). */
    private function freierName(Folder $ordner, string $basis): string
    {
        if (!$ordner->nodeExists($basis)) {
            return $basis;
        }
        $i = 1;
        while ($ordner->nodeExists($basis . '.' . $i)) {
            $i++;
        }
        return $basis . '.' . $i;
    }
}
