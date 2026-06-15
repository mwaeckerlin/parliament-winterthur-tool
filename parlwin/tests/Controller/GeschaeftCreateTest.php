<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\GeschaeftController;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeschaeftCreateTest extends TestCase
{
    public function testCreateSetQuelleAktualisiertAm(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnMap([
            ['titel', '', 'Test Geschäft'],
            ['typ', 'Eigenes Geschäft', 'Eigenes Geschäft'],
            ['status', 'Pendent', 'Pendent'],
        ]);

        $geschaeft = new Geschaeft();
        $geschaeft->setId(1);

        $mapper = $this->createMock(GeschaeftMapper::class);
        $mapper->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function($g) use ($geschaeft) {
                // Prüfe dass alle erforderlichen Felder gesetzt sind
                $this->assertNotEmpty($g->getTitel());
                $this->assertNotEmpty($g->getErstelltAm());
                $this->assertNotEmpty($g->getAktualisiertAm());
                $this->assertNotEmpty($g->getQuelleAktualisiertAm());
                return $geschaeft;
            });

        $fraktionsarbeiter = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeiter->expects($this->once())
            ->method('angereichertesGeschaeft')
            ->willReturn(['id' => 1, 'titel' => 'Test Geschäft']);

        $controller = new GeschaeftController(
            $request,
            $this->createMock(GeschaeftService::class),
            $fraktionsarbeiter,
            $this->createMock(RealtimePublisherService::class),
            $this->createMock(IRootFolder::class),
            $this->createMock(IUserSession::class),
            $this->createMock(LoggerInterface::class),
            $mapper,
        );

        $result = $controller->create();

        $this->assertEquals(201, $result->getStatus());
    }

    public function testCreateGeneratesUniqueExternId(): void
    {
        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnMap([
            ['titel', '', 'Test Geschäft'],
            ['typ', 'Eigenes Geschäft', 'Eigenes Geschäft'],
            ['status', 'Pendent', 'Pendent'],
        ]);

        $externIdCapture = null;
        $mapper = $this->createMock(GeschaeftMapper::class);
        $mapper->method('insert')
            ->willReturnCallback(function($g) use (&$externIdCapture) {
                $externIdCapture = $g->getExternId();
                $g->setId(1);
                return $g;
            });

        $fraktionsarbeiter = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeiter->method('angereichertesGeschaeft')->willReturn(['id' => 1]);

        $controller = new GeschaeftController(
            $request,
            $this->createMock(GeschaeftService::class),
            $fraktionsarbeiter,
            $this->createMock(RealtimePublisherService::class),
            $this->createMock(IRootFolder::class),
            $this->createMock(IUserSession::class),
            $this->createMock(LoggerInterface::class),
            $mapper,
        );

        $controller->create();

        $this->assertNotNull($externIdCapture);
        $this->assertStringStartsWith('eigen:', $externIdCapture);
    }
}
