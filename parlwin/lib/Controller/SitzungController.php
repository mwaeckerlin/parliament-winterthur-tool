<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SitzungGeschaeftService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für Parlamentssitzungen und Traktanden.
 */
class SitzungController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly SitzungService $service,
        private readonly SitzungstypService $sitzungstypService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly IUserSession $userSession,
        private readonly IRootFolder $rootFolder,
        private readonly LoggerInterface $logger,
        private readonly SitzungGeschaeftService $sitzungGeschaeftService,
        private readonly \OCA\ParliamentWinterthur\Service\DeckService $deckService,
        private readonly \OCP\IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** Legt aus einer Sitzung ein To-do als Deck-Karte im Fraktions-Board an. */
    #[NoAdminRequired]
    public function todoErstellen(int $id): DataResponse
    {
        $titel = trim((string) $this->request->getParam('titel', ''));
        if ($titel === '') {
            return new DataResponse(['fehler' => 'Titel fehlt'], Http::STATUS_BAD_REQUEST);
        }
        $beschreibung = (string) $this->request->getParam('beschreibung', '');
        $gruppe = trim((string) $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', ''));
        $kartenId = $this->deckService->erstelleTodoKarte('admin', $gruppe, $titel, $beschreibung);
        if ($kartenId === null) {
            return new DataResponse(['fehler' => 'Deck nicht verfügbar oder keine Gruppe konfiguriert'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(['kartenId' => $kartenId]);
    }

    /** Verknüpft ein Geschäft mit einer Sitzung. */
    #[NoAdminRequired]
    public function geschaeftVerlinken(int $id): DataResponse
    {
        $geschaeftId = (int) $this->request->getParam('geschaeftId', 0);
        if ($geschaeftId <= 0) {
            return new DataResponse(['fehler' => 'geschaeftId fehlt'], Http::STATUS_BAD_REQUEST);
        }
        $this->sitzungGeschaeftService->verlinke($id, $geschaeftId);
        $this->realtimePublisher->publish('sitzungen.updated', ['id' => $id]);
        return new DataResponse(['geschaeftIds' => $this->sitzungGeschaeftService->geschaeftIdsFuerSitzung($id)]);
    }

    /** Löst die Verknüpfung eines Geschäfts von einer Sitzung. */
    #[NoAdminRequired]
    public function geschaeftEntlinken(int $id, int $geschaeftId): DataResponse
    {
        $this->sitzungGeschaeftService->entlinke($id, $geschaeftId);
        $this->realtimePublisher->publish('sitzungen.updated', ['id' => $id]);
        return new DataResponse(['geschaeftIds' => $this->sitzungGeschaeftService->geschaeftIdsFuerSitzung($id)]);
    }

    /** Gibt die IDs der mit einer Sitzung verknüpften Geschäfte zurück. */
    #[NoAdminRequired]
    public function geschaefte(int $id): DataResponse
    {
        return new DataResponse(['geschaeftIds' => $this->sitzungGeschaeftService->geschaeftIdsFuerSitzung($id)]);
    }

    /** Relativer Ordner für die Dokumente einer Sitzung (im Jahr der Sitzung). */
    private function sitzungOrdnerPfad(\OCA\ParliamentWinterthur\Db\Sitzung $sitzung): array
    {
        $datum = (string) $sitzung->getDatum();
        $jahr = preg_match('/^(\d{4})-/', $datum, $m) ? $m[1] : date('Y');
        // Dateipräfix = Datum, analog zur Geschäftsnummer bei Geschäftsdokumenten.
        return ['Fraktion/10_Sitzungen/' . $jahr, $datum];
    }

    /** Listet die Dokumente einer Sitzung (Dateien mit Datums-Präfix). */
    #[NoAdminRequired]
    public function dokumente(int $id): DataResponse
    {
        try {
            $sitzung = $this->service->eins($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Sitzung nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        [$ordnerPfad, $praefix] = $this->sitzungOrdnerPfad($sitzung);
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
            usort($eintraege, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            return new DataResponse($eintraege);
        } catch (NotFoundException) {
            return new DataResponse([]);
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: sitzung dokumente() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /** Erstellt ein neues Dokument zu einer Sitzung (Datums-Präfix + Name). */
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
            $sitzung = $this->service->eins($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Sitzung nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['fehler' => 'Nicht angemeldet'], Http::STATUS_UNAUTHORIZED);
        }
        [$ordnerPfad, $praefix] = $this->sitzungOrdnerPfad($sitzung);
        $sanitisiert = str_replace([' ', '/', '\\'], ['_', '_', '_'], $name);
        $dateiName = $praefix . '-' . $sanitisiert . '.' . $extension;
        try {
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            $aktuell = '';
            foreach (explode('/', $ordnerPfad) as $teil) {
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
            $this->logger->warning('parlwin: sitzung dokumentErstellen() Fehler: {msg}', ['msg' => $e->getMessage()]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Erstellt eine neue interne Sitzung aus einer Vorlage.
     */
    #[NoAdminRequired]
    public function create(): DataResponse
    {
        $typId = (int) $this->request->getParam('typId', 0);
        if ($typId <= 0) {
            return new DataResponse(['fehler' => 'typId fehlt'], Http::STATUS_BAD_REQUEST);
        }
        $datum = (string) $this->request->getParam('datum', '');
        if ($datum === '') {
            return new DataResponse(['fehler' => 'datum fehlt'], Http::STATUS_BAD_REQUEST);
        }

        $traktanden = $this->request->getParam('traktanden', []);
        if (is_string($traktanden)) {
            $traktanden = json_decode($traktanden, true) ?? [];
        }

        $daten = [
            'typId'       => $typId,
            'datum'       => $datum,
            'titel'       => (string) $this->request->getParam('titel', ''),
            'ort'         => (string) $this->request->getParam('ort', ''),
            'zeitVon'     => (string) $this->request->getParam('zeitVon', ''),
            'zeitBis'     => (string) $this->request->getParam('zeitBis', ''),
            'bemerkungen' => (string) $this->request->getParam('bemerkungen', ''),
            'traktanden'  => is_array($traktanden) ? $traktanden : [],
        ];

        try {
            $sitzung = $this->sitzungstypService->erstelleAusTyp($daten);
            $this->realtimePublisher->publish('sitzungen.created', ['id' => $sitzung->getId()]);
            return new DataResponse($sitzung->jsonSerialize(), Http::STATUS_CREATED);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Sitzungstyp nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Gibt alle Sitzungen zurück.
     */
    #[NoAdminRequired]
    public function index(): DataResponse
    {
        // limit/offset über getParam: Nextcloud 34 begrenzt einen Controller-
        // Parameter namens "limit" hart auf 1–500 (ParameterOutOfRangeException).
        $limit = max(1, (int) $this->request->getParam('limit', 50));
        $offset = max(0, (int) $this->request->getParam('offset', 0));
        $sitzungen = $this->service->alle($limit, $offset);
        return new DataResponse(array_map(
            fn($s) => $s->jsonSerialize(),
            $sitzungen
        ));
    }

    /**
     * Gibt eine einzelne Sitzung zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse
    {
        try {
            $sitzung = $this->service->eins($id);
            $traktanden = $this->service->traktanden($id);
            $daten = $sitzung->jsonSerialize();
            $daten['traktanden'] = array_map(fn($t) => $t->jsonSerialize(), $traktanden);
            return new DataResponse($daten);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Aktualisiert die fraktionsinternen Felder einer Sitzung.
     */
    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        $felder = [];
        if ($this->request->offsetExists('bemerkungen')) {
            $felder['bemerkungen'] = $this->request->getParam('bemerkungen', '');
        }
        if ($this->request->offsetExists('notizen')) {
            $rohwert = $this->request->getParam('notizen', '[]');
            $felder['notizen'] = $this->normalisiereNotizen($rohwert);
        }

        try {
            $sitzung = $this->service->aktualisiereInterneSitzung($id, $felder);
            $this->realtimePublisher->publish('sitzungen.updated', [
                'id' => $id,
            ]);
            return new DataResponse($sitzung->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /** Verknüpft die Sitzung mit einer Zielsitzung (gemeinsame, aggregierte Sicht). */
    #[NoAdminRequired]
    public function verknuepfen(int $id): DataResponse
    {
        $zielId = (int) $this->request->getParam('zielId', 0);
        if ($zielId <= 0 || $zielId === $id) {
            return new DataResponse(['fehler' => 'Ungültige Zielsitzung'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $sitzung = $this->service->verknuepfe($id, $zielId);
            $this->realtimePublisher->publish('sitzungen.updated', ['id' => $id]);
            return new DataResponse($sitzung->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /** Liefert alle Sitzungen der Verknüpfungs-Gruppe (inkl. dieser) für die aggregierte Sicht. */
    #[NoAdminRequired]
    public function verknuepft(int $id): DataResponse
    {
        try {
            $sitzungen = $this->service->verknuepfteSitzungen($id);
            return new DataResponse(array_map(static fn ($s) => $s->jsonSerialize(), $sitzungen));
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /** Löst die Sitzung aus ihrer Verknüpfungs-Gruppe; Daten bleiben erhalten. */
    #[NoAdminRequired]
    public function entkoppeln(int $id): DataResponse
    {
        try {
            $sitzung = $this->service->entkopple($id);
            $this->realtimePublisher->publish('sitzungen.updated', ['id' => $id]);
            return new DataResponse($sitzung->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Stellt sicher, dass jede Notiz `datum`, `uid`, `displayName` und `text`
     * trägt. Fehlende Audit-Felder werden mit der aktuellen Session
     * befüllt. Eingaben können ein JSON-String oder ein Array sein.
     */
    private function normalisiereNotizen(mixed $rohwert): string
    {
        if (is_string($rohwert)) {
            $arr = json_decode($rohwert, true);
        } elseif (is_array($rohwert)) {
            $arr = $rohwert;
        } else {
            $arr = [];
        }
        if (!is_array($arr)) {
            $arr = [];
        }
        $user = $this->userSession->getUser();
        $aktUid = $user?->getUID() ?? '';
        $aktName = $user?->getDisplayName() ?? $aktUid;
        $jetzt = (new \DateTime())->format('d.m.Y H:i');
        $ergebnis = [];
        foreach ($arr as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $text = (string) ($eintrag['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $datum = (string) ($eintrag['datum'] ?? '');
            $uid = (string) ($eintrag['uid'] ?? '');
            $name = (string) ($eintrag['displayName'] ?? '');
            if ($datum === '')
                $datum = $jetzt;
            if ($uid === '')
                $uid = $aktUid;
            if ($name === '')
                $name = $aktName !== '' ? $aktName : $uid;
            $ergebnis[] = [
                'datum' => $datum,
                'uid' => $uid,
                'displayName' => $name,
                'text' => $text,
            ];
        }
        return json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
    }
}
