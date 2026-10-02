<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\DokumenteLesenCommand;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Der Befehl, der die amtlichen Dokumente nachliest (F121). Ein Betreiber ruft
 * ihn auf, wenn er nicht auf den stündlichen Auftrag warten will, und der
 * Testlauf im laufenden System benutzt ihn, damit seine Messung nicht davon
 * abhängt, wie weit der Abgleich zufällig gekommen ist.
 */
class DokumenteLesenCommandTest extends TestCase
{
    private function befehl(GeschaeftDokumentService $dienst): DokumenteLesenCommand
    {
        return new DokumenteLesenCommand($dienst);
    }

    private function eingabe(string $anzahl, bool $auchGescheiterte = false): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getOption')->willReturnCallback(
            static fn (string $name): string|bool => $name === 'auch-gescheiterte' ? $auchGescheiterte : $anzahl,
        );
        return $input;
    }

    /** @var array<int, string> */
    private array $zeilen = [];

    private function ausgabe(): OutputInterface
    {
        $this->zeilen = [];
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')->willReturnCallback(function (string $zeile): void {
            $this->zeilen[] = $zeile;
        });
        return $output;
    }

    public function testLiestDieAngefordertenDokumente(): void
    {
        $gefragt = null;
        $dienst = $this->createStub(GeschaeftDokumentService::class);
        $dienst->method('leseOffene')->willReturnCallback(function (?int $anzahl) use (&$gefragt): int {
            $gefragt = $anzahl;
            return 7;
        });

        $code = $this->befehl($dienst)->run($this->eingabe('12'), $this->ausgabe());

        self::assertSame(0, $code);
        self::assertSame(12, $gefragt, 'die Zahl aus dem Aufruf kommt beim Dienst an');
        self::assertStringContainsString('7 von höchstens 12', implode(' ', $this->zeilen));
    }

    public function testEineUnsinnigeZahlWirdAufMindestensEinsGehoben(): void
    {
        $gefragt = null;
        $dienst = $this->createStub(GeschaeftDokumentService::class);
        $dienst->method('leseOffene')->willReturnCallback(function (?int $anzahl) use (&$gefragt): int {
            $gefragt = $anzahl;
            return 0;
        });

        $this->befehl($dienst)->run($this->eingabe('-5'), $this->ausgabe());
        self::assertSame(1, $gefragt);
    }

    /**
     * Ein Dokument, das an einer Grenze gescheitert ist, trägt seine Prüfsumme
     * und wird nie wieder angefasst. Hebt der Betrieb die Grenze an, holt dieser
     * Schalter es zurück.
     */
    public function testMitDemSchalterWerdenGescheiterteWiederGelesen(): void
    {
        $vergessen = 0;
        $dienst = $this->createStub(GeschaeftDokumentService::class);
        $dienst->method('vergisseGescheiterte')->willReturnCallback(function () use (&$vergessen): int {
            ++$vergessen;
            return 15;
        });
        $dienst->method('leseOffene')->willReturn(15);

        $this->befehl($dienst)->run($this->eingabe('50', true), $this->ausgabe());

        self::assertSame(1, $vergessen);
        self::assertStringContainsString('15 gescheiterte Dokumente', implode(' ', $this->zeilen));
    }

    public function testOhneDenSchalterBleibenGescheiterteInRuhe(): void
    {
        $vergessen = 0;
        $dienst = $this->createStub(GeschaeftDokumentService::class);
        $dienst->method('vergisseGescheiterte')->willReturnCallback(function () use (&$vergessen): int {
            ++$vergessen;
            return 15;
        });
        $dienst->method('leseOffene')->willReturn(0);

        $this->befehl($dienst)->run($this->eingabe('50'), $this->ausgabe());

        self::assertSame(0, $vergessen);
    }

    public function testDerBefehlStehtInDerAppBeschreibung(): void
    {
        $info = (string) file_get_contents(__DIR__ . '/../../appinfo/info.xml');
        self::assertStringContainsString(
            'Command\\DokumenteLesenCommand',
            $info,
            'ohne Eintrag in info.xml kennt occ den Befehl nicht',
        );
    }
}
