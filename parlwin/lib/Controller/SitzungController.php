<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für Parlamentssitzungen und Traktanden.
 */
class SitzungController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly SitzungService $service,
        private readonly RealtimePublisherService $realtimePublisher,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Sitzungen zurück.
     */
    #[NoAdminRequired]
    public function index(int $limit = 50, int $offset = 0): DataResponse {
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
    public function show(int $id): DataResponse {
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
    public function update(int $id): DataResponse {
        $felder = [];
        if ($this->request->offsetExists('bemerkungen')) {
            $felder['bemerkungen'] = $this->request->getParam('bemerkungen', '');
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
}
