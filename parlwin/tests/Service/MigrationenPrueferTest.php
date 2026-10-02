<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\MigrationenPruefer;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Liest die Migrationen aus dem Code und vergleicht sie mit denen, die in einer
 * Instanz gelaufen sind.
 */
class MigrationenPrueferTest extends TestCase
{
    private function pruefer(): MigrationenPruefer
    {
        return new MigrationenPruefer($this->createStub(IDBConnection::class));
    }

    public function testDieMigrationenDesCodesWerdenGefunden(): void
    {
        $namen = $this->pruefer()->imCode(__DIR__ . '/../../lib/Migration');

        self::assertNotSame([], $namen, 'die App trägt Migrationen');
        self::assertContains('000053Date20260924090000', $namen);
        foreach ($namen as $name) {
            self::assertMatchesRegularExpression(
                '/^\d{6}Date\d{14}$/',
                $name,
                'der Name steht so auch in der Tabelle migrations',
            );
        }
    }

    public function testFehlendIstWasImCodeStehtUndNieGelaufenIst(): void
    {
        self::assertSame(
            ['000053Date20260924090000'],
            $this->pruefer()->fehlende(
                ['000052Date20260923180000', '000053Date20260924090000'],
                ['000052Date20260923180000'],
            ),
        );
    }

    public function testEineGelaufeneMigrationMehrStoertNicht(): void
    {
        self::assertSame(
            [],
            $this->pruefer()->fehlende(
                ['000052Date20260923180000'],
                ['000051Date20260901120000', '000052Date20260923180000'],
            ),
        );
    }
}
