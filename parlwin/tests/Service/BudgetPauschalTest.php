<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragEntscheidMapper;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\BudgetVerteilung;
use OCA\ParliamentWinterthur\Db\BudgetVerteilungMapper;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Pauschalantrag (F100/F101): der Einreichen-Entscheid vererbt sich auf die je
 * Position erzeugten Einzelanträge; eine ausgenommene Position bekommt keinen
 * Antrag, der Gesamtbetrag verteilt sich neu auf die übrigen.
 */
class BudgetPauschalTest extends TestCase {
    /** @var array<int, \OCA\ParliamentWinterthur\Db\BudgetAntrag> */
    private array $inserted = [];

    private function gruppe(string $code, int $aufwand): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setDepartement('Finanzen');
        $g->setGlobalkreditSoll($aufwand);
        $g->setAufwandSoll($aufwand);
        $g->setAufwandSollVorjahr($aufwand);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll(0);
        $g->setStellenSollVorjahr(0);
        return $g;
    }

    private function festeVerteilung(int $betrag, float $prozent, array $ausnahmen = [], string $haltung = 'einreichen'): BudgetVerteilung {
        $v = new BudgetVerteilung();
        $v->setModus('fest');
        $v->setAutomatikEin(1);
        $v->setBetrag($betrag);
        $v->setProzent($prozent);
        $v->setHaltung($haltung);
        $v->setAusnahmen((string) json_encode(array_values($ausnahmen)));
        return $v;
    }

    /**
     * Baut den Service; erzeugte Anträge landen in $this->inserted. Ohne Argument
     * gibt es eine Ziel-Verteilung (Ausgleich); sonst die übergebene Liste.
     *
     * @param array<int, BudgetVerteilung>|null $verteilungen
     */
    private function serviceMitCapture(?array $verteilungen = null, ?BudgetVerteilungMapper $mapperOverride = null): BudgetService {
        $this->inserted = [];
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(0);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        // Aufwand 7M + 3M, kein Ertrag → Defizit 10M.
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121', 7000000), $this->gruppe('142', 3000000)]);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);

        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([]);
        $antraege->method('insert')->willReturnCallback(function ($a) {
            $this->inserted[] = $a;
            return $a;
        });

        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn([]);

        $zielVerteilung = new BudgetVerteilung();
        $zielVerteilung->setModus('ziel');
        if ($mapperOverride !== null) {
            $verteilungenMapper = $mapperOverride;
        } else {
            $verteilungenMapper = $this->createStub(BudgetVerteilungMapper::class);
            $verteilungenMapper->method('findeOderStandard')->willReturn($zielVerteilung);
            $verteilungenMapper->method('alleFuerJahr')->willReturn($verteilungen ?? [$zielVerteilung]);
        }

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $notiz = $this->createStub(NotizService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungenMapper,
            $entscheide, $config, $time, $userSession, $realtime, $notiz,
        );
    }

    public function testPauschalKinderErbenEinreichenEntscheid(): void {
        // F100: der Pauschalantrag ist «nicht einreichen» → alle erzeugten
        // Einzelanträge tragen dieselbe Haltung und zählen damit nicht (F102).
        $service = $this->serviceMitCapture();
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'nicht_einreichen', []);
        self::assertNotEmpty($this->inserted, 'Automatik erzeugt Pauschalkürzungen');
        foreach ($this->inserted as $a) {
            self::assertSame('nicht_einreichen', $a->getHaltung(), 'Einzelanträge folgen dem Einreichen-Entscheid (F100)');
            self::assertFalse($a->wirdUnterstuetzt(), 'nicht eingereicht → zählt nicht (F100/F102)');
        }
    }

    public function testPauschalAusnahmeErzeugtKeinenAntragDort(): void {
        // F101: Position 121 ausgenommen → kein Antrag dort, der volle Betrag
        // (10M) geht auf 142.
        $service = $this->serviceMitCapture();
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'einreichen', ['121']);
        $ziele = array_map(static fn ($a) => (string) $a->getZielRef(), $this->inserted);
        self::assertNotContains('121', $ziele, 'ausgenommene Position bekommt keinen Pauschalantrag (F101)');
        self::assertContains('142', $ziele, 'übrige Position trägt die Umverteilung (F101)');
        $summe = array_sum(array_map(static fn ($a) => (int) $a->getBetragDelta(), $this->inserted));
        self::assertSame(-10000000, $summe, 'derselbe Gesamtbetrag wird eingespart (F101)');
    }

    public function testFesteVerteilungVerteiltDenBetragAnteilig(): void {
        // F100: eine feste Pauschalverteilung von −4M wird anteilig verteilt.
        $service = $this->serviceMitCapture([$this->festeVerteilung(-4000000, 0.0)]);
        $service->verteilungSetzen(2026, false, 'schwarze_null', 0); // löst nachAenderung aus
        $summe = array_sum(array_map(static fn ($a) => (int) $a->getBetragDelta(), $this->inserted));
        self::assertNotEmpty($this->inserted);
        self::assertSame(-4000000, $summe, 'die feste Verteilung verteilt genau ihren Betrag (F100)');
    }

    public function testProzentualeVerteilungBeziehtSichAufUrspruenglichenAufwand(): void {
        // F100: «10% einsparen» = 10% des ursprünglichen Gesamt-Aufwands (10M) = 1M —
        // unabhängig von bereits gekürzten Beträgen.
        $service = $this->serviceMitCapture([$this->festeVerteilung(0, -10.0)]);
        $service->verteilungSetzen(2026, false, 'schwarze_null', 0);
        $summe = array_sum(array_map(static fn ($a) => (int) $a->getBetragDelta(), $this->inserted));
        self::assertSame(-1000000, $summe, '10% von 10M ursprünglichem Aufwand = 1M (F100)');
    }

    public function testMehrerePauschalverteilungenKumulieren(): void {
        // F100: zwei unabhängige feste Verteilungen (−4M und −3M) summieren sich.
        $service = $this->serviceMitCapture([$this->festeVerteilung(-4000000, 0.0), $this->festeVerteilung(-3000000, 0.0)]);
        $service->verteilungSetzen(2026, false, 'schwarze_null', 0);
        $summe = array_sum(array_map(static fn ($a) => (int) $a->getBetragDelta(), $this->inserted));
        self::assertSame(-7000000, $summe, 'beliebig viele Pauschalverteilungen kumulieren (F100)');
    }

    public function testNurEinAbsolutesZiel(): void {
        // F84/F85: Es darf nur EIN absolutes Ziel geben. Wird ein zweites gewählt,
        // wird das ältere zur Einsparung 0 herabgestuft (der ältere weicht).
        $alt = new BudgetVerteilung();
        $alt->setId(1);
        $alt->setJahr(2026);
        $alt->setModus('ziel');
        $alt->setZielModus('festes_defizit');
        $alt->setZielBetrag(-500000);
        $neu = new BudgetVerteilung();
        $neu->setId(2);
        $neu->setJahr(2026);
        $neu->setModus('fest');

        $mapper = $this->createStub(BudgetVerteilungMapper::class);
        $mapper->method('alleFuerJahr')->willReturn([$alt, $neu]);
        $mapper->method('findeVerteilung')->willReturnCallback(static fn (int $id) => $id === 1 ? $alt : $neu);
        $mapper->method('update')->willReturnArgument(0);

        $service = $this->serviceMitCapture(null, $mapper);
        // Der zweite Pauschalantrag wird zum absoluten Ziel «schwarze Null».
        $service->pauschalAendern(2, ['zielModus' => 'schwarze_null']);

        self::assertSame('fest', $alt->modusOderStandard(), 'älteres absolutes Ziel wurde zur Einsparung herabgestuft');
        self::assertSame('schwarze_null', $alt->getZielModus());
        self::assertSame(0, (int) $alt->getBetrag(), 'herabgestuftes Ziel spart nichts mehr');
        self::assertSame('ziel', $neu->modusOderStandard(), 'das neue absolute Ziel ist aktiv');
    }
}
