<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Die Adresse der Datenquelle ist eine Einstellung.
 *
 * Fest im Code stand `https://parlament.winterthur.ch`. Damit fragt jeder
 * Abgleich die echte Webseite der Stadt, auch der im Testlauf: Der E2E-Stack
 * holt 1236 Geschäfte mit je einer Detailseite, auf dem GitHub-Runner war das
 * nach 34 Minuten nicht fertig, und darum läuft der Browserteil im Bau nicht
 * mit. Mit `PARLWIN_QUELLE_BASE_URL` zeigt der Abgleich auf eine lokale
 * Gegenstelle, die die gespeicherten Seiten ausliefert.
 *
 * Ohne die Einstellung bleibt es bei der echten Webseite, denn im Betrieb ist
 * sie die Quelle.
 */
class QuelleBasisUrlTest extends TestCase
{
    private const ECHT = 'https://parlament.winterthur.ch';

    /** @var array<int, string> */
    private array $aufgerufen = [];

    protected function tearDown(): void
    {
        putenv('PARLWIN_QUELLE_BASE_URL');
    }

    /**
     * Ein Dienst, der jede abgefragte Adresse in `$this->aufgerufen` legt.
     *
     * Die Listen holt er über `IClient`, die Detailseiten über den
     * Parallel-Download; beide Wege schreiben hier mit.
     */
    private function dienstMitUrlProtokoll(): ScraperServiceMitFixtures
    {
        $this->aufgerufen = [];
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $this->aufgerufen[] = $url;
            $response = $this->createStub(IResponse::class);
            $response->method('getBody')->willReturn('<html></html>');
            return $response;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);

        $dienst = new ScraperServiceMitFixtures($clientService, $this->createStub(LoggerInterface::class));
        $dienst->setzeResolver(function (string $url): string {
            $this->aufgerufen[] = $url;
            return '<html></html>';
        });
        return $dienst;
    }

    public function testOhneEinstellungFragtDerAbgleichDieEchteWebseite(): void
    {
        $this->dienstMitUrlProtokoll()->ladeSitzungen();
        self::assertSame([self::ECHT . '/sitzung'], $this->aufgerufen);
    }

    public function testMitEinstellungFragtDerAbgleichDieLokaleGegenstelle(): void
    {
        putenv('PARLWIN_QUELLE_BASE_URL=http://parlwin-quelle:8080');
        $this->dienstMitUrlProtokoll()->ladeSitzungen();
        self::assertSame(['http://parlwin-quelle:8080/sitzung'], $this->aufgerufen);
    }

    public function testJedeListeFolgtDerEinstellung(): void
    {
        putenv('PARLWIN_QUELLE_BASE_URL=http://parlwin-quelle:8080');
        $dienst = $this->dienstMitUrlProtokoll();
        $dienst->ladeSitzungen();
        $dienst->ladeMitglieder();
        $dienst->ladeKommissionen();
        $dienst->ladeFraktionen();
        self::assertNotEmpty($this->aufgerufen);
        foreach ($this->aufgerufen as $url) {
            self::assertStringStartsWith('http://parlwin-quelle:8080/', $url, 'Jede Liste muss der Einstellung folgen');
        }
    }

    public function testDasBudgetHoltSeineDateienBeiDerselbenAdresse(): void
    {
        putenv('PARLWIN_QUELLE_BASE_URL=http://parlwin-quelle:8080');
        self::assertSame(
            'http://parlwin-quelle:8080/datei/drehbuch.pdf',
            BudgetImportService::absoluteQuellUrl('/datei/drehbuch.pdf'),
        );
    }

    public function testOhneEinstellungHoltDasBudgetBeiDerEchtenWebseite(): void
    {
        self::assertSame(
            self::ECHT . '/datei/drehbuch.pdf',
            BudgetImportService::absoluteQuellUrl('/datei/drehbuch.pdf'),
        );
    }
}
