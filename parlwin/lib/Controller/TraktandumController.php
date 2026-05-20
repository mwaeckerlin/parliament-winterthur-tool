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
 * REST-Controller für Traktanden.
 */
class TraktandumController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly SitzungService $service,
        private readonly RealtimePublisherService $realtimePublisher,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Traktanden einer Sitzung zurück.
     */
    #[NoAdminRequired]
    public function index(int $sitzungId): DataResponse {
        $traktanden = $this->service->traktanden($sitzungId);
        return new DataResponse(array_map(
            fn($t) => $t->jsonSerialize(),
            $traktanden
        ));
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Traktandums.
     */
    #[NoAdminRequired]
    public function update(int $sitzungId, int $id): DataResponse {
        $felder = [];
        if ($this->request->offsetExists('bemerkungen')) {
            $felder['bemerkungen'] = $this->request->getParam('bemerkungen', '');
        }
        if ($this->request->offsetExists('notizen')) {
            $felder['notizen'] = $this->request->getParam('notizen', '[]');
        }

        try {
            $traktandum = $this->service->aktualisiereInternesTraktandum($id, $felder);
            $this->realtimePublisher->publish('traktanden.updated', [
                'id' => $id,
                'sitzungId' => $sitzungId,
            ]);
            return new DataResponse($traktandum->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }
}
