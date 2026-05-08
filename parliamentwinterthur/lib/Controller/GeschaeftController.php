<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für politische Geschäfte.
 */
class GeschaeftController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly GeschaeftService $service,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Geschäfte zurück.
     */
    #[NoAdminRequired]
    public function index(int $limit = 100, int $offset = 0): DataResponse {
        $geschaefte = $this->service->alle($limit, $offset);
        return new DataResponse(array_map(
            fn($g) => $g->jsonSerialize(),
            $geschaefte
        ));
    }

    /**
     * Gibt ein einzelnes Geschäft zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        try {
            $geschaeft = $this->service->eins($id);
            return new DataResponse($geschaeft->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Geschäfts.
     */
    #[NoAdminRequired]
    public function update(int $id): DataResponse {
        $erlaubteFelder = [
            'bemerkungen',
            'zustaendige_person',
            'antrag_fraktion',
            'entscheid_fraktion',
            'notizen',
        ];

        $felder = [];
        foreach ($erlaubteFelder as $feld) {
            if ($this->request->offsetExists($feld)) {
                $felder[$feld] = $this->request->getParam($feld, '');
            }
        }

        if (empty($felder)) {
            return new DataResponse(['fehler' => 'Keine gültigen Felder angegeben'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $geschaeft = $this->service->aktualisiereInterneFelder($id, $felder);
            return new DataResponse($geschaeft->jsonSerialize());
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }
}
