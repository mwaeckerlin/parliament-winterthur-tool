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
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * F76: Der Budget-Filter «zuständige Kommission» löst über die
 * verwaltungsseitige Departement→Kommission-Zuordnung auf die betroffenen
 * Departemente auf und schränkt die Ansicht darauf ein.
 */
class BudgetServiceTest extends TestCase {
    private function gruppe(string $code, string $name, string $departement): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setName($name);
        $g->setDepartement($departement);
        $g->setAufwandSoll(1000);
        $g->setAufwandSollVorjahr(900);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll(0);
        $g->setStellenSollVorjahr(0);
        return $g;
    }

    /**
     * @param array<int, \OCA\ParliamentWinterthur\Db\BudgetAntrag> $antraegeListe
     * @param array<int, string> $statusMap
     */
    private function service(string $zuordnungJson, array $antraegeListe = [], array $statusMap = []): BudgetService {
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(0);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([
            $this->gruppe('121', 'Personalamt', 'Präsidiales'),
            $this->gruppe('221', 'Finanzamt', 'Finanzen'),
        ]);
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn($antraegeListe);
        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn($statusMap);
        $verteilungen = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungen->method('findeOderStandard')->willReturn(new BudgetVerteilung());

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') =>
                $key === 'budget_kommission_zuordnung' ? $zuordnungJson : $default
        );
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungen,
            $entscheide, $config, $time, $userSession, $realtime,
        );
    }

    public function testKommissionFilterLoestAufDepartementeAuf(): void {
        $zuordnung = json_encode([
            ['departement' => 'Präsidiales', 'kommission' => 'SK Präsidiales'],
            ['departement' => 'Finanzen', 'kommission' => 'SK Finanzen'],
        ]);
        $ansicht = $this->service($zuordnung)->ansicht(2026, null, null, null, 'SK Präsidiales');

        $codes = array_map(static fn ($g) => $g['code'], $ansicht['produktegruppen']);
        self::assertSame(['121'], $codes, 'Kommissionsfilter zeigt nur Departement Präsidiales');
        self::assertSame($zuordnung, json_encode($ansicht['kommissionZuordnung']), 'Zuordnung wird mitgeliefert');
    }

    public function testOhneFilterAlleDepartemente(): void {
        $ansicht = $this->service('[]')->ansicht(2026);
        $codes = array_map(static fn ($g) => $g['code'], $ansicht['produktegruppen']);
        self::assertSame(['121', '221'], $codes, 'ohne Filter alle Produktegruppen');
    }

    private function antrag(int $id, string $zielRef, int $betragDelta, string $phase): \OCA\ParliamentWinterthur\Db\BudgetAntrag {
        $a = new \OCA\ParliamentWinterthur\Db\BudgetAntrag();
        $a->setId($id);
        $a->setJahr(2026);
        $a->setBereich('globalbudget');
        $a->setZielTyp('produktegruppe');
        $a->setZielRef($zielRef);
        $a->setBetragDelta($betragDelta);
        $a->setStellenDelta(0.0);
        $a->setPhase($phase);
        return $a;
    }

    public function testPhaseTrenntDieSummen(): void {
        // Fraktionsantrag (Vorbereitung) und ein angenommener Sitzungsantrag.
        $antraege = [
            $this->antrag(1, '121', -100000, 'fraktion'),
            $this->antrag(2, '121', -50000, 'sitzung'),
        ];
        $status = [2 => 'angenommen'];
        $service = $this->service('[]', $antraege, $status);

        $fraktion = $service->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        $sitzung = $service->ansicht(2026, null, null, null, null, 'sitzung')['summen'];
        self::assertNotSame($fraktion['ausgaben'], $sitzung['ausgaben'], 'Fraktions- und Sitzungsphase zählen verschiedene Anträge');
    }

    public function testNichtAngenommenerSitzungsantragZaehltNicht(): void {
        // Ein offener Sitzungsantrag verändert die Sitzungssumme nicht.
        $mitAntrag = $this->service('[]', [$this->antrag(2, '121', -50000, 'sitzung')], [2 => 'offen'])
            ->ansicht(2026, null, null, null, null, 'sitzung')['summen'];
        $ohneAntrag = $this->service('[]', [], [])
            ->ansicht(2026, null, null, null, null, 'sitzung')['summen'];
        self::assertSame($ohneAntrag['ausgaben'], $mitAntrag['ausgaben'], 'nur angenommene Sitzungsanträge zählen');
    }
}
