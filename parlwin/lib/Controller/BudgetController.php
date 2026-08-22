<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * REST-Controller für das Budget-Modul.
 */
class BudgetController extends Controller {
    public function __construct(
        IRequest $request,
        private readonly BudgetService $service,
        private readonly BudgetImportService $import,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** Vorhandene Budgetjahre (neuestes zuerst). */
    #[NoAdminRequired]
    public function jahre(): DataResponse {
        return new DataResponse($this->service->jahre());
    }

    /**
     * Jahre, für die eine Budgetquelle vorliegt, und welche davon noch nicht
     * importiert sind — Grundlage für die «vergangenes Budgetjahr importieren»-Auswahl.
     */
    #[NoAdminRequired]
    public function verfuegbar(): DataResponse {
        $verfuegbar = $this->import->verfuegbareJahre();
        $importiert = array_map(static fn ($j) => (int) $j['jahr'], $this->service->jahre());
        $importierbar = array_values(array_filter($verfuegbar, static fn ($j) => !in_array($j, $importiert, true)));
        return new DataResponse(['verfuegbar' => $verfuegbar, 'importierbar' => $importierbar]);
    }

    /** Vollständige, gefilterte Ansicht eines Budgetjahres. */
    #[NoAdminRequired]
    public function ansicht(int $jahr): DataResponse {
        $departement = $this->strOrNull('departement');
        $kommission = $this->strOrNull('kommission');
        $phase = (string) ($this->strOrNull('phase') ?? 'fraktion');
        $minProzent = $this->request->getParam('minProzent');
        $minAbsolut = $this->request->getParam('minAbsolut');
        try {
            return new DataResponse($this->service->ansicht(
                $jahr,
                $departement,
                $minProzent !== null && $minProzent !== '' ? (float) $minProzent : null,
                $minAbsolut !== null && $minAbsolut !== '' ? (int) $minAbsolut : null,
                $kommission,
                $phase,
            ));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Budgetjahr nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function antragErstellen(int $jahr): DataResponse {
        $daten = $this->antragDaten();
        return new DataResponse($this->service->antragErstellen($jahr, $daten), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function antragAendern(int $id): DataResponse {
        try {
            return new DataResponse($this->service->antragAendern($id, $this->antragDaten()));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function antragLoeschen(int $id): DataResponse {
        try {
            $this->service->antragLoeschen($id);
            return new DataResponse([]);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function verteilung(int $jahr): DataResponse {
        $automatik = (bool) $this->request->getParam('automatikEin', true);
        $modus = (string) $this->request->getParam('zielModus', 'schwarze_null');
        $betrag = (int) $this->request->getParam('zielBetrag', 0);
        $this->service->verteilungSetzen($jahr, $automatik, $modus, $betrag);
        return new DataResponse($this->service->ansicht($jahr));
    }

    #[NoAdminRequired]
    public function entscheid(int $id): DataResponse {
        $status = (string) $this->request->getParam('status', 'offen');
        try {
            $this->service->entscheidSetzen($id, $status);
            return new DataResponse([]);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /** Importiert ein Budgetjahr (Weisung, optional mit Novemberbrief). */
    #[NoAdminRequired]
    public function importieren(int $jahr): DataResponse {
        $mitNovemberbrief = (bool) $this->request->getParam('mitNovemberbrief', false);
        try {
            $this->import->importiereJahr($jahr, $mitNovemberbrief);
            return new DataResponse($this->service->ansicht($jahr), Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('Budget-Import fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function novemberbrief(int $jahr): DataResponse {
        try {
            $this->import->importiereNovemberbrief($jahr);
            return new DataResponse($this->service->ansicht($jahr));
        } catch (\Throwable $e) {
            $this->logger->error('Novemberbrief-Import fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * PDF mit allen Anträgen der Fraktion (gesamt oder je Kommission). Nutzt
     * denselben Druck-Mechanismus wie das Votum-PDF: eine eigenständige
     * Druckseite (TemplateResponse, Layout «blank»), die sich selbst als PDF
     * speichern lässt — kein serverseitiges PDF.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function antraegePdf(int $jahr): TemplateResponse {
        $kommission = $this->strOrNull('kommission');
        $daten = $this->import->antraegePdf($jahr, $kommission);
        return new TemplateResponse(Application::APP_ID, 'budget_antraege_pdf', $daten, 'blank');
    }

    /** @return array<string, mixed> */
    private function antragDaten(): array {
        $felder = ['bereich', 'zielTyp', 'zielRef', 'betragDelta', 'stellenDelta', 'betragProStelle', 'quelle', 'antragsteller', 'begruendung', 'phase'];
        $daten = [];
        foreach ($felder as $f) {
            $wert = $this->request->getParam($f);
            if ($wert !== null) {
                $daten[$f] = $wert;
            }
        }
        return $daten;
    }

    private function strOrNull(string $key): ?string {
        $wert = $this->request->getParam($key);
        return ($wert === null || $wert === '') ? null : (string) $wert;
    }
}
