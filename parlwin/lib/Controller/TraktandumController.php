<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für Traktanden.
 */
class TraktandumController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly SitzungService $service,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Traktanden einer Sitzung zurück.
     *
     * Jedes Traktandum mit verknüpftem Geschäft enthält zusätzlich unter
     * `geschaeft` die angereicherten Geschäftsdaten, damit die Traktandenliste
     * dieselbe Darstellung und dieselben Inline-Bearbeitungen wie die
     * Geschäftsliste-Hauptseite verwenden kann.
     */
    #[NoAdminRequired]
    public function index(int $sitzungId): DataResponse
    {
        $traktanden = $this->service->traktanden($sitzungId);
        $result = [];
        foreach ($traktanden as $t) {
            $eintrag = $t->jsonSerialize();
            $geschaeftId = (int) ($eintrag['geschaeftId'] ?? 0);
            $eintrag['geschaeft'] = null;
            if ($geschaeftId > 0) {
                try {
                    $eintrag['geschaeft'] = $this->fraktionsarbeitService->angereichertesGeschaeft($geschaeftId);
                } catch (\OCP\AppFramework\Db\DoesNotExistException) {
                    // Verwaistes Traktandum (Geschäft gelöscht) – ohne Anreicherung zurückgeben.
                    $eintrag['geschaeft'] = null;
                } catch (\Throwable $e) {
                    $this->logger->warning('parlwin: Anreichern Geschäft {id} fehlgeschlagen: {msg}', [
                        'id' => $geschaeftId,
                        'msg' => $e->getMessage(),
                    ]);
                    $eintrag['geschaeft'] = null;
                }
            }
            $result[] = $eintrag;
        }
        return new DataResponse($result);
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Traktandums.
     *
     * Aktuell wird nur `notizen` unterstützt; das ehemalige Feld `bemerkungen`
     * ist entfernt – die Notizen reichen.
     */
    #[NoAdminRequired]
    public function update(int $sitzungId, int $id): DataResponse
    {
        $felder = [];
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
