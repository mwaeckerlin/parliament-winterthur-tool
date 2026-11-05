<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Jede Fabrik in `Application::register()` übergibt ihrer Klasse genau die
 * Argumente, die deren Konstruktor verlangt, in derselben Reihenfolge.
 *
 * Die Fabriken bauen die Dienste von Hand mit Argumenten nach Position. Bekommt
 * ein Konstruktor einen Parameter dazu und die Fabrik nicht, scheitert jeder
 * Aufruf von `occ` mit einem TypeError, und die Synchronisation läuft nie an.
 *
 * Gelesen wird der Quelltext beider Seiten, weil die Klassen von Nextcloud im
 * Testlauf fehlen und sich die Dienste darum nicht bauen lassen.
 */
class ApplicationRegistrierungTest extends TestCase
{
    private const LIB = __DIR__ . '/../../lib';
    private const NAMENSRAUM = 'OCA\\ParliamentWinterthur\\';

    /**
     * Ein Quelltext ohne seine Zeilenkommentare: Ein Kommentar zwischen zwei
     * Parametern trägt oft selbst ein Komma.
     */
    private static function lesen(string $datei): string
    {
        return (string) preg_replace('#^\s*//.*$#m', '', (string) file_get_contents($datei));
    }

    /** Kurzname zu vollem Namen, aus den `use`-Zeilen eines Quelltexts. */
    private static function importe(string $quelle): array
    {
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?;/m', $quelle, $treffer, PREG_SET_ORDER);
        $importe = [];
        foreach ($treffer as $t) {
            $voll = ltrim($t[1], '\\');
            $kurz = $t[2] ?? '';
            $importe[$kurz !== '' ? $kurz : substr($voll, (int) strrpos('\\' . $voll, '\\'))] = $voll;
        }
        return $importe;
    }

    /** Der volle Name eines Klassennamens, wie er in einer Datei steht. */
    private static function voll(string $name, array $importe, string $namensraum): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        $erster = explode('\\', $name)[0];
        if (isset($importe[$erster])) {
            return $importe[$erster] . substr($name, strlen($erster));
        }
        return $namensraum . '\\' . $name;
    }

    /** Die Liste der Argumente bis zur passenden Klammer, an Kommas der obersten Ebene geteilt. */
    private static function argumente(string $text, int $start): array
    {
        $tiefe = 0;
        $teil = '';
        $teile = [];
        for ($i = $start; $i < strlen($text); $i++) {
            $zeichen = $text[$i];
            if ($zeichen === '(') {
                $tiefe++;
                if ($tiefe === 1) {
                    continue;
                }
            } elseif ($zeichen === ')') {
                $tiefe--;
                if ($tiefe === 0) {
                    break;
                }
            } elseif ($zeichen === ',' && $tiefe === 1) {
                $teile[] = trim($teil);
                $teil = '';
                continue;
            }
            if ($tiefe >= 1) {
                $teil .= $zeichen;
            }
        }
        if (trim($teil) !== '') {
            $teile[] = trim($teil);
        }
        return $teile;
    }

    /** @return array<string, array{klasse: string, argumente: list<string>}> */
    private static function fabriken(): array
    {
        $quelle = self::lesen(self::LIB . '/AppInfo/Application.php');
        $importe = self::importe($quelle);
        preg_match_all('/registerService\(\s*([\\\\\w]+)::class,\s*function\s*\(\$c\)\s*\{\s*return new ([\\\\\w]+)\s*\(/', $quelle, $treffer, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $fabriken = [];
        foreach ($treffer as $t) {
            $klasse = self::voll($t[2][0], $importe, 'OCA\\ParliamentWinterthur\\AppInfo');
            $argumente = [];
            $klammer = $t[0][1] + strlen($t[0][0]) - 1;
            foreach (self::argumente($quelle, $klammer) as $argument) {
                if ($argument === '$c') {
                    $argumente[] = '$c';
                } elseif (preg_match('/^\$c->get\(([\\\\\w]+)::class\)$/', $argument, $m) === 1) {
                    $argumente[] = self::voll($m[1], $importe, 'OCA\\ParliamentWinterthur\\AppInfo');
                } else {
                    $argumente[] = 'unbekannt: ' . $argument;
                }
            }
            $fabriken[$klasse] = ['klasse' => $klasse, 'argumente' => $argumente];
        }
        return $fabriken;
    }

    /** Die Typen der Konstruktor-Parameter einer Klasse des Projekts, aus ihrem Quelltext. */
    private static function parameter(string $klasse): array
    {
        $datei = self::LIB . '/' . str_replace('\\', '/', substr($klasse, strlen(self::NAMENSRAUM))) . '.php';
        self::assertFileExists($datei, "Die Fabrik baut {$klasse}, aber die Datei fehlt");
        $quelle = self::lesen($datei);
        preg_match('/^namespace\s+([\\\\\w]+);/m', $quelle, $ns);
        $importe = self::importe($quelle);
        $stelle = strpos($quelle, 'function __construct');
        if ($stelle === false) {
            return [];
        }
        $typen = [];
        foreach (self::argumente($quelle, (int) strpos($quelle, '(', $stelle)) as $parameter) {
            $parameter = (string) preg_replace('/^(?:(?:private|protected|public|readonly)\s+)*/', '', $parameter);
            preg_match('/^\??([\\\\\w]+)\s+\$/', $parameter, $typ);
            $typen[] = [
                'typ' => isset($typ[1]) ? self::voll($typ[1], $importe, $ns[1]) : '',
                'pflicht' => !str_contains($parameter, '='),
            ];
        }
        return $typen;
    }

    public function testDieFabrikenUmfassenDieBefehleDerSynchronisation(): void
    {
        $fabriken = self::fabriken();
        self::assertArrayHasKey('OCA\\ParliamentWinterthur\\Command\\SyncCommand', $fabriken);
        self::assertArrayHasKey('OCA\\ParliamentWinterthur\\Command\\SyncCancelCommand', $fabriken);
        // Eine Fabrik in einer anderen Form als `function ($c) { return new …(`
        // fände der Test nicht und prüfte sie darum nie.
        $aufrufe = preg_match_all('/->registerService\(/', self::lesen(self::LIB . '/AppInfo/Application.php'));
        self::assertGreaterThan(0, $aufrufe, 'Application.php registriert keinen Dienst');
        self::assertSame($aufrufe, count($fabriken), 'nicht jede Fabrik in Application.php wird gelesen');
    }

    public function testJedeFabrikUebergibtGenauDieVerlangtenArgumente(): void
    {
        $abweichungen = [];
        foreach (self::fabriken() as $klasse => $fabrik) {
            $parameter = self::parameter($klasse);
            $pflicht = count(array_filter($parameter, static fn (array $p): bool => $p['pflicht']));
            $anzahl = count($fabrik['argumente']);
            if ($anzahl < $pflicht || $anzahl > count($parameter)) {
                $abweichungen[] = sprintf('%s: %d Argumente, der Konstruktor verlangt %d bis %d', $klasse, $anzahl, $pflicht, count($parameter));
                continue;
            }
            foreach ($fabrik['argumente'] as $i => $argument) {
                $erwartet = $parameter[$i]['typ'];
                if ($argument === '$c' || $erwartet === '') {
                    continue;
                }
                if ($argument !== $erwartet) {
                    $abweichungen[] = sprintf('%s: Argument %d ist %s, der Konstruktor verlangt %s', $klasse, $i + 1, $argument, $erwartet);
                }
            }
        }
        self::assertSame([], $abweichungen);
    }
}
