<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
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
class PageController extends Controller {
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
        Util::addScript(Application::APP_ID, 'parliamentwinterthur-main');
        Util::addStyle(Application::APP_ID, 'parliamentwinterthur-style');

        $letzteSync = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        $fraktion = $this->config->getAppValue(Application::APP_ID, 'fraktion', '');

        return new TemplateResponse(Application::APP_ID, 'main', [
            'letzte_synchronisation' => $letzteSync,
            'fraktion' => $fraktion,
        ]);
    }
}
