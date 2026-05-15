<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Settings;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Settings\ISettings;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Util;

class AdminSettings implements ISettings {
    public function __construct(
        private readonly IConfig $config,
        private readonly IL10N $l,
        private readonly IRequest $request,
        private readonly FraktionMapper $fraktionMapper,
        private readonly IGroupManager $groupManager,
        private readonly IUserManager $userManager,
    ) {
    }

    public function getForm(): TemplateResponse {
        Util::addStyle(Application::APP_ID, 'parlwin-style');

        $fraktion = $this->config->getAppValue(Application::APP_ID, 'fraktion', '');
        $nextcloudGruppe = $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', '');
        $kalenderNutzer = $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', '');
        $absenderEmail = $this->config->getAppValue(Application::APP_ID, 'absender_email', '');
        $absenderName = $this->config->getAppValue(Application::APP_ID, 'absender_name', 'Parlament Winterthur Tool');
        $letzteSync = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        $fraktionsOptionen = $this->fraktionsOptionen();
        $gruppenOptionen = $this->gruppenOptionen();
        $kalenderNutzerOptionen = $this->kalenderNutzerOptionen();

        $realtimeWsUrl = $this->realtimeWsUrl();
        $response = new TemplateResponse(Application::APP_ID, 'admin', [
            'fraktion' => $fraktion,
            'nextcloud_gruppe' => $nextcloudGruppe,
            'kalender_nutzer' => $kalenderNutzer,
            'absender_email' => $absenderEmail,
            'absender_name' => $absenderName,
            'letzte_synchronisation' => $letzteSync,
            'fraktion_optionen' => $fraktionsOptionen,
            'nextcloud_gruppen_optionen' => $gruppenOptionen,
            'kalender_nutzer_optionen_aktiv' => $kalenderNutzerOptionen['aktiv'],
            'kalender_nutzer_optionen_inaktiv' => $kalenderNutzerOptionen['inaktiv'],
            'realtime_ws_url' => $realtimeWsUrl,
        ], '');

        return $response;
    }

    public function getSection(): string {
        return 'parlwin';
    }

    public function getPriority(): int {
        return 50;
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

        // Default: same-origin reverse-proxy convention provided by
        // mwaeckerlin/nextcloud:nginx. The /ws/<appid>/ location is
        // proxied to the parlwin-ws service on the internal port 3001.
        $host = trim((string) $this->request->getServerHost());
        if ($host === '') {
            $host = 'localhost';
        }
        $scheme = $this->isHttpsRequest() ? 'wss' : 'ws';
        $webroot = rtrim((string) \OC::$WEBROOT, '/');
        return sprintf('%s://%s%s/ws/%s/', $scheme, $host, $webroot, Application::APP_ID);
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

    /**
     * @return string[]
     */
    private function fraktionsOptionen(): array {
        $namen = [];
        foreach ($this->fraktionMapper->findAll() as $fraktion) {
            if ($fraktion->getAktiv() !== true) {
                continue;
            }
            $name = trim((string) $fraktion->getName());
            if ($name === '') {
                continue;
            }
            $namen[] = $name;
        }

        $namen = array_values(array_unique($namen));
        natcasesort($namen);
        return array_values($namen);
    }

    /**
     * @return string[]
     */
    private function gruppenOptionen(): array {
        $gruppen = [];
        foreach ($this->groupManager->search('', 500, 0) as $gruppe) {
            $gid = trim((string) $gruppe->getGID());
            if ($gid === '') {
                continue;
            }
            $gruppen[] = $gid;
        }

        $gruppen = array_values(array_unique($gruppen));
        natcasesort($gruppen);
        return array_values($gruppen);
    }

    /**
     * @return array{aktiv: array<int, array{uid: string, label: string}>, inaktiv: array<int, array{uid: string, label: string}>}
     */
    private function kalenderNutzerOptionen(): array {
        $aktiv = [];
        $inaktiv = [];
        foreach ($this->userManager->search('', 500, 0) as $user) {
            $eintrag = $this->kalenderNutzerEintrag($user);
            if ($eintrag === null) {
                continue;
            }
            if ($eintrag['aktiv']) {
                $aktiv[] = $eintrag['daten'];
            } else {
                $inaktiv[] = $eintrag['daten'];
            }
        }

        $sort = static function (array &$items): void {
            usort($items, static function (array $a, array $b): int {
                return strcasecmp($a['label'], $b['label']);
            });
        };
        $sort($aktiv);
        $sort($inaktiv);

        return [
            'aktiv' => $aktiv,
            'inaktiv' => $inaktiv,
        ];
    }

    /**
     * @return array{aktiv: bool, daten: array{uid: string, label: string}}|null
     */
    private function kalenderNutzerEintrag(IUser $user): ?array {
        $uid = trim($user->getUID());
        if ($uid === '') {
            return null;
        }

        $anzeigename = trim($user->getDisplayName() ?? '');
        $label = $anzeigename !== '' ? sprintf('%s (%s)', $anzeigename, $uid) : $uid;
        return [
            'aktiv' => $user->isEnabled(),
            'daten' => [
                'uid' => $uid,
                'label' => $label,
            ],
        ];
    }
}
