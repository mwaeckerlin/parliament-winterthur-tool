<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
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
        private readonly RealtimePublisherService $realtime,
        private readonly EreignisService $ereignisse,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    // ── Notizen an Anträgen (F103): geteilter NotizService wie beim Vorstoss ────

    /** Alle Notizen eines Antrags (aktiv und gelöscht) — für die shared Komponente. */
    #[NoAdminRequired]
    public function notizen(int $id): DataResponse {
        try {
            return new DataResponse($this->service->notizen($id));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function addNotiz(int $id): DataResponse {
        $text = (string) $this->request->getParam('text', '');
        try {
            $aktion = $this->service->notizHinzufuegen($id, $text);
            $this->realtime->publish('budget.updated', ['aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function updateNotiz(int $id, int $aktionId): DataResponse {
        $text = (string) $this->request->getParam('text', '');
        try {
            $aktion = $this->service->notizAktualisieren($id, $aktionId, $text);
            $this->realtime->publish('budget.updated', ['aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function deleteNotiz(int $id, int $aktionId): DataResponse {
        try {
            $this->service->notizLoeschen($id, $aktionId);
            $this->realtime->publish('budget.updated', ['aktionTyp' => 'notiz']);
            return new DataResponse([]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /** Macht das Löschen einer Notiz rückgängig (Undo) — nur der Autor darf das. */
    #[NoAdminRequired]
    public function restoreNotiz(int $id, int $aktionId): DataResponse {
        try {
            $aktion = $this->service->notizWiederherstellen($id, $aktionId);
            $this->realtime->publish('budget.updated', ['aktionTyp' => 'notiz']);
            return new DataResponse($aktion);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /** Archivierte Vorversionen einer Notiz (älteste zuerst). */
    #[NoAdminRequired]
    public function notizRevisionen(int $id, int $aktionId): DataResponse {
        try {
            return new DataResponse($this->service->notizRevisionen($id, $aktionId));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
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
            $ansicht = $this->service->ansicht(
                $jahr,
                $departement,
                $minProzent !== null && $minProzent !== '' ? (float) $minProzent : null,
                $minAbsolut !== null && $minAbsolut !== '' ? (int) $minAbsolut : null,
                $kommission,
                $phase,
            );
            // Ob der «Novemberbrief einlesen»-Knopf erscheinen darf (F91) — hängt an
            // der Quelle im Bestand, nicht am DB-Zustand allein.
            if (is_array($ansicht['jahr'] ?? null)) {
                $ansicht['jahr']['novemberbriefVerfuegbar'] = $this->import->novemberbriefVerfuegbar($jahr);
                // Link zum Budget-Geschäft (Weisung) auf der Parlamentswebseite (F89).
                $weisung = $this->import->weisungLink($jahr);
                $ansicht['jahr']['weisungQuelle'] = $weisung['url'] ?? null;
                $ansicht['jahr']['weisungNummer'] = $weisung['nummer'] ?? null;
            }
            return new DataResponse($ansicht);
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
    public function steuerfussAutomatik(int $jahr): DataResponse {
        $an = (bool) $this->request->getParam('an', true);
        $this->service->steuerfussAutomatikSetzen($jahr, $an);
        return new DataResponse($this->service->ansicht($jahr));
    }

    #[NoAdminRequired]
    public function verteilung(int $jahr): DataResponse {
        $automatik = (bool) $this->request->getParam('automatikEin', true);
        $modus = (string) $this->request->getParam('zielModus', 'schwarze_null');
        $betrag = (int) $this->request->getParam('zielBetrag', 0);
        $haltung = (string) $this->request->getParam('haltung', 'einreichen');
        $ausnahmen = $this->request->getParam('ausnahmen', []);
        $ausnahmen = is_array($ausnahmen) ? array_values(array_map(static fn ($x) => (string) $x, $ausnahmen)) : [];
        $this->service->verteilungSetzen($jahr, $automatik, $modus, $betrag, $haltung, $ausnahmen);
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
            $this->service->automatikNeuBerechnen($jahr);
            $ansicht = $this->service->ansicht($jahr);
            $this->ereignisse->protokolliere('budget_import', 'budget', true,
                'Budget ' . $jahr . ' eingelesen', $this->budgetZusammenfassung($ansicht));
            return new DataResponse($ansicht, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error('Budget-Import fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            $this->ereignisse->protokolliere('fehler', 'budget', false,
                'Budget ' . $jahr . ' einlesen fehlgeschlagen', $e->getMessage());
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Budget eines Jahres sauber neu einlesen (F91): löscht ALLE Fraktionsdaten
     * zu diesem Budget (Anträge, Notizen, Pauschalanträge) und liest das Budgetbuch
     * neu ein. Destruktiv — das Frontend löst dies nur nach doppelter Bestätigung
     * aus (Dialog «Bist du ganz sicher?» plus Verstanden-Checkbox).
     */
    #[NoAdminRequired]
    public function reimport(int $jahr): DataResponse {
        try {
            $this->service->budgetFraktionsdatenLeeren($jahr);
            $this->import->importiereJahr($jahr);
            $this->service->automatikNeuBerechnen($jahr);
            $ansicht = $this->service->ansicht($jahr);
            $this->ereignisse->protokolliere('budget_reimport', 'budget', true,
                'Budget ' . $jahr . ' neu eingelesen', $this->budgetZusammenfassung($ansicht));
            return new DataResponse($ansicht);
        } catch (\Throwable $e) {
            $this->logger->error('Budget-Re-Import fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            $this->ereignisse->protokolliere('fehler', 'budget', false,
                'Budget ' . $jahr . ' neu einlesen fehlgeschlagen', $e->getMessage());
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function novemberbrief(int $jahr): DataResponse {
        try {
            $this->import->importiereNovemberbrief($jahr);
            $this->service->automatikNeuBerechnen($jahr);
            $this->ereignisse->protokolliere('novemberbrief', 'budget', true,
                'Novemberbrief ' . $jahr . ' eingelesen');
            return new DataResponse($this->service->ansicht($jahr));
        } catch (\Throwable $e) {
            $this->logger->error('Novemberbrief-Import fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            $this->ereignisse->protokolliere('fehler', 'budget', false,
                'Novemberbrief ' . $jahr . ' einlesen fehlgeschlagen', $e->getMessage());
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Liest die tatsächlichen Sitzungsanträge (F90) live aus dem Drehbuch der
     * Budgetsitzung und bringt sie als offizielle Sitzungsanträge ein. Idempotent:
     * bereits vorhandene, gleich lautende Anträge werden nicht dupliziert. Liefert
     * die aktualisierte Ansicht samt Zahl der neu eingelesenen Anträge.
     */
    #[NoAdminRequired]
    public function sitzungsantraege(int $jahr): DataResponse {
        try {
            $geparst = $this->import->sitzungsantraege($jahr);
            $neu = $this->service->sitzungsantraegeEinlesen($jahr, $geparst);
            $this->service->automatikNeuBerechnen($jahr);
            $ansicht = $this->service->ansicht($jahr);
            $ansicht['sitzungsantraegeNeu'] = $neu;
            $ansicht['sitzungsantraegeGefunden'] = count($geparst);
            $this->ereignisse->protokolliere('sitzungsantraege', 'budget', count($geparst) > 0,
                'Sitzungsanträge ' . $jahr . ' eingelesen',
                $neu . ' neu von ' . count($geparst) . ' im Drehbuch gefundenen Anträgen'
                . (count($geparst) === 0 ? ' — kein Drehbuch zur Budgetsitzung gefunden' : ''));
            return new DataResponse($ansicht);
        } catch (\Throwable $e) {
            $this->logger->error('Sitzungsanträge einlesen fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);
            $this->ereignisse->protokolliere('fehler', 'budget', false,
                'Sitzungsanträge ' . $jahr . ' einlesen fehlgeschlagen', $e->getMessage());
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /** Kurze Zusammenfassung eines Budget-Imports für das Protokoll (F105). */
    private function budgetZusammenfassung(array $ansicht): string {
        $gruppen = is_array($ansicht['produktegruppen'] ?? null) ? \count($ansicht['produktegruppen']) : 0;
        $investitionen = is_array($ansicht['investitionen'] ?? null) ? \count($ansicht['investitionen']) : 0;
        return $gruppen . ' Produktegruppen, ' . $investitionen . ' Investitionen';
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
        // Optional die von uns unterstützten fremden Anträge mit aufnehmen (F92).
        $mitFremden = in_array((string) $this->request->getParam('mitFremden'), ['1', 'true'], true);
        // Im PDF steht als Antragsteller immer die Fraktion (im Rat stellt die
        // Fraktion die Anträge); die Person erscheint nur in der Übersicht.
        $daten = $this->import->antraegePdf($jahr, $kommission, $mitFremden, $this->service->eigeneFraktion());
        return new TemplateResponse(Application::APP_ID, 'budget_antraege_pdf', $daten, 'blank');
    }

    /** Setzt oder löst eine Verknüpfung Vorbereitung↔Sitzung von Hand (F104). */
    #[NoAdminRequired]
    public function verknuepfen(int $id): DataResponse {
        $zielId = (int) $this->request->getParam('zielId', 0);
        try {
            $this->service->verknuepfungSetzen($id, $zielId);
            return new DataResponse([]);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Antrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    // ── Weitere Pauschalanträge (F100) ────────────────────────────────────────

    #[NoAdminRequired]
    public function pauschalErstellen(int $jahr): DataResponse {
        $this->service->pauschalErstellen($jahr, $this->pauschalDaten());
        return new DataResponse($this->service->ansicht($jahr), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function pauschalAendern(int $id): DataResponse {
        try {
            $v = $this->service->pauschalAendern($id, $this->pauschalDaten());
            return new DataResponse($this->service->ansicht((int) $v->getJahr()));
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Pauschalantrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function pauschalLoeschen(int $id): DataResponse {
        try {
            $this->service->pauschalLoeschen($id);
            return new DataResponse([]);
        } catch (DoesNotExistException) {
            return new DataResponse(['fehler' => 'Pauschalantrag nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /** @return array<string, mixed> */
    private function pauschalDaten(): array {
        $felder = ['betrag', 'prozent', 'haltung', 'herkunft', 'antragsteller', 'begruendung', 'ausnahmen'];
        $daten = [];
        foreach ($felder as $f) {
            $wert = $this->request->getParam($f);
            if ($wert !== null) {
                $daten[$f] = $wert;
            }
        }
        return $daten;
    }

    /** @return array<string, mixed> */
    private function antragDaten(): array {
        $felder = [
            'bereich', 'zielTyp', 'zielRef', 'betragDelta', 'prozentDelta', 'stellenDelta',
            'betragProStelle', 'quelle', 'herkunft', 'haltung', 'unterstuetzer',
            'pauschalAusnahme', 'antragsteller', 'begruendung', 'phase',
            // Zielvorgaben-Änderungen und Einsparungsverteilung (F109): Ohne sie
            // kommt beim Dienst nichts an, was die Oberfläche unter «Zielvorgaben
            // ändern» und «Einsparung verteilen» erfasst hat.
            'zielAenderungen', 'aufteilung',
        ];
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
