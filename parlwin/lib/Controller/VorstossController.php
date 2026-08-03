<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\VorstossService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für politische Vorstösse.
 */
class VorstossController extends Controller
{
    /** Ordner je Herkunft (analog VorstossImportService). */
    private const UNTERORDNER = ['eigene' => '10_Eigene', 'fremde' => '20_Fremde'];

    public function __construct(
        IRequest $request,
        private readonly VorstossService $service,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly IRootFolder $rootFolder,
        private readonly IUserSession $userSession,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function index(): DataResponse
    {
        return new DataResponse($this->service->mitNotizen($this->service->alle()));
    }

    /** Die mit einem Geschäft verknüpften Vorstösse (für die Anzeige im Geschäft). */
    #[NoAdminRequired]
    public function fuerGeschaeft(int $geschaeftId): DataResponse
    {
        return new DataResponse($this->service->mitNotizen($this->service->fuerGeschaeft($geschaeftId)));
    }

    #[NoAdminRequired]
    public function create(): DataResponse
    {
        // Angelegt wird erst beim ausdrücklichen Speichern — ohne Titel gibt es
        // nichts anzulegen.
        $titel = trim((string) $this->request->getParam('titel', ''));
        if ($titel === '') {
            return new DataResponse(['fehler' => 'Titel fehlt'], Http::STATUS_BAD_REQUEST);
        }
        $vorstoss = $this->service->erstelle($this->daten());
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $vorstoss->getId()]);
        return new DataResponse($vorstoss, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        try {
            $vorstoss = $this->service->aktualisiere($id, $this->daten());
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id]);
        return new DataResponse($vorstoss);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse
    {
        try {
            $this->service->loesche($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id]);
        return new DataResponse([]);
    }

