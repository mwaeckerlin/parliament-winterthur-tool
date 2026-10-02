<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use OCA\ParliamentWinterthur\Command\MigrationenPruefenCommand;
use OCA\ParliamentWinterthur\Service\MigrationenPruefer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Der Start meldet jede Migration, die im Code steht und in der Instanz nie
 * gelaufen ist. Am 24.09.2026 blieb die Version in info.xml gleich, darum lief
 * die neue Migration nicht, die Spalte `quelle_hash` blieb zu kurz, und jedes
 * Lesen eines Dokuments endete mit «Data too long» — ohne dass Start, Test oder
 * Protokoll etwas gemeldet hätten.
 */
class MigrationenPruefenCommandTest extends TestCase
{
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

    private function pruefer(array $imCode, array $ausgefuehrt): MigrationenPruefer
    {
        $pruefer = $this->createStub(MigrationenPruefer::class);
        $pruefer->method('imCode')->willReturn($imCode);
        $pruefer->method('ausgefuehrt')->willReturn($ausgefuehrt);
        $pruefer->method('fehlende')->willReturn(array_values(array_diff($imCode, $ausgefuehrt)));

        return $pruefer;
    }

    public function testEineNichtGelaufeneMigrationMeldetSich(): void
    {
        $befehl = new MigrationenPruefenCommand($this->pruefer(
            ['000052Date20260923180000', '000053Date20260924090000'],
            ['000052Date20260923180000'],
        ));

        $code = $befehl->run($this->createStub(InputInterface::class), $this->ausgabe());

        self::assertSame(1, $code, 'der Befehl endet mit einem Fehler');
        self::assertStringContainsString('000053Date20260924090000', implode(' ', $this->zeilen));
    }

    public function testSindAlleGelaufenMeldetErNichts(): void
    {
        $befehl = new MigrationenPruefenCommand($this->pruefer(
            ['000052Date20260923180000'],
            ['000052Date20260923180000', '000051Date20260901120000'],
        ));

        self::assertSame(0, $befehl->run($this->createStub(InputInterface::class), $this->ausgabe()));
    }

    public function testDerBefehlStehtInDerAppBeschreibung(): void
    {
        $info = (string) file_get_contents(__DIR__ . '/../../appinfo/info.xml');
        self::assertStringContainsString(
            'Command\\MigrationenPruefenCommand',
            $info,
            'ohne Eintrag in info.xml kennt occ den Befehl nicht',
        );
    }

    public function testDerStartHaeltAnWennEineMigrationFehlt(): void
    {
        $watcher = (string) file_get_contents(__DIR__ . '/../../../docker/parlwin-watcher.php');
        self::assertStringContainsString(
            'parlwin:migrationen-pruefen',
            $watcher,
            'der Watcher ruft die Prüfung beim Start auf',
        );
        self::assertStringContainsString(
            'parlwin migrations are missing in this instance',
            $watcher,
            'findet die Prüfung etwas, hält der Container an',
        );
    }
}
