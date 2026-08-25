<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\TraktandumController;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TraktandumControllerTest extends TestCase
{
    private function makeRequest(array $params): IRequest
    {
        $request = $this->createStub(IRequest::class);
        $request->method('offsetExists')->willReturnCallback(
            static fn(string $key): bool => array_key_exists($key, $params)
        );
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );
        return $request;
    }

    private function makeUserSession(string $uid = 'admin'): IUserSession
    {
        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $user->method('getDisplayName')->willReturn($uid);
        $session = $this->createStub(IUserSession::class);
        $session->method('getUser')->willReturn($user);
        return $session;
    }

    private function makeTraktandum(string $bemerkungen = '', string $notizen = '[]'): Traktandum
    {
        $t = new Traktandum();
        $t->setBemerkungen($bemerkungen);
        $t->setNotizen($notizen);
        return $t;
    }

    private function makeController(IRequest $request, SitzungService $service, ?IUserSession $session = null, ?NotizService $notiz = null): TraktandumController
    {
        return new TraktandumController(
            $request,
            $service,
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $session ?? $this->createStub(IUserSession::class),
            $notiz ?? $this->createStub(NotizService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testTraktandumNotizDelegiertMitTraktandumTyp(): void
    {
        // Traktandum-Notizen laufen über den geteilten NotizService mit dem
        // Objekttyp «traktandum» — dieselbe Mechanik wie bei Geschäft/Vorstoss/Budget.
        $notiz = $this->createStub(NotizService::class);
        $erfasst = [];
        $notiz->method('hinzufuegen')->willReturnCallback(function ($typ, $id, $text) use (&$erfasst) {
            $erfasst = [$typ, $id, $text];
            return ['id' => 1];
        });
        $request = $this->makeRequest(['text' => 'Meine Notiz']);
        $this->makeController($request, $this->createStub(SitzungService::class), null, $notiz)->addNotiz(5, 42);
        $this->assertSame(['traktandum', 42, 'Meine Notiz'], $erfasst);
    }

    public function testUpdateSpeichertBemerkungen(): void
    {
        $request = $this->makeRequest(['bemerkungen' => 'Testbemerkung']);

        $service = $this->createMock(SitzungService::class);
        $service->expects($this->once())
            ->method('aktualisiereInternesTraktandum')
            ->with(42, ['bemerkungen' => 'Testbemerkung'])
            ->willReturn($this->makeTraktandum('Testbemerkung'));

        $response = $this->makeController($request, $service)->update(1, 42);

        $this->assertSame('Testbemerkung', $response->getData()['bemerkungen']);
    }

    public function testUpdateSpeichertNotizenOhneBemerkungen(): void
    {
        $notizen = json_encode([['text' => 'Test', 'datum' => '24.05.2026 10:00', 'uid' => 'admin', 'displayName' => 'admin']]);
        $request = $this->makeRequest(['notizen' => $notizen]);

        $service = $this->createMock(SitzungService::class);
        $service->expects($this->once())
            ->method('aktualisiereInternesTraktandum')
            ->with(7, $this->isArray())
            ->willReturn($this->makeTraktandum('', $notizen));

        $response = $this->makeController($request, $service, $this->makeUserSession())->update(1, 7);

        $this->assertSame('', $response->getData()['bemerkungen']);
    }

    public function testUpdateSpeichertBeidesGleichzeitig(): void
    {
        $notizen = json_encode([['text' => 'Notiz', 'datum' => '24.05.2026 10:00', 'uid' => 'admin', 'displayName' => 'admin']]);
        $request = $this->makeRequest(['bemerkungen' => 'Meine Bemerkung', 'notizen' => $notizen]);

        $capturedFelder = null;
        $service = $this->createMock(SitzungService::class);
        $service->expects($this->once())
            ->method('aktualisiereInternesTraktandum')
            ->willReturnCallback(function (int $id, array $felder) use (&$capturedFelder, $notizen): Traktandum {
                $capturedFelder = $felder;
                return $this->makeTraktandum('Meine Bemerkung', $notizen);
            });

        $this->makeController($request, $service, $this->makeUserSession())->update(1, 3);

        $this->assertArrayHasKey('bemerkungen', $capturedFelder);
        $this->assertSame('Meine Bemerkung', $capturedFelder['bemerkungen']);
        $this->assertArrayHasKey('notizen', $capturedFelder);
    }
}
