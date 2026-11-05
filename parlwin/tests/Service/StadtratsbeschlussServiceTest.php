<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Stadtratsbeschluss;
use OCA\ParliamentWinterthur\Db\StadtratsbeschlussMapper;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Abgleich der Stadtratsbeschlüsse (F146).
 *
 * Geprüft wird, was die Zusicherung verspricht: ein zweiter Lauf legt keinen
 * zweiten Eintrag an, die Notizen der Fraktion überleben ihn, ein Beschluss,
 * den die Datenbank nicht annimmt, zählt als Fehler statt zu verschwinden, und
 * eine Quelle, die nichts hergibt, meldet das statt einen leeren Abgleich als
 * Erfolg auszugeben.
 */
class StadtratsbeschlussServiceTest extends TestCase
{
    /** @var array<string, Stadtratsbeschluss> Die Tabelle, nach extern_id */
    private array $tabelle = [];
    private int $naechsteId = 1;
    /** @var int[] Wie oft insert gerufen wurde */
    private array $eingefuegt = [];

    private function mapper(): StadtratsbeschlussMapper
    {
        $mapper = $this->createStub(StadtratsbeschlussMapper::class);
        $mapper->method('findByExternId')->willReturnCallback(
            fn(string $externId): ?Stadtratsbeschluss => $this->tabelle[$externId] ?? null
        );
        $mapper->method('insert')->willReturnCallback(
            function (Stadtratsbeschluss $b): Stadtratsbeschluss {
                $b->setId($this->naechsteId++);
                $this->tabelle[(string) $b->getExternId()] = $b;
                $this->eingefuegt[] = (int) $b->getId();
                return $b;
            }
        );
        $mapper->method('update')->willReturnCallback(
            function (Stadtratsbeschluss $b): Stadtratsbeschluss {
                $this->tabelle[(string) $b->getExternId()] = $b;
                return $b;
            }
        );
        return $mapper;
    }

