<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\AppInfo;

use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Die manuelle DI-Registrierung in Application.php muss exakt so
 * viele Argumente übergeben, wie der Service-Konstruktor erwartet. Andernfalls
 * scheitert die Auflösung zur Laufzeit mit ArgumentCountError (interner
 * Serverfehler) – wie geschehen, als FraktionsraumService einen neuen
 * Parameter (DeckService) bekam, die Registrierung aber nicht angepasst wurde.
 */
class ApplicationDiTest extends TestCase {
    private function registrierungsArgumente(string $klasse): int {
        $src = file_get_contents(__DIR__ . '/../../lib/AppInfo/Application.php');
        $kurz = (new \ReflectionClass($klasse))->getShortName();
        if (!preg_match('/new ' . $kurz . '\((.*?)\);/s', $src, $m)) {
            $this->fail("Keine Registrierung 'new {$kurz}(...)' in Application.php gefunden");
        }
        // Argumente sind durch Kommas auf eigenen Zeilen getrennt; leere ignorieren.
        $zeilen = array_filter(array_map('trim', explode("\n", $m[1])), static fn ($l) => $l !== '');
        return count($zeilen);
    }

    public function testFraktionsraumServiceRegistrierungPasstZumKonstruktor(): void {
        $ctor = (new \ReflectionClass(FraktionsraumService::class))->getConstructor();
        $this->assertSame(
            $ctor->getNumberOfParameters(),
            $this->registrierungsArgumente(FraktionsraumService::class),
            'Die DI-Registrierung von FraktionsraumService übergibt nicht gleich viele Argumente wie der Konstruktor erwartet'
        );
    }
}
