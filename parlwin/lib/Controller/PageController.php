<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

/**
 * Seitencontroller: Liefert die Hauptseite der App.
 */
class PageController extends Controller {
    private const DEFAULT_REALTIME_PORT = 29825;

    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Hauptseite der App.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'parlwin-main');
        Util::addStyle(Application::APP_ID, 'parlwin-style');

        $letzteSync = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        $fraktion = $this->config->getAppValue(Application::APP_ID, 'fraktion', '');
        $realtimeWsUrl = $this->realtimeWsUrl();

        $response = new TemplateResponse(Application::APP_ID, 'main', [
            'letzte_synchronisation' => $letzteSync,
            'fraktion' => $fraktion,
            'realtime_ws_url' => $realtimeWsUrl,
            'realtime_ws_port' => $this->realtimePort(),
            'realtime_ws_path' => $this->realtimePath(),
        ]);

        $connectDomain = $this->connectDomainFromUrl($realtimeWsUrl);
        if ($connectDomain !== '') {
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedConnectDomain($connectDomain);
            $response->setContentSecurityPolicy($csp);
        }

        return $response;
    }

    private function realtimeWsUrl(): string {
        $env = trim((string) getenv('PARLWIN_REALTIME_WS_URL'));
        if ($env !== '') {
            return $env;
        }

        $configured = trim((string) $this->config->getAppValue(Application::APP_ID, 'realtime_ws_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = trim((string) $this->request->getServerHost());
        if ($host === '') {
            $host = 'localhost';
        }
        $scheme = $this->isHttpsRequest() ? 'wss' : 'ws';
        return sprintf('%s://%s:%d%s', $scheme, $host, $this->realtimePort(), $this->realtimePath());
    }

    private function realtimePort(): int {
        $env = trim((string) getenv('PARLWIN_REALTIME_PORT'));
        if ($env !== '' && ctype_digit($env)) {
            return (int) $env;
        }
        return self::DEFAULT_REALTIME_PORT;
    }

    private function realtimePath(): string {
        $path = trim((string) getenv('PARLWIN_REALTIME_PATH'));
        if ($path === '') {
            return '/ws';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $path;
    }

    private function isHttpsRequest(): bool {
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

    private function connectDomainFromUrl(string $url): string {
        if ($url === '') {
            return '';
        }

        $teile = parse_url($url);
        if (!is_array($teile) || empty($teile['scheme']) || empty($teile['host'])) {
            return '';
        }

        $scheme = strtolower((string) $teile['scheme']);
        if (!in_array($scheme, ['ws', 'wss', 'http', 'https'], true)) {
            return '';
        }

        $domain = $scheme . '://' . $teile['host'];
        if (isset($teile['port'])) {
            $domain .= ':' . (int) $teile['port'];
        }
        return $domain;
    }
}
