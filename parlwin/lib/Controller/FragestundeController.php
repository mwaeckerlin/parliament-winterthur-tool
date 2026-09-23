<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FragestundeService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * REST-Controller für die Fragestunde (F114).
 *
 * Die Notizen an einer Frage laufen über den GETEILTEN Notiz-Code (NotizService)
 * — dieselben Endpunkte, dieselben Versionen und dasselbe Wiederherstellen wie am
 * Geschäft und am Vorstoss.
 */
class FragestundeController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly FragestundeService $service,
        private readonly NotizService $notizen,
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
        try {
            $fragestunde = $this->service->erstelleFragestunde($this->daten(['datum', 'titel', 'frist', 'geschaeftId']));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['id' => $fragestunde->getId()]);
        return new DataResponse($this->service->eine((int) $fragestunde->getId()), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        try {
            $this->service->aktualisiereFragestunde($id, $this->daten(['datum', 'titel', 'frist', 'geschaeftId']));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Fragestunde nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['id' => $id]);
        return new DataResponse($this->service->eine($id));
    }

    /**
     * Trägt eine Frage ein. Die Fragestunde ist freiwillig: Ohne sie wird die
     * Frage gesammelt und später einer zugeteilt.
     */
    #[NoAdminRequired]
    public function frageErstellen(): DataResponse
    {
        $fragestundeId = (int) $this->request->getParam('fragestundeId', 0);
        try {
            $frage = $this->service->erstelleFrage(
                $fragestundeId,
                $this->daten(['frage', 'kommentar', 'urheber', 'einreicher'])
            );
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Fragestunde nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frage->getId()]);
        return new DataResponse($frage, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function frageAendern(int $frageId): DataResponse
    {
        try {
            $frage = $this->service->aktualisiereFrage(
                $frageId,
                $this->daten(['frage', 'kommentar', 'status', 'urheber', 'einreicher', 'fragestundeId'])
            );
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Frage nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId]);
        return new DataResponse($frage);
    }

    #[NoAdminRequired]
    public function frageLoeschen(int $frageId): DataResponse
    {
        try {
            $this->service->loescheFrage($frageId);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Frage nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId]);
        return new DataResponse([]);
    }

    /** Alle Notizen einer Frage (aktive und gelöschte) — für die geteilte Komponente. */
    #[NoAdminRequired]
    public function notizen(int $frageId): DataResponse
    {
        return new DataResponse($this->notizen->liste('frage', $frageId));
    }

    #[NoAdminRequired]
    public function addNotiz(int $frageId): DataResponse
    {
        try {
            $aktion = $this->notizen->hinzufuegen('frage', $frageId, (string) $this->request->getParam('text', ''));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId, 'aktionTyp' => 'notiz']);
        return new DataResponse($aktion);
    }

    #[NoAdminRequired]
    public function updateNotiz(int $frageId, int $aktionId): DataResponse
    {
        try {
            $aktion = $this->notizen->aktualisieren('frage', $frageId, $aktionId, (string) $this->request->getParam('text', ''));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId, 'aktionTyp' => 'notiz']);
        return new DataResponse($aktion);
    }

    #[NoAdminRequired]
    public function deleteNotiz(int $frageId, int $aktionId): DataResponse
    {
        try {
            $this->notizen->loeschen('frage', $frageId, $aktionId);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId, 'aktionTyp' => 'notiz']);
        return new DataResponse([]);
    }

    #[NoAdminRequired]
    public function restoreNotiz(int $frageId, int $aktionId): DataResponse
    {
        try {
            $aktion = $this->notizen->wiederherstellen('frage', $frageId, $aktionId);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
        $this->realtimePublisher->publish('fragestunde.updated', ['frageId' => $frageId, 'aktionTyp' => 'notiz']);
        return new DataResponse($aktion);
    }

    #[NoAdminRequired]
    public function notizRevisionen(int $frageId, int $aktionId): DataResponse
    {
        try {
            return new DataResponse($this->notizen->revisionen('frage', $frageId, $aktionId));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Liest nur die tatsächlich übermittelten Felder (verhindert Leeren beim Update).
     *
     * @param list<string> $felder
     * @return array<string, mixed>
     */
    private function daten(array $felder): array
    {
        $daten = [];
        foreach ($felder as $feld) {
            $wert = $this->request->getParam($feld);
            if ($wert !== null) {
                $daten[$feld] = $wert;
            }
        }
        return $daten;
    }
}
