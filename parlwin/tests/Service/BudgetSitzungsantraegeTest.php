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
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Sitzungsanträge (F90): der Live-Abruf aus dem Drehbuch (Budget-Geschäft →
 * Traktandum → Budgetsitzung → Beilage «Drehbuch») und das Einbringen der
 * gelesenen Anträge als offizielle Sitzungsanträge (Phase «sitzung», Herkunft
 * «fremde», Quelle «sitzung»), inklusive Dedup beim erneuten Einlesen.
 */
class BudgetSitzungsantraegeTest extends TestCase {
    // ── Live-Abruf über die echte Drehbuch-Fixture ──────────────────────────

    /** Baut den Import-Service, dessen HTTP-Mock die Sitzungsseite und die echte Drehbuch-PDF liefert. */
    private function importService(): BudgetImportService {
        $geschaeft = new Geschaeft();
        $geschaeft->setId(5);
        $geschaeft->setTitel('Budget 2026 und Festsetzung des Steuerfusses');
        $geschaeft->setUrl('https://parlament.winterthur.ch/_rte/information/1');
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturn($geschaeft);

        $traktandum = new Traktandum();
        $traktandum->setSitzungId(9);
        $traktandum->setGeschaeftId(5);
        $traktanden = $this->createStub(TraktandumMapper::class);
        $traktanden->method('findByGeschaeft')->willReturn([$traktandum]);

        $sitzung = new Sitzung();
        $sitzung->setUrl('https://parlament.winterthur.ch/sitzung/9');
        $sitzungen = $this->createStub(SitzungMapper::class);
        $sitzungen->method('find')->willReturn($sitzung);

        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $resp = $this->createStub(IResponse::class);
            $resp->method('getBody')->willReturn($this->netzInhalt($url));
            return $resp;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);

