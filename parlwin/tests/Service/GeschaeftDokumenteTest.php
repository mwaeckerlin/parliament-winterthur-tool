<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Die amtlichen Dokumente eines Geschäfts stehen auf seiner Seite in einer
 * Tabelle: je Zeile der Verweis auf das PDF («/_doc/<id>»), seine Bezeichnung,
 * Grösse, das Dokumentdatum und die Kategorie («Vorstoss», «Antwort Stadtrat»,
 * «Beilage»). Gelesen wird gegen die echte Seite des Budgets 2027.
 */
class GeschaeftDokumenteTest extends TestCase
{
    private function scraper(): ScraperService
    {
        return new ScraperService(
            $this->createStub(IClientService::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(IConfig::class),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function dokumente(): array
    {
        $html = (string) file_get_contents(\dirname(__DIR__, 1) . '/Fixtures/html/geschaeft-detail-2990959.html');
        $details = $this->scraper()->extrahiereGeschaeftDetailsAusHtml($html);
        return $details['dokumente'] ?? [];
    }

    public function testJedesDokumentDerSeiteWirdGelesen(): void
    {
        $dokumente = $this->dokumente();
        self::assertCount(5, $dokumente, 'die Seite führt fünf Dokumente');
        self::assertSame(
            [
                '2026.95W - Antrag inkl. Beilage 1',
                '2026.95W - Beilage 1: Stellenplanveränderungen',
                '2026.95W - Beilage 2: Teil A (Antrag) - Budget und Finanzplan',
                '2026.95W - Beilage 3: Teil B (Antrag) - Produktegruppen Globalbudgets',
                'Medienmitteilung Budget 2027',
            ],
            array_column($dokumente, 'titel'),
        );
    }

    public function testEinDokumentTraegtHerkunftKategorieUndDatum(): void
    {
        $erstes = $this->dokumente()[0] ?? [];
        self::assertSame('7242601', $erstes['externId'] ?? '', 'die Nummer des Dokuments');
        self::assertSame('https://parlament.winterthur.ch/_doc/7242601', $erstes['url'] ?? '');
        self::assertSame('Antrag Stadtrat', $erstes['kategorie'] ?? '');
        self::assertSame('2026-09-16', $erstes['datum'] ?? '', 'das Dokumentdatum in ISO-Form');
    }

    public function testKategorienStehenJeDokument(): void
    {
        self::assertSame(
            ['Antrag Stadtrat', 'Beilage', 'Beilage', 'Beilage', 'Pressemitteilung'],
            array_column($this->dokumente(), 'kategorie'),
        );
    }
}
