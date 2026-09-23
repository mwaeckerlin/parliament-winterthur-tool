<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * WERKZEUG, kein Regressionstest (Gruppe «messung», in keinem Testlauf
 * enthalten, Aufruf mit `npm run test:messung`).
 */
#[Group('messung')]
class BudgetZeilenMessungTest extends TestCase {
    public function testGruppenTechnischeBetriebe(): void {
        $pfad = \dirname(__DIR__, 1) . '/Fixtures/budget/2022/teil-a.pdf';
        $parser = new BudgetBuchParser();
        $inv = (new ReflectionMethod(BudgetBuchParser::class, 'parseInvestitionen'))
            ->invoke($parser, $pfad, 2022);

        // Buch: Öffentliche Beleuchtung 1'817'670, FinöV Stadt 400'000,
        // Stadtgrün Winterthur 6'642'999, Departement 8'860'669.
        $summen = [];
        foreach ($inv as $p) {
            if ($p['departement'] !== 'Technische Betriebe') {
                continue;
            }
            $summen[$p['cluster']] = ($summen[$p['cluster']] ?? 0) + (int) $p['bu'];
        }
        foreach ($summen as $g => $s) {
            echo "\n=== $g: $s ===\n";
            foreach ($inv as $p) {
                if ($p['cluster'] === $g && $p['departement'] === 'Technische Betriebe') {
                    echo '    ' . $p['projekt'] . ' bu=' . $p['bu'] . "\n";
                }
            }
        }
        self::assertTrue(true);
    }
}
