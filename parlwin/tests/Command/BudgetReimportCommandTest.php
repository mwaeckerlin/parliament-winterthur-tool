<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\BudgetReimportCommand;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetVerteilungMapper;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use PHPUnit\Framework\TestCase;

/**
 * occ-Befehl `parlwin:budget-reimport <jahr>`: liest ein Budgetjahr sauber neu aus
 * dem Budgetbuch ein; «--purge» löscht zusätzlich Anträge und Pauschalanträge.
 */
class BudgetReimportCommandTest extends TestCase {
    public function testReimportRuftImportiereJahrOhneZuLoeschen(): void {
        $import = $this->createMock(BudgetImportService::class);
        $import->expects(self::once())->method('importiereJahr')->with(2026);
        $antraege = $this->createMock(BudgetAntragMapper::class);
        $antraege->expects(self::never())->method('deleteByJahr');
        $verteilungen = $this->createMock(BudgetVerteilungMapper::class);
        $verteilungen->expects(self::never())->method('deleteByJahr');

        $cmd = new BudgetReimportCommand($import, $antraege, $verteilungen, $this->createStub(EreignisService::class));
        self::assertSame(0, $cmd->reimportiere(2026, false));
    }

    public function testPurgeLoeschtAntraegeUndVerteilungen(): void {
        $import = $this->createStub(BudgetImportService::class);
        $antraege = $this->createMock(BudgetAntragMapper::class);
        $antraege->expects(self::once())->method('deleteByJahr')->with(2026);
        $verteilungen = $this->createMock(BudgetVerteilungMapper::class);
        $verteilungen->expects(self::once())->method('deleteByJahr')->with(2026);

        $cmd = new BudgetReimportCommand($import, $antraege, $verteilungen, $this->createStub(EreignisService::class));
        self::assertSame(0, $cmd->reimportiere(2026, true));
    }

    public function testUngueltigesJahrScheitertOhneImport(): void {
        $import = $this->createMock(BudgetImportService::class);
        $import->expects(self::never())->method('importiereJahr');
        $cmd = new BudgetReimportCommand(
            $import,
            $this->createStub(BudgetAntragMapper::class),
            $this->createStub(BudgetVerteilungMapper::class),
            $this->createStub(EreignisService::class),
        );
        self::assertSame(1, $cmd->reimportiere(99, false));
    }

    public function testFehlendesBudgetbuchScheitert(): void {
        $import = $this->createStub(BudgetImportService::class);
        $import->method('importiereJahr')->willThrowException(new \RuntimeException('kein teil-b.pdf'));
        $cmd = new BudgetReimportCommand(
            $import,
            $this->createStub(BudgetAntragMapper::class),
            $this->createStub(BudgetVerteilungMapper::class),
            $this->createStub(EreignisService::class),
        );
        self::assertSame(1, $cmd->reimportiere(2026, false));
    }
}
