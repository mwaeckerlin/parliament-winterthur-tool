<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * Automatischer Budget-Import (F89): liest über den geplanten Job das neueste
 * verfügbare Budgetjahr ein, sobald dessen Weisung vorliegt. Nutzt die echte
 * ausgelieferte reference/budget/<jahr>/budget.json (JSON-Pfad, kein PDF-Parser),
 * darum ohne vendor/smalot lauffähig — Teil des schnellen test:unit-Laufs.
 */
class BudgetImportServiceTest extends TestCase {
    private function service(BudgetJahrMapper $jahre): BudgetImportService {
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('insert')->willReturnArgument(0);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('insert')->willReturnArgument(0);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $parser = $this->createStub(BudgetBuchParser::class);
        // Der Test-Bootstrap definiert ITimeFactory als leeres Interface; eine
        // lokale Implementierung liefert das vom Import benötigte getTime().
        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        return new BudgetImportService($jahre, $gruppen, $investitionen, $antraege, $parser, $time);
    }

    public function testImportiertNeuestesJahrAutomatischWennNochNichtVorhanden(): void {
        $jahre = $this->createMock(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturnCallback(static fn (int $j): bool => false);
        // schreibeStruktur legt das Jahr an (findByJahr wirft zuerst).
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $jahre->expects(self::atLeastOnce())->method('update');

        $ergebnis = $this->service($jahre)->automatischerImport();

        self::assertNotNull($ergebnis);
        self::assertSame(2026, $ergebnis['jahr'], 'neuestes verfügbares Jahr');
        self::assertTrue($ergebnis['importiert'], 'wird automatisch eingelesen');
        self::assertFalse($ergebnis['novemberbrief'], 'ohne Novemberbrief-Quelle nichts nachgezogen');
    }

    public function testImportiertNichtsWennJahrBereitsVorhanden(): void {
        $jahre = $this->createMock(BudgetJahrMapper::class);
        $jahre->method('existiert')->willReturn(true);
        $jahre->expects(self::never())->method('insert');

        $ergebnis = $this->service($jahre)->automatischerImport();

        self::assertNotNull($ergebnis);
        self::assertSame(2026, $ergebnis['jahr']);
        self::assertFalse($ergebnis['importiert'], 'bereits vorhandenes Jahr wird nicht erneut importiert');
    }
}
