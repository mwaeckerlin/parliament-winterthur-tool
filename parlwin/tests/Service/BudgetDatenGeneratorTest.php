<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Erzeugt die ausgelieferten Referenzdaten (parlwin/reference/budget/<jahr>/
 * budget.json) aus den committeten Büchern (tests/fixtures/budget). Kein Teil
 * der Regression: die Gruppe «generate» läuft nur explizit über
 * `npm run test:budget-data`, wenn neue Bücher eingespielt wurden. Braucht
 * vendor/ (smalot). Die Korrektheit des Parsers prüft BudgetBuchParserTest.
 */
#[Group('generate')]
class BudgetDatenGeneratorTest extends TestCase {
    public function testSchreibtReferenzDaten(): void {
        $parser = new BudgetBuchParser();
        $fixtures = \dirname(__DIR__, 3) . '/tests/fixtures/budget';
        $ausgeliefert = \dirname(__DIR__, 2) . '/reference/budget';
        $anzahl = 0;
        foreach ([2022, 2023, 2024, 2025, 2026] as $jahr) {
            $teilB = "$fixtures/$jahr/teil-b.pdf";
            if (!is_file($teilB)) {
                continue;
            }
            $teilA = "$fixtures/$jahr/teil-a.pdf";
            $struktur = $parser->struktur($teilB, is_file($teilA) ? $teilA : null, $jahr);
            $ziel = "$ausgeliefert/$jahr";
            if (!is_dir($ziel)) {
                mkdir($ziel, 0o755, true);
            }
            file_put_contents(
                "$ziel/budget.json",
                json_encode($struktur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );
            $anzahl++;
        }
        $this->assertGreaterThan(0, $anzahl, 'Keine Bücher gefunden — nichts erzeugt');
    }
}