        return new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class),
            $this->createStub(BudgetProduktegruppeMapper::class),
            $this->createStub(BudgetInvestitionMapper::class),
            $this->createStub(BudgetAntragMapper::class),
            $this->createStub(BudgetBuchParser::class),
            $this->createStub(ITimeFactory::class),
            $geschaefte,
            $clientService,
            $traktanden,
            $sitzungen,
            new BudgetDrehbuchParser(),
        );
    }

    /** Der «über das Netz geladene» Inhalt: Sitzungsseite (mit Drehbuch-Link) oder Drehbuch-PDF-Bytes. */
    private function netzInhalt(string $url): string {
        if (str_contains($url, '/sitzung/')) {
            return '<html><body>'
                . '<a href="/_doc/6122698">2025.110W - Beilage 3: Teil B</a>'
                . '<a href="/_doc/6398939">Kommissionsbeschlüsse Stadtparlament 8. Dezember 2025 inkl. Drehbuch zur Budgetbehandlung</a>'
                . '</body></html>';
        }
        if (str_contains($url, '/_doc/6398939')) {
            $pfad = \dirname(__DIR__, 1) . '/Fixtures/drehbuch/2026/drehbuch.pdf';
            return is_file($pfad) ? (string) file_get_contents($pfad) : '';
        }
        return '';
    }

    #[Group('pdf')]
    public function testSitzungsantraegeLiveAusDrehbuchGeladen(): void {
        // Der ganze Live-Pfad: findeBudgetWeisung → Traktandum → Sitzung.url →
        // Sitzungsseite → Drehbuch-Beilage → Parsen der echten PDF.
        $antraege = $this->importService()->sitzungsantraege(2026);
        self::assertCount(35, $antraege, 'alle Sitzungsanträge des Drehbuchs 2026 live geladen');
        $codes = array_map(static fn ($a) => (string) $a['code'], $antraege);
        self::assertContains('121', $codes);
        self::assertContains('865', $codes);
    }

    // ── Einbringen der Anträge (Persistenz + Dedup) ─────────────────────────

    /**
     * BudgetService mit einer array-gestützten Antrags-Ablage: insert() sammelt,
     * findByJahr() liefert das Gesammelte zurück (für den Dedup-Test).
     *
     * @param list<BudgetAntrag> $ablage
     */
    private function budgetService(array &$ablage): BudgetService {
        $jahrRow = new BudgetJahr();
        $jahrRow->setJahr(2026);
        $jahrRow->setSteuerfuss(125);
        $jahrRow->setSteuerertrag(1000000);
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willReturn($jahrRow);

        $gruppe = new BudgetProduktegruppe();
        $gruppe->setJahr(2026);
        $gruppe->setCode('121');
        $gruppe->setDepartement('Finanzen');
        $gruppe->setGlobalkreditSoll(1000000);
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$gruppe]);

        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturnCallback(static function () use (&$ablage): array {
            return $ablage;
        });
        $antraege->method('insert')->willReturnCallback(static function (BudgetAntrag $a) use (&$ablage): BudgetAntrag {
            $ablage[] = $a;
            return $a;
        });

        $verteilung = new BudgetVerteilung();
        $verteilung->setAutomatikEin(0);
        $verteilungen = $this->createStub(BudgetVerteilungMapper::class);
        $verteilungen->method('findeOderStandard')->willReturn($verteilung);
        $verteilungen->method('alleFuerJahr')->willReturn([]);

        $entscheide = $this->createStub(BudgetAntragEntscheidMapper::class);
        $entscheide->method('statusFuer')->willReturn([]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn (string $app, string $key, string $default = '') => $default
        );

        return new BudgetService(
            $jahre, $gruppen,
            $this->createStub(BudgetInvestitionMapper::class),
            $antraege, $verteilungen, $entscheide, $config,
            $this->createStub(ITimeFactory::class),
            $this->createStub(IUserSession::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );
    }

    /** @return list<array<string, mixed>> */
    private function geparst(): array {
        return [
            ['code' => '121', 'bereich' => 'globalbudget', 'antragsteller' => 'AK', 'gremium' => 'kommission', 'betragDelta' => 103000, 'begruendung' => 'HR-Tool', 'ergebnis' => '11:0 angenommen'],
            ['code' => '157', 'bereich' => 'globalbudget', 'antragsteller' => 'Fraktion SP', 'gremium' => 'fraktion', 'betragDelta' => 50000, 'begruendung' => 'Kunstankäufe', 'ergebnis' => ''],
        ];
    }

    public function testEinlesenLegtSitzungsantraegeAn(): void {
        $ablage = [];
        $neu = $this->budgetService($ablage)->sitzungsantraegeEinlesen(2026, $this->geparst());
        self::assertSame(2, $neu, 'beide Anträge neu angelegt');
        self::assertCount(2, $ablage);
        foreach ($ablage as $a) {
            self::assertSame('sitzung', $a->getPhase(), 'Sitzungsantrag in Phase «sitzung» (F93)');
            self::assertSame('sitzung', $a->getQuelle(), 'Quelle «sitzung»');
            self::assertSame('fremde', $a->getHerkunft(), 'offizielle Anträge sind «fremde»');
            self::assertSame('offen', $a->haltungOderStandard(), 'fremde Anträge stehen zunächst offen (F97)');
        }
        $ak = array_values(array_filter($ablage, static fn ($a) => $a->getBetragDelta() === 103000))[0];
        self::assertStringContainsString('11:0 angenommen', (string) $ak->getBegruendung(), 'Kommissionsergebnis in der Begründung vermerkt');
    }

    public function testErneutesEinlesenDupliziertNicht(): void {
        $ablage = [];
        $service = $this->budgetService($ablage);
        $service->sitzungsantraegeEinlesen(2026, $this->geparst());
        // Zweiter Lauf mit denselben Anträgen: nichts Neues, keine Dubletten.
        $neu = $service->sitzungsantraegeEinlesen(2026, $this->geparst());
        self::assertSame(0, $neu, 'gleich lautende Anträge werden nicht dupliziert');
        self::assertCount(2, $ablage, 'Bestand unverändert');
    }
}
