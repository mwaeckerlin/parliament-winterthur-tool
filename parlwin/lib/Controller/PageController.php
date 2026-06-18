<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

/**
 * Seitencontroller: Liefert die Hauptseite der App.
 */
class PageController extends Controller
{
    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
        private readonly IAppManager $appManager,
        private readonly FraktionsraumService $fraktionsraumService,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Hauptseite der App.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        Util::addScript(Application::APP_ID, 'parlwin-main');
        Util::addStyle(Application::APP_ID, 'parlwin-style');

        // Fraktions-Infrastruktur (geteilter Ordner + Kalender) bei jedem
        // App-Start prüfen und fehlende Teile automatisch ergänzen.
        try {
            $this->fraktionsraumService->sicherstellen();
        } catch (\Throwable) {
            /* non-fatal: Seite muss auch ohne Infrastruktur laden */
        }

        $letzteSync = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        $fraktion = $this->config->getAppValue(Application::APP_ID, 'fraktion', '');
        $nextcloudGruppe = $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', '');
        $realtimeWsUrl = $this->realtimeWsUrl();

        $statusKuerzel = (string) $this->config->getAppValue(Application::APP_ID, 'status_kuerzel', '[]');
        return new TemplateResponse(Application::APP_ID, 'main', [
            'letzte_synchronisation' => $letzteSync,
            'fraktion' => $fraktion,
            'nextcloud_gruppe' => $nextcloudGruppe,
            'realtime_ws_url' => $realtimeWsUrl,
            'version' => $this->appManager->getAppVersion(Application::APP_ID),
            'status_kuerzel' => $statusKuerzel,
        ]);
    }

    private function realtimeWsUrl(): string
    {
        $env = trim((string) getenv('PARLWIN_REALTIME_WS_URL'));
        if ($env !== '') {
            return $env;
        }

        $configured = trim((string) $this->config->getAppValue(Application::APP_ID, 'realtime_ws_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        // Default: same-origin reverse-proxy convention provided by
        // mwaeckerlin/nextcloud:nginx. The /ws/<appid>/ location proxies
        // to docker-compose service parlwin-realtime on internal port 3001.
        $host = trim((string) $this->request->getServerHost());
        if ($host === '') {
            $host = 'localhost';
        }
        $scheme = $this->isHttpsRequest() ? 'wss' : 'ws';
        $webroot = rtrim((string) \OC::$WEBROOT, '/');
        return sprintf('%s://%s%s/ws/%s/', $scheme, $host, $webroot, Application::APP_ID);
    }

    private function isHttpsRequest(): bool
    {
        $forwardedProto = strtolower(trim((string) $this->request->getHeader('x-forwarded-proto')));
        if (str_contains($forwardedProto, 'https')) {
            return true;
        }

        $forwardedSsl = strtolower(trim((string) $this->request->getHeader('x-forwarded-ssl')));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $protocol = strtolower(trim((string) $this->request->getServerProtocol()));
        return $protocol === 'https';
    }
}
