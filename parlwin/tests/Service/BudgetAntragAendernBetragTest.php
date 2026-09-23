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
 * F117: Wer einen Antrag durch einen Klick in seine Zeile öffnet und dort den
 * Betrag ändert, dessen neuer Betrag muss gespeichert werden. Das Formular
 * schickt Betrag UND Prozent — den Prozentwert unverändert, weil nur am Betrag
 * gedreht wurde. Gewinnt dann der alte Prozentsatz, rechnet der Dienst den
 * alten Betrag zurück, und die Änderung verschwindet ohne Fehlermeldung.
 */
class BudgetAntragAendernBetragTest extends TestCase {
    private const BASIS = 4950828;

    private function gruppe(): BudgetProduktegruppe {
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

    private function service(BudgetAntrag $bestand): BudgetService {
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
        $antragMapper->method('findeAntrag')->willReturn($bestand);
        $antragMapper->method('findByJahr')->willReturn([$bestand]);
        $antragMapper->method('update')->willReturnArgument(0);

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

    private function bestehenderAntrag(): BudgetAntrag {
        $a = new BudgetAntrag();
        $a->setId(10);
        $a->setJahr(2026);
        $a->setBereich('globalbudget');
        $a->setZielTyp('produktegruppe');
        $a->setZielRef('121');
        $a->setBetragDelta(-70000);
        // Der Dienst hat den Prozentsatz beim Anlegen aus dem Betrag errechnet.
        $a->setProzentDelta(-70000 / self::BASIS * 100);
        $a->setStellenDelta(0.0);
        $a->setHerkunft('eigene');
        $a->setHaltung('einreichen');
        $a->setPhase('fraktion');
        return $a;
    }

    public function testGeaenderterBetragGewinntGegenDenUnveraendertenProzentsatz(): void {
        $a = $this->bestehenderAntrag();
        $altesProzent = (float) $a->getProzentDelta();

        // Genau das, was das Formular beim Speichern schickt: der neue Betrag und
        // der Prozentsatz, wie er vorbelegt war.
        $geaendert = $this->service($a)->antragAendern(10, [
            'betragDelta' => -90000,
            'prozentDelta' => $altesProzent,
            'haltung' => 'einreichen',
        ]);

        self::assertSame(-90000, (int) $geaendert->getBetragDelta(), 'der geänderte Betrag wird nicht gespeichert (F117)');
        self::assertEqualsWithDelta(
            -90000 / self::BASIS * 100,
            (float) $geaendert->getProzentDelta(),
            0.0001,
            'der Prozentsatz folgt dem neuen Betrag nicht (F117)'
        );
    }
}
