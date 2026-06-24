<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\Sitzungstyp;
use OCA\ParliamentWinterthur\Db\SitzungstypMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmerMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandumMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\SitzungstypService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SitzungstypServiceTest extends TestCase
{
    private function makeTyp(int $id = 1, bool $kalenderAnlegen = false): Sitzungstyp
    {
        $typ = new Sitzungstyp();
        $typ->setId($id);
        $typ->setName('Fraktionssitzung');
        $typ->setZweck('Wöchentliche Besprechung');
        $typ->setKalenderAnlegen($kalenderAnlegen);
        $typ->setEinladungVersenden(false);
        $typ->setStandardOrt('Rathaus');
        $typ->setStandardZeitVon('18:00');
        $typ->setStandardZeitBis('20:00');
        $typ->setGeloescht(false);
        return $typ;
    }

    public function testSpeichernUebernimmtKommissionen(): void
    {
        $gespeichert = null;
        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('insert')->willReturnCallback(function ($t) use (&$gespeichert) {
            $t->setId(1);
            $gespeichert = $t;
            return $t;
        });
        $typMapper->method('find')->willReturnCallback(function () use (&$gespeichert) {
            return $gespeichert;
        });
        $service = $this->makeService(
            $typMapper,
            $this->createStub(SitzungMapper::class),
            $this->createStub(TraktandumMapper::class),
        );

        $result = $service->speichern(['name' => 'Test', 'kommissionen' => [3, 7]]);

        $this->assertSame([3, 7], $result['kommissionen']);
    }

    private function makeService(
        SitzungstypMapper $typMapper,
        SitzungMapper $sitzungMapper,
        TraktandumMapper $traktandumMapper,
        ?KalenderService $kalenderService = null,
    ): SitzungstypService {
        return new SitzungstypService(
            $typMapper,
            $this->createStub(SitzungstypTraktandumMapper::class),
            $this->createStub(SitzungstypTeilnehmerMapper::class),
            $sitzungMapper,
            $traktandumMapper,
            $this->createStub(MitgliedMapper::class),
            $this->createStub(KommissionMapper::class),
            $this->createStub(FraktionsrolleMapper::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(IUserSession::class),
            $this->createStub(IConfig::class),
            $this->createStub(LoggerInterface::class),
            $kalenderService ?? $this->createStub(KalenderService::class),
        );
    }

    public function testErstelleAusTypSpeichertSitzungMitKorrektenFeldern(): void
    {
        $typ = $this->makeTyp();

        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('find')->willReturn($typ);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('insert')->willReturnCallback(
            function (Sitzung $s): Sitzung {
                $s->setId(42);
                return $s;
            }
        );

        $traktandumMapper = $this->createStub(TraktandumMapper::class);

        $service = $this->makeService($typMapper, $sitzungMapper, $traktandumMapper);

        $result = $service->erstelleAusTyp([
            'typId'       => 1,
            'datum'       => '2026-06-15',
            'titel'       => 'Meine Sitzung',
            'ort'         => 'Saal A',
            'zeitVon'     => '19:00',
            'zeitBis'     => '21:00',
            'bemerkungen' => 'Zweck der Sitzung',
            'traktanden'  => [],
        ]);

        $this->assertSame(1, $result->getTypId());
        $this->assertSame('2026-06-15', $result->getDatum());
        $this->assertSame('Meine Sitzung', $result->getTitel());
        $this->assertSame('Saal A', $result->getOrt());
        $this->assertSame('19:00', $result->getZeitVon());
        $this->assertSame('21:00', $result->getZeitBis());
        $this->assertSame('Zweck der Sitzung', $result->getBemerkungen());
        $this->assertNull($result->getExternId());
        $this->assertFalse($result->getGeloescht());
    }

    public function testErstelleAusTypVerwendetVorlagenwerteAlsFallback(): void
    {
        $typ = $this->makeTyp();

        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('find')->willReturn($typ);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('insert')->willReturnCallback(fn(Sitzung $s): Sitzung => $s->setId(1) ?? $s);

        $service = $this->makeService($typMapper, $sitzungMapper, $this->createStub(TraktandumMapper::class));

        $result = $service->erstelleAusTyp([
            'typId' => 1,
            'datum' => '2026-06-15',
            'titel' => '',
        ]);

        $this->assertSame('Fraktionssitzung', $result->getTitel());
        $this->assertSame('Rathaus', $result->getOrt());
        $this->assertSame('18:00', $result->getZeitVon());
    }

    public function testErstelleAusTypSpeichertTraktanden(): void
    {
        $typ = $this->makeTyp();

        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('find')->willReturn($typ);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('insert')->willReturnCallback(fn(Sitzung $s): Sitzung => $s->setId(5) ?? $s);

        $gespeicherteTraktanden = [];
        $traktandumMapper = $this->createMock(TraktandumMapper::class);
        $traktandumMapper->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function ($t) use (&$gespeicherteTraktanden) {
                $gespeicherteTraktanden[] = $t;
                return $t;
            });

        $service = $this->makeService($typMapper, $sitzungMapper, $traktandumMapper);

        $service->erstelleAusTyp([
            'typId'      => 1,
            'datum'      => '2026-06-15',
            'traktanden' => [
                ['titel' => 'Begrüssung', 'beschreibung' => ''],
                ['titel' => 'Berichte', 'beschreibung' => 'Kurzberichte'],
            ],
        ]);

        $this->assertCount(2, $gespeicherteTraktanden);
        $this->assertSame(1, $gespeicherteTraktanden[0]->getNummer());
        $this->assertSame('Begrüssung', $gespeicherteTraktanden[0]->getTitel());
        $this->assertSame(2, $gespeicherteTraktanden[1]->getNummer());
        $this->assertSame('Berichte', $gespeicherteTraktanden[1]->getTitel());
    }

    public function testErstelleAusTypRuftKalenderServiceAufWennKonfiguriert(): void
    {
        $typ = $this->makeTyp(kalenderAnlegen: true);

        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('find')->willReturn($typ);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('insert')->willReturnCallback(fn(Sitzung $s): Sitzung => $s->setId(7) ?? $s);

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects($this->once())
            ->method('erstelleInterneSitzung')
            ->with(
                $this->isInstanceOf(Sitzung::class),
                $this->stringContains('Wöchentliche Besprechung')
            );

        $service = $this->makeService($typMapper, $sitzungMapper, $this->createStub(TraktandumMapper::class), $kalenderService);

        $service->erstelleAusTyp([
            'typId' => 1,
            'datum' => '2026-06-15',
        ]);
    }

    public function testErstelleAusTypRuftKalenderServiceNichtAufWennDeaktiviert(): void
    {
        $typ = $this->makeTyp(kalenderAnlegen: false);

        $typMapper = $this->createStub(SitzungstypMapper::class);
        $typMapper->method('find')->willReturn($typ);

        $sitzungMapper = $this->createStub(SitzungMapper::class);
        $sitzungMapper->method('insert')->willReturnCallback(fn(Sitzung $s): Sitzung => $s->setId(8) ?? $s);

        $kalenderService = $this->createMock(KalenderService::class);
        $kalenderService->expects($this->never())->method('erstelleInterneSitzung');

        $service = $this->makeService($typMapper, $sitzungMapper, $this->createStub(TraktandumMapper::class), $kalenderService);

        $service->erstelleAusTyp([
            'typId' => 1,
            'datum' => '2026-06-15',
        ]);
    }
}
