<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * REST-Controller für Sitzungs-Vorlagen / Sitzungstypen.
 */
class SitzungstypController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly SitzungstypService $service,
        private readonly KalenderService $kalenderService,
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
    public function show(int $id): DataResponse
    {
        try {
            return new DataResponse($this->service->eins($id));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function create(): DataResponse
    {
        $daten = $this->sammleDaten(false);
        $ergebnis = $this->service->speichern($daten);
        $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => (int) ($ergebnis['id'] ?? 0)]);
        return new DataResponse($ergebnis, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse
    {
        try {
            $this->service->eins($id);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $daten = $this->sammleDaten(true);
        $daten['id'] = $id;
        $ergebnis = $this->service->speichern($daten);
        $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => $id]);
        return new DataResponse($ergebnis);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse
    {
        $this->service->loeschen($id);
        $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => $id, 'geloescht' => true]);
        return new DataResponse(['ok' => true]);
    }

    /**
     * Erstellt aus dem Sitzungstyp eine konkrete Sitzung.
     */
    #[NoAdminRequired]
    public function neueSitzung(int $id): DataResponse
    {
        $overrides = [
            'titel' => (string) $this->request->getParam('titel', ''),
            'datum' => (string) $this->request->getParam('datum', ''),
            'zeitVon' => (string) $this->request->getParam('zeitVon', ''),
            'zeitBis' => (string) $this->request->getParam('zeitBis', ''),
            'ort' => (string) $this->request->getParam('ort', ''),
            'bemerkungen' => (string) $this->request->getParam('bemerkungen', ''),
        ];
        try {
            $sitzung = $this->service->sitzungAusTyp($id, $overrides);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Sitzungstyp nicht gefunden'], Http::STATUS_NOT_FOUND);
        }

        // Kalendereintrag (best-effort) anlegen.
        try {
            $this->kalenderService->sitzungenAktualisieren([$sitzung]);
        } catch (\Throwable) {
            // Fehler werden bereits intern geloggt.
        }

        $this->realtimePublisher->publish('sitzungen.updated', ['id' => (int) $sitzung->getId()]);
        return new DataResponse($sitzung->jsonSerialize(), Http::STATUS_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function sammleDaten(bool $erlaubteilweise): array
    {
        $traktanden = $this->request->getParam('traktanden', []);
        $teilnehmer = $this->request->getParam('teilnehmer', []);
        return [
            'name' => (string) $this->request->getParam('name', ''),
            'zweck' => (string) $this->request->getParam('zweck', ''),
            'kalenderAnlegen' => (bool) $this->request->getParam('kalenderAnlegen', true),
            'einladungVersenden' => (bool) $this->request->getParam('einladungVersenden', false),
            'standardOrt' => (string) $this->request->getParam('standardOrt', ''),
            'standardZeitVon' => (string) $this->request->getParam('standardZeitVon', ''),
            'standardZeitBis' => (string) $this->request->getParam('standardZeitBis', ''),
            'traktanden' => is_array($traktanden) ? $traktanden : [],
            'teilnehmer' => is_array($teilnehmer) ? $teilnehmer : [],
        ];
    }
}
