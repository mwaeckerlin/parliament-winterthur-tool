<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\DokumentLink;
use OCA\ParliamentWinterthur\Db\DokumentLinkMapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

/**
 * Verwaltet die expliziten Dokument-Verknüpfungen (siehe DokumentLinkMapper):
 * eine bestehende Datei wird — unabhängig vom Namen und Ablageort — einem Objekt
 * (Vorstoss/Geschäft/Sitzung) zugeordnet, und die Liste löst die aktuellen
 * Dateimetadaten über die File-ID auf (Umbenennen/Verschieben bleibt gültig,
 * eine gelöschte Datei fällt aus der Liste).
 */
class DokumentLinkService
{
    public function __construct(
        private readonly DokumentLinkMapper $mapper,
        private readonly IRootFolder $rootFolder,
        private readonly IUserSession $userSession,
    ) {
    }

    private function userFolder()
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Nicht angemeldet');
        }
        return $this->rootFolder->getUserFolder($user->getUID());
    }

    /**
     * Verknüpft eine bestehende Datei (per File-ID) mit dem Objekt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function verknuepfe(string $objektTyp, int $objektId, int $fileId): array
    {
        $userFolder = $this->userFolder();
        $nodes = $userFolder->getById($fileId);
        if ($nodes === []) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        if (!$this->mapper->existiert($objektTyp, $objektId, $fileId)) {
            $node = $nodes[0];
            $link = new DokumentLink();
            $link->setObjektTyp($objektTyp);
            $link->setObjektId($objektId);
            $link->setFileId($fileId);
            $link->setPfad(ltrim((string) $userFolder->getRelativePath($node->getPath()), '/'));
            $link->setErstelltAm((new \DateTime())->format('Y-m-d H:i:s'));
            $this->mapper->insert($link);
        }
        return $this->liste($objektTyp, $objektId);
    }

    /**
     * Verknüpft eine bestehende Datei über ihren Pfad (wie ihn der NC-Filepicker
     * liefert) — löst den Pfad auf die File-ID auf.
     *
     * @return array<int, array<string, mixed>>
     */
    public function verknuepfePfad(string $objektTyp, int $objektId, string $pfad): array
    {
        $userFolder = $this->userFolder();
        $pfad = ltrim($pfad, '/');
        if ($pfad === '' || !$userFolder->nodeExists($pfad)) {
            throw new \InvalidArgumentException('Datei nicht gefunden');
        }
        return $this->verknuepfe($objektTyp, $objektId, $userFolder->get($pfad)->getId());
    }

    /**
     * Löst die Verknüpfung; die Datei selbst bleibt bestehen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entferne(string $objektTyp, int $objektId, int $fileId): array
    {
        $this->mapper->entferne($objektTyp, $objektId, $fileId);
        return $this->liste($objektTyp, $objektId);
    }

    /**
     * Die verknüpften Dateien mit aktuellen Metadaten (gelöschte werden übersprungen).
     *
     * @return array<int, array<string, mixed>>
     */
    public function liste(string $objektTyp, int $objektId): array
    {
        $userFolder = $this->userFolder();
        $eintraege = [];
        foreach ($this->mapper->findByObjekt($objektTyp, $objektId) as $link) {
            $nodes = $userFolder->getById($link->getFileId());
            if ($nodes === []) {
                continue;
            }
            $node = $nodes[0];
            $eintraege[] = [
                'name' => $node->getName(),
                'pfad' => ltrim((string) $userFolder->getRelativePath($node->getPath()), '/'),
                'mime' => $node instanceof File ? $node->getMimeType() : 'httpd/unix-directory',
                'groesse' => $node->getSize(),
                'mtime' => $node->getMTime(),
                'fileId' => $node->getId(),
            ];
        }
        return $eintraege;
    }
}
