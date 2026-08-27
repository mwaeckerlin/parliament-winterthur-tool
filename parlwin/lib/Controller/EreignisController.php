<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\Service\EreignisService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Ereignis-Protokoll (F105): die Historie der Synchronisationen und Budget-Importe.
 */
class EreignisController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly EreignisService $ereignisse,
    ) {
        parent::__construct($appName, $request);
    }

    /** Das Protokoll, neueste zuerst. */
    #[NoAdminRequired]
    public function index(): DataResponse {
        $limit = (int) $this->request->getParam('limit', 200);
        return new DataResponse(['ereignisse' => $this->ereignisse->liste($limit > 0 ? $limit : 200)]);
    }
}
