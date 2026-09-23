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
 * F93: In der Sitzung entstehen offizielle Sitzungsanträge neben den
 * Fraktionsanträgen derselben Produktegruppe. Beide dürfen nebeneinander
 * bestehen — die Kürzungsgrenze vergleicht je Phase.
 */
class SitzungsantragTest extends TestCase
{
    private const BASIS = 4950828;

    /** @var list<BudgetAntrag> */
    private array $gespeicherte = [];

    private function gruppe(): BudgetProduktegruppe
    {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode('121');
        $g->setName('Personalamt');
        $g->setDepartement('Präsidiales');
        $g->setGlobalkreditSoll(self::BASIS);
        $g->setGlobalkreditSollVorjahr(4395510);
        $g->setAufwandSoll(self::BASIS);
        $g->setAufwandSollVorjahr(4395510);
        $g->setErtragSoll(0);
        $g->setErtragSollVorjahr(0);
        $g->setStellenSoll(19.5);
        $g->setStellenSollVorjahr(16.85);
        return $g;
    }

    private function service(): BudgetService
    {
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(1000000);
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$this->gruppe()]);

        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([]);

        $antragMapper = $this->createStub(BudgetAntragMapper::class);
        $antragMapper->method('findByJahr')->willReturnCallback(fn (): array => $this->gespeicherte);
        $antragMapper->method('insert')->willReturnCallback(function (BudgetAntrag $a): BudgetAntrag {
            $a->setId(count($this->gespeicherte) + 1);
            $this->gespeicherte[] = $a;
            return $a;
        });

        $entscheidMapper = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheidMapper->method('statusFuer')->willReturn([]);

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

    public function testSitzungsantragEntstehtNebenDemFraktionsantrag(): void
    {
        $dienst = $this->service();

        $fraktion = $dienst->antragErstellen(2026, [
            'bereich' => 'globalbudget',
            'zielTyp' => 'produktegruppe',
            'zielRef' => '121',
            'betragDelta' => -11000,
            'begruendung' => 'Fraktionsantrag',
            'phase' => 'fraktion',
            'haltung' => 'einreichen',
        ]);
        self::assertSame(-11000, (int) $fraktion->getBetragDelta());

        // Genau der Weg des Browser-Tests: derselbe Zielort, andere Phase.
        $sitzung = $dienst->antragErstellen(2026, [
            'bereich' => 'globalbudget',
            'zielTyp' => 'produktegruppe',
            'zielRef' => '121',
            'betragDelta' => -12000,
            'begruendung' => 'Sitzungsantrag',
            'phase' => 'sitzung',
            'haltung' => 'einreichen',
        ]);

        self::assertSame(-12000, (int) $sitzung->getBetragDelta(), 'der Sitzungsantrag wurde nicht angelegt (F93)');
        self::assertSame('sitzung', (string) $sitzung->getPhase());
        self::assertCount(2, $this->gespeicherte, 'beide Anträge müssen nebeneinander bestehen');
    }
}