    /** Alle Notizen eines Vorstosses (aktiv und gelöscht) — für die shared Komponente. */
    #[NoAdminRequired]
    public function notizen(int $id): DataResponse
    {
        try {
            return new DataResponse($this->service->notizen($id));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Fügt eine Notiz hinzu. Notizen laufen über den GETEILTEN Code
     * (NotizService) — identisch zu den Geschäfts-Notizen (Versionen,
     * Soft-Delete, Undo). Gibt die neue Notiz-Aktion zurück.
     */
    #[NoAdminRequired]
    public function addNotiz(int $id): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');
        try {
            $aktion = $this->service->notizHinzufuegen($id, $text);
            $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function updateNotiz(int $id, int $aktionId): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');
        try {
            $aktion = $this->service->notizAktualisieren($id, $aktionId, $text);
            $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function deleteNotiz(int $id, int $aktionId): DataResponse
    {
        try {
            $this->service->notizLoeschen($id, $aktionId);
            $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse([]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /** Macht das Löschen einer Notiz rückgängig (Undo) — nur der Autor darf das. */
    #[NoAdminRequired]
    public function restoreNotiz(int $id, int $aktionId): DataResponse
    {
        try {
            $aktion = $this->service->notizWiederherstellen($id, $aktionId);
            $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /** Archivierte Vorversionen einer Notiz (älteste zuerst). */
    #[NoAdminRequired]
    public function notizRevisionen(int $id, int $aktionId): DataResponse
    {
        try {
            return new DataResponse($this->service->notizRevisionen($id, $aktionId));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Verknüpft den Vorstoss mit einem Geschäft (schliesst ihn ab). Die
     * Priorität des Vorstosses wird ins Geschäft übernommen.
     */
    #[NoAdminRequired]
    public function verknuepfen(int $id): DataResponse
    {
        $geschaeftId = (int) $this->request->getParam('geschaeftId', 0);
        if ($geschaeftId <= 0) {
            return new DataResponse(['fehler' => 'Geschäft fehlt'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $vorstoss = $this->service->verknuepfen($id, $geschaeftId);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id]);
        return new DataResponse($vorstoss);
    }

    /**
     * Listet die Dokumente eines Vorstosses.
     * Pfad: Fraktion/40_Vorstösse/{10_Eigene|20_Fremde}/V{id}-* relativ zum Userverzeichnis.
     */
    #[NoAdminRequired]
    public function dokumente(int $id): DataResponse
    {
        try {
            [$ordnerPfad, $praefix] = $this->ordnerUndPraefix($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            if (!$userFolder->nodeExists($ordnerPfad)) {
                return new DataResponse([]);
            }
            $ordner = $userFolder->get($ordnerPfad);
            if (!($ordner instanceof \OCP\Files\Folder)) {
                return new DataResponse([]);
            }
            $eintraege = [];
            foreach ($ordner->getDirectoryListing() as $node) {
                $name = $node->getName();
                if (!str_starts_with($name, $praefix . '-')) {
                    continue;
                }
                $eintraege[] = [
                    'name' => $name,
                    'pfad' => ltrim($ordnerPfad . '/' . $name, '/'),
                    'mime' => $node instanceof \OCP\Files\File ? $node->getMimeType() : 'httpd/unix-directory',
                    'groesse' => $node->getSize(),
                    'mtime' => $node->getMTime(),
                    'fileId' => $node->getId(),
                ];
            }
            usort($eintraege, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
            return new DataResponse($eintraege);
        } catch (NotFoundException) {
            return new DataResponse([]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: vorstoss dokumente() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Erstellt ein neues Dokument zu einem Vorstoss.
     * Body: `name` (Suffix nach `V{id}-`), `extension` (z.B. "docx"), `vorlage` (optional).
     */
    #[NoAdminRequired]
    public function dokumentErstellen(int $id): DataResponse
    {
        $name = trim((string) $this->request->getParam('name', ''));
        $extension = ltrim(trim((string) $this->request->getParam('extension', '')), '.');
        $vorlage = trim((string) $this->request->getParam('vorlage', ''));
        if ($name === '' || $extension === '') {
            return new DataResponse(['fehler' => 'Name und Endung erforderlich'], Http::STATUS_BAD_REQUEST);
        }
        try {
            [$ordnerPfad, $praefix] = $this->ordnerUndPraefix($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        $sanitisiert = str_replace([' ', '/', '\\'], ['_', '_', '_'], $name);
        $dateiName = $praefix . '-' . $sanitisiert . '.' . $extension;
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $this->ordnerketteAnlegen($userFolder, $ordnerPfad);
            $zielPfad = $ordnerPfad . '/' . $dateiName;
            if ($userFolder->nodeExists($zielPfad)) {
                return new DataResponse(['fehler' => 'Datei existiert bereits'], Http::STATUS_CONFLICT);
            }
            $inhalt = '';
            if ($vorlage !== '' && $userFolder->nodeExists($vorlage)) {
                $vorlageNode = $userFolder->get($vorlage);
                if ($vorlageNode instanceof \OCP\Files\File) {
                    $inhalt = $vorlageNode->getContent();
                }
            }
            $datei = $userFolder->newFile($zielPfad, $inhalt);
            return new DataResponse([
                'name' => $datei->getName(),
                'pfad' => $zielPfad,
                'fileId' => $datei->getId(),
                'mime' => $datei->getMimeType(),
                'groesse' => $datei->getSize(),
                'mtime' => $datei->getMTime(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: vorstoss dokumentErstellen() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /** Lädt eine Datei in den Vorstoss-Ordner hoch. */
    #[NoAdminRequired]
    public function dokumentHochladen(int $id): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        $datei = $this->request->getUploadedFile('datei');
        if (empty($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new DataResponse(['fehler' => 'Keine Datei hochgeladen'], Http::STATUS_BAD_REQUEST);
        }
        try {
            [$ordnerPfad, $praefix] = $this->ordnerUndPraefix($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $originalName = basename((string) ($datei['name'] ?? 'upload'));
        $sanitisiert = (string) preg_replace('/[\/\\\\]/', '_', $originalName);
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $this->ordnerketteAnlegen($userFolder, $ordnerPfad);
            $zielPfad = $ordnerPfad . '/' . $praefix . '-' . $sanitisiert;
            $inhalt = file_get_contents($datei['tmp_name']);
            $node = $userFolder->nodeExists($zielPfad)
                ? $userFolder->get($zielPfad)
                : $userFolder->newFile($zielPfad, $inhalt);
            if ($node instanceof \OCP\Files\File) {
                $node->putContent($inhalt);
            }
            return new DataResponse([
                'name' => $node->getName(),
                'pfad' => $zielPfad,
                'fileId' => $node->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: vorstoss dokumentHochladen() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ordnerpfad und Datei-Präfix für die Dokumente eines Vorstosses. Der
     * Unterordner richtet sich nach der Herkunft (eigene/fremde).
     *
     * @return array{0:string,1:string}
     * @throws DoesNotExistException
     */
    private function ordnerUndPraefix(int $id): array
    {
        $vorstoss = $this->service->find($id);
        $unterordner = self::UNTERORDNER[$vorstoss->getHerkunft()] ?? self::UNTERORDNER['eigene'];
        return ['Fraktion/40_Vorstösse/' . $unterordner, 'V' . $id];
    }

    private function ordnerketteAnlegen(\OCP\Files\Folder $userFolder, string $ordnerPfad): void
    {
        $aktuell = '';
        foreach (explode('/', $ordnerPfad) as $teil) {
            $aktuell = $aktuell === '' ? $teil : $aktuell . '/' . $teil;
            if (!$userFolder->nodeExists($aktuell)) {
                $userFolder->newFolder($aktuell);
            }
        }
    }

    /** Liest nur die tatsächlich übermittelten Felder (verhindert Leeren beim Update). */
    private function daten(): array
    {
        $daten = [];
        $felder = [
            'titel', 'art', 'herkunft', 'status', 'prioritaet', 'beschluss',
            'zustaendigkeit', 'herkunftFraktion', 'ansprechpartner', 'inhalt', 'dokument',
        ];
        foreach ($felder as $feld) {
            $wert = $this->request->getParam($feld);
            if ($wert !== null) {
                $daten[$feld] = $wert;
            }
        }
        return $daten;
    }
}
