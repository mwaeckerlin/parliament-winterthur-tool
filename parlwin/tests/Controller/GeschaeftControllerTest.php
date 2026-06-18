<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\GeschaeftController;
use OCA\ParliamentWinterthur\Db\Geschaeft;
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

    /**
     * Regression: Der status-Parameter muss die Liste serverseitig auf genau
     * die passenden Geschäfte einschränken — sonst zeigt ein gefilterter View
     * fälschlicherweise alle oder gar keine Geschäfte an.
     */
    public function testIndexFiltertNachStatus(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'show_erledigt' => '1',
                    'status' => 'Pendent',
                    default => $default,
                };
            });

        $pendent = new Geschaeft();
        $pendent->setStatus('Pendent');
        $pendent->setNummer('2024.1');
        $erledigt = new Geschaeft();
        $erledigt->setStatus('Erledigt');
        $erledigt->setNummer('2024.2');

        $service = $this->createStub(GeschaeftService::class);
        $service->method('alle')->willReturn([$pendent, $erledigt]);

        // angereicherteGeschaefte echot die (gefilterte) Eingabe zurück, damit
        // wir prüfen können, welche Geschäfte der Controller durchgelassen hat.
        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereicherteGeschaefte')
            ->willReturnCallback(static fn(array $g): array => $g);

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

        $daten = $controller->index()->getData();
        $this->assertCount(1, $daten);
        $this->assertSame('Pendent', $daten[0]->getStatus());
        $this->assertSame('2024.1', $daten[0]->getNummer());
    }

    /**
     * Regression: Selbst angelegte Geschäfte müssen eine explizite ID erhalten,
     * weil die Tabelle nicht überall AUTO_INCREMENT ist (sonst SQL-Fehler
     * «Field 'id' doesn't have a default value» und HTTP 500 beim Anlegen).
     */
    public function testCreateVergibtExpliziteId(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'titel' => 'Mein eigenes Geschäft',
                    default => $default,
                };
            });

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('naechsteId')->willReturn(42);
        $gespeichert = null;
        $mapper->method('insert')->willReturnCallback(static function (Geschaeft $g) use (&$gespeichert): Geschaeft {
            $gespeichert = $g;
            return $g;
        });

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereichertesGeschaeft')
            ->willReturnCallback(static fn(int $id): array => ['id' => $id, 'status' => 'Pendent']);

        $controller = new GeschaeftController(
            $request,
            $this->createStub(GeschaeftService::class),
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $mapper,
        );

        $response = $controller->create();
        $this->assertSame(201, $response->getStatus());
        $this->assertNotNull($gespeichert);
        $this->assertSame(42, $gespeichert->getId());
        $this->assertSame('Pendent', $response->getData()['status']);
    }
}
