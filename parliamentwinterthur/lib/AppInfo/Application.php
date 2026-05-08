<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\AppInfo;

use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'parliamentwinterthur';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Mapper
        $context->registerService(GeschaeftMapper::class, function ($c) {
            return new GeschaeftMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(SitzungMapper::class, function ($c) {
            return new SitzungMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(TraktandumMapper::class, function ($c) {
            return new TraktandumMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(MitgliedMapper::class, function ($c) {
            return new MitgliedMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(KommissionMapper::class, function ($c) {
            return new KommissionMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(FraktionMapper::class, function ($c) {
            return new FraktionMapper($c->get(\OCP\IDBConnection::class));
        });

        // Services
        $context->registerService(ScraperService::class, function ($c) {
            return new ScraperService(
                $c->get(\OCP\Http\Client\IClientService::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(GeschaeftService::class, function ($c) {
            return new GeschaeftService(
                $c->get(GeschaeftMapper::class),
                $c->get(ScraperService::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(SitzungService::class, function ($c) {
            return new SitzungService(
                $c->get(SitzungMapper::class),
                $c->get(TraktandumMapper::class),
                $c->get(GeschaeftMapper::class),
                $c->get(ScraperService::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(MitgliedService::class, function ($c) {
            return new MitgliedService(
                $c->get(MitgliedMapper::class),
                $c->get(FraktionMapper::class),
                $c->get(KommissionMapper::class),
                $c->get(ScraperService::class),
                $c->get(\OCP\IGroupManager::class),
                $c->get(\OCP\IUserManager::class),
                $c->get(\OCP\Mail\IMailer::class),
                $c->get(\OCP\IConfig::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(KalenderService::class, function ($c) {
            return new KalenderService(
                $c->get(\OCP\IConfig::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
    }
}
