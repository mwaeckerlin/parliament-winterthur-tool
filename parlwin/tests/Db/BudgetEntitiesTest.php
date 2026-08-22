<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetVerteilung;
use PHPUnit\Framework\TestCase;

/**
 * Lädt die Budget-Entities und prüft ihre JSON-Struktur (fängt Parse-/
 * Struktur-Fehler früh ab, unabhängig von der Datenbank).
 */
class BudgetEntitiesTest extends TestCase {
    public function testProduktegruppeSerialisiertVerschachtelt(): void {
        $g = new BudgetProduktegruppe();
        $g->setCode('121');
        $g->setName('Personalamt');
        $g->setDepartement('Präsidiales');
        $g->setGlobalkreditSoll(4866124);
        $g->setAufwandSoll(7428985);
        $g->setStellenSoll(19.5);
        $g->setProdukte(json_encode([['nummer' => 1, 'name' => 'Personalrecht']]));
        $d = $g->jsonSerialize();
        $this->assertSame('121', $d['code']);
        $this->assertSame(4866124, $d['globalkredit']['soll']);
        $this->assertSame(7428985, $d['aufwand']['soll']);
        $this->assertSame(19.5, $d['stellen']['soll']);
        $this->assertSame('Personalrecht', $d['produkte'][0]['name']);
    }

    public function testAntragKennzeichnetAutomatisch(): void {
        $manuell = new BudgetAntrag();
        $manuell->setBereich('globalbudget');
        $this->assertFalse($manuell->jsonSerialize()['automatisch']);

        $auto = new BudgetAntrag();
        $auto->setVerteilungId(7);
        $this->assertTrue($auto->jsonSerialize()['automatisch']);
    }

    public function testVerteilungStandard(): void {
        $v = new BudgetVerteilung();
        $d = $v->jsonSerialize();
        $this->assertTrue($d['automatikEin']);
        $this->assertSame('schwarze_null', $d['zielModus']);
    }
}
