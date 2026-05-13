<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für Fraktionen.
 */
class FraktionController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly FraktionMapper $mapper,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Fraktionen zurück.
     */
    #[NoAdminRequired]
    public function index(): DataResponse {
        $fraktionen = $this->mapper->findAll();
        return new DataResponse(array_map(fn($f) => $f->jsonSerialize(), $fraktionen));
    }

    /**
     * Gibt eine einzelne Fraktion zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        try {
            $fraktion = $this->mapper->find($id);
            return new DataResponse($fraktion->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }
}
