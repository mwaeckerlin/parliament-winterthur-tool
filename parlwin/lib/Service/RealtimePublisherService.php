<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Veröffentlicht Realtime-Ereignisse an den WebSocket-Broker.
 *
 * Das Frontend konsumiert die Ereignisse via WebSocket und aktualisiert die
 * betroffenen Ansichten sofort.
 */
class RealtimePublisherService
{
    private const APP_ID = 'parlwin';
    private const DEFAULT_PUBLISH_URL = 'http://parlwin-realtime:3001/publish';
    /** Der Header, mit dem die Oberfläche ihr Browserfenster nennt (nachladen.js). */
    private const FENSTER_HEADER = 'X-Parlwin-Fenster';

    public function __construct(
        private readonly IClientService $clientService,
        private readonly IConfig $config,
        private readonly LoggerInterface $logger,
        private readonly ?IRequest $request = null,
        private readonly ?IUserSession $userSession = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $type, array $payload = []): void
    {
        $url = $this->publishUrl();
        if ($url === '') {
            return;
        }

        // Das Fenster, das gespeichert hat, erkennt sein eigenes Ereignis an der
        // Kennung des Fensters und an der Benutzer-ID der Sitzung und lädt nicht
        // nach. Angenommen wird nur eine Kennung in der Form, die die Oberfläche
        // erzeugt; die Benutzer-ID kommt immer aus der Sitzung.
        $fenster = $this->fenster();
        if ($fenster !== '' && !array_key_exists('_fenster', $payload)) {
            $payload['_fenster'] = $fenster;
        }
        unset($payload['_uid']);
        $uid = $fenster !== '' ? (string) $this->userSession?->getUser()?->getUID() : '';
        if ($uid !== '') {
            $payload['_uid'] = $uid;
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
            $headers['X-Parlwin-Secret'] = $secret;
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

    /**
     * Die Geschäfte, die ein Abgleich geändert hat, für die Ereignisse
     * `sync.completed`, `sync.cancelled` und `sync.failed` (F150): Die
     * Oberfläche lädt nur sie nach. Hat der
     * Abgleich Geschäfte als gelöscht markiert, steht `null` da, und die
     * Oberfläche lädt die ganze Liste, weil das Ergebnis die gelöschten nicht
     * mit Namen nennt.
     *
     * @param array<string, mixed>|null $geschaefteStatistik
     * @param int[] ...$listen
     * @return list<int>|null
     */
    public static function geaenderteGeschaefte(?array $geschaefteStatistik, array ...$listen): ?array
    {
        if ((int) ($geschaefteStatistik['geloescht'] ?? 0) > 0) {
            return null;
        }
        return array_values(array_unique(array_map('intval', array_merge(...$listen))));
    }

    /** Die Kennung des Browserfensters der laufenden Anfrage, leer ohne gültige. */
    private function fenster(): string
    {
        if ($this->request === null) {
            return '';
        }
        $wert = trim((string) $this->request->getHeader(self::FENSTER_HEADER));
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $wert) === 1 ? $wert : '';
    }

    private function publishUrl(): string
    {
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

    private function publishSecret(): string
    {
        $env = trim((string) getenv('PARLWIN_REALTIME_SECRET'));
        if ($env !== '') {
            return $env;
        }
        return trim((string) $this->config->getAppValue(self::APP_ID, 'realtime_secret', ''));
    }
}
