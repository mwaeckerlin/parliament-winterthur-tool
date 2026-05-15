<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Veröffentlicht Realtime-Ereignisse an den WebSocket-Broker.
 *
 * Das Frontend konsumiert die Ereignisse via WebSocket und aktualisiert die
 * betroffenen Ansichten sofort.
 */
class RealtimePublisherService {
    private const APP_ID = 'parlwin';
    private const DEFAULT_PUBLISH_URL = 'http://parlwin-ws:3001/publish';

    public function __construct(
        private readonly IClientService $clientService,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $type, array $payload = []): void {
        $url = $this->publishUrl();
        if ($url === '') {
            return;
        }

        $secret = $this->publishSecret();
        $event = [
            'type' => $type,
            'payload' => $payload,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($secret !== '') {
            $headers['X-PWT-Secret'] = $secret;
        }

        try {
            $this->clientService->newClient()->post($url, [
                'timeout' => 3,
                'headers' => $headers,
                'body' => json_encode($event, JSON_THROW_ON_ERROR),
                'nextcloud' => [
                    'allow_local_address' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->debug('Parlament Winterthur: Realtime publish fehlgeschlagen', [
                'url' => $url,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function publishUrl(): string {
        $env = trim((string) getenv('PARLWIN_REALTIME_PUBLISH_URL'));
        if ($env !== '') {
            return $env;
        }

        $configured = trim((string) $this->config->getAppValue(self::APP_ID, 'realtime_publish_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return self::DEFAULT_PUBLISH_URL;
    }

    private function publishSecret(): string {
        $env = trim((string) getenv('PARLWIN_REALTIME_SECRET'));
        if ($env !== '') {
            return $env;
        }
        return trim((string) $this->config->getAppValue(self::APP_ID, 'realtime_secret', ''));
    }
}