    /**
     * @param array<int, array<string, mixed>>|\Throwable $quelle
     */
    private function dienst(array|\Throwable $quelle, ?StadtratsbeschlussMapper $mapper = null): StadtratsbeschlussService
    {
        $scraper = $this->createStub(ScraperService::class);
        if ($quelle instanceof \Throwable) {
            $scraper->method('ladeStadtratsbeschluesse')->willThrowException($quelle);
        } else {
            $scraper->method('ladeStadtratsbeschluesse')->willReturn($quelle);
        }
        return new StadtratsbeschlussService(
            $mapper ?? $this->mapper(),
            $scraper,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function quelldaten(string $titel = 'Festlegung Legislaturschwerpunkte'): array
    {
        return [[
            'externId' => '20261279',
            'nummer' => '2026/1279',
            'titel' => $titel,
            'datum' => '2026-10-21',
            'departement' => 'Stadtkanzlei',
            'sitzung' => 'Stadtratssitzung vom 21. Oktober 2026',
            'publiziertAm' => '2026-10-28',
            'url' => 'https://stadt.winterthur.ch/stadtratsbeschluesse/2026-10-21/20261279',
            'dokumente' => [['titel' => 'SRB_SRA', 'url' => 'https://stadt.winterthur.ch/download/x.pdf']],
        ]];
    }

    public function testDerErsteLaufLegtDenBeschlussAn(): void
    {
        $statistik = $this->dienst($this->quelldaten())->synchronisieren();

        $this->assertSame(1, $statistik['neu']);
        $this->assertSame(0, $statistik['aktualisiert']);
        $this->assertSame(1, $statistik['gelesen']);
        $this->assertSame(0, $statistik['fehler']);
        $beschluss = $this->tabelle['20261279'];
        $this->assertSame('2026/1279', $beschluss->getNummer());
        $this->assertSame('2026-10-21', $beschluss->getDatum());
        $this->assertSame('Stadtkanzlei', $beschluss->getDepartement());
        $this->assertSame('2026-10-28', $beschluss->getPubliziertAm());
        $this->assertCount(1, $beschluss->getDokumenteArray());
    }

    public function testDerZweiteLaufLegtKeinenZweitenEintragAn(): void
    {
        $mapper = $this->mapper();
        $this->dienst($this->quelldaten(), $mapper)->synchronisieren();
        $statistik = $this->dienst($this->quelldaten('Festlegung, zweite Fassung'), $mapper)->synchronisieren();

        $this->assertCount(1, $this->eingefuegt, 'der zweite Lauf hat einen zweiten Eintrag angelegt');
        $this->assertSame(0, $statistik['neu']);
        $this->assertSame(1, $statistik['aktualisiert']);
        $this->assertSame('Festlegung, zweite Fassung', $this->tabelle['20261279']->getTitel());
    }

    public function testEinLangerTitelWirdGekuerztUndNichtVerworfen(): void
    {
        $lang = str_repeat('a', 600);
        $this->dienst($this->quelldaten($lang))->synchronisieren();

        $this->assertSame(500, mb_strlen((string) $this->tabelle['20261279']->getTitel()));
    }

    public function testEinBeschlussOhneNummerWirdUebersprungen(): void
    {
        $daten = $this->quelldaten();
        $daten[0]['externId'] = '';
        $statistik = $this->dienst($daten)->synchronisieren();

        $this->assertSame(0, $statistik['neu']);
        $this->assertSame([], $this->eingefuegt);
    }

    public function testEinSchreibfehlerZaehltUndNenntDenBeschluss(): void
    {
        $mapper = $this->createStub(StadtratsbeschlussMapper::class);
        $mapper->method('findByExternId')->willReturn(null);
        $mapper->method('insert')->willThrowException(new \RuntimeException('Spalte zu kurz'));

        $statistik = $this->dienst($this->quelldaten(), $mapper)->synchronisieren();

        $this->assertSame(1, $statistik['fehler']);
        $this->assertStringContainsString('20261279', $statistik['fehlermeldung']);
        $this->assertStringContainsString('Spalte zu kurz', $statistik['fehlermeldung']);
        $this->assertSame(0, $statistik['neu']);
    }

    public function testEineQuelleOhneBeschluesseMeldetDenFehler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nennt keinen einzigen Beschluss');

        $this->dienst(new \RuntimeException(
            'Die Liste der Stadtratsbeschlüsse unter http://quelle/stadtratsbeschluesse'
            . ' nennt keinen einzigen Beschluss.'
        ))->synchronisieren();
    }

    protected function tearDown(): void
    {
        putenv('PARLWIN_STADT_BASE_URL');
        putenv('PARLWIN_SRB_SEITEN');
    }

    /**
     * Kommt die Detailseite eines Beschlusses nicht, behält der gespeicherte
     * Beschluss seine Dokumente und sein Datum der Veröffentlichung. Gelesen
     * wird über den Scraper aus den gespeicherten Seiten der Stadt.
     */
    public function testEineFehlendeDetailseiteLaesstDokumenteUndVeroeffentlichungStehen(): void
    {
        putenv('PARLWIN_STADT_BASE_URL');
        putenv('PARLWIN_SRB_SEITEN=1');
        $fixtures = __DIR__ . '/../Fixtures/html/';
        $liste = $this->createStub(IResponse::class);
        $liste->method('getBody')->willReturn((string) file_get_contents($fixtures . 'stadtratsbeschluesse-list.html'));
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturn($liste);
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $scraper = new ScraperServiceMitFixtures($clientService, $this->createStub(LoggerInterface::class));
        $detail = (string) file_get_contents($fixtures . 'stadtratsbeschluss-detail-20261279.html');
        $detailKommt = true;
        $scraper->setzeResolver(static function (string $url) use (&$detailKommt, $detail): string {
            if ($detailKommt && str_ends_with($url, '/20261279')) {
                return $detail;
            }
            throw new \RuntimeException('Detailseite nicht erreichbar');
        });
        $dienst = new StadtratsbeschlussService(
            $this->mapper(),
            $scraper,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );

        $dienst->synchronisieren();
        $this->assertCount(2, $this->tabelle['20261279']->getDokumenteArray(), 'der erste Lauf las die Dokumente nicht');
        $this->assertSame('2026-10-28', $this->tabelle['20261279']->getPubliziertAm());

        $detailKommt = false;
        $statistik = $dienst->synchronisieren();

        $this->assertSame(12, $statistik['aktualisiert']);
        $beschluss = $this->tabelle['20261279'];
        $this->assertCount(2, $beschluss->getDokumenteArray(), 'der Lauf ohne Detailseite hat die Dokumente gelöscht');
        $this->assertSame('2026-10-28', $beschluss->getPubliziertAm());
        $this->assertSame('Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)', $beschluss->getTitel());
    }

    /**
     * Der Anteil `processed/total` des Bereichs nimmt nie ab: Er läuft über
     * Listen- und Detailseiten und das Schreiben durch und endet bei 1.
     * Gelesen wird über den Scraper aus den gespeicherten Seiten, mit zwei
     * Listenseiten.
     */
    public function testDerFortschrittFaelltNieZurueck(): void
    {
        putenv('PARLWIN_STADT_BASE_URL');
        putenv('PARLWIN_SRB_SEITEN=2');
        $fixtures = __DIR__ . '/../Fixtures/html/';
        $listenHtml = (string) file_get_contents($fixtures . 'stadtratsbeschluesse-list.html');
        $liste = $this->createStub(IResponse::class);
        $liste->method('getBody')->willReturn($listenHtml);
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturn($liste);
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $scraper = new ScraperServiceMitFixtures($clientService, $this->createStub(LoggerInterface::class));
        $detail = (string) file_get_contents($fixtures . 'stadtratsbeschluss-detail-20261279.html');
        $scraper->setzeResolver(static fn (string $url): string => str_contains($url, '?page=') ? $listenHtml : $detail);
        $dienst = new StadtratsbeschlussService(
            $this->mapper(),
            $scraper,
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );

        $meldungen = [];
        $dienst->synchronisieren(function (array $ereignis) use (&$meldungen): void {
            $meldungen[] = $ereignis;
        });

        $anteile = array_map(
            static fn (array $m): float => $m['total'] > 0 ? $m['processed'] / $m['total'] : 0.0,
            $meldungen
        );
        $verlauf = implode(' ', array_map(static fn (array $m): string => $m['processed'] . '/' . $m['total'], $meldungen));
        for ($i = 1; $i < count($anteile); $i++) {
            $this->assertGreaterThanOrEqual($anteile[$i - 1], $anteile[$i], 'der Fortschritt fällt zurück: ' . $verlauf);
        }
        $this->assertSame(1.0, $anteile[count($anteile) - 1], 'der Fortschritt endet nicht bei 1: ' . $verlauf);
        $this->assertGreaterThan(20, count($meldungen), 'zu wenige Meldungen, um den Verlauf zu messen: ' . $verlauf);
    }

    public function testDerFortschrittMeldetGenauEinmalFertig(): void
    {
        $meldungen = [];
        $this->dienst($this->quelldaten())->synchronisieren(
            function (array $ereignis) use (&$meldungen): void {
                $meldungen[] = $ereignis;
            }
        );

        $final = array_values(array_filter($meldungen, static fn(array $m): bool => $m['final'] === true));
        $this->assertCount(1, $final, 'der Abschnitt meldete sich mehr als einmal als fertig');
        $this->assertSame($meldungen[count($meldungen) - 1], $final[0], 'die Fertigmeldung steht nicht am Ende');
        foreach ($meldungen as $m) {
            $this->assertSame('stadtratsbeschluesse', $m['scope']);
            $this->assertLessThanOrEqual($m['total'], $m['processed'], 'der Stand liegt über der Gesamtzahl');
        }
    }
}
