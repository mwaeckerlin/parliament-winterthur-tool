<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;

/**
 * REST-Controller für Einstellungen und manuelle Synchronisation.
 */
class SettingsController extends Controller {
    /** Erlaubte Einstellungsschlüssel und ihre Standardwerte */
    private const EINSTELLUNGEN = [
        'fraktion'             => '',
        'nextcloud_gruppe'     => '',
        'kalender_nutzer'      => '',
        'absender_email'       => '',
        'absender_name'        => 'Parliament Winterthur Tool',
    ];

    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly KalenderService $kalenderService,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Einstellungen zurück.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function get(): DataResponse {
        $daten = [];
        foreach (self::EINSTELLUNGEN as $schluessel => $standard) {
            $daten[$schluessel] = $this->config->getAppValue(Application::APP_ID, $schluessel, $standard);
        }
        $daten['letzte_synchronisation'] = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        return new DataResponse($daten);
    }

    /**
     * Speichert Einstellungen.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function set(): DataResponse {
        foreach (self::EINSTELLUNGEN as $schluessel => $standard) {
            if ($this->request->offsetExists($schluessel)) {
                $wert = $this->request->getParam($schluessel, $standard);
                $this->config->setAppValue(Application::APP_ID, $schluessel, $wert);
            }
        }
        return $this->get();
    }

    /**
     * Löst eine manuelle Synchronisation aus.
     * Nur für Administratoren.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function run(): DataResponse {
        try {
            $statistik = [
                'mitglieder'  => $this->mitgliedService->synchronisieren(),
                'geschaefte'  => $this->geschaeftService->synchronisieren(),
                'sitzungen'   => $this->sitzungService->synchronisieren(),
            ];

            $this->kalenderService->sitzungenAktualisieren(
                $this->sitzungService->alleAktiven()
            );

            $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
            $this->config->setAppValue(Application::APP_ID, 'letzte_synchronisation', $jetzt);

            return new DataResponse([
                'erfolg' => true,
                'zeitpunkt' => $jetzt,
                'statistik' => $statistik,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(
                ['fehler' => 'Synchronisation fehlgeschlagen: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
