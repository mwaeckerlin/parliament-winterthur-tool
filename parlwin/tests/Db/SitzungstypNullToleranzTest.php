<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\Sitzungstyp;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Die Spalte pw_sitzungstypen.kommissionen wurde per Migration
 * nullable ergänzt. Enthält eine Zeile NULL (oder wird beim Anlegen nicht
 * gesetzt), darf das Laden/Serialisieren NICHT mit einem TypeError scheitern
 * (500 «Cannot assign null to property Sitzungstyp::$kommissionen»).
 */
class SitzungstypNullToleranzTest extends TestCase
{
    public function testKommissionenNullAusDerDatenbankWirftKeinenFehler(): void
    {
        $typ = new Sitzungstyp();
        // Simuliert eine NULL-Spalte aus der Datenbank (QBMapper-Mapping).
        $typ->setKommissionen(null);

        $json = $typ->jsonSerialize();

        self::assertSame([], $json['kommissionen'], 'NULL-Kommissionen ergeben eine leere Liste');
    }

    public function testGueltigeKommissionenBleibenErhalten(): void
    {
        $typ = new Sitzungstyp();
        $typ->setKommissionen(json_encode([3, 7]));

        self::assertSame([3, 7], $typ->jsonSerialize()['kommissionen']);
    }
}
