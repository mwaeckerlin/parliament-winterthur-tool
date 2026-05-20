<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

/**
 * REST-Controller für politische Geschäfte.
 */
class GeschaeftController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly GeschaeftService $service,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly RealtimePublisherService $realtimePublisher,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Geschäfte zurück.
     */
    #[NoAdminRequired]
    public function index(int $limit = 100, int $offset = 0): DataResponse {
        $filterLetzterBeschluss = (string) $this->request->getParam('letzter_beschluss', '');
        $filterEntscheidungsbedarfRaw = strtolower((string) $this->request->getParam('entscheidungsbedarf', ''));
        $showErledigtRaw = strtolower((string) $this->request->getParam('show_erledigt', '0'));
        $inklusiveErledigt = in_array($showErledigtRaw, ['1', 'true', 'ja'], true);
        $filterEntscheidungsbedarf = null;
        if (in_array($filterEntscheidungsbedarfRaw, ['1', 'true', 'ja'], true)) {
            $filterEntscheidungsbedarf = true;
        } elseif (in_array($filterEntscheidungsbedarfRaw, ['0', 'false', 'nein'], true)) {
            $filterEntscheidungsbedarf = false;
        }
        $geschaefte = $this->service->alle($limit, $offset, $inklusiveErledigt);
        $daten = $this->fraktionsarbeitService->angereicherteGeschaefte(
            $geschaefte,
            $filterLetzterBeschluss,
            $filterEntscheidungsbedarf
        );
        return new DataResponse($daten);
    }

    /**
     * Gibt ein einzelnes Geschäft zurück.
     */
    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        try {
            $daten = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
            return new DataResponse($daten);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Aktualisiert die fraktionsinternen Felder eines Geschäfts.
     */
    #[NoAdminRequired]
    public function update(int $id): DataResponse {
        try {
            $zustaendigkeiten = $this->request->getParam('zustaendigkeiten', null);
            $hauptPersonKey = (string) $this->request->getParam('haupt_person_key', '');
            if (!is_array($zustaendigkeiten)) {
                return new DataResponse(
                    ['fehler' => 'Nur zustaendigkeiten/haupt_person_key sind via PUT erlaubt'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $neu = $this->fraktionsarbeitService->zustaendigkeitenSetzen($id, $zustaendigkeiten, $hauptPersonKey);
            $this->realtimePublisher->publish('geschaefte.updated', [
                'id' => $id,
                'grund' => 'zustaendigkeiten',
            ]);
            return new DataResponse(['zustaendigkeiten' => $neu]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addNotiz(int $id): DataResponse {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->notizHinzufuegen($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'notiz',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addBeschluss(int $id): DataResponse {
        $code = (string) $this->request->getParam('code', '');
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->beschlussHinzufuegen($id, $code, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'beschluss',
                'aktionCode' => $code,
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function removeBeschluss(int $id): DataResponse {
        try {
            $aktion = $this->fraktionsarbeitService->beschlussZuruecknehmen($id);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'beschluss',
                'aktionCode' => 'beschluss_zurueckgenommen',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function addVotum(int $id): DataResponse {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->votumHinzufuegen($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Aktualisiert den Text des aktuell aktiven Votums (Autosave) oder
     * erstellt ein neues, falls keines existiert. Nur die zuständige Person
     * darf das Votum bearbeiten.
     */
    #[NoAdminRequired]
    public function updateVotum(int $id): DataResponse {
        $text = (string) $this->request->getParam('text', '');

        try {
            $aktion = $this->fraktionsarbeitService->votumAktualisieren($id, $text);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
                'aktionCode' => 'votum_im_rat',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Archiviert das aktuell aktive Votum (entscheid_gueltig -> false).
     * Damit bleibt es als historischer Eintrag in der Zeitleiste und ein
     * neues Votum kann erfasst werden.
     */
    #[NoAdminRequired]
    public function archiviereVotum(int $id): DataResponse {
        try {
            $aktion = $this->fraktionsarbeitService->votumArchivieren($id);
            $this->realtimePublisher->publish('geschaefte.action', [
                'id' => $id,
                'aktionTyp' => 'votum',
                'aktionCode' => 'votum_archiviert',
            ]);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Liefert eine druckoptimierte HTML-Ansicht des aktuellen Votums.
     * Browser kann diese via "Drucken -> Als PDF speichern" als PDF
     * exportieren (vermeidet zusätzliche PHP-PDF-Bibliotheken und
     * Composer-Abhängigkeiten).
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function votumPdf(int $id): TemplateResponse {
        try {
            $daten = $this->fraktionsarbeitService->angereichertesGeschaeft($id);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new TemplateResponse(
                Application::APP_ID,
                'votum_pdf',
                ['id' => $id, 'titel' => 'Nicht gefunden'],
                'blank'
            );
        }
        $response = new TemplateResponse(Application::APP_ID, 'votum_pdf', $daten, 'blank');
        // Erlaubt Inline-Skripte des Templates (window.print()).
        return $response;
    }
}
