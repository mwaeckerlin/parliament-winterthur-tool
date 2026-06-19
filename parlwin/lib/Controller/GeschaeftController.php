<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Controller;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für politische Geschäfte.
 */
class GeschaeftController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly GeschaeftService $service,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly IRootFolder $rootFolder,
        private readonly IUserSession $userSession,
        private readonly LoggerInterface $logger,
        private readonly GeschaeftMapper $geschaeftMapper,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Geschäfte zurück.
     */
    #[NoAdminRequired]
    public function index(): DataResponse
    {
        // limit/offset bewusst über getParam statt typisierte Methoden-Parameter:
        // Nextcloud 34 begrenzt einen Controller-Parameter namens "limit" hart auf
        // 1–500 (ParameterOutOfRangeException im Dispatcher). parlwin lädt aber die
        // ganze Geschäftsliste auf einmal (z.B. limit=2000).
        $limit = max(1, (int) $this->request->getParam('limit', 100));
        $offset = max(0, (int) $this->request->getParam('offset', 0));
        $filterLetzterBeschluss = (string) $this->request->getParam('letzter_beschluss', '');
        $filterEntscheidungsbedarfRaw = strtolower((string) $this->request->getParam('entscheidungsbedarf', ''));
        $showErledigtRaw = strtolower((string) $this->request->getParam('show_erledigt', '0'));
        $filterStatus = (string) $this->request->getParam('status', '');

        $inklusiveErledigt = in_array($showErledigtRaw, ['1', 'true', 'ja'], true);
        $filterEntscheidungsbedarf = null;
        if (in_array($filterEntscheidungsbedarfRaw, ['1', 'true', 'ja'], true)) {
            $filterEntscheidungsbedarf = true;
        } elseif (in_array($filterEntscheidungsbedarfRaw, ['0', 'false', 'nein'], true)) {
            $filterEntscheidungsbedarf = false;
        }

        $geschaefte = $this->service->alle($limit, $offset, $inklusiveErledigt);

        // Filter nach Status wenn angegeben
        if ($filterStatus !== '') {
            $geschaefte = array_filter($geschaefte, fn($g) => $g->getStatus() === $filterStatus);
        }

        $daten = $this->fraktionsarbeitService->angereicherteGeschaefte(
            $geschaefte,
            $filterLetzterBeschluss,
            $filterEntscheidungsbedarf
        );
        return new DataResponse(array_values($daten));
    }

    /**
     * Gibt ein einzelnes Geschäft zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse
    {
        try {
            $daten = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
            return new DataResponse($daten);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Geschäfts.
     */
    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        try {
            $zustaendigkeiten = $this->request->getParam('zustaendigkeiten', null);
            $hauptPersonKey = (string) $this->request->getParam('haupt_person_key', '');
            if (!is_array($zustaendigkeiten)) {
                return new DataResponse(
                    ['fehler' => 'Nur zustaendigkeiten/haupt_person_key sind via PUT erlaubt'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $neu = $this->fraktionsarbeitService->zustaendigkeitenSetzen($id, $zustaendigkeiten, $hauptPersonKey);
            $this->realtimePublisher->publish('geschaefte.updated', [
                'id' => $id,
                'grund' => 'zustaendigkeiten',
            ]);
            return new DataResponse(['zustaendigkeiten' => $neu]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addNotiz(int $id): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->notizHinzufuegen($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'notiz',
            ]);
            return new DataResponse($aktion);
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
            $aktion = $this->fraktionsarbeitService->notizAktualisieren($id, $aktionId, $text);
            $this->realtimePublisher->publish('geschaefte.action', ['id' => $id, 'aktionTyp' => 'notiz']);
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
            $this->fraktionsarbeitService->notizLoeschen($id, $aktionId);
            $this->realtimePublisher->publish('geschaefte.action', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse([]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addBeschluss(int $id): DataResponse
    {
        $code = (string) $this->request->getParam('code', '');
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->beschlussHinzufuegen($id, $code, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'beschluss',
                'aktionCode' => $code,
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function removeBeschluss(int $id): DataResponse
    {
        try {
            $aktion = $this->fraktionsarbeitService->beschlussZuruecknehmen($id);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'beschluss',
                'aktionCode' => 'beschluss_zurueckgenommen',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addVotum(int $id): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->votumHinzufuegen($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Aktualisiert den Text des aktuell aktiven Votums (Autosave) oder
     * erstellt ein neues, falls keines existiert. Nur die zuständige Person
     * darf das Votum bearbeiten.
     */
    #[NoAdminRequired]
    public function updateVotum(int $id): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->votumAktualisieren($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
                'aktionCode' => 'votum_im_rat',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Archiviert das aktuell aktive Votum (entscheid_gueltig -> false).
     * Damit bleibt es als historischer Eintrag in der Zeitleiste und ein
     * neues Votum kann erfasst werden.
     */
    #[NoAdminRequired]
    public function archiviereVotum(int $id): DataResponse
    {
        try {
            $aktion = $this->fraktionsarbeitService->votumArchivieren($id);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
                'aktionCode' => 'votum_archiviert',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Liefert eine druckoptimierte HTML-Ansicht des aktuellen Votums.
     * Browser kann diese via "Drucken -> Als PDF speichern" als PDF
     * exportieren (vermeidet zusätzliche PHP-PDF-Bibliotheken und
     * Composer-Abhängigkeiten).
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function votumPdf(int $id): TemplateResponse
    {
        try {
            $daten = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new TemplateResponse(
                Application::APP_ID,
                'votum_pdf',
                ['id' => $id, 'titel' => 'Nicht gefunden'],
                'blank'
            );
        }
        $response = new TemplateResponse(Application::APP_ID, 'votum_pdf', $daten, 'blank');
        // Erlaubt Inline-Skripte des Templates (window.print()).
        return $response;
    }

    /**
     * Listet die Dokumente zu einem Geschäft.
     * Pfad: Fraktion/20_Geschäfte/{YYYY}/{YYYY.XXXX}-* relativ zum Userverzeichnis.
     *
     * @return DataResponse Liste mit Eintrag pro Datei: {name, pfad, mime, groesse, mtime, downloadUrl, openUrl}
     */
    #[NoAdminRequired]
    public function dokumente(int $id): DataResponse
    {
        try {
            $geschaeft = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Geschäft nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $nummer = (string) ($geschaeft['nummer'] ?? '');
        if (!preg_match('/^(\d{4})\.(\d+)$/', $nummer, $m)) {
            return new DataResponse(['fehler' => 'Ungültige Geschäftsnummer'], Http::STATUS_BAD_REQUEST);
        }
        $jahr = $m[1];
        $praefix = $nummer; // z.B. "2026.88"
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $ordnerPfad = 'Fraktion/20_Geschäfte/' . $jahr;
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
                $relPath = ltrim($ordnerPfad . '/' . $name, '/');
                $eintraege[] = [
                    'name' => $name,
                    'pfad' => $relPath,
                    'mime' => $node instanceof \OCP\Files\File ? $node->getMimeType() : 'httpd/unix-directory',
                    'groesse' => $node->getSize(),
                    'mtime' => $node->getMTime(),
                    'fileId' => $node->getId(),
                ];
            }
            usort($eintraege, fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
            return new DataResponse($eintraege);
        } catch (NotFoundException) {
            return new DataResponse([]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: dokumente() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Erstellt ein neues Dokument zu einem Geschäft.
     * Erwartete Body-Felder: `name` (Suffix nach `YYYY.XXXX-`), `extension` (z.B. "docx"), `vorlage` (optional, Pfad zu Vorlage).
     */
    #[NoAdminRequired]
    public function dokumentErstellen(int $id): DataResponse
    {
        $name = trim((string) $this->request->getParam('name', ''));
        $extension = trim((string) $this->request->getParam('extension', ''));
        $vorlage = trim((string) $this->request->getParam('vorlage', ''));
        if ($name === '' || $extension === '') {
            return new DataResponse(['fehler' => 'Name und Endung erforderlich'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $geschaeft = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Geschäft nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $nummer = (string) ($geschaeft['nummer'] ?? '');
        if (!preg_match('/^(\d{4})\.(\d+)$/', $nummer, $m)) {
            return new DataResponse(['fehler' => 'Ungültige Geschäftsnummer'], Http::STATUS_BAD_REQUEST);
        }
        $jahr = $m[1];
        // Spaces -> Underscores, Sanity-Cleanup für Pfadtrenner.
        $sanitisiert = str_replace([' ', '/', '\\'], ['_', '_', '_'], $name);
        $extension = ltrim($extension, '.');
        $dateiName = $nummer . '-' . $sanitisiert . '.' . $extension;
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $ordnerPfad = 'Fraktion/20_Geschäfte/' . $jahr;
            // Ordnerkette anlegen falls noch nicht vorhanden.
            $teile = explode('/', $ordnerPfad);
            $aktuell = '';
            foreach ($teile as $teil) {
                $aktuell = $aktuell === '' ? $teil : $aktuell . '/' . $teil;
                if (!$userFolder->nodeExists($aktuell)) {
                    $userFolder->newFolder($aktuell);
                }
            }
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
            $this->logger->warning('parlwin: dokumentErstellen() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Lädt eine Datei in den Geschäftsordner hoch.
     */
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
            $geschaeft = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Geschäft nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $nummer = (string) ($geschaeft['nummer'] ?? '');
        $jahr = preg_match('/^(\d{4})\./', $nummer, $m) ? $m[1] : (string) date('Y');
        $originalName = basename((string) ($datei['name'] ?? 'upload'));
        $sanitisiert = preg_replace('/[\/\\\\]/', '_', $originalName);
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $ordnerPfad = 'Fraktion/20_Geschäfte/' . $jahr;
            foreach (explode('/', $ordnerPfad) as $i => $teil) {
                $pfad = implode('/', array_slice(explode('/', $ordnerPfad), 0, $i + 1));
                if (!$userFolder->nodeExists($pfad)) {
                    $userFolder->newFolder($pfad);
                }
            }
            $zielPfad = $ordnerPfad . '/' . ($nummer !== '' ? $nummer . '-' : '') . $sanitisiert;
            $inhalt = file_get_contents($datei['tmp_name']);
            $node = $userFolder->nodeExists($zielPfad)
                ? $userFolder->get($zielPfad)
                : $userFolder->newFile($zielPfad, $inhalt);
            if ($userFolder->nodeExists($zielPfad) && $node instanceof \OCP\Files\File) {
                $node->putContent($inhalt);
            }
            return new DataResponse([
                'name' => $node->getName(),
                'pfad' => $zielPfad,
                'fileId' => $node->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: dokumentHochladen() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Erstellt ein neues eigenes Geschäft (ohne Parlamentszugehörigkeit).
     */
    #[NoAdminRequired]
    public function create(): DataResponse
    {
        $titel = trim((string) $this->request->getParam('titel', ''));
        $typ = trim((string) $this->request->getParam('typ', 'Eigenes Geschäft'));
        $status = trim((string) $this->request->getParam('status', 'Pendent'));
        if ($titel === '') {
            return new DataResponse(['fehler' => 'Titel erforderlich'], Http::STATUS_BAD_REQUEST);
        }
        $g = new Geschaeft();
        // Explizite ID vergeben: die Tabelle führt importierte Geschäfte mit
        // der Geschäftsnummer als ID und ist nicht überall AUTO_INCREMENT.
        $g->setId($this->geschaeftMapper->naechsteId());
        $g->setTitel($titel);
        $g->setTyp($typ);
        $g->setStatus($status);
        $g->setGeloescht(false);
        // Eindeutige ExternId für eigene Geschäfte (nicht aus dem Parlament).
        $g->setExternId('eigen:' . uniqid('', true));
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $g->setErstelltAm($jetzt);
        $g->setAktualisiertAm($jetzt);
        $g->setQuelleAktualisiertAm($jetzt);
        try {
            $erstellt = $this->geschaeftMapper->insert($g);
            $daten = $this->fraktionsarbeitService->angereichertesGeschaeft($erstellt->getId());
            return new DataResponse($daten, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: create() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
