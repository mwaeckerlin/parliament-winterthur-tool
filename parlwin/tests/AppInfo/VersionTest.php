<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Die Version steht in `parlwin/appinfo/info.xml`, denn Nextcloud liest sie von
 * dort und führt die Migrationen nur bei einer höheren Nummer aus. `package.json`
 * trägt dieselbe Nummer, damit der Arbeitsbereich und die übrigen Projekte der
 * Familie dasselbe sehen. Zwei Dateien mit zwei Nummern lassen jeden im Zweifel,
 * welche gilt — gemessen am 26.09.2026, als `package.json` bei 1.0.0 stand,
 * während die App 1.9.1 war.
 */
class VersionTest extends TestCase
{
    public function testInfoXmlUndPackageJsonNennenDieselbeVersion(): void
    {
        $info = (string) file_get_contents(__DIR__ . '/../../appinfo/info.xml');
        self::assertSame(
            1,
            preg_match('#<version>([^<]+)</version>#', $info, $treffer),
            'info.xml trägt eine Version',
        );
        $paket = json_decode((string) file_get_contents(__DIR__ . '/../../../package.json'), true);
        self::assertIsArray($paket);

        self::assertSame(
            $treffer[1],
            (string) ($paket['version'] ?? ''),
            'package.json und info.xml nennen dieselbe Version',
        );
    }

    public function testDasPaketTraegtDenNamenDerFamilie(): void
    {
        $paket = json_decode((string) file_get_contents(__DIR__ . '/../../../package.json'), true);
        self::assertIsArray($paket);
        self::assertSame('@mwaeckerlin/parliament-winterthur-tool', $paket['name'] ?? '');
    }

    /**
     * Die Namen der Skripte sind in allen Projekten der Familie dieselben; wer
     * von einem Projekt zum nächsten wechselt, tippt denselben Befehl.
     */
    public function testDieSkripteDerFamilieStehenAlleDa(): void
    {
        $paket = json_decode((string) file_get_contents(__DIR__ . '/../../../package.json'), true);
        self::assertIsArray($paket);
        $skripte = $paket['scripts'] ?? [];

        foreach (['build', 'start', 'start:daemon', 'start:dev', 'stop', 'test'] as $name) {
            self::assertArrayHasKey($name, $skripte, 'das Skript «' . $name . '» gehört zur Familie');
        }
        self::assertStringNotContainsString(
            '-d',
            (string) $skripte['start'],
            '«start» läuft im Vordergrund, «start:daemon» im Hintergrund',
        );
        self::assertStringContainsString('-d', (string) $skripte['start:daemon']);
    }
}
