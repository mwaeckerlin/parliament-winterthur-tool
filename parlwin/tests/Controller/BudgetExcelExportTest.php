<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\BudgetController;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\DokumentAblageService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Excel-Export der Budgetanträge (F138): Dieselbe Auswahl wie beim PDF,
 * und zwei Wege — in die Dateien der Fraktion (POST, es wird etwas
 * geschrieben) oder zum Herunterladen (GET, es bleibt nichts zurück). Die
 * Datei geht in den Ordner des Budgetjahrs, wo auch die Dokumente der Anträge
 * liegen, und ein zweiter Export überschreibt sie, statt neben ihr eine zweite
 * Datei stehen zu lassen.
 */
final class BudgetExcelExportTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $geschrieben = [];

    /** @var array<string, mixed> */
    private array $gefragt = [];

    /** @param array<string, mixed> $params */
    private function controller(array $params): BudgetController
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );

        $service = $this->createStub(BudgetService::class);
        $service->method('eigeneFraktion')->willReturn('Unsere Fraktion');

        $import = $this->createStub(BudgetImportService::class);
        $import->method('antraegePdf')->willReturnCallback(
            function (int $jahr, ?string $kommission, bool $mitFremden, string $fraktion): array {
                $this->gefragt = ['jahr' => $jahr, 'kommission' => $kommission, 'mitFremden' => $mitFremden, 'fraktion' => $fraktion];
                return [
                    'jahr' => $jahr,
                    'kommission' => $kommission,
                    'fraktion' => $fraktion,
                    'eintraege' => [[
                        'departement' => 'Finanzen',
                        'produktegruppe' => 'Immobilien',
                        'buchseite' => 240,
                        'antrag' => [
                            'bereich' => 'globalbudget',
                            'betragDelta' => -250000,
                            'stellenDelta' => 0,
                            'prozentDelta' => 0,
                            'neuerWert' => '',
                            'antragsteller' => $fraktion,
                            'haltung' => 'einreichen',
                            'begruendung' => 'Weil es nötig ist.',
                            'zielAenderungen' => [],
                            'aufteilung' => [],
                        ],
                    ]],
                ];
            }
        );

        $ablage = $this->createStub(DokumentAblageService::class);
        $ablage->method('schreiben')->willReturnCallback(
            function (string $ordner, string $name, string $inhalt): array {
                $this->geschrieben = ['ordner' => $ordner, 'name' => $name, 'groesse' => strlen($inhalt), 'inhalt' => $inhalt];
                return ['name' => $name, 'pfad' => $ordner . '/' . $name, 'id' => 4711];
            }
        );

        return new BudgetController(
            $request,
            $service,
            $import,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(EreignisService::class),
            $ablage,
        );
    }

    public function testAblegenSchreibtDieDateiInDenOrdnerDesBudgetjahrs(): void
    {
        $antwort = $this->controller([])->antraegeExcelAblegen(2027);

        self::assertInstanceOf(DataResponse::class, $antwort);
        self::assertSame('Fraktion/50_Budget/2027', $this->geschrieben['ordner'], 'die Datei liegt nicht beim Budgetjahr');
        self::assertSame('Budgetantraege-2027.xlsx', $this->geschrieben['name']);
        self::assertSame('Fraktion/50_Budget/2027/Budgetantraege-2027.xlsx', $antwort->getData()['pfad']);
        // Was geschrieben wurde, ist eine Tabellenkalkulationsdatei: ein ZIP,
        // das mit «PK» beginnt und das Arbeitsblatt trägt.
        self::assertStringStartsWith('PK', (string) $this->geschrieben['inhalt']);
        self::assertStringContainsString('xl/worksheets/sheet1.xml', (string) $this->geschrieben['inhalt']);
    }

    public function testDerAbrufGibtDieDateiZumHerunterladen(): void
    {
        $antwort = $this->controller([])->antraegeExcel(2027);

        self::assertInstanceOf(DataDownloadResponse::class, $antwort);
        self::assertSame('Budgetantraege-2027.xlsx', $antwort->getFilename());
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $antwort->getContentType(),
            'ohne den richtigen Typ öffnet der Browser die Datei nicht als Tabelle',
        );
        self::assertStringStartsWith('PK', $antwort->getData());
        self::assertSame([], $this->geschrieben, 'der Download legt nichts in den Dateien ab');
    }

    /**
     * Der Name der Datei kommt aus der gewählten Kommission, und die wählt der
     * Aufrufer. Er darf den Ordner nicht verlassen: Was keine Buchstabe und
     * keine Ziffer ist, fällt weg.
     */
    public function testEineKommissionMitWegangabeVerlaesstDenOrdnerNicht(): void
    {
        $antwort = $this->controller(['kommission' => '../../../etc'])->antraegeExcelAblegen(2027);

        self::assertInstanceOf(DataResponse::class, $antwort);
        self::assertSame('Fraktion/50_Budget/2027', $this->geschrieben['ordner']);
        self::assertSame('Budgetantraege-2027-etc.xlsx', $this->geschrieben['name']);
        self::assertStringNotContainsString('..', (string) $this->geschrieben['name']);
    }

    public function testKommissionUndFremdeAntraegeWirkenWieBeimPdf(): void
    {
        $antwort = $this->controller([
            'kommission' => 'Sachkommission Bau und Betriebe',
            'mitFremden' => '1',
        ])->antraegeExcelAblegen(2027);

        self::assertInstanceOf(DataResponse::class, $antwort);
        self::assertSame('Sachkommission Bau und Betriebe', $this->gefragt['kommission']);
        self::assertTrue($this->gefragt['mitFremden'], 'die unterstützten fremden Anträge fehlen im Export');
        self::assertSame(
            'Budgetantraege-2027-Sachkommission-Bau-und-Betriebe.xlsx',
            $this->geschrieben['name'],
            'der Dateiname nennt die gewählte Kommission nicht',
        );
    }
}
