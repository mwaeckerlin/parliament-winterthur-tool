<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use PHPUnit\Framework\TestCase;

class FraktionsstatusTest extends TestCase {
    public function testOhneBeschlussIstStatusOffen(): void {
        $status = FraktionsarbeitService::ableiteFraktionsstatus('', '2026-05-12 10:00:00');

        $this->assertSame('offen', $status['fraktionsstatus']);
        $this->assertTrue($status['entscheidungsbedarf']);
        $this->assertSame('kein_beschluss', $status['entscheidungsgrund']);
    }

    public function testNeuZuEntscheidenWennQuelleNachBeschlussAktualisiertWurde(): void {
        $status = FraktionsarbeitService::ableiteFraktionsstatus('2026-05-11 09:00:00', '2026-05-12 08:00:00');

        $this->assertSame('neu_zu_entscheiden', $status['fraktionsstatus']);
        $this->assertTrue($status['entscheidungsbedarf']);
        $this->assertSame('quelle_aktualisiert', $status['entscheidungsgrund']);
    }

    public function testEntschiedenWennKeinNeuerQuellenstandVorliegt(): void {
        $status = FraktionsarbeitService::ableiteFraktionsstatus('2026-05-12 09:00:00', '2026-05-12 08:00:00');

        $this->assertSame('entschieden', $status['fraktionsstatus']);
        $this->assertFalse($status['entscheidungsbedarf']);
        $this->assertSame('kein_neuer_entscheid', $status['entscheidungsgrund']);
    }
}
