<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\SitzungController;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class SitzungControllerTest extends TestCase
{
    private function makeRequest(array $params): IRequest
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );
        return $request;
    }

    private function makeSitzung(int $id = 1, string $titel = 'Testsitzung', int $typId = 1): Sitzung
    {
        $s = new Sitzung();
        $s->setId($id);
        $s->setTitel($titel);
        $s->setDatum('2026-06-01');
        $s->setTypId($typId);
        $s->setExternId('');
        $s->setNotizen('[]');
        $s->setTeilnehmer('[]');
        $s->setBemerkungen('');
        $s->setErstelltAm('2026-06-01 10:00:00');
        $s->setAktualisiertAm('2026-06-01 10:00:00');
        return $s;
    }

    private function makeController(
        IRequest $request,
        ?SitzungstypService $sitzungstypService = null,
    ): SitzungController {
        return new SitzungController(
            $request,
            $this->createStub(SitzungService::class),
            $sitzungstypService ?? $this->createStub(SitzungstypService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IUserSession::class),
        );
    }

    public function testCreateGibtSitzungZurueckMitStatus201(): void
    {
        $sitzung = $this->makeSitzung();

        $sitzungstypService = $this->createMock(SitzungstypService::class);
        $sitzungstypService->expects($this->once())
            ->method('erstelleAusTyp')
            ->with($this->callback(fn(array $d): bool =>
                $d['typId'] === 2 && $d['datum'] === '2026-06-15'
            ))
            ->willReturn($sitzung);

        $request = $this->makeRequest([
            'typId' => 2,
            'datum' => '2026-06-15',
            'titel' => 'Fraktionssitzung',
            'ort'   => 'Rathaus',
        ]);

        $response = $this->makeController($request, $sitzungstypService)->create();

        $this->assertSame(Http::STATUS_CREATED, $response->getStatus());
        $this->assertSame('Testsitzung', $response->getData()['titel']);
    }

    public function testCreateGibt400WennTypIdFehlt(): void
    {
        $request = $this->makeRequest(['datum' => '2026-06-15']);

        $response = $this->makeController($request)->create();

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testCreateGibt400WennDatumFehlt(): void
    {
        $request = $this->makeRequest(['typId' => 1]);

        $response = $this->makeController($request)->create();

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    public function testCreateGibt404WennTypNichtGefunden(): void
    {
        $sitzungstypService = $this->createStub(SitzungstypService::class);
        $sitzungstypService->method('erstelleAusTyp')
            ->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('not found'));

        $request = $this->makeRequest(['typId' => 99, 'datum' => '2026-06-15']);

        $response = $this->makeController($request, $sitzungstypService)->create();

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }
}
