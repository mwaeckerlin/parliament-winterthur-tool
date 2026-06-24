<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\VorstossService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * REST-Controller für politische Vorstösse.
 */
class VorstossController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly VorstossService $service,
        private readonly RealtimePublisherService $realtimePublisher,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function index(): DataResponse
    {
        return new DataResponse($this->service->alle());
    }

    #[NoAdminRequired]
    public function create(): DataResponse
    {
        $titel = trim((string) $this->request->getParam('titel', ''));
        if ($titel === '') {
            return new DataResponse(['fehler' => 'Titel fehlt'], Http::STATUS_BAD_REQUEST);
        }
        $vorstoss = $this->service->erstelle($this->daten());
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $vorstoss->getId()]);
        return new DataResponse($vorstoss, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        try {
            $vorstoss = $this->service->aktualisiere($id, $this->daten());
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id]);
        return new DataResponse($vorstoss);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse
    {
        try {
            $this->service->loesche($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Vorstoss nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('vorstoesse.updated', ['id' => $id]);
        return new DataResponse([]);
    }

    /** Liest nur die tatsächlich übermittelten Felder (verhindert Leeren beim Update). */
    private function daten(): array
    {
        $daten = [];
        foreach (['titel', 'art', 'herkunft', 'status', 'beschluss', 'zustaendigkeit', 'inhalt', 'dokument'] as $feld) {
            $wert = $this->request->getParam($feld);
            if ($wert !== null) {
                $daten[$feld] = $wert;
            }
        }
        return $daten;
    }
}
