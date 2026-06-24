<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\VorstossController;
use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\VorstossService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class VorstossControllerTest extends TestCase
{
    private function makeRequest(array $params): IRequest
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );
        return $request;
    }

    private function controller(IRequest $request, ?VorstossService $service = null): VorstossController
    {
        return new VorstossController(
            $request,
            $service ?? $this->createStub(VorstossService::class),
            $this->createStub(RealtimePublisherService::class),
        );
    }

    public function testCreateGibt400OhneTitel(): void
    {
        $response = $this->controller($this->makeRequest([]))->create();

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testCreateGibt201MitVorstoss(): void
    {
        $vorstoss = new Vorstoss();
        $vorstoss->setId(7);
        $vorstoss->setTitel('Mehr Velowege');

        $service = $this->createStub(VorstossService::class);
        $service->method('erstelle')->willReturn($vorstoss);

        $request = $this->makeRequest(['titel' => 'Mehr Velowege', 'herkunft' => 'eigene']);
        $response = $this->controller($request, $service)->create();

        $this->assertSame(Http::STATUS_CREATED, $response->getStatus());
        $this->assertSame('Mehr Velowege', $response->getData()->getTitel());
    }

    public function testUpdateGibt404WennNichtGefunden(): void
    {
        $service = $this->createStub(VorstossService::class);
        $service->method('aktualisiere')->willThrowException(new DoesNotExistException('weg'));

        $request = $this->makeRequest(['titel' => 'X']);
        $response = $this->controller($request, $service)->update(99);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }
}
