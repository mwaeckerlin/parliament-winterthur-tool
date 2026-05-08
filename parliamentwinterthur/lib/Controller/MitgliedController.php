<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für Parlamentsmitglieder.
 */
class MitgliedController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly MitgliedService $service,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Parlamentsmitglieder zurück.
     * Query-Parameter: aktiv=1 für nur aktive Mitglieder
     */
    #[NoAdminRequired]
    public function index(): DataResponse {
        $nurAktive = $this->request->getParam('aktiv', '0') === '1';
        $mitglieder = $nurAktive ? $this->service->aktive() : $this->service->alle();
        return new DataResponse(array_map(
            fn($m) => $m->jsonSerialize(),
            $mitglieder
        ));
    }

    /**
     * Gibt ein einzelnes Mitglied zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        try {
            $mitglied = $this->service->eins($id);
            return new DataResponse($mitglied->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }
}
