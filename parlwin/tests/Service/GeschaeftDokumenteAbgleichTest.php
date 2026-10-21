<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Abgleich trägt die Dokumente eines Geschäfts nach (F121): Was auf der
 * Geschäftsseite steht, wird gelesen und abgelegt. Und weil die Dokumente in
 * die Prüfsumme der Quelle eingehen, holt der Abgleich sie auch bei einem
 * längst erledigten Geschäft ein einziges Mal nach.
 */
class GeschaeftDokumenteAbgleichTest extends TestCase
{
    /** @var array<int, list<array<string, mixed>>> */
    private array $abgeglichen = [];

    /** @return array<string, mixed> */
    private function rohdaten(): array
    {
        return [
            'id' => '2777407',
            'number' => '2026.15',
            'title' => 'Zweck der Immobilien im Finanzhaushalt',
            'status' => 'Erledigt',
            'url' => 'https://parlament.winterthur.ch/_rte/information/2777407',
            'dokumente' => [[
                'externId' => '6821653',
                'titel' => '2026.15V',
                'url' => 'https://parlament.winterthur.ch/_doc/6821653',
                'kategorie' => 'Vorstoss',
                'datum' => '2026-03-02',
            ]],
        ];
    }

    private function dienst(GeschaeftMapper $mapper): GeschaeftService
    {
        $scraper = $this->createStub(ScraperService::class);
        $scraper->method('ladeGeschaefte')->willReturn([$this->rohdaten()]);

        $dokumente = $this->createStub(GeschaeftDokumentService::class);
        $dokumente->method('aktualisiere')->willReturnCallback(
            function (int $geschaeftId, array $daten): int {
                $this->abgeglichen[$geschaeftId] = $daten;
                return \count($daten);
            },
        );

        return new GeschaeftService(
            $mapper,
            $this->createStub(VorstossEntwurfMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $scraper,
            $this->createStub(LoggerInterface::class),
            $dokumente,
        );
    }

    private function bestand(string $hash): Geschaeft
    {
        $geschaeft = new Geschaeft();
        $geschaeft->setId(2777407);
        $geschaeft->setExternId('2777407');
        $geschaeft->setNummer('2026.15');
        $geschaeft->setStatus('Erledigt');
        $geschaeft->setQuelleHash($hash);
        return $geschaeft;
    }

    private function mapperMit(Geschaeft $bestand): GeschaeftMapper
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findByExternId')->willReturn($bestand);
        $mapper->method('update')->willReturnArgument(0);
        $mapper->method('markiereNichtMehrVorhandeneAlsGeloescht')->willReturn(0);
        return $mapper;
    }

    public function testDerAbgleichTraegtDieDokumenteNach(): void
    {
        $this->dienst($this->mapperMit($this->bestand('veraltet')))->synchronisieren();
        self::assertArrayHasKey(2777407, $this->abgeglichen, 'die Dokumente des Geschäfts werden abgeglichen');
        self::assertSame('6821653', $this->abgeglichen[2777407][0]['externId']);
    }

    public function testEinErledigtesGeschaeftOhneDokumenteWirdEinmalNachgezogen(): void
    {
        // Der gespeicherte Hash stammt aus der Zeit vor den Dokumenten. Träge
        // sie die Prüfsumme nicht, bliebe das Geschäft für immer ohne seinen
        // Inhalt stehen, weil der Abgleich es als unverändert überspringt.
        $ohneDokumente = new GeschaeftService(
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(VorstossEntwurfMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $this->createStub(ScraperService::class),
            $this->createStub(LoggerInterface::class),
        );
        $daten = $this->rohdaten();
        $hashMit = $this->quellversion($ohneDokumente, $daten);
        unset($daten['dokumente']);
        $hashOhne = $this->quellversion($ohneDokumente, $daten);
        self::assertNotSame($hashOhne, $hashMit, 'die Dokumente gehören in die Prüfsumme der Quelle');

        $this->dienst($this->mapperMit($this->bestand($hashOhne)))->synchronisieren();
        self::assertArrayHasKey(2777407, $this->abgeglichen);
    }

    /** @param array<string, mixed> $daten */
    private function quellversion(GeschaeftService $dienst, array $daten): string
    {
        $methode = new \ReflectionMethod($dienst, 'berechneQuellversion');
        return (string) $methode->invoke($dienst, $daten);
    }
}
