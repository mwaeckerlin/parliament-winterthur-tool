<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Die Stadtratsbeschlüsse für die Ansicht «Stadtratsbeschlüsse» (F144).
 *
 * Die Notizen laufen über dieselben Endpunkte wie am Geschäft und am Vorstoss,
 * weil die Oberfläche dieselbe Komponente dafür benutzt.
 */
class StadtratsbeschlussController extends Controller
{
    /** So viele Beschlüsse liefert die Liste ohne eigene Angabe. */
    private const GRENZE = 500;

    public function __construct(
        IRequest $request,
        private readonly StadtratsbeschlussService $service,
        private readonly RealtimePublisherService $realtimePublisher,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** Die Meldung für einen Beschluss, den es nicht mehr gibt. */
    private function beschlussFehlt(): DataResponse
    {
        return new DataResponse(
            ['fehler' => 'Dieser Stadtratsbeschluss steht nicht mehr in der Liste. Bitte die Seite neu laden.'],
            Http::STATUS_NOT_FOUND
        );
    }

    /**
     * Die Meldung für eine Notiz, die es nicht mehr gibt.
     *
     * Der Beschluss ist in diesem Fall da; stünde hier dieselbe Meldung wie für
     * den Beschluss, lüde der Benutzer die Seite neu und fände alles vor.
     */
    private function notizFehlt(): DataResponse
    {
        return new DataResponse(
            ['fehler' => 'Diese Notiz gibt es nicht mehr.'],
            Http::STATUS_NOT_FOUND
        );
    }

    /**
     * Die Antwort auf einen Fehler des geteilten Notiz-Codes: eine Notiz, die
     * es an diesem Beschluss nicht gibt, ergibt 404, ein leerer Text 400, eine
     * Notiz eines anderen Autors 403.
     */
    private function notizFehler(\Throwable $e, bool $leererText = false): DataResponse
    {
        if ($e instanceof \InvalidArgumentException) {
            return $leererText
                ? new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST)
                : $this->notizFehlt();
        }
        return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
    }

    /**
     * Die Beschlüsse, der jüngste zuerst.
     *
     * `limit` und `offset` begrenzen die Antwort wie bei den Geschäften; ohne
     * Angabe kommen die 500 jüngsten.
     */
    #[NoAdminRequired]
    public function index(int $limit = self::GRENZE, int $offset = 0): DataResponse
    {
        return new DataResponse($this->service->mitNotizen($this->service->alle($limit, $offset)));
    }

    #[NoAdminRequired]
    public function show(int $id): DataResponse
    {
        try {
            return new DataResponse($this->service->mitNotizen([$this->service->einer($id)])[0]);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
    }

    /** Alle Notizen eines Beschlusses (aktiv und gelöscht) — für die geteilte Komponente. */
    #[NoAdminRequired]
    public function notizen(int $id): DataResponse
    {
        try {
            return new DataResponse($this->service->notizen($id));
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
    }

    #[NoAdminRequired]
    public function addNotiz(int $id): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');
        try {
            $this->service->einer($id);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
        try {
            $aktion = $this->service->notizHinzufuegen($id, $text);
            $this->realtimePublisher->publish('stadtratsbeschluesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function updateNotiz(int $id, int $aktionId): DataResponse
    {
        $text = (string) $this->request->getParam('text', '');
        try {
            $this->service->einer($id);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
        try {
            $aktion = $this->service->notizAktualisieren($id, $aktionId, $text);
            $this->realtimePublisher->publish('stadtratsbeschluesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (DoesNotExistException) {
            return $this->notizFehlt();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->notizFehler($e, trim($text) === '');
        }
    }

    #[NoAdminRequired]
    public function deleteNotiz(int $id, int $aktionId): DataResponse
    {
        try {
            $this->service->einer($id);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
        try {
            $this->service->notizLoeschen($id, $aktionId);
            $this->realtimePublisher->publish('stadtratsbeschluesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse([]);
        } catch (DoesNotExistException) {
            return $this->notizFehlt();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->notizFehler($e);
        }
    }

    /** Macht das Löschen einer Notiz rückgängig — nur der Autor darf das. */
    #[NoAdminRequired]
    public function restoreNotiz(int $id, int $aktionId): DataResponse
    {
        try {
            $this->service->einer($id);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
        try {
            $aktion = $this->service->notizWiederherstellen($id, $aktionId);
            $this->realtimePublisher->publish('stadtratsbeschluesse.updated', ['id' => $id, 'aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (DoesNotExistException) {
            return $this->notizFehlt();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->notizFehler($e);
        }
    }

    /** Die archivierten Vorversionen einer Notiz (älteste zuerst). */
    #[NoAdminRequired]
    public function notizRevisionen(int $id, int $aktionId): DataResponse
    {
        try {
            $this->service->einer($id);
        } catch (DoesNotExistException) {
            return $this->beschlussFehlt();
        }
        try {
            return new DataResponse($this->service->notizRevisionen($id, $aktionId));
        } catch (DoesNotExistException) {
            return $this->notizFehlt();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->notizFehler($e);
        }
    }
}
