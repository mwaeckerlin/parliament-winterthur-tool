<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Services, die in Application.php von Hand registriert werden, müssen exakt so
 * viele Abhängigkeiten übergeben bekommen, wie ihr Konstruktor erwartet.
 *
 * Sonst schlägt erst zur Laufzeit fehl, was hier auffallen muss: Ein zusätzlicher
 * Konstruktor-Parameter (z.B. ein neuer Mapper) führt zu «Too few arguments» —
 * der Service lässt sich nicht mehr bauen und JEDE Anfrage endet in HTTP 500.
 */
class DiRegistrierungTest extends TestCase
{
    private function applicationQuelltext(): string
    {
        $pfad = __DIR__ . '/../../lib/AppInfo/Application.php';
        $inhalt = file_get_contents($pfad);
        self::assertIsString($inhalt, 'Application.php nicht lesbar');
        return $inhalt;
    }

    /**
     * @return array<int, array{0: class-string}>
     */
    public static function handRegistrierteServices(): array
    {
        return [
            [\OCA\ParliamentWinterthur\Service\FraktionsarbeitService::class],
            [\OCA\ParliamentWinterthur\Service\GeschaeftService::class],
        ];
    }

    /**
     * @param class-string $klasse
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('handRegistrierteServices')]
    public function testRegistrierungUebergibtAlleKonstruktorParameter(string $klasse): void
    {
        $quelltext = $this->applicationQuelltext();
        $kurzname = (new \ReflectionClass($klasse))->getShortName();

        // Den registerService-Block dieses Services herausschneiden.
        $start = strpos($quelltext, 'new ' . $kurzname . '(');
        self::assertIsInt($start, $kurzname . ' wird in Application.php nicht registriert');

        $ende = strpos($quelltext, ');', $start);
        self::assertIsInt($ende);
        $block = substr($quelltext, $start, $ende - $start);

        $uebergeben = preg_match_all('/\$c->get\(/', $block);

        $konstruktor = (new \ReflectionClass($klasse))->getConstructor();
        self::assertNotNull($konstruktor);
        $erwartet = $konstruktor->getNumberOfParameters();

        self::assertSame(
            $erwartet,
            $uebergeben,
            $kurzname . ': Der Konstruktor erwartet ' . $erwartet . ' Abhängigkeiten, '
            . 'Application.php übergibt aber ' . $uebergeben . '. '
            . 'Ein neuer Konstruktor-Parameter muss in der DI-Registrierung nachgezogen werden, '
            . 'sonst endet jede Anfrage in HTTP 500.'
        );
    }
}
