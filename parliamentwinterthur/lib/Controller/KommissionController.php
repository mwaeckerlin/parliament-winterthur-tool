<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für Kommissionen.
 */
class KommissionController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly KommissionMapper $mapper,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Kommissionen zurück.
     */
    #[NoAdminRequired]
    public function index(): DataResponse {
        $kommissionen = $this->mapper->findAll();
        return new DataResponse(array_map(fn($k) => $k->jsonSerialize(), $kommissionen));
    }

    /**
     * Gibt eine einzelne Kommission zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        try {
            $kommission = $this->mapper->find($id);
            return new DataResponse($kommission->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }
}
