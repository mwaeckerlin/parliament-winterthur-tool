<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\BackgroundJob;

use OCA\ParliamentWinterthur\BackgroundJob\BudgetNeuEinlesenJob;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Der Auftrag, der nach einer Verbesserung des Einlesers neu einliest (F130),
 * nimmt EIN Jahr je Lauf.
 *
 * Ein Jahr zu lesen lädt beide Bücher und liest sie; das dauert eine knappe
 * Minute und belegt so lange den Cron von Nextcloud, der seine Aufträge
 * nacheinander abarbeitet. Mit allen Jahren in einem Lauf stand der Cron
 * minutenlang still, und die Synchronisation kam im Browsertest innerhalb von
 * zehn Minuten nicht an die Reihe (gemessen am 25.10.2026).
 */
final class BudgetNeuEinlesenJobTest extends TestCase
{
    /** @param list<int> $jahre */
    private function job(array $jahre, array &$gelesen): BudgetNeuEinlesenJob
    {
        $import = $this->createStub(BudgetImportService::class);
        $import->method('jahreMitAltemEinleser')->willReturn($jahre);
        $import->method('erneuereEinlesen')->willReturnCallback(
            function (int $jahr) use (&$gelesen): void {
                $gelesen[] = $jahr;
            }
        );
        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        return new BudgetNeuEinlesenJob(
            $time,
            $import,
            $this->createStub(BudgetService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function laufen(BudgetNeuEinlesenJob $job): void
    {
        (new ReflectionMethod(BudgetNeuEinlesenJob::class, 'run'))->invoke($job, null);
    }

    public function testLiestEinJahrJeLauf(): void
    {
        $gelesen = [];
        $this->laufen($this->job([2025, 2026, 2027], $gelesen));

        self::assertSame([2025], $gelesen, 'der Auftrag liest mehr als ein Jahr in einem Lauf');
    }

    public function testOhneAltesJahrPassiertNichts(): void
    {
        $gelesen = [];
        $this->laufen($this->job([], $gelesen));

        self::assertSame([], $gelesen);
    }
}
