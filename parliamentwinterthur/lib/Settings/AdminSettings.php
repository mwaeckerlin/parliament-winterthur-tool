<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Settings;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
    public function __construct(
        private readonly IConfig $config,
        private readonly IL10N $l,
    ) {
    }

    public function getForm(): TemplateResponse {
        $fraktion = $this->config->getAppValue(Application::APP_ID, 'fraktion', '');
        $nextcloudGruppe = $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', '');
        $kalenderNutzer = $this->config->getAppValue(Application::APP_ID, 'kalender_nutzer', '');
        $absenderEmail = $this->config->getAppValue(Application::APP_ID, 'absender_email', '');
        $absenderName = $this->config->getAppValue(Application::APP_ID, 'absender_name', 'Parliament Winterthur Tool');
        $letzteSync = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');

        return new TemplateResponse(Application::APP_ID, 'admin', [
            'fraktion' => $fraktion,
            'nextcloud_gruppe' => $nextcloudGruppe,
            'kalender_nutzer' => $kalenderNutzer,
            'absender_email' => $absenderEmail,
            'absender_name' => $absenderName,
            'letzte_synchronisation' => $letzteSync,
        ], '');
    }

    public function getSection(): string {
        return 'parliamentwinterthur';
    }

    public function getPriority(): int {
        return 50;
    }
}
