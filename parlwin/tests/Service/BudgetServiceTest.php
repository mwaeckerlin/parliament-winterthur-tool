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
        $verteilungen->method('alleFuerJahr')->willReturn([]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') =>
                $key === 'budget_kommission_zuordnung' ? $zuordnungJson : $default
        );
        $time = $this->createStub(ITimeFactory::class);
        $userSession = $this->createStub(IUserSession::class);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $notiz = $this->createStub(NotizService::class);

        return new BudgetService(
            $jahre, $gruppen, $investitionen, $antraege, $verteilungen,
            $entscheide, $config, $time, $userSession, $realtime, $notiz,
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

    public function testFilterAnstiegNutztGlobalkreditNichtAufwand(): void {
        // «Anstieg ab CHF/%» filtert auf den ANGEZEIGTEN Globalkredit-Anstieg
        // (soll − sollVorjahr), nicht auf den Aufwand: negative und zu kleine
        // Anstiege werden ausgeschlossen, auch wenn der Aufwand kräftig steigt.
        $svc = $this->service('[]');
        $filter = new \ReflectionMethod($svc, 'trifftFilter');
        $mach = static function (int $vorjahr, int $soll): BudgetProduktegruppe {
            $g = new BudgetProduktegruppe();
            $g->setGlobalkreditSollVorjahr($vorjahr);
            $g->setGlobalkreditSoll($soll);
            // Aufwand bewusst stark steigend, um zu beweisen, dass er NICHT zählt.
            $g->setAufwandSollVorjahr(0);
            $g->setAufwandSoll(1_000_000);
            return $g;
        };

        // Absolut: +555'318 ≥ 100'000 → sichtbar.
        self::assertTrue($filter->invoke($svc, $mach(4395510, 4950828), null, null, 100000));
        // Negativer Anstieg (−699'192) → NICHT sichtbar (Informatikdienste-Fall).
        self::assertFalse($filter->invoke($svc, $mach(2018000, 1318808), null, null, 100000));
        // Zu kleiner Anstieg (+52'172 < 100'000) → NICHT sichtbar (Schutz-Fall).
        self::assertFalse($filter->invoke($svc, $mach(11543188, 11595360), null, null, 100000));

        // Prozentual: +12.6% ≥ 10% sichtbar; negativ und zu klein nicht.
        self::assertTrue($filter->invoke($svc, $mach(4395510, 4950828), null, 10.0, null));
        self::assertFalse($filter->invoke($svc, $mach(2018000, 1318808), null, 10.0, null));
        self::assertFalse($filter->invoke($svc, $mach(1000000, 1050000), null, 10.0, null));
    }

    private function antrag(int $id, string $zielRef, int $betragDelta, string $phase, string $herkunft = 'eigene', string $haltung = ''): \OCA\ParliamentWinterthur\Db\BudgetAntrag {
        $a = new \OCA\ParliamentWinterthur\Db\BudgetAntrag();
        $a->setId($id);
        $a->setJahr(2026);
        $a->setBereich('globalbudget');
        $a->setZielTyp('produktegruppe');
        $a->setZielRef($zielRef);
        $a->setBetragDelta($betragDelta);
        $a->setStellenDelta(0.0);
        $a->setPhase($phase);
        $a->setHerkunft($herkunft);
        $a->setHaltung($haltung);
        return $a;
    }

    public function testNichtUnterstuetzterFraktionsantragZaehltNichtInSumme(): void {
        // F102: ein eigener Antrag «nicht einreichen» beeinflusst die korrigierte
        // Summe nicht — nur Unterstütztes zählt.
        $mit = $this->service('[]', [$this->antrag(1, '121', -100000, 'fraktion', 'eigene', 'nicht_einreichen')], [])
            ->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        $ohne = $this->service('[]', [], [])->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        self::assertSame($ohne['ausgaben'], $mit['ausgaben'], 'nicht eingereichte eigene Anträge zählen nicht (F102)');
    }

    public function testUnterstuetzterFraktionsantragZaehltInSumme(): void {
        // F102: ein eigener Antrag «einreichen» und ein fremder «unterstuetzen» zählen.
        $eigen = $this->service('[]', [$this->antrag(1, '121', -100000, 'fraktion', 'eigene', 'einreichen')], [])
            ->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        $fremd = $this->service('[]', [$this->antrag(1, '121', -100000, 'fraktion', 'fremde', 'unterstuetzen')], [])
            ->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        $ohne = $this->service('[]', [], [])->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        self::assertSame($ohne['ausgaben'] - 100000, $eigen['ausgaben'], 'eingereichter eigener Antrag senkt die Ausgaben (F102)');
        self::assertSame($ohne['ausgaben'] - 100000, $fremd['ausgaben'], 'unterstützter fremder Antrag senkt die Ausgaben (F102)');
    }

    public function testOffenerFremderAntragZaehltNicht(): void {
        // F102: ein fremder Antrag ohne Haltung (offen) zählt nicht.
        $mit = $this->service('[]', [$this->antrag(1, '121', -100000, 'fraktion', 'fremde', '')], [])
            ->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        $ohne = $this->service('[]', [], [])->ansicht(2026, null, null, null, null, 'fraktion')['summen'];
        self::assertSame($ohne['ausgaben'], $mit['ausgaben'], 'offene fremde Anträge zählen nicht (F102)');
    }

    public function testVerknuepfungAutomatischBeiEindeutigkeit(): void {
        // F104: gleiche Position und gleicher Betrag, je genau ein freier Kandidat
        // → automatische Verknüpfung, Haltung wandert in die Sitzung.
        $f = $this->antrag(1, '121', -100000, 'fraktion', 'eigene', 'einreichen');
        $s = $this->antrag(2, '121', -100000, 'sitzung', 'eigene', '');
        $service = $this->service('[]', [$f, $s], []);
        $service->verteilungSetzen(2026, false, 'schwarze_null', 0); // löst nachAenderung aus
        self::assertSame(1, $s->getVerknuepftMitId(), 'Sitzungsantrag verweist auf den Vorbereitungsantrag (F104)');
        self::assertSame(2, $f->getVerknuepftMitId(), 'Gegenrichtung ebenfalls gesetzt');
        self::assertSame('einreichen', $s->getHaltung(), 'Haltung wird in die Sitzung übernommen (F104)');
    }

    public function testKeineVerknuepfungBeiMehrdeutigkeit(): void {
        // F104: zwei gleich passende Vorbereitungsanträge → nicht eindeutig, keine Verknüpfung.
        $f1 = $this->antrag(1, '121', -100000, 'fraktion', 'eigene', 'einreichen');
        $f2 = $this->antrag(3, '121', -100000, 'fraktion', 'eigene', 'einreichen');
        $s = $this->antrag(2, '121', -100000, 'sitzung', 'eigene', '');
        $service = $this->service('[]', [$f1, $f2, $s], []);
        $service->verteilungSetzen(2026, false, 'schwarze_null', 0);
        self::assertSame(0, $s->getVerknuepftMitId(), 'mehrdeutige Kandidaten bleiben unverknüpft (F104)');
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

    public function testBudgetFraktionsdatenLeerenLoeschtAllesUnwiederbringlich(): void {
        // Frontend-Re-Import (F91): löscht Anträge samt Notizen und Entscheiden
        // sowie Pauschalanträge des Jahres hart.
        $antraege = $this->createMock(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([
            $this->antrag(1, '121', -100000, 'fraktion'),
            $this->antrag(2, '221', -50000, 'fraktion'),
        ]);
        $antraege->expects(self::once())->method('deleteByJahr')->with(2026);
        $verteilungen = $this->createMock(BudgetVerteilungMapper::class);
        $verteilungen->expects(self::once())->method('deleteByJahr')->with(2026);
        $entscheide = $this->createMock(BudgetAntragEntscheidMapper::class);
        $entscheide->expects(self::once())->method('deleteByAntraege')->with([1, 2]);
        $notiz = $this->createMock(NotizService::class);
        $notiz->expects(self::once())->method('alleLoeschen')->with('budget-antrag', [1, 2]);

        $service = new BudgetService(
            $this->createStub(BudgetJahrMapper::class),
            $this->createStub(BudgetProduktegruppeMapper::class),
            $this->createStub(BudgetInvestitionMapper::class),
            $antraege,
            $verteilungen,
            $entscheide,
            $this->createStub(IConfig::class),
            $this->createStub(ITimeFactory::class),
            $this->createStub(IUserSession::class),
            $this->createStub(RealtimePublisherService::class),
            $notiz,
        );
        $service->budgetFraktionsdatenLeeren(2026);
    }
}
