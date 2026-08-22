<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lädt alle Budget-Backend-Klassen (erzwingt Autoload + Parsen), damit
 * Syntaxfehler früh auffallen, auch bevor Integrationstests sie ausführen.
 */
class BudgetKlassenLadenTest extends TestCase {
    /** @return array<int, array{0: string}> */
    public static function klassen(): array {
        return array_map(static fn ($k) => [$k], [
            'OCA\\ParliamentWinterthur\\Db\\BudgetJahr',
            'OCA\\ParliamentWinterthur\\Db\\BudgetJahrMapper',
            'OCA\\ParliamentWinterthur\\Db\\BudgetProduktegruppe',
            'OCA\\ParliamentWinterthur\\Db\\BudgetProduktegruppeMapper',
            'OCA\\ParliamentWinterthur\\Db\\BudgetInvestition',
            'OCA\\ParliamentWinterthur\\Db\\BudgetInvestitionMapper',
            'OCA\\ParliamentWinterthur\\Db\\BudgetAntrag',
            'OCA\\ParliamentWinterthur\\Db\\BudgetAntragMapper',
            'OCA\\ParliamentWinterthur\\Db\\BudgetVerteilung',
            'OCA\\ParliamentWinterthur\\Db\\BudgetVerteilungMapper',
            'OCA\\ParliamentWinterthur\\Db\\BudgetAntragEntscheid',
            'OCA\\ParliamentWinterthur\\Db\\BudgetAntragEntscheidMapper',
            'OCA\\ParliamentWinterthur\\Service\\BudgetRechnung',
            'OCA\\ParliamentWinterthur\\Service\\BudgetService',
            'OCA\\ParliamentWinterthur\\Service\\BudgetImportService',
            'OCA\\ParliamentWinterthur\\Service\\BudgetBuchParser',
            'OCA\\ParliamentWinterthur\\Controller\\BudgetController',
            'OCA\\ParliamentWinterthur\\Migration\\Version000038Date20260822100000',
        ]);
    }

    #[DataProvider('klassen')]
    public function testKlasseLaedt(string $klasse): void {
        $this->assertTrue(class_exists($klasse), $klasse . ' lädt nicht');
    }
}
