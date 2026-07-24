<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use PHPUnit\Framework\TestCase;

/**
 * Feature: Die Priorität muss in der API-Ausgabe (jsonSerialize) erscheinen,
 * sonst erreicht sie das Frontend nie. Standard ist Leerstring («nicht
 * gesetzt», gilt im Frontend als «mittel»).
 */
class GeschaeftPrioritaetTest extends TestCase
{
    public function testJsonSerializeEnthaeltGesetztePrioritaet(): void
    {
        $g = new Geschaeft();
        $g->setPrioritaet('hoch');
        self::assertSame('hoch', $g->jsonSerialize()['prioritaet']);
    }

    public function testPrioritaetDefaultIstLeer(): void
    {
        $g = new Geschaeft();
        self::assertArrayHasKey('prioritaet', $g->jsonSerialize());
        self::assertSame('', $g->jsonSerialize()['prioritaet']);
    }
}
