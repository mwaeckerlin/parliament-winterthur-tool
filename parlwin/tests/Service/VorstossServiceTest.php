<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;
use OCA\ParliamentWinterthur\Service\VorstossService;
use PHPUnit\Framework\TestCase;

class VorstossServiceTest extends TestCase
{
    private function service(VorstossMapper $mapper): VorstossService
    {
        return new VorstossService($mapper);
    }

    public function testErstelleUebernimmtFelderUndSetztZeit(): void
    {
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('insert')->willReturnArgument(0);

        $vorstoss = $this->service($mapper)->erstelle([
            'titel' => '  Mein Vorstoss  ',
            'herkunft' => 'fremde',
            'status' => 'bereit',
            'zustaendigkeit' => 'Müller',
        ]);

        $this->assertSame('Mein Vorstoss', $vorstoss->getTitel());
        $this->assertSame('fremde', $vorstoss->getHerkunft());
        $this->assertSame('bereit', $vorstoss->getStatus());
        $this->assertSame('Müller', $vorstoss->getZustaendigkeit());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $vorstoss->getErstelltAm());
    }

    public function testErstelleNormalisiertUngueltigeWerte(): void
    {
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('insert')->willReturnArgument(0);

        $vorstoss = $this->service($mapper)->erstelle([
            'titel' => 'X',
            'herkunft' => 'quatsch',
            'status' => 'unsinn',
        ]);

        $this->assertSame('eigene', $vorstoss->getHerkunft());
        $this->assertSame('neu', $vorstoss->getStatus());
    }

    public function testLoescheSetztGeloeschtUndAktualisiert(): void
    {
        $vorstoss = new Vorstoss();
        $vorstoss->setId(1);

        $mapper = $this->createMock(VorstossMapper::class);
        $mapper->method('find')->willReturn($vorstoss);
        $mapper->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (Vorstoss $v): bool => $v->getGeloescht() === true));

        $this->service($mapper)->loesche(1);
    }
}
