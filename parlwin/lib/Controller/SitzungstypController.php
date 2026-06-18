<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für Sitzungs-Vorlagen / Sitzungstypen.
 */
class SitzungstypController extends Controller
{
  public function __construct(
    IRequest $request,
    private readonly SitzungstypService $service,
    private readonly RealtimePublisherService $realtimePublisher,
    private readonly IGroupManager $groupManager,
    private readonly IUserManager $userManager,
    private readonly LoggerInterface $logger,
    private readonly FraktionsraumService $fraktionsraumService,
  ) {
    parent::__construct(Application::APP_ID, $request);
  }

  #[NoAdminRequired]
  public function index(): DataResponse
  {
    return new DataResponse($this->service->alle());
  }

  #[NoAdminRequired]
  public function vorschau(int $id): DataResponse
  {
    try {
      return new DataResponse($this->service->vorschau($id));
    } catch (\OCP\AppFramework\Db\DoesNotExistException) {
      return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: vorschau fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
      return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
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
    try {
      $daten = $this->sammleDaten(false);
      $ergebnis = $this->service->speichern($daten);
      $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => (int) ($ergebnis['id'] ?? 0)]);
      return new DataResponse($ergebnis, Http::STATUS_CREATED);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: Sitzungstyp create fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
      return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
  }

  #[NoAdminRequired]
  public function update(int $id): DataResponse
  {
    try {
      $this->service->eins($id);
    } catch (DoesNotExistException) {
      return new DataResponse(['fehler' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
    try {
      $daten = $this->sammleDaten(true);
      $daten['id'] = $id;
      $ergebnis = $this->service->speichern($daten);
      $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => $id]);
      return new DataResponse($ergebnis);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: Sitzungstyp update fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
      return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
  }

  #[NoAdminRequired]
  public function destroy(int $id): DataResponse
  {
    $this->service->loeschen($id);
    $this->realtimePublisher->publish('sitzungstypen.updated', ['id' => $id, 'geloescht' => true]);
    return new DataResponse(['ok' => true]);
  }

  /**
   * Sucht Nextcloud-Gruppen für die Teilnehmer-Regeln.
   */
  #[NoAdminRequired]
  public function ncGroups(string $search = '', int $limit = 25): DataResponse
  {
    $limit = max(1, min(100, $limit));
    try {
      $groups = $this->groupManager->search($search, $limit);
      $result = [];
      foreach ($groups as $g) {
        $result[] = ['gid' => $g->getGID(), 'displayName' => $g->getDisplayName()];
      }
      return new DataResponse($result);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: ncGroups fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
      return new DataResponse(['fehler' => 'Gruppen konnten nicht geladen werden: ' . $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Sucht Nextcloud-Benutzer für die Teilnehmer-Regeln.
   */
  #[NoAdminRequired]
  public function ncUsers(string $search = '', int $limit = 25): DataResponse
  {
    $limit = max(1, min(100, $limit));
    try {
      $users = $this->userManager->search($search, $limit);
      $result = [];
      foreach ($users as $u) {
        $result[] = [
          'uid' => $u->getUID(),
          'displayName' => $u->getDisplayName(),
          'email' => method_exists($u, 'getEMailAddress') ? ((string) $u->getEMailAddress()) : '',
        ];
      }
      return new DataResponse($result);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: ncUsers fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
      return new DataResponse(['fehler' => 'Benutzer konnten nicht geladen werden: ' . $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
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
      'einladungVersenden' => (bool) $this->request->getParam('einladungVersenden', true),
      'standardOrt' => (string) $this->request->getParam('standardOrt', ''),
      'standardZeitVon' => (string) $this->request->getParam('standardZeitVon', ''),
      'standardZeitBis' => (string) $this->request->getParam('standardZeitBis', ''),
      'traktanden' => is_array($traktanden) ? $traktanden : [],
      'teilnehmer' => is_array($teilnehmer) ? $teilnehmer : [],
    ];
  }

  public function fraktionsraumSicherstellen(): DataResponse
  {
    try {
      $this->fraktionsraumService->sicherstellen();
      return new DataResponse(['erfolg' => true, 'bericht' => $this->fraktionsraumService->getBericht()]);
    } catch (\Throwable $e) {
      $this->logger->error('parlwin: fraktionsraum-sicherstellen fehlgeschlagen: ' . $e->getMessage());
      return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
  }
}
