<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RealtimePublisherServiceTest extends TestCase {
    protected function tearDown(): void {
        putenv('PARLWIN_REALTIME_PUBLISH_URL');
        putenv('PARLWIN_REALTIME_SECRET');
        parent::tearDown();
    }

    public function testPublishMitAppConfigUrlUndSecret(): void {
        putenv('PARLWIN_REALTIME_PUBLISH_URL');
        putenv('PARLWIN_REALTIME_SECRET');

        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);

        $config->method('getAppValue')->willReturnMap([
            ['parlwin', 'realtime_publish_url', '', 'http://realtime.local/publish'],
            ['parlwin', 'realtime_secret', '', 'shared-secret'],
        ]);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'http://realtime.local/publish',
                $this->callback(function (array $options): bool {
                    if (($options['headers']['X-PWT-Secret'] ?? '') !== 'shared-secret') {
                        return false;
                    }
                    $decoded = json_decode((string) ($options['body'] ?? ''), true);
                    return is_array($decoded)
                        && ($decoded['type'] ?? '') === 'geschaefte.updated'
                        && ($decoded['payload']['id'] ?? 0) === 42;
                })
            );

        $service = new RealtimePublisherService($clientService, $config, $logger);
        $service->publish('geschaefte.updated', ['id' => 42]);
    }

    public function testPublishNutztUmgebungsvariablenVorrangig(): void {
        putenv('PARLWIN_REALTIME_PUBLISH_URL=http://env-realtime/publish');
        putenv('PARLWIN_REALTIME_SECRET=env-secret');

        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'http://env-realtime/publish',
                $this->callback(fn(array $options): bool => ($options['headers']['X-PWT-Secret'] ?? '') === 'env-secret')
            );

        $service = new RealtimePublisherService($clientService, $config, $logger);
        $service->publish('sync.completed');
    }

    public function testPublishNutztDefaultPublishUrlWennNichtsKonfiguriertIst(): void {
        putenv('PARLWIN_REALTIME_PUBLISH_URL');
        putenv('PARLWIN_REALTIME_SECRET');

        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);

        $config->method('getAppValue')->willReturnMap([
            ['parlwin', 'realtime_publish_url', '', ''],
            ['parlwin', 'realtime_secret', '', ''],
        ]);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'http://parlwin-ws:3001/publish',
                $this->callback(fn(array $options): bool => !isset($options['headers']['X-PWT-Secret']))
            );

        $service = new RealtimePublisherService($clientService, $config, $logger);
        $service->publish('sync.completed');
    }
}
