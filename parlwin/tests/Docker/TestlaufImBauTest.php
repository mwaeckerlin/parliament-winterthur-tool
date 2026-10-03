<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Docker;

use PHPUnit\Framework\TestCase;

/**
 * Der Testlauf muss dort laufen, wo der Bau ihn startet.
 *
 * Der GitHub-Runner hat kein PHPUnit, keinen Composer und kein
 * `parlwin/vendor` — das ist git-ignoriert und entsteht im Bau. Ein `npm test`
 * allein konnte darum auf dem Runner keine einzige PHP-Suite starten, und der
 * Bau meldete «suite-konnte-nicht-starten» (erster Lauf zu 1.9.2, 2026-10-02).
 * Der Bau ruft jetzt `npm run test:ci`, das die Abhängigkeiten vorher aus der
 * Stufe `php-vendor` holt.
 */
class TestlaufImBauTest extends TestCase
{
    private function datei(string $pfad): string
    {
        $voll = __DIR__ . '/../../../' . $pfad;
        self::assertFileExists($voll);
        return (string) file_get_contents($voll);
    }

    public function testDerBauRuftDenLaufMitEinrichtungAuf(): void
    {
        $text = $this->datei('.github/workflows/docker.yml');
        self::assertMatchesRegularExpression(
            '/^\s*test:\s*npm run test:ci\s*$/m',
            $text,
            'docker.yml muss den Testlauf auf npm run test:ci stellen',
        );
    }

    public function testDerLaufImBauRichtetDieAbhaengigkeitenEinUndFuehrtDannAlleTestsAus(): void
    {
        $skripte = $this->skripte();
        self::assertArrayHasKey('test:ci', $skripte);
        $befehl = $skripte['test:ci'];
        self::assertStringContainsString('composer:update', $befehl);
        self::assertStringContainsString('npm test', $befehl);
        self::assertLessThan(
            strpos($befehl, 'npm test'),
            strpos($befehl, 'composer:update'),
            'Die Einrichtung muss VOR dem Testlauf stehen',
        );
    }

    public function testJedesTestskriptNimmtDasPhpunitDesProjekts(): void
    {
        foreach ($this->skripte() as $name => $befehl) {
            if (!str_starts_with($name, 'test')) {
                continue;
            }
            if (!str_contains($befehl, 'phpunit')) {
                continue;
            }
            self::assertStringContainsString(
                './vendor/bin/phpunit',
                $befehl,
                "Das Skript {$name} muss das PHPUnit aus parlwin/vendor nehmen",
            );
        }
    }

    public function testDerGesamtlaufBrichtOhneDasPhpunitDesProjektsAb(): void
    {
        $text = $this->datei('tests/run-all.sh');
        self::assertStringContainsString('PHPUNIT="${ROOT}/parlwin/vendor/bin/phpunit"', $text);
        self::assertStringNotContainsString(
            'PHPUNIT="phpunit"',
            $text,
            'Ein PHPUnit vom Wirt wäre eine zweite, andere Fassung',
        );
        self::assertStringContainsString('--fail-on-phpunit-deprecation', $text);
    }

    public function testDerBauKenntDieStufeMitDenTestwerkzeugen(): void
    {
        $text = $this->datei('Dockerfile.php-fpm');
        self::assertStringContainsString('AS php-deps-test', $text);
        self::assertStringContainsString('AS php-vendor', $text);

        // Die letzte Stufe einer Datei ist das Standardziel des Baus. Stünde
        // «php-vendor» am Ende, baute `docker compose build` statt des Abbilds
        // ein Verzeichnis mit Bibliotheken.
        $stufen = [];
        preg_match_all('/^FROM\s+.*\s+AS\s+(\S+)/mi', $text, $stufen);
        self::assertNotSame('php-vendor', end($stufen[1]));
    }

    /** @return array<string, string> */
    private function skripte(): array
    {
        $paket = json_decode($this->datei('package.json'), true);
        self::assertIsArray($paket);
        self::assertIsArray($paket['scripts'] ?? null);
        return $paket['scripts'];
    }
}
