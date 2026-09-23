<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\TestCase;

/**
 * Ein Budgetbuch zu lesen braucht mehr Speicher, als eine Nextcloud-Installation
 * einer Anfrage standardmässig zugesteht. Gemessen am 22.09.2026 im ausgelieferten
 * Abbild: `occ parlwin:budget-reimport 2027` endete nach 503,8 MB mit «Allowed
 * memory size of 536870912 bytes exhausted» — das Budget 2027 liess sich in der
 * laufenden Instanz nicht einlesen, während derselbe Parse auf dem Rechner (ohne
 * Grenze) durchlief.
 *
 * Der Parser hebt die Grenze darum für die Dauer des Lesens an und stellt sie
 * danach wieder her. Wie hoch, sagt die Umgebungsvariable
 * `PARLWIN_BUDGET_MEMORY_LIMIT` (Standard 1024M); wer mehr Bücher gleichzeitig
 * liest oder weniger Speicher hat, stellt sie im Deployment ein.
 */
class BudgetSpeichergrenzeTest extends TestCase {
    private string $vorher = '';

    protected function setUp(): void {
        $this->vorher = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void {
        ini_set('memory_limit', $this->vorher);
        putenv('PARLWIN_BUDGET_MEMORY_LIMIT');
    }

    public function testHebtEineZuKnappeGrenzeAn(): void {
        ini_set('memory_limit', '256M');

        $gemessen = (new BudgetBuchParser())->mitSpeichergrenze(
            static fn (): string => (string) ini_get('memory_limit')
        );

        self::assertSame('1024M', $gemessen, 'während des Lesens gilt die höhere Grenze');
        self::assertSame('256M', ini_get('memory_limit'), 'danach gilt wieder die Grenze der Installation');
    }

    public function testLaesstEineHoehereGrenzeStehen(): void {
        ini_set('memory_limit', '2048M');

        $gemessen = (new BudgetBuchParser())->mitSpeichergrenze(
            static fn (): string => (string) ini_get('memory_limit')
        );

        self::assertSame('2048M', $gemessen, 'eine grosszügigere Grenze wird nicht gesenkt');
    }

    public function testDasDeploymentBestimmtDieGrenze(): void {
        ini_set('memory_limit', '256M');
        putenv('PARLWIN_BUDGET_MEMORY_LIMIT=1536M');

        $gemessen = (new BudgetBuchParser())->mitSpeichergrenze(
            static fn (): string => (string) ini_get('memory_limit')
        );

        self::assertSame('1536M', $gemessen);
    }

    public function testOhneGrenzeBleibtOhneGrenze(): void {
        ini_set('memory_limit', '-1');

        $gemessen = (new BudgetBuchParser())->mitSpeichergrenze(
            static fn (): string => (string) ini_get('memory_limit')
        );

        self::assertSame('-1', $gemessen, 'wer keine Grenze setzt, bekommt keine');
    }

    public function testGibtDasGeleseneBuchFrei(): void {
        $parser = new BudgetBuchParser();
        $feld = new \ReflectionProperty($parser, 'gehaltenesDokument');
        $parser->mitSpeichergrenze(static function () use ($feld, $parser): void {
            // Während der Arbeit hält der Parser das Buch (hier gestellt).
            $feld->setValue($parser, new \stdClass());
        });

        self::assertNull(
            $feld->getValue($parser),
            'nach dem Lesen hält der Parser kein Buch mehr — sonst bleiben hunderte Megabyte belegt, '
            . 'und die Speichergrenze der Installation lässt sich nicht mehr setzen'
        );
    }

    public function testStelltDieGrenzeAuchNachEinemFehlerWiederHer(): void {
        ini_set('memory_limit', '256M');

        try {
            (new BudgetBuchParser())->mitSpeichergrenze(static function (): string {
                throw new \RuntimeException('Buch unlesbar');
            });
            self::fail('die Ausnahme muss durchkommen');
        } catch (\RuntimeException) {
            // erwartet
        }

        self::assertSame('256M', ini_get('memory_limit'));
    }
}
