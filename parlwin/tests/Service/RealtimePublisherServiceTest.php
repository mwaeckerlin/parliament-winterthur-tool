<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
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
        $config = $this->createStub(IConfig::class);
        $logger = $this->createStub(LoggerInterface::class);

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
                    if (($options['headers']['X-Parlwin-Secret'] ?? '') !== 'shared-secret') {
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
        $config = $this->createStub(IConfig::class);
        $logger = $this->createStub(LoggerInterface::class);

        $clientService->expects($this->once())
            ->method('newClient')
            ->willReturn($client);

        $client->expects($this->once())
            ->method('post')
            ->with(
                'http://env-realtime/publish',
                $this->callback(fn(array $options): bool => ($options['headers']['X-Parlwin-Secret'] ?? '') === 'env-secret')
            );

        $service = new RealtimePublisherService($clientService, $config, $logger);
        $service->publish('sync.completed');
    }

    public function testPublishNutztDefaultPublishUrlWennNichtsKonfiguriertIst(): void {
        putenv('PARLWIN_REALTIME_PUBLISH_URL');
        putenv('PARLWIN_REALTIME_SECRET');

        $clientService = $this->createMock(IClientService::class);
        $client = $this->createMock(IClient::class);
        $config = $this->createStub(IConfig::class);
        $logger = $this->createStub(LoggerInterface::class);

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
                'http://parlwin-realtime:3001/publish',
                $this->callback(fn(array $options): bool => !isset($options['headers']['X-Parlwin-Secret']))
            );

        $service = new RealtimePublisherService($clientService, $config, $logger);
        $service->publish('sync.completed');
    }

    /**
     * Die Nutzlast, die ein Ereignis mit diesem Fenster-Header an den Broker
     * schickt, angemeldet als `$uid` (leer: ohne Anmeldung).
     *
     * @param array<string, mixed> $nutzlast
     */
    private function gesendeteNutzlast(string $header, string $uid = '', array $nutzlast = ['id' => 7, 'sitzungId' => 3, 'aktionTyp' => 'notiz']): array {
        putenv('PARLWIN_REALTIME_PUBLISH_URL=http://realtime.local/publish');
        $gesendet = [];
        $client = $this->createStub(IClient::class);
        $client->method('post')->willReturnCallback(function (string $_url, array $options) use (&$gesendet) {
            $gesendet = json_decode((string) ($options['body'] ?? ''), true);
            return null;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $request = $this->createStub(IRequest::class);
        $request->method('getHeader')->willReturnCallback(
            static fn(string $name): string => $name === 'X-Parlwin-Fenster' ? $header : ''
        );

        $sitzung = $this->createStub(IUserSession::class);
        if ($uid !== '') {
            $benutzer = $this->createStub(IUser::class);
            $benutzer->method('getUID')->willReturn($uid);
            $sitzung->method('getUser')->willReturn($benutzer);
        } else {
            $sitzung->method('getUser')->willReturn(null);
        }

        $service = new RealtimePublisherService(
            $clientService,
            $this->createStub(IConfig::class),
            $this->createStub(LoggerInterface::class),
            $request,
            $sitzung,
        );
        $service->publish('traktanden.updated', $nutzlast);
        return (array) ($gesendet['payload'] ?? []);
    }

    /**
     * Das Ereignis nennt die Benutzer-ID aus der Sitzung neben dem Fenster:
     * Nur das Fenster dieses Benutzers hält es für sein eigenes.
     */
    public function testDasEreignisNenntDenBenutzerAusDerSitzung(): void {
        $nutzlast = $this->gesendeteNutzlast('a1b2c3d4-e5f6-4711-8899-aabbccddeeff', 'anna');
        self::assertSame('anna', $nutzlast['_uid'] ?? null);
        self::assertSame('a1b2c3d4-e5f6-4711-8899-aabbccddeeff', $nutzlast['_fenster'] ?? null);
    }

    /** Eine Benutzer-ID in der Nutzlast des Aufrufers gilt nicht; es zählt die Sitzung. */
    public function testEineMitgeschickteBenutzerIdWirdErsetzt(): void {
        $nutzlast = $this->gesendeteNutzlast('a1b2c3d4-e5f6-4711-8899-aabbccddeeff', 'anna', ['id' => 7, '_uid' => 'bruno']);
        self::assertSame('anna', $nutzlast['_uid'] ?? null);

        $ohneAnmeldung = $this->gesendeteNutzlast('a1b2c3d4-e5f6-4711-8899-aabbccddeeff', '', ['id' => 7, '_uid' => 'bruno']);
        self::assertArrayNotHasKey('_uid', $ohneAnmeldung);
    }

    /**
     * Das Ereignis nennt das Browserfenster, das gespeichert hat; dieses
     * Fenster lädt dann nicht nach, die anderen schon.
     */
    public function testDasEreignisNenntDasFensterDasGespeichertHat(): void {
        $nutzlast = $this->gesendeteNutzlast('a1b2c3d4-e5f6-4711-8899-aabbccddeeff');
        self::assertSame('a1b2c3d4-e5f6-4711-8899-aabbccddeeff', $nutzlast['_fenster'] ?? null);
        self::assertSame(7, $nutzlast['id'] ?? null, 'die übrige Nutzlast fehlt');
    }

    /** Ein Header in fremder Form kommt nicht ins Ereignis. */
    public function testEineFremdeKennungKommtNichtInsEreignis(): void {
        $nutzlast = $this->gesendeteNutzlast('<script>alert(1)</script>');
        self::assertArrayNotHasKey('_fenster', $nutzlast);
    }
}
