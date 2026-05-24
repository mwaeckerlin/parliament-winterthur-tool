<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurf;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeschaeftServiceTest extends TestCase {
    public function testSynchronisierenMeldetFortschrittBereitsBeimLadenDerDetailseiten(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createStub(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $scraper->expects($this->once())
            ->method('ladeGeschaefte')
            ->willReturnCallback(function (?callable $fortschritt): array {
                if ($fortschritt !== null) {
                    $fortschritt(['processed' => 0, 'total' => 1, 'cursor' => '', 'final' => false]);
                    $fortschritt(['processed' => 1, 'total' => 1, 'cursor' => '1388420', 'final' => true]);
                }
                return [[
                    'id' => '1388420',
                    'title' => 'Wahl von zwei Mitgliedern',
                    'number' => '2021.82',
                    'type' => 'Wahlen',
                    'status' => 'Erledigt',
                    'date' => '2021-10-04',
                    'url' => 'https://parlament.winterthur.ch/_rte/information/1388420',
                ]];
            });

        $mapper->expects($this->once())
            ->method('findByExternId')
            ->with('1388420')
            ->willThrowException(new DoesNotExistException('nicht gefunden'));

        $mapper->expects($this->once())->method('insert')->willReturnArgument(0);
        $mapper->expects($this->once())
            ->method('markiereNichtMehrVorhandeneAlsGeloescht')
            ->with(['1388420'])
            ->willReturn(0);

        $events = [];
        $service->synchronisieren(function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertNotEmpty($events);
        $this->assertSame('geschaefte', $events[0]['scope']);
        $this->assertSame(0, $events[0]['processed']);
        $this->assertSame(2, $events[0]['total']);

        $hatLadeFortschritt = false;
        foreach ($events as $event) {
            if (($event['scope'] ?? '') !== 'geschaefte') {
                continue;
            }
            if ((int) ($event['processed'] ?? -1) === 1 && (int) ($event['total'] ?? 0) === 2) {
                $hatLadeFortschritt = true;
                break;
            }
        }
        $this->assertTrue($hatLadeFortschritt, 'Es wurde kein früher Lade-Fortschritt (1/2) gemeldet.');
    }

    public function testSynchronisierenSpeichertUnnummeriertenVorstossAlsEntwurf(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createMock(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $scraper->expects($this->once())
            ->method('ladeGeschaefte')
            ->willReturn([[
                'id' => '2999999',
                'title' => 'Noch nicht nummerierter Vorstoss',
                'number' => '',
                'type' => 'Motion',
                'date' => '2026-05-01',
                'url' => 'https://parlament.winterthur.ch/_rte/information/2999999',
            ]]);

        $mapper->expects($this->never())->method('findByExternId');
        $mapper->expects($this->never())->method('insert');
        $mapper->expects($this->never())->method('update');
        $mapper->expects($this->never())->method('markiereNichtMehrVorhandeneAlsGeloescht');

        $entwurfMapper->expects($this->once())
            ->method('findByExternId')
            ->with('2999999')
            ->willReturn(null);

        $entwurfMapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (VorstossEntwurf $entwurf): bool {
                return $entwurf->getExternId() === '2999999'
                    && $entwurf->getStatus() === 'eingereicht_ohne_nummer'
                    && $entwurf->getGeschaeftId() === 0;
            }))
            ->willReturnArgument(0);

        $result = $service->synchronisieren();
        $this->assertSame(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0], $result);
    }

    public function testSynchronisierenSetztDbIdAusExternIdBeiNeuemGeschaeft(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createStub(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $scraper->expects($this->once())
            ->method('ladeGeschaefte')
            ->willReturn([[
                'id' => '1388420',
                'title' => 'Wahl von zwei Mitgliedern',
                'number' => '2021.82',
                'type' => 'Wahlen',
                'status' => 'Erledigt',
                'date' => '2021-10-04',
                'url' => 'https://parlament.winterthur.ch/_rte/information/1388420',
            ]]);

        $mapper->expects($this->once())
            ->method('findByExternId')
            ->with('1388420')
            ->willThrowException(new DoesNotExistException('nicht gefunden'));

        $mapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (Geschaeft $geschaeft): bool {
                return $geschaeft->getId() === 1388420
                    && $geschaeft->getExternId() === '1388420'
                    && $geschaeft->getNummer() === '2021.82'
                    && $geschaeft->getQuelleHash() !== ''
                    && $geschaeft->getQuelleAktualisiertAm() !== '';
            }))
            ->willReturnArgument(0);

        $mapper->expects($this->once())
            ->method('markiereNichtMehrVorhandeneAlsGeloescht')
            ->with(['1388420'])
            ->willReturn(0);

        $result = $service->synchronisieren();

        $this->assertSame(['neu' => 1, 'aktualisiert' => 0, 'geloescht' => 0], $result);
    }

    public function testSynchronisierenHarmonisiertBestehendeIdMitExternId(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createStub(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $bestehend = new Geschaeft();
        $bestehend->setId(42);
        $bestehend->setExternId('1388420');

        $scraper->expects($this->once())
            ->method('ladeGeschaefte')
            ->willReturn([[
                'id' => '1388420',
                'title' => 'Wahl von zwei Mitgliedern',
                'number' => '2021.82',
                'type' => 'Wahlen',
                'status' => 'Pendent',
                'date' => '2021-10-04',
                'url' => 'https://parlament.winterthur.ch/_rte/information/1388420',
            ]]);

        $mapper->expects($this->once())
            ->method('findByExternId')
            ->with('1388420')
            ->willReturn($bestehend);

        $mapper->expects($this->once())
            ->method('harmonisiereIdMitExternId')
            ->with($bestehend, 1388420);

        $mapper->expects($this->once())
            ->method('update')
            ->with($bestehend)
            ->willReturn($bestehend);

        $mapper->expects($this->once())
            ->method('markiereNichtMehrVorhandeneAlsGeloescht')
            ->with(['1388420'])
            ->willReturn(0);

        $result = $service->synchronisieren();

        $this->assertSame(['neu' => 0, 'aktualisiert' => 1, 'geloescht' => 0], $result);
        $this->assertNotSame('', $bestehend->getQuelleHash());
        $this->assertNotSame('', $bestehend->getQuelleAktualisiertAm());
    }

    public function testSynchronisierenUeberspringtBereitsErledigteGeschaefteBeiUpdate(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createStub(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createMock(GeschaeftEreignisMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $bestehend = new Geschaeft();
        $bestehend->setId(42);
        $bestehend->setExternId('1388420');
        $bestehend->setStatus('Erledigt');

        $scraper->expects($this->once())
            ->method('ladeGeschaefte')
            ->willReturn([[
                'id' => '1388420',
                'title' => 'Wahl von zwei Mitgliedern',
                'number' => '2021.82',
                'type' => 'Wahlen',
                'status' => 'Pendent',
                'date' => '2021-10-04',
                'url' => 'https://parlament.winterthur.ch/_rte/information/1388420',
            ]]);

        $mapper->expects($this->once())
            ->method('findByExternId')
            ->with('1388420')
            ->willReturn($bestehend);

        $mapper->expects($this->once())
            ->method('harmonisiereIdMitExternId')
            ->with($bestehend, 1388420);

        $mapper->expects($this->never())->method('update');

        $ereignisMapper->expects($this->never())->method('ersetzeFuerGeschaeft');

        $mapper->expects($this->once())
            ->method('markiereNichtMehrVorhandeneAlsGeloescht')
            ->with(['1388420'])
            ->willReturn(0);

        $result = $service->synchronisieren();
        $this->assertSame(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0], $result);
    }

    public function testAlleLeitetInklusiveErledigtFlagAnMapperWeiter(): void {
        $mapper = $this->createMock(GeschaeftMapper::class);
        $entwurfMapper = $this->createStub(VorstossEntwurfMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $scraper = $this->createStub(ScraperService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $service = new GeschaeftService($mapper, $entwurfMapper, $ereignisMapper, $scraper, $logger);

        $expected = [];
        $mapper->expects($this->once())
            ->method('findAll')
            ->with(25, 10, true)
            ->willReturn($expected);

        $this->assertSame($expected, $service->alle(25, 10, true));
    }
}
