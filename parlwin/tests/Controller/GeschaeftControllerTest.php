<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\GeschaeftController;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeschaeftControllerTest extends TestCase
{
    public function testIndexBlendetErledigteStandardmaessigAus(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return $default;
            });

        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->once())
            ->method('alle')
            ->with(50, 5, false)
            ->willReturn([]);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->expects($this->once())
            ->method('angereicherteGeschaefte')
            ->with([], '', null)
            ->willReturn([]);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->index(50, 5);
        $this->assertSame([], $response->getData());
    }

    public function testIndexZeigtErledigteBeiShowErledigtFlag(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'show_erledigt' => '1',
                    default => $default,
                };
            });

        $geschaefte = [['id' => 1]];
        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->once())
            ->method('alle')
            ->with(100, 0, true)
            ->willReturn($geschaefte);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->expects($this->once())
            ->method('angereicherteGeschaefte')
            ->with($geschaefte, '', null)
            ->willReturn($geschaefte);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->index();
        $this->assertSame($geschaefte, $response->getData());
    }
}
