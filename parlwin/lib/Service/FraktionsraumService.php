<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Stellt sicher, dass die gemeinsame Fraktions-Infrastruktur in Nextcloud
 * existiert: Ordnerstruktur (geteilt mit Fraktionsgruppe) und Kalender.
 *
 * Wird beim Containerstart und bei Konfigurationsänderungen aufgerufen.
 * Idempotent — erstellt und teilt nur was fehlt.
 */
class FraktionsraumService
{
    /** Ordnerstruktur gemäss README-Vorgabe (relativ zum Home-Verzeichnis des Kalendernutzers). */
    private const ORDNER_STRUKTUR = [
        'Fraktion',
        'Fraktion/00_Allgemein',
        'Fraktion/10_Sitzungen',
        'Fraktion/10_Sitzungen/2026',
        'Fraktion/20_Geschäfte',
        'Fraktion/30_Kommissionen',
        'Fraktion/30_Kommissionen/Aufsichtskommission',
        'Fraktion/30_Kommissionen/Sachkommission Bildung Sport Kultur',
        'Fraktion/40_Wahlkampf',
        'Fraktion/50_Medien',
        'Fraktion/90_Archiv',
    ];

    /** Ordner-Berechtigungen für die Gruppe: Lesen + Erstellen + Ändern + Löschen */
    private const ORDNER_PERMISSIONS = Constants::PERMISSION_READ
        | Constants::PERMISSION_CREATE
        | Constants::PERMISSION_UPDATE
        | Constants::PERMISSION_DELETE;

    public function __construct(
        private readonly IConfig $config,
        private readonly IRootFolder $rootFolder,
        private readonly IGroupManager $groupManager,
        private readonly IUserManager $userManager,
        private readonly IDBConnection $db,
        private readonly KalenderService $kalenderService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Prüft und erstellt bei Bedarf alle gemeinsamen Fraktions-Ressourcen.
     * Sicher wiederholbar — erstellt und teilt nur was fehlt.
     */
    public function sicherstellen(): void
    {
        $kalenderNutzer = trim((string) $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', ''));
        $gruppe = trim((string) $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', ''));

        if ($kalenderNutzer === '') {
            return;
        }

        $this->sicherstelleOrdnerStruktur($kalenderNutzer, $gruppe);
        $this->kalenderService->sicherstelleKalenderOeffentlich($kalenderNutzer, $gruppe);
    }

    private function sicherstelleOrdnerStruktur(string $nutzer, string $gruppe): void
    {
        try {
            $userFolder = $this->rootFolder->getUserFolder($nutzer);
            foreach (self::ORDNER_STRUKTUR as $pfad) {
                if (!$userFolder->nodeExists($pfad)) {
                    $userFolder->newFolder($pfad);
                    $this->logger->info(sprintf('parlwin: Ordner "%s" für Nutzer "%s" angelegt', $pfad, $nutzer));
                }
            }
            // Nur den Wurzelordner teilen — Unterordner erben die Freigabe.
            if ($gruppe !== '') {
                $wurzel = $userFolder->get('Fraktion');
                $this->sicherstelleOrdnerFreigabe($wurzel, $nutzer, $gruppe);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: Ordnerstruktur konnte nicht erstellt werden: ' . $e->getMessage());
        }
    }

    private function sicherstelleOrdnerFreigabe(Node $node, string $nutzer, string $gruppe): void
    {
        if (!$this->groupManager->groupExists($gruppe)) {
            $this->logger->info(sprintf('parlwin: Gruppe "%s" existiert nicht, kein Sharing', $gruppe));
            return;
        }

        try {
            $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
            $fileId = $node->getId();

            // Prüfen ob bereits geteilt
            $res = $this->db->executeQuery(
                "SELECT id FROM `{$prefix}share` WHERE file_source = ? AND share_type = ? AND share_with = ? LIMIT 1",
                [$fileId, 1, $gruppe] // TYPE_GROUP = 1
            );
            if ($res->fetchOne()) {
                return; // Bereits geteilt
            }

            // Neuen Share in oc_share einfügen
            $this->db->executeQuery(
                "INSERT INTO `{$prefix}share` (share_type, share_with, uid_initiator, file_source, file_target, permissions, stime) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [1, $gruppe, $nutzer, $fileId, '/' . $node->getName(), self::ORDNER_PERMISSIONS, (int) time()]
            );
            $this->logger->info(sprintf('parlwin: Ordner "Fraktion" mit Gruppe "%s" geteilt', $gruppe));
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: Ordner-Sharing fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
