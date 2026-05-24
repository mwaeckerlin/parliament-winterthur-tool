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
use OCP\IUserSession;
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
        private readonly IUserSession $userSession,
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
     */
    #[NoAdminRequired]
    public function update(int $sitzungId, int $id): DataResponse
    {
        $felder = [];
        if ($this->request->offsetExists('bemerkungen')) {
            $felder['bemerkungen'] = (string) $this->request->getParam('bemerkungen', '');
        }
        if ($this->request->offsetExists('notizen')) {
            $rohwert = $this->request->getParam('notizen', '[]');
            $felder['notizen'] = $this->normalisiereNotizen($rohwert);
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

    /**
     * Stellt sicher, dass jede Notiz ein vollständiges Audit-Trail trägt:
     * `datum`, `uid` und `displayName` werden aus der Session ergänzt, falls
     * sie fehlen. Eingaben können ein JSON-String oder ein Array sein.
     *
     * @param mixed $rohwert
     */
    private function normalisiereNotizen(mixed $rohwert): string
    {
        if (is_string($rohwert)) {
            $arr = json_decode($rohwert, true);
        } elseif (is_array($rohwert)) {
            $arr = $rohwert;
        } else {
            $arr = [];
        }
        if (!is_array($arr)) {
            $arr = [];
        }
        $user = $this->userSession->getUser();
        $aktUid = $user?->getUID() ?? '';
        $aktName = $user?->getDisplayName() ?? $aktUid;
        $jetzt = (new \DateTime())->format('d.m.Y H:i');
        $ergebnis = [];
        foreach ($arr as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $text = (string) ($eintrag['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $datum = (string) ($eintrag['datum'] ?? '');
            $uid = (string) ($eintrag['uid'] ?? '');
            $name = (string) ($eintrag['displayName'] ?? '');
            // Fehlende Audit-Felder mit aktueller Session befüllen.
            if ($datum === '')
                $datum = $jetzt;
            if ($uid === '')
                $uid = $aktUid;
            if ($name === '')
                $name = $aktName !== '' ? $aktName : $uid;
            $ergebnis[] = [
                'datum' => $datum,
                'uid' => $uid,
                'displayName' => $name,
                'text' => $text,
            ];
        }
        return json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
    }
}
