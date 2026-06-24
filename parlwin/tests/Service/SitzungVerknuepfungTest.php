<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SitzungVerknuepfungTest extends TestCase {
    private function service(SitzungMapper $mapper): SitzungService {
        return new SitzungService(
            $mapper,
            $this->createStub(TraktandumMapper::class),
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(ScraperService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function sitzung(int $id, ?int $verknuepfungId = null): Sitzung {
        $s = new Sitzung();
        $s->setId($id);
        $s->setVerknuepfungId($verknuepfungId);
        return $s;
    }

    public function testVerknuepfeSetztGemeinsameGruppe(): void {
        // Ziel B hat noch keine Gruppe → dessen eigene ID (2) wird zur Gruppe.
        $b = $this->sitzung(2, null);
        $a = $this->sitzung(1, null);

        $mapper = $this->createMock(SitzungMapper::class);
        $mapper->method('find')->willReturnMap([[2, $b], [1, $a]]);
        $mapper->expects($this->exactly(2))->method('update')->willReturnArgument(0);

        $this->service($mapper)->verknuepfe(1, 2);
        $this->assertSame(2, $a->getVerknuepfungId());
        $this->assertSame(2, $b->getVerknuepfungId());
    }

    public function testVerknuepfeNutztBestehendeGruppeDerZielsitzung(): void {
        // Ziel B ist bereits in Gruppe 5 → A wird ebenfalls Gruppe 5, B unverändert.
        $b = $this->sitzung(2, 5);
        $a = $this->sitzung(1, null);

        $mapper = $this->createMock(SitzungMapper::class);
        $mapper->method('find')->willReturnMap([[2, $b], [1, $a]]);
        // Nur A wird aktualisiert (B war schon in der Gruppe).
        $mapper->expects($this->once())->method('update')->willReturnArgument(0);

        $this->service($mapper)->verknuepfe(1, 2);
        $this->assertSame(5, $a->getVerknuepfungId());
        $this->assertSame(5, $b->getVerknuepfungId());
    }

    public function testEntkoppeltSetztGruppeAufNull(): void {
        $a = $this->sitzung(1, 7);

        $mapper = $this->createMock(SitzungMapper::class);
        $mapper->method('find')->willReturn($a);
        $mapper->expects($this->once())->method('update')->willReturnArgument(0);

        $this->service($mapper)->entkopple(1);
        $this->assertNull($a->getVerknuepfungId());
    }

    public function testVerknuepfteSitzungenLiefertGruppe(): void {
        $a = $this->sitzung(1, 7);
        $gruppe = [$this->sitzung(1, 7), $this->sitzung(3, 7)];

        $mapper = $this->createMock(SitzungMapper::class);
        $mapper->method('find')->willReturn($a);
        $mapper->expects($this->once())->method('findByVerknuepfungId')->with(7)->willReturn($gruppe);

        $this->assertSame($gruppe, $this->service($mapper)->verknuepfteSitzungen(1));
    }

    public function testVerknuepfteSitzungenOhneGruppeNurSelbst(): void {
        $a = $this->sitzung(1, null);

        $mapper = $this->createMock(SitzungMapper::class);
        $mapper->method('find')->willReturn($a);
        $mapper->expects($this->never())->method('findByVerknuepfungId');

        $this->assertSame([$a], $this->service($mapper)->verknuepfteSitzungen(1));
    }
}
