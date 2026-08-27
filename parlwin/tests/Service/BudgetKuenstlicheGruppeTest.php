<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Künstliche Produktegruppe und Summen-Guard (F89): der echte Import über den
 * echten Parser muss Σ aller Produktegruppen (inkl. der künstlichen «Interne
 * Verrechnung / Abgrenzung») exakt auf das vom Stadtrat deklarierte Gesamtergebnis
 * bringen. Ohne die künstliche Produktegruppe ergibt die Summe der operativen
 * Produktegruppen 98,3 Mio statt 113,8 Mio — der Test ist dann rot (npnp-Beweis).
 * Gruppe «pdf»: braucht vendor/ (smalot).
 */
#[Group('pdf')]
class BudgetKuenstlicheGruppeTest extends TestCase {
    /** @var list<BudgetProduktegruppe> */
    private array $eingefuegt = [];
    private int $gesamtergebnis = 0;

    private function service(): BudgetImportService {
        $this->eingefuegt = [];
        $this->gesamtergebnis = 0;
        $jahre = $this->createStub(BudgetJahrMapper::class);
        $jahre->method('findByJahr')->willThrowException(new DoesNotExistException('neu'));
        $jahre->method('insert')->willReturnArgument(0);
        $jahre->method('update')->willReturnCallback(function ($row) {
            $this->gesamtergebnis = (int) $row->getGesamtergebnis();
            return $row;
        });
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('insert')->willReturnCallback(function ($g) {
            $this->eingefuegt[] = $g;
            return $g;
        });
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('insert')->willReturnArgument(0);
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $parser = new BudgetBuchParser();
        $time = new class implements ITimeFactory {
            public function getTime(): int {
                return 100;
            }
        };
        // Budget-Geschäft je Jahr (die Seiten-URL kodiert das Jahr, damit der
        // HTTP-Mock die richtigen Fixture-PDFs liefert).
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturnCallback(static function (int $jahr): Geschaeft {
            $g = new Geschaeft();
            $g->setUrl('https://test.local/budget/' . $jahr);
            return $g;
        });
        // HTTP-Client: liefert die Geschäft-Seite (mit Teil-A/B-Links) und die
        // echten Fixture-PDF-Bytes — der Parser parst also die echten Bücher über
        // den Live-Pfad neu (nie ein vorgeparstes Ergebnis).
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $url): IResponse {
            $resp = $this->createStub(IResponse::class);
            $resp->method('getBody')->willReturn($this->netzInhalt($url));
            return $resp;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $traktanden = $this->createStub(TraktandumMapper::class);
        $traktanden->method('findByGeschaeft')->willReturn([]);
        $sitzungen = $this->createStub(SitzungMapper::class);
        $drehbuch = new BudgetDrehbuchParser();
        return new BudgetImportService($jahre, $gruppen, $investitionen, $antraege, $parser, $time, $geschaefte, $clientService, $traktanden, $sitzungen, $drehbuch);
    }

    private function fixturePfad(int $jahr, string $teil): string {
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $jahr . '/teil-' . $teil . '.pdf';
    }

    /** Der «über das Netz geladene» Inhalt zu einer URL: Geschäft-Seite oder PDF-Bytes. */
    private function netzInhalt(string $url): string {
        if (preg_match('#/budget/(\d{4})$#', $url, $m) === 1) {
            $jahr = (int) $m[1];
            return '<html><body>'
                . '<a href="/_doc/teil-a-' . $jahr . '">2025.110W - Beilage 2: Teil A - Antrag (Budget und Finanzplan)</a>'
                . '<a href="/_doc/teil-b-' . $jahr . '">2025.110W - Beilage 3: Teil B - Antrag (Produktegruppen Globalbudgets)</a>'
                . '</body></html>';
        }
        if (preg_match('#/_doc/teil-([ab])-(\d{4})#', $url, $m) === 1) {
            $pfad = $this->fixturePfad((int) $m[2], $m[1]);
            return is_file($pfad) ? (string) file_get_contents($pfad) : '';
        }
        return '';
    }

    private function summeErgebnis(): int {
        $sum = 0;
        foreach ($this->eingefuegt as $g) {
            $sum += $g->getErtragSoll() - $g->getAufwandSoll();
        }
        return $sum;
    }

    /** @return list<BudgetProduktegruppe> */
    private function kuenstliche(): array {
        return array_values(array_filter($this->eingefuegt, static fn ($g) => (int) $g->getKuenstlich() === 1));
    }

    public function testKuenstlicheGruppeSchliesstDieSumme2026(): void {
        $this->service()->importiereJahr(2026);

        $kuenstliche = $this->kuenstliche();
        $this->assertCount(1, $kuenstliche, 'genau eine künstliche Produktegruppe');
        $k = $kuenstliche[0];
        $this->assertSame('Finanzen', $k->getDepartement(), 'künstliche Produktegruppe im Departement Finanzen');
        $this->assertSame('Interne Verrechnung / Abgrenzung', $k->getName());
        $this->assertSame('IV', $k->getCode());

        // Die operativen Produktegruppen allein ergeben 98,3 Mio — erst die künstliche
        // schliesst auf das deklarierte Gesamtergebnis.
        $operativ = 0;
        foreach ($this->eingefuegt as $g) {
            if ((int) $g->getKuenstlich() !== 1) {
                $operativ += $g->getErtragSoll() - $g->getAufwandSoll();
            }
        }
        $this->assertSame(98296708, $operativ, 'Σ nur operative Produktegruppen 2026');

        // Summen-Guard: Σ aller Produktegruppen (Ertrag − Aufwand) == deklariertes Gesamtergebnis.
        $this->assertSame(113800000, $this->gesamtergebnis, 'deklariertes Gesamtergebnis 2026 (113,8 Mio)');
        $this->assertSame($this->gesamtergebnis, $this->summeErgebnis(), 'Σ aller Produktegruppen ergibt das deklarierte Gesamtergebnis');
    }

    public function testSummenGuardUeberAlleJahrgaenge(): void {
        $geprueft = 0;
        foreach ([2022, 2023, 2024, 2025, 2026] as $jahr) {
            $this->service()->importiereJahr($jahr);
            if ($this->gesamtergebnis === 0 || \count($this->kuenstliche()) === 0) {
                // Jahrgang ohne geparste Teil-A-Totale — hier greift der Guard nicht.
                continue;
            }
            $this->assertCount(1, $this->kuenstliche(), "genau eine künstliche Produktegruppe für $jahr");
            $this->assertSame(
                $this->gesamtergebnis,
                $this->summeErgebnis(),
                "Σ aller Produktegruppen == deklariertes Gesamtergebnis für $jahr"
            );
            $geprueft++;
        }
        // Die drei jüngeren Bücher (2024–2026) tragen die Schlagzeile in der geparsten
        // Form; ältere (2022/2023) weichen im Aufbau ab und werden hier übersprungen.
        $this->assertGreaterThanOrEqual(3, $geprueft, 'Summen-Guard deckt mindestens drei Jahrgänge');
    }
}
