<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * WERKZEUG, kein Regressionstest (Gruppe «messung», in keinem Testlauf
 * enthalten, Aufruf mit `npm run test:messung`).
 *
 * Misst, wie lange das Einlesen eines Budgetbuchs dauert. Der Hintergrundauftrag
 * liest das neueste Budgetjahr ein, BEVOR er die Synchronisation startet — das
 * Wartefenster des E2E-Tests auf den Cron-Tick muss diese Dauer abdecken.
 */
#[Group('messung')]
class BudgetImportDauerMessungTest extends TestCase {
    public function testDauerDesBuchs2027(): void {
        $basis = \dirname(__DIR__, 1) . '/Fixtures/budget/2027';
        $parser = new BudgetBuchParser();

        $start = microtime(true);
        $struktur = $parser->struktur($basis . '/teil-b.pdf', $basis . '/teil-a.pdf', 2027);
        $dauer = microtime(true) - $start;

        echo "\n=== Budget 2027: " . number_format($dauer, 1) . ' s, '
            . count($struktur['produktegruppen'] ?? []) . ' Produktegruppen, '
            . number_format(memory_get_peak_usage(true) / 1048576, 1) . " MB Spitze ===\n";
        self::assertNotSame([], $struktur['produktegruppen'] ?? []);
    }
}
