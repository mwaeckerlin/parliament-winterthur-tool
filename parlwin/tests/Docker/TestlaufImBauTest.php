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
        // Der E2E-Teil bleibt im Bau aussen vor: Er synchronisiert die echte
        // Webseite des Parlaments (1236 Geschäfte mit Detailseite, ohne Limit),
        // und auf dem Runner war das nach 34 Minuten nicht fertig.
        self::assertStringContainsString('PARLWIN_TESTS_OHNE_E2E=1', $befehl);
        self::assertLessThan(
            strpos($befehl, 'npm test'),
            strpos($befehl, 'composer:update'),
            'Die Einrichtung muss VOR dem Testlauf stehen',
        );
    }

    public function testJedeTestsuiteLaeuftImAbbild(): void
    {
        // Mit dem PHP des Wirts lief PHPUnit 13 auf dem Runner (PHP 8.3) gar
        // nicht an, und eine andere PHP-Fassung prüft die falsche Laufzeit.
        foreach ($this->skripte() as $name => $befehl) {
            if (!str_starts_with($name, 'test') || str_contains($name, 'e2e')) {
                continue;
            }
            if (!str_contains($befehl, 'bootstrap tests/bootstrap.php')) {
                continue;
            }
            self::assertStringContainsString(
                'tests/php-suite.sh',
                $befehl,
                "Das Skript {$name} muss PHPUnit im Abbild starten (tests/php-suite.sh)",
            );
        }
    }

    public function testDerGesamtlaufStartetJedePhpSuiteImAbbild(): void
    {
        $text = $this->datei('tests/run-all.sh');
        self::assertSame(
            3,
            preg_match_all('/bash "\$PHP_SUITE"/', $text),
            'Alle drei PHP-Suiten (unit, pdf, live) müssen im Abbild laufen',
        );
        self::assertStringNotContainsString(
            'PHPUNIT="phpunit"',
            $text,
            'Ein PHPUnit vom Wirt wäre eine andere Fassung als die der Anwendung',
        );
        self::assertStringContainsString('--fail-on-phpunit-deprecation', $text);
    }

    public function testDasTestabbildBringtDieErweiterungenUndDenIconvVorlauf(): void
    {
        $text = $this->datei('Dockerfile.php-test');
        foreach (['php-tokenizer', 'php-dom', 'php-mbstring', 'php-xmlwriter'] as $paket) {
            self::assertStringContainsString($paket, $text, "PHPUnit braucht {$paket}");
        }
        self::assertStringContainsString('LD_PRELOAD=/usr/lib/preloadable_libiconv.so', $text);
        self::assertStringContainsString('docker/iconv-shim.c', $text);
        // Keine Versionsnummer in den Anweisungen: die Pakete heissen «php-…»,
        // die Fassung kommt aus dem Paketmanager der Basis. Kommentare dürfen
        // eine Fassung nennen, sie erklären ja gerade warum.
        $anweisungen = array_filter(
            explode("\n", $text),
            static fn(string $zeile): bool => !str_starts_with(ltrim($zeile), '#'),
        );
        self::assertDoesNotMatchRegularExpression('/\bphp8\d\b/', implode("\n", $anweisungen));
    }

    public function testDerIconvVorlaufStehtEinmalUndWirdZweimalGebaut(): void
    {
        self::assertFileExists(__DIR__ . '/../../../docker/iconv-shim.c');
        foreach (['Dockerfile.php-fpm', 'Dockerfile.php-test'] as $datei) {
            self::assertStringContainsString('docker/iconv-shim.c', $this->datei($datei), $datei);
        }
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
