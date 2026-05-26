<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;

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
    ) {
        parent::__construct(Application::APP_ID, $request);
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
    public function index(int $limit = 50, int $offset = 0): DataResponse
    {
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
