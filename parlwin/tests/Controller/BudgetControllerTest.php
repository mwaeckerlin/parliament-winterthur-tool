<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\BudgetController;
use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Weg eines Antrags durch den Controller: Was die Oberfläche sendet, muss im
 * Dienst ankommen — die Zielvorgaben-Änderungen und die Einsparungsverteilung
 * (F109) ebenso wie Betrag und Begründung. Ohne sie verliert ein bearbeiteter
 * Antrag beim Speichern, was unter «Zielvorgaben ändern» und «Einsparung
 * verteilen» steht.
 */
class BudgetControllerTest extends TestCase {
    /** @param array<string, mixed> $params */
    private function request(array $params): IRequest {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );
        return $request;
    }

    /** @param array<string, mixed> $daten */
    private function controller(IRequest $request, BudgetService $service): BudgetController {
        return new BudgetController(
            $request,
            $service,
            $this->createStub(BudgetImportService::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(EreignisService::class),
        );
    }

    public function testAntragErstellenReichtZielvorgabenUndAufteilungDurch(): void {
        $gesendet = [];
        $service = $this->createStub(BudgetService::class);
        $service->method('antragErstellen')->willReturnCallback(
            function (int $jahr, array $daten) use (&$gesendet): BudgetAntrag {
                $gesendet = $daten;
                return new BudgetAntrag();
            }
        );

        $request = $this->request([
            'bereich' => 'globalbudget',
            'zielRef' => '121',
            'betragDelta' => -1000,
            'zielAenderungen' => [['zielNummer' => 2, 'messgroesse' => 'Anzahl Kurstage', 'neuerWert' => '1200']],
            'aufteilung' => [['ebene' => 'produkt', 'ref' => '1', 'betrag' => -1000]],
        ]);
        $this->controller($request, $service)->antragErstellen(2026);

        self::assertArrayHasKey('zielAenderungen', $gesendet, 'die Zielvorgaben-Änderungen erreichen den Dienst (F109)');
        self::assertSame('1200', $gesendet['zielAenderungen'][0]['neuerWert']);
        self::assertArrayHasKey('aufteilung', $gesendet, 'die Einsparungsverteilung erreicht den Dienst (F109)');
        self::assertSame(-1000, $gesendet['aufteilung'][0]['betrag']);
    }

    public function testAntragAendernReichtZielvorgabenUndAufteilungDurch(): void {
        $gesendet = [];
        $service = $this->createStub(BudgetService::class);
        $service->method('antragAendern')->willReturnCallback(
            function (int $id, array $daten) use (&$gesendet): BudgetAntrag {
                $gesendet = $daten;
                return new BudgetAntrag();
            }
        );

        $request = $this->request([
            'betragDelta' => -2000,
            'begruendung' => 'geändert',
            'zielAenderungen' => [['zielNummer' => 3, 'messgroesse' => 'Fälle', 'neuerWert' => '90']],
            'aufteilung' => [['ebene' => 'pg-kosten', 'ref' => 'Sachkosten', 'betrag' => -2000]],
        ]);
        $this->controller($request, $service)->antragAendern(7);

        self::assertSame('geändert', $gesendet['begruendung']);
        self::assertArrayHasKey('zielAenderungen', $gesendet, 'beim Ändern gehen die Zielvorgaben nicht verloren (F109)');
        self::assertSame('90', $gesendet['zielAenderungen'][0]['neuerWert']);
        self::assertArrayHasKey('aufteilung', $gesendet, 'beim Ändern geht die Verteilung nicht verloren (F109)');
        self::assertSame('Sachkosten', $gesendet['aufteilung'][0]['ref']);
    }

    public function testNichtGesendeteFelderBleibenAus(): void {
        // Nur was gesendet wurde, wird geändert: ein Feld ohne Wert im Aufruf darf
        // im Dienst nicht als leerer Wert ankommen und dort etwas überschreiben.
        $gesendet = ['vorbelegt' => true];
        $service = $this->createStub(BudgetService::class);
        $service->method('antragAendern')->willReturnCallback(
            function (int $id, array $daten) use (&$gesendet): BudgetAntrag {
                $gesendet = $daten;
                return new BudgetAntrag();
            }
        );

        $this->controller($this->request(['haltung' => 'einreichen']), $service)->antragAendern(7);

        self::assertSame(['haltung' => 'einreichen'], $gesendet);
    }
}
