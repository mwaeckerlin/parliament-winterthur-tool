<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypMapper;
use OCA\ParliamentWinterthur\Service\KommissionsVerknuepfungService;
use OCA\ParliamentWinterthur\Service\SitzungGeschaeftService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KommissionsVerknuepfungServiceTest extends TestCase {
    private function geschaeft(int $id, string $status): Geschaeft {
        $g = new Geschaeft();
        $g->setId($id);
        $g->setStatus($status);
        $g->setGeloescht(false);
        return $g;
    }

    private function service(GeschaeftMapper $geschaeftMapper): KommissionsVerknuepfungService {
        return new KommissionsVerknuepfungService(
            $this->createStub(SitzungMapper::class),
            $this->createStub(SitzungstypMapper::class),
            $this->createStub(KommissionMapper::class),
            $geschaeftMapper,
            $this->createStub(SitzungGeschaeftService::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testGeschaefteFuerKommissionMatchtUeberStatus(): void {
        $geschaefte = [
            $this->geschaeft(1, 'Bei der Aufsichtskommission pendent'),
            $this->geschaeft(2, 'Erledigt'),
            $this->geschaeft(3, 'In Sachkommission Bildung Sport Kultur pendent'),
        ];
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findAll')->willReturn($geschaefte);

        $treffer = $this->service($mapper)->geschaefteFuerKommission('Aufsichtskommission');

        $this->assertSame([1], array_map(static fn ($g) => $g->getId(), $treffer));
    }

    public function testGeschaefteFuerKommissionMitTokens(): void {
        $geschaefte = [
            $this->geschaeft(3, 'In Sachkommission Bildung Sport Kultur pendent'),
            $this->geschaeft(4, 'Bei der Aufsichtskommission pendent'),
        ];
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findAll')->willReturn($geschaefte);

        $treffer = $this->service($mapper)->geschaefteFuerKommission('Sachkommission Bildung Sport Kultur');

        $this->assertSame([3], array_map(static fn ($g) => $g->getId(), $treffer));
    }

    public function testGeschaefteFuerKommissionIgnoriertStatusOhneKommission(): void {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findAll')->willReturn([$this->geschaeft(1, 'Im Rat pendent')]);

        $this->assertSame([], $this->service($mapper)->geschaefteFuerKommission('Aufsichtskommission'));
    }
}
