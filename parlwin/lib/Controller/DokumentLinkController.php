<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\Service\DokumentLinkService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Ein Ort für die expliziten Dokument-Verknüpfungen aller Objektarten
 * (Vorstoss/Geschäft/Sitzung). Die geteilte Dokument-Komponente im Frontend
 * spricht diese Endpunkte mit dem jeweiligen Objekttyp an — überall gleich.
 */
class DokumentLinkController extends Controller
{
    private const OBJEKT_TYPEN = ['vorstoss', 'geschaeft', 'sitzung'];

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly DokumentLinkService $service,
    ) {
        parent::__construct($appName, $request);
    }

    private function pruefeTyp(string $objektTyp): string
    {
        if (!in_array($objektTyp, self::OBJEKT_TYPEN, true)) {
            throw new \InvalidArgumentException('Ungültiger Objekttyp');
        }
        return $objektTyp;
    }

    /** Die verknüpften Dateien eines Objekts. */
    #[NoAdminRequired]
    public function index(string $objektTyp, int $objektId): DataResponse
    {
        try {
            return new DataResponse($this->service->liste($this->pruefeTyp($objektTyp), $objektId));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /** Verknüpft eine bestehende Datei (Pfad aus dem Filepicker, ersatzweise File-ID). */
    #[NoAdminRequired]
    public function verknuepfe(string $objektTyp, int $objektId): DataResponse
    {
        $pfad = trim((string) $this->request->getParam('pfad', ''));
        $fileId = (int) $this->request->getParam('fileId', 0);
        if ($pfad === '' && $fileId <= 0) {
            return new DataResponse(['fehler' => 'pfad oder fileId fehlt'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $typ = $this->pruefeTyp($objektTyp);
            $liste = $pfad !== ''
                ? $this->service->verknuepfePfad($typ, $objektId, $pfad)
                : $this->service->verknuepfe($typ, $objektId, $fileId);
            return new DataResponse($liste);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /** Löst eine Verknüpfung; die Datei bleibt bestehen. */
    #[NoAdminRequired]
    public function loese(string $objektTyp, int $objektId, int $fileId): DataResponse
    {
        try {
            return new DataResponse($this->service->entferne($this->pruefeTyp($objektTyp), $objektId, $fileId));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
}
