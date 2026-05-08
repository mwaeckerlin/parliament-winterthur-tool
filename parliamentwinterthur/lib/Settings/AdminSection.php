<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Settings;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    public function __construct(
        private readonly IL10N $l,
        private readonly IURLGenerator $urlGenerator,
    ) {
    }

    public function getIcon(): string {
        return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
    }

    public function getID(): string {
        return 'parliamentwinterthur';
    }

    public function getName(): string {
        return $this->l->t('Parliament Winterthur');
    }

    public function getPriority(): int {
        return 75;
    }
}
