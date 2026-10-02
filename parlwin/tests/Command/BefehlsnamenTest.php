<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Jeder occ-Befehl der App trägt seinen Namen als Attribut `#[AsCommand]`.
 *
 * Die ältere Form `protected static $defaultName` liest die Console-Bibliothek
 * ab Symfony 7 nicht mehr. Nextcloud 35 bringt diese Version mit, und ein
 * Befehl ohne Attribut lässt dann die ganze occ-Kommandozeile der Installation
 * scheitern: «The command defined in … cannot have an empty name», bei JEDEM
 * occ-Aufruf, auch bei fremden. Gemessen am 25.09.2026, als darum keine
 * Synchronisation mehr startete und der Abgleich nach 15 Minuten ohne
 * Lebenszeichen abgebrochen wurde.
 */
class BefehlsnamenTest extends TestCase
{
    /** @return list<array{string, string}> */
    private function befehle(): array
    {
        $gefunden = [];
        foreach (glob(__DIR__ . '/../../lib/Command/*Command.php') ?: [] as $datei) {
            $klasse = 'OCA\\ParliamentWinterthur\\Command\\' . basename($datei, '.php');
            $code = (string) file_get_contents($datei);
            $gefunden[] = [$klasse, $code];
        }

        return $gefunden;
    }

    public function testJederBefehlTraegtSeinenNamenAlsAttribut(): void
    {
        $befehle = $this->befehle();
        self::assertNotSame([], $befehle, 'die App bringt occ-Befehle mit');

        foreach ($befehle as [$klasse, $code]) {
            self::assertMatchesRegularExpression(
                "/#\[AsCommand\(name: '[a-z:-]+'\)\]/",
                $code,
                $klasse . ' braucht #[AsCommand(name: …)] — sonst scheitert jeder occ-Aufruf der Installation',
            );
        }
    }

    public function testDerNameImAttributGiltUndBeginntMitDerApp(): void
    {
        foreach ($this->befehle() as [$klasse, $code]) {
            self::assertTrue(class_exists($klasse), $klasse . ' ist ladbar');
            $attribute = (new \ReflectionClass($klasse))->getAttributes(AsCommand::class);
            self::assertCount(1, $attribute, $klasse . ' trägt genau ein AsCommand-Attribut');
            $name = (string) ($attribute[0]->getArguments()['name'] ?? $attribute[0]->getArguments()[0] ?? '');
            self::assertStringStartsWith('parlwin:', $name, $klasse . ' gehört in den Namensraum der App');

            self::assertMatchesRegularExpression(
                "/defaultName = '" . preg_quote($name, '/') . "'/",
                $code,
                $klasse . ': Attribut und $defaultName nennen denselben Namen',
            );
        }
    }
}
