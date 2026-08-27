<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Ereignis;
use OCA\ParliamentWinterthur\Db\EreignisMapper;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Ereignis-Protokoll (F105): protokolliert Synchronisationen und Budget-Importe
 * (inkl. Parsing-Fehler) mit Zeitpunkt, Art, Bereich, Erfolg und Meldung; die Liste
 * kommt neueste zuerst, und alte Einträge werden beim Schreiben aufgeräumt.
 */
class EreignisServiceTest extends TestCase {
    private function zeit(int $jetzt): ITimeFactory {
        return new class($jetzt) implements ITimeFactory {
            public function __construct(private readonly int $jetzt) {
            }

            public function getTime(): int {
                return $this->jetzt;
            }
        };
    }

    public function testProtokolliertMitAllenFeldern(): void {
        /** @var list<Ereignis> $gespeichert */
        $gespeichert = [];
        $mapper = $this->createStub(EreignisMapper::class);
        $mapper->method('insert')->willReturnCallback(static function (Ereignis $e) use (&$gespeichert): Ereignis {
            $gespeichert[] = $e;
            return $e;
        });
        // Ohne angemeldeten Nutzer wird «auto» eingetragen.
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn(null);

        $service = new EreignisService($mapper, $this->zeit(1000), $userSession);
        $service->protokolliere('fehler', 'budget', false, 'Budget 2026 einlesen fehlgeschlagen', 'Teil B lieferte keine Produktegruppen');

        self::assertCount(1, $gespeichert);
        $e = $gespeichert[0];
        self::assertSame(1000, $e->getZeitpunkt());
        self::assertSame('fehler', $e->getArt());
        self::assertSame('budget', $e->getBereich());
        self::assertSame(0, $e->getErfolg(), 'Fehler → erfolg 0');
        self::assertSame('Budget 2026 einlesen fehlgeschlagen', $e->getTitel());
        self::assertSame('Teil B lieferte keine Produktegruppen', $e->getMeldung());
        self::assertSame('auto', $e->getAusgeloestVon(), 'ohne Nutzer → auto');
    }

    public function testAusgeloestVonExplizitUeberschreibtNutzer(): void {
        $mapper = $this->createStub(EreignisMapper::class);
        $mapper->method('insert')->willReturnArgument(0);
        $userSession = $this->createStub(IUserSession::class);

        $service = new EreignisService($mapper, $this->zeit(1000), $userSession);
        $e = $service->protokolliere('budget_import', 'budget', true, 'Budget 2026 automatisch eingelesen', '', 'auto');

        self::assertSame('auto', $e->getAusgeloestVon());
        self::assertSame(1, $e->getErfolg());
    }

    public function testListeKommtNeuesteZuerstUndRaeumtAlteWeg(): void {
        $alt = new Ereignis();
        $alt->setZeitpunkt(100);
        $neu = new Ereignis();
        $neu->setZeitpunkt(200);
        $geloeschtAb = null;
        $mapper = $this->createMock(EreignisMapper::class);
        $mapper->method('insert')->willReturnArgument(0);
        $mapper->method('neueste')->willReturn([$neu, $alt]);
        $mapper->expects(self::once())->method('loescheAelterAls')
            ->willReturnCallback(static function (int $ts) use (&$geloeschtAb): void {
                $geloeschtAb = $ts;
            });
        $userSession = $this->createStub(IUserSession::class);

        // Zeit 200 Tage (in Sekunden), Aufbewahrung 180 Tage → gelöscht wird alles vor (jetzt − 180 Tage).
        $jetzt = 200 * 86400;
        $service = new EreignisService($mapper, $this->zeit($jetzt), $userSession);
        $service->protokolliere('sync', '', true, 'Synchronisation abgeschlossen', '3 neu, 1 geändert');
        self::assertSame($jetzt - 180 * 86400, $geloeschtAb, 'räumt Ereignisse älter als 180 Tage weg');

        $liste = $service->liste();
        self::assertSame([$neu, $alt], $liste, 'neueste zuerst');
    }
}
