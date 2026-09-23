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
 * F116: Jede Produktegruppe trägt neben dem Wert des Stadtrats den Wert der
 * Fraktion — den Betrag, der nach unseren Anträgen übrig bleibt. Es gilt
 * dieselbe Regel wie für die Übersicht (F102): in der Vorbereitung zählt, was
 * die Fraktion unterstützt, in der Sitzung, was das Parlament angenommen hat.
 */
class BudgetPgFraktionswertTest extends TestCase {
    private function gruppe(string $code, int $globalkreditSoll, int $vorjahr, float $stellen = 10.0): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setName('Produktegruppe ' . $code);
        $g->setDepartement('Finanzen');
        $g->setGlobalkreditSoll($globalkreditSoll);
        $g->setGlobalkreditSollVorjahr($vorjahr);
        $g->setAufwandSoll($globalkreditSoll);
        $g->setAufwandSollVorjahr($vorjahr);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll($stellen);
        $g->setStellenSollVorjahr($stellen);
        return $g;
    }

    private function antrag(int $id, string $bereich, string $zielRef, int $betragDelta, string $haltung, string $phase = 'fraktion', float $stellenDelta = 0.0): BudgetAntrag {
        $a = new BudgetAntrag();
        $a->setId($id);
        $a->setJahr(2026);
        $a->setBereich($bereich);
        $a->setZielTyp('produktegruppe');
        $a->setZielRef($zielRef);
        $a->setBetragDelta($betragDelta);
        $a->setStellenDelta($stellenDelta);
        $a->setHerkunft('eigene');
        $a->setHaltung($haltung);
        $a->setPhase($phase);
        return $a;
    }

    /**
     * @param list<BudgetAntrag> $antraege
     * @param array<int, string> $entscheide
     */
    private function service(array $antraege, array $entscheide = []): BudgetService {
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(1000000);
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([
            $this->gruppe('121', 4950828, 4395510),
            $this->gruppe('142', 6206839, 5451141),
        ]);

        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);

        $antragMapper = $this->createStub(BudgetAntragMapper::class);
        $antragMapper->method('findByJahr')->willReturn($antraege);

        $entscheidMapper = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheidMapper->method('statusFuer')->willReturn($entscheide);

        $verteilung = new BudgetVerteilung();
        $verteilung->setAutomatikEin(0);
        $verteilungen = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungen->method('findeOderStandard')->willReturn($verteilung);
        $verteilungen->method('alleFuerJahr')->willReturn([]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );
        $notiz = $this->createStub(NotizService::class);
        $notiz->method('listeGruppiert')->willReturn([]);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antragMapper, $verteilungen,
            $entscheidMapper, $config, $this->createStub(ITimeFactory::class),
            $this->createStub(IUserSession::class),
            $this->createStub(RealtimePublisherService::class), $notiz,
        );
    }

    /** @return array<string, mixed> */
    private function gruppeAusAnsicht(array $ansicht, string $code): array {
        foreach ($ansicht['produktegruppen'] as $g) {
            if ((string) $g['code'] === $code) {
                return $g;
            }
        }
        self::fail('Produktegruppe ' . $code . ' fehlt in der Ansicht');
    }

    public function testUnterstuetzterAntragZiehtDenBetragDerFraktionAb(): void {
        $ansicht = $this->service([
            $this->antrag(1, 'globalbudget', '121', -247541, 'einreichen'),
            $this->antrag(2, 'globalbudget', '121', -990166, 'einreichen'),
        ])->ansicht(2026);

        $g = $this->gruppeAusAnsicht($ansicht, '121');
        self::assertSame(4950828, $g['globalkredit']['soll'], 'der Wert des Stadtrats bleibt, wie er vorgelegt wurde');
        self::assertSame(
            4950828 - 247541 - 990166,
            $g['globalkredit']['sollFraktion'],
            'der Wert der Fraktion ist der Betrag nach unseren Anträgen'
        );
    }

    public function testOhneAntragSindBeideWerteGleich(): void {
        $ansicht = $this->service([])->ansicht(2026);
        $g = $this->gruppeAusAnsicht($ansicht, '142');
        self::assertSame($g['globalkredit']['soll'], $g['globalkredit']['sollFraktion']);
        self::assertSame($g['stellen']['soll'], $g['stellen']['sollFraktion']);
    }

    public function testNichtUnterstuetzterAntragZaehltNicht(): void {
        // F102: in der Vorbereitung zählt nur, was die Fraktion unterstützt.
        $ansicht = $this->service([
            $this->antrag(1, 'globalbudget', '121', -247541, 'nicht_einreichen'),
        ])->ansicht(2026);

        $g = $this->gruppeAusAnsicht($ansicht, '121');
        self::assertSame(4950828, $g['globalkredit']['sollFraktion']);
    }

    public function testPersonalantragWirktAufBetragUndStellen(): void {
        $ansicht = $this->service([
            $this->antrag(1, 'personal', '121', -200000, 'einreichen', 'fraktion', -1.5),
        ])->ansicht(2026);

        $g = $this->gruppeAusAnsicht($ansicht, '121');
        self::assertSame(4950828 - 200000, $g['globalkredit']['sollFraktion'], 'eine gestrichene Stelle senkt auch den Betrag');
        self::assertEqualsWithDelta(8.5, $g['stellen']['sollFraktion'], 0.001);
    }

    public function testInDerSitzungZaehltNurDasAngenommene(): void {
        // F93/F102: im Sitzungsmodus zählt der Beschluss des Parlaments.
        $service = $this->service([
            $this->antrag(1, 'globalbudget', '121', -100000, 'einreichen', 'sitzung'),
            $this->antrag(2, 'globalbudget', '121', -900000, 'einreichen', 'sitzung'),
        ], [1 => 'angenommen', 2 => 'abgelehnt']);

        $g = $this->gruppeAusAnsicht($service->ansicht(2026, null, null, null, null, 'sitzung'), '121');
        self::assertSame(4950828 - 100000, $g['globalkredit']['sollFraktion']);
    }

    public function testAntragEinerAnderenProduktegruppeBleibtDort(): void {
        $ansicht = $this->service([
            $this->antrag(1, 'globalbudget', '121', -247541, 'einreichen'),
        ])->ansicht(2026);

        self::assertSame(6206839, $this->gruppeAusAnsicht($ansicht, '142')['globalkredit']['sollFraktion']);
    }
}
