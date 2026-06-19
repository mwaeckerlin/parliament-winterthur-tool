<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Migration;

use OCA\ParliamentWinterthur\Migration\Version000016Date20260615150000;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Regression (Nextcloud 34): Die Migration darf das Tabellen-Prefix nicht über
 * das in Nextcloud 34 entfernte IDBConnection::getTablePrefix() holen. Tat sie
 * es doch, starb «occ app:enable parlwin» mit «Call to undefined method
 * OC\DB\ConnectionAdapter::getTablePrefix()» und die App liess sich nach einem
 * Nextcloud-Upgrade nicht mehr aktivieren. Das Prefix muss aus der
 * System-Konfiguration (dbtableprefix) kommen.
 */
class Version000016Test extends TestCase
{
    public function testPostSchemaChangeNutztKonfiguriertesPrefixOhneGetTablePrefix(): void
    {
        $config = $this->createStub(IConfig::class);
        $config->method('getSystemValue')->willReturn('nc_');

        $ausgefuehrtesSql = null;
        $connection = $this->createMock(IDBConnection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$ausgefuehrtesSql): int {
                $ausgefuehrtesSql = $sql;
                return 0;
            });

        $output = $this->createStub(IOutput::class);

        $migration = new Version000016Date20260615150000($connection, $config);
        // Darf NICHT mit «Call to undefined method getTablePrefix()» sterben.
        $migration->postSchemaChange($output, static fn() => null, []);

        $this->assertNotNull($ausgefuehrtesSql, 'Migration muss ein ALTER TABLE ausführen');
        $this->assertStringContainsString(
            'nc_pw_geschaefte',
            (string) $ausgefuehrtesSql,
            'Tabellenname muss das konfigurierte DB-Prefix (dbtableprefix) verwenden',
        );
        $this->assertStringContainsStringIgnoringCase(
            'AUTO_INCREMENT',
            (string) $ausgefuehrtesSql,
        );
    }
}
