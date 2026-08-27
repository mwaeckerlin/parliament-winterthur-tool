<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
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
 * Automatische Steuerfuss-Senkung (F88, Lösung B): Steht am Ende ein Überschuss,
 * wird der Steuerfuss in ganzen Prozent-Schritten gesenkt. Die Senkung ist ein
 * echter Antrag (quelle «pauschal»), der die Einnahmen reduziert — der
 * Gesamtertrag geht damit auf (nahe) null. Ein manuell gestellter Steuerfuss-
 * Antrag schaltet die Automatik aus.
 */
class BudgetSteuerfussAutomatikTest extends TestCase {
    /** @var array<int, BudgetAntrag> */
    private array $inserted = [];

    private function gruppe(string $code, int $aufwand, int $ertrag): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setDepartement('Finanzen');
        $g->setGlobalkreditSoll($aufwand);
        $g->setAufwandSoll($aufwand);
        $g->setAufwandSollVorjahr($aufwand);
        $g->setErtragSoll($ertrag);
        $g->setErtragSollVorjahr($ertrag);
        $g->setStellenSoll(0);
        $g->setStellenSollVorjahr(0);
        return $g;
    }

    /** Config-Stub mit gespeichertem Zustand (get/set), für die Flag- und Fraktions-Tests. */
    private function statefulConfig(array &$store): IConfig {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static function (string $app, string $key, string $default = '') use (&$store) {
                return $store[$key] ?? $default;
            }
        );
        $config->method('setAppValue')->willReturnCallback(
            static function (string $app, string $key, string $value) use (&$store): void {
                $store[$key] = $value;
            }
        );
        return $config;
    }

    /**
     * @param array<int, BudgetAntrag> $vorhandene  was findByJahr liefert
     */
    private function serviceMitUeberschuss(array $vorhandene = [], ?IConfig $config = null): BudgetService {
        $this->inserted = [];
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(250000000); // 1 Steuerprozent = 2 Mio
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        // Aufwand 1M, Ertrag 11M → Überschuss 10M.
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121', 1000000, 11000000)]);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);

        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn($vorhandene);
        $antraege->method('insert')->willReturnCallback(function ($a) {
            $this->inserted[] = $a;
            return $a;
        });

        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn([]);

        $zielVerteilung = new BudgetVerteilung();
        $zielVerteilung->setModus('ziel');
        $verteilungenMapper = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungenMapper->method('findeOderStandard')->willReturn($zielVerteilung);
        $verteilungenMapper->method('alleFuerJahr')->willReturn([$zielVerteilung]);

        if ($config === null) {
            $config = $this->createStub(IConfig::class);
            $config->method('getAppValue')->willReturnCallback(
                static fn (string $app, string $key, string $default = '') => $default
            );
        }
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $notiz = $this->createStub(NotizService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungenMapper,
            $entscheide, $config, $time, $userSession, $realtime, $notiz,
        );
    }

    private function steuerfussAntraege(): array {
        return array_values(array_filter($this->inserted, static fn ($a) => (string) $a->getBereich() === 'steuerfuss'));
    }

    public function testUeberschussErzeugtAutomatischenSteuerfussAntrag(): void {
        // F88: 10M Überschuss, 1 Steuerprozent = 2M → 5 Prozentpunkte Senkung,
        // 10M weniger Einnahmen; der Ertrag geht damit auf null.
        $service = $this->serviceMitUeberschuss();
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'einreichen', []);

        $steuer = $this->steuerfussAntraege();
        self::assertCount(1, $steuer, 'ein automatischer Steuerfuss-Antrag entsteht (F88)');
        $a = $steuer[0];
        self::assertSame('pauschal', $a->getQuelle(), 'automatisch erzeugt → quelle «pauschal»');
        self::assertSame(-5.0, (float) $a->getProzentDelta(), '5 Prozentpunkte gesenkt');
        self::assertSame(-10000000, (int) $a->getBetragDelta(), 'Einnahmen sinken um den Überschuss (F88, 5a)');
        self::assertSame('einreichen', $a->getHaltung(), 'die Senkung wird eingereicht → erscheint im Antrags-PDF (5b)');
    }

    public function testSteuerfussGehtNieUnterNull(): void {
        // Regressionsfall zum −234%-Bug: ein Überschuss, der grösser ist als der
        // gesamte Steuerertrag, kann den Steuerfuss höchstens auf 0% senken — nie
        // darunter. (Steuerfuss 125, Steuerertrag 521,2 Mio → 1 Steuerprozent ≈
        // 4,17 Mio; ein Überschuss von 1,5 Mia entspräche ungedeckelt 359
        // Prozentpunkten.)
        $this->inserted = [];
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(521200000);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        // Kein Aufwand, 1,5 Mia Ertrag → Überschuss 1,5 Mia (> gesamter Steuerertrag).
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121', 0, 1500000000)]);
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
        $verteilungenMapper = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungenMapper->method('findeOderStandard')->willReturn($zielVerteilung);
        $verteilungenMapper->method('alleFuerJahr')->willReturn([$zielVerteilung]);
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );
        $service = new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungenMapper,
            $entscheide, $config, $this->createStub(ITimeFactory::class),
            $this->createStub(IUserSession::class), $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );
        $service->verteilungSetzen(2026, true, 'ertrag', 1500000000, 'einreichen', []);

        $steuer = $this->steuerfussAntraege();
        self::assertCount(1, $steuer, 'ein automatischer Steuerfuss-Antrag entsteht');
        $a = $steuer[0];
        // Höchstens die vollen 125 Prozentpunkte (auf 0%), nie mehr.
        self::assertSame(-125.0, (float) $a->getProzentDelta(), 'Senkung auf 0%, nicht darunter');
        self::assertGreaterThanOrEqual(0, 125 + (int) $a->getProzentDelta(), 'der effektive Steuerfuss bleibt ≥ 0');
        self::assertSame(-521200000, (int) $a->getBetragDelta(), 'die Einnahmen sinken höchstens um den gesamten Steuerertrag');
    }

    public function testManuellerSteuerfussAntragSchaltetAutomatikAus(): void {
        // Ein von Hand gestellter Steuerfuss-Antrag (quelle «manuell») unterdrückt
        // die Automatik — dann bestimmt der manuelle Antrag den Steuerfuss.
        $manuell = new BudgetAntrag();
        $manuell->setJahr(2026);
        $manuell->setBereich('steuerfuss');
        $manuell->setQuelle('manuell');
        $manuell->setProzentDelta(-2.0);
        $manuell->setBetragDelta(-4000000);
        $manuell->setHaltung('einreichen');
        $manuell->setPhase('fraktion');

        $service = $this->serviceMitUeberschuss([$manuell]);
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'einreichen', []);

        $automatisch = array_values(array_filter(
            $this->steuerfussAntraege(),
            static fn ($a) => $a->getQuelle() === 'pauschal'
        ));
        self::assertCount(0, $automatisch, 'kein automatischer Steuerfuss-Antrag bei manueller Kontrolle');
    }

    public function testAutomatischerAntragTraegtEigeneFraktionAlsAntragsteller(): void {
        // «Antragsteller fehlt!»: der automatische Steuerfussantrag ist ein Antrag der
        // eigenen Fraktion — ihr Name (Config «fraktion») steht als Antragsteller,
        // damit er im Antrags-PDF nicht leer bleibt.
        $store = ['fraktion' => 'Schweizerische Volkspartei-Fraktion (SVP)'];
        $service = $this->serviceMitUeberschuss([], $this->statefulConfig($store));
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'einreichen', []);

        $steuer = $this->steuerfussAntraege();
        self::assertCount(1, $steuer);
        self::assertSame(
            'Schweizerische Volkspartei-Fraktion (SVP)',
            (string) $steuer[0]->getAntragsteller(),
            'der automatische Steuerfussantrag trägt die eigene Fraktion als Antragsteller',
        );
    }

    public function testAusgeschalteteAutomatikErzeugtKeinenAntrag(): void {
        // F88: ist die Automatik für das Jahr ausgeschaltet, entsteht trotz Überschuss
        // KEIN automatischer Steuerfussantrag — der Steuerfuss bleibt beim Stadtratsantrag.
        $store = ['budget_steuerfuss_automatik_2026' => '0'];
        $service = $this->serviceMitUeberschuss([], $this->statefulConfig($store));
        self::assertFalse($service->steuerfussAutomatikAn(2026), 'Flag ist aus');
        $service->verteilungSetzen(2026, true, 'schwarze_null', 0, 'einreichen', []);
        self::assertCount(0, $this->steuerfussAntraege(), 'kein automatischer Antrag bei ausgeschalteter Automatik');
    }

    public function testAutomatikSetzenPersistiertUndRevertiertAufStadtratsantrag(): void {
        // F88: Ausschalten speichert das Flag pro Jahr (überlebt Reload) und erzeugt
        // beim Neurechnen keinen Auto-Antrag mehr (Steuerfuss zurück auf Stadtrats-
        // antrag); Einschalten bringt die automatische Senkung zurück.
        $store = [];
        $service = $this->serviceMitUeberschuss([], $this->statefulConfig($store));
        self::assertTrue($service->steuerfussAutomatikAn(2026), 'Default: eingeschaltet');

        $service->steuerfussAutomatikSetzen(2026, false);
        self::assertFalse($service->steuerfussAutomatikAn(2026), 'nach dem Ausschalten gespeichert (persistiert)');
        self::assertCount(0, $this->steuerfussAntraege(), 'ausgeschaltet → kein automatischer Antrag');

        $this->inserted = [];
        $service->steuerfussAutomatikSetzen(2026, true);
        self::assertTrue($service->steuerfussAutomatikAn(2026), 'wieder eingeschaltet');
        self::assertCount(1, $this->steuerfussAntraege(), 'eingeschaltet → automatischer Antrag zurück');
    }
}
