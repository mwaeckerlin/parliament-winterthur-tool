<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetAntragEntscheidMapper;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
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
 * Feld- und Betragslogik beim Anlegen eines Antrags:
 *  - F94  Herkunft «eigene»/«fremde»
 *  - F95  Betrag in CHF und Prozent, wechselseitig aus dem Budgetwert der Position
 *  - F96  Steuerfuss-Antrag in Prozentpunkten → abgeleiteter Ertrag-Effekt
 *  - F97  Haltung nach Herkunft
 *  - F98  unterstützende Fraktionen
 */
class BudgetAntragErstellenTest extends TestCase {
    private function gruppe(string $code, int $globalkreditSoll): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setDepartement('Finanzen');
        $g->setGlobalkreditSoll($globalkreditSoll);
        $g->setAufwandSoll($globalkreditSoll);
        $g->setAufwandSollVorjahr($globalkreditSoll);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll(0);
        $g->setStellenSollVorjahr(0);
        return $g;
    }

    /** Service mit Insert-Durchreichung; Automatik aus, damit nur der Antrag entsteht. */
    private function service(?NotizService $notiz = null): BudgetService {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(1000000);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121', 100000)]);

        $inv = new BudgetInvestition();
        $inv->setId(7);
        $inv->setJahr(2026);
        $inv->setDepartement('Finanzen');
        $inv->setBu(500000);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([$inv]);

        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([]);
        $antraege->method('insert')->willReturnArgument(0);

        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn([]);

        $verteilung = new BudgetVerteilung();
        $verteilung->setAutomatikEin(0);
        $verteilungen = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungen->method('findeOderStandard')->willReturn($verteilung);
        $verteilungen->method('alleFuerJahr')->willReturn([]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $notiz = $notiz ?? $this->createStub(NotizService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungen,
            $entscheide, $config, $time, $userSession, $realtime, $notiz,
        );
    }

    public function testProzentAusChfBerechnet(): void {
        // F95: −20'000 CHF auf ein Budget von 100'000 → −20%.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -20000]);
        self::assertSame(-20000, $a->getBetragDelta());
        self::assertEqualsWithDelta(-20.0, $a->getProzentDelta(), 0.001, 'Prozent folgt aus dem Budgetwert der Position (F95)');
    }

    public function testChfAusProzentBerechnet(): void {
        // F95: −20% auf ein Budget von 100'000 → −20'000 CHF.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'prozentDelta' => -20]);
        self::assertSame(-20000, $a->getBetragDelta(), 'CHF folgt aus dem Prozentwert (F95)');
        self::assertEqualsWithDelta(-20.0, $a->getProzentDelta(), 0.001);
    }

    public function testInvestitionProzentBasisIstBudgetwert(): void {
        // F95: Prozent bei Investitionen bezieht sich auf den Budgetwert (bu).
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'investition', 'zielTyp' => 'investition', 'zielRef' => '7', 'prozentDelta' => -10]);
        self::assertSame(-50000, $a->getBetragDelta(), '−10% von 500\'000 = −50\'000 (F95)');
    }

    public function testSteuerfussProzentpunkteErtragEffekt(): void {
        // F96: −2 Prozentpunkte bei Steuerfuss 125% → Ertrag × 123/125,
        // Delta = −2 × Ertrag/125 = −16'000.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'steuerfuss', 'zielTyp' => 'steuerfuss', 'prozentDelta' => -2]);
        self::assertEqualsWithDelta(-2.0, $a->getProzentDelta(), 0.001, 'Prozentpunkte werden geführt');
        self::assertSame(-16000, $a->getBetragDelta(), 'Ertrag-Effekt = Ertrag × Prozentpunkte / Steuerfuss (F96)');
    }

    public function testHerkunftUndHaltungStandard(): void {
        // F94/F97: Standard-Herkunft «eigene», Standard-Haltung «einreichen».
        $eigen = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000]);
        self::assertSame('eigene', $eigen->getHerkunft());
        self::assertSame('einreichen', $eigen->haltungOderStandard());
        // Fremd ohne Haltung → «offen».
        $fremd = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000, 'herkunft' => 'fremde']);
        self::assertSame('fremde', $fremd->getHerkunft());
        self::assertSame('offen', $fremd->haltungOderStandard());
    }

    public function testHaltungWirdAufHerkunftBereinigt(): void {
        // F97: eine für die Herkunft unpassende Haltung fällt auf den Standard zurück.
        $a = $this->service()->antragErstellen(2026, ['bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000, 'herkunft' => 'eigene', 'haltung' => 'unterstuetzen']);
        self::assertSame('einreichen', $a->haltungOderStandard(), 'ungültige Haltung → Herkunfts-Standard (F97)');
    }

    public function testNotizHinzufuegenNutztBudgetAntragTyp(): void {
        // F103: Notizen laufen über den geteilten NotizService mit dem Objekttyp
        // «budget-antrag» — dieselbe Mechanik wie beim Vorstoss.
        $notiz = $this->createStub(NotizService::class);
        $erfasst = [];
        $notiz->method('hinzufuegen')->willReturnCallback(function ($typ, $id, $text) use (&$erfasst) {
            $erfasst = [$typ, $id, $text];
            return ['id' => 1, 'text' => $text];
        });
        $this->service($notiz)->notizHinzufuegen(42, 'Bitte kürzen');
        self::assertSame(['budget-antrag', 42, 'Bitte kürzen'], $erfasst, 'Notiz delegiert an den geteilten Dienst (F103)');
    }

    public function testUnterstuetzerFraktionenGespeichert(): void {
        // F98: die Liste der unterstützenden Fraktionen wird als [{key,name}] gespeichert.
        $a = $this->service()->antragErstellen(2026, [
            'bereich' => 'globalbudget', 'zielRef' => '121', 'betragDelta' => -1000,
            'unterstuetzer' => ['Grüne', 'SP'],
        ]);
        $namen = array_map(static fn ($f) => $f['name'], $a->getUnterstuetzerArray());
        self::assertSame(['Grüne', 'SP'], $namen, 'unterstützende Fraktionen gespeichert (F98)');
    }
}
