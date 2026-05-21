<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\AppInfo;

use OCA\ParliamentWinterthur\BackgroundJob\SyncJob;
use OCA\ParliamentWinterthur\Command\SyncCancelCommand;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmerMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandumMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCA\ParliamentWinterthur\Search\GeschaeftSearchProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'parlwin';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
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
        $context->registerService(SitzungstypMapper::class, function ($c) {
            return new SitzungstypMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(SitzungstypTraktandumMapper::class, function ($c) {
            return new SitzungstypTraktandumMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(SitzungstypTeilnehmerMapper::class, function ($c) {
            return new SitzungstypTeilnehmerMapper($c->get(\OCP\IDBConnection::class));
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
        $context->registerService(FraktionsrolleMapper::class, function ($c) {
            return new FraktionsrolleMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(VorstossEntwurfMapper::class, function ($c) {
            return new VorstossEntwurfMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(GeschaeftEreignisMapper::class, function ($c) {
            return new GeschaeftEreignisMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(GeschaeftAktionMapper::class, function ($c) {
            return new GeschaeftAktionMapper($c->get(\OCP\IDBConnection::class));
        });
        $context->registerService(GeschaeftZustaendigkeitMapper::class, function ($c) {
            return new GeschaeftZustaendigkeitMapper($c->get(\OCP\IDBConnection::class));
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
                $c->get(VorstossEntwurfMapper::class),
                $c->get(GeschaeftEreignisMapper::class),
                $c->get(ScraperService::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(FraktionsarbeitService::class, function ($c) {
            return new FraktionsarbeitService(
                $c->get(GeschaeftMapper::class),
                $c->get(GeschaeftAktionMapper::class),
                $c->get(GeschaeftZustaendigkeitMapper::class),
                $c->get(FraktionsrolleMapper::class),
                $c->get(MitgliedMapper::class),
                $c->get(KommissionMapper::class),
                $c->get(GeschaeftEreignisMapper::class),
                $c->get(\OCP\IConfig::class),
                $c->get(\OCP\IUserSession::class),
                $c->get(\OCP\IGroupManager::class),
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
        $context->registerService(SitzungstypService::class, function ($c) {
            return new SitzungstypService(
                $c->get(SitzungstypMapper::class),
                $c->get(SitzungstypTraktandumMapper::class),
                $c->get(SitzungstypTeilnehmerMapper::class),
                $c->get(SitzungMapper::class),
                $c->get(TraktandumMapper::class),
                $c->get(MitgliedMapper::class),
                $c->get(KommissionMapper::class),
                $c->get(FraktionsrolleMapper::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        $context->registerService(MitgliedService::class, function ($c) {            return new MitgliedService(
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
                $c->get(\Psr\Log\LoggerInterface::class),
                $c->get(\OCP\IUserManager::class),
            );
        });
        $context->registerService(RealtimePublisherService::class, function ($c) {
            return new RealtimePublisherService(
                $c->get(\OCP\Http\Client\IClientService::class),
                $c->get(\OCP\IConfig::class),
                $c->get(\Psr\Log\LoggerInterface::class),
            );
        });
        $context->registerService(SyncLockService::class, function ($c) {
            return new SyncLockService();
        });
        $context->registerService(SyncProcessService::class, function ($c) {
            return new SyncProcessService();
        });
        $context->registerService(SyncCommand::class, function ($c) {
            return new SyncCommand(
                $c->get(GeschaeftService::class),
                $c->get(SitzungService::class),
                $c->get(MitgliedService::class),
                $c->get(KalenderService::class),
                $c->get(RealtimePublisherService::class),
                $c->get(ScraperService::class),
                $c->get(SyncLockService::class),
                $c->get(FraktionsarbeitService::class),
                $c->get(\OCP\IConfig::class),
            );
        });
        $context->registerService(SyncCancelCommand::class, function ($c) {
            return new SyncCancelCommand(
                $c->get(\OCP\IConfig::class),
                $c->get(SyncLockService::class),
                $c->get(RealtimePublisherService::class),
                $c->get(SyncProcessService::class),
            );
        });

        // Unified Search Provider für Geschäfte
        $context->registerSearchProvider(GeschaeftSearchProvider::class);
    }

    public function boot(IBootContext $context): void
    {
    }
}
