<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\SyncZeitplan;
use PHPUnit\Framework\TestCase;

/**
 * Feature: Die automatische Synchronisation läuft nach einem konfigurierbaren
 * Zeitplan — beliebige Einträge mit Wochentagen (1 = Montag … 7 = Sonntag) und
 * Uhrzeit. Fällig ist ein Lauf, wenn zwischen zwei Prüfzeitpunkten ein
 * Zeitplan-Punkt liegt.
 */
class SyncZeitplanTest extends TestCase
{
    private const TZ = 'Europe/Zurich';

    private static function ts(string $wann): int
    {
        return (new \DateTimeImmutable($wann, new \DateTimeZone(self::TZ)))->getTimestamp();
    }

    public function testParseNormalisiertGueltigeEintraege(): void
    {
        $json = json_encode([
            ['tage' => [3, 1, 1, '5'], 'zeit' => '6:30'],
            ['tage' => [7], 'zeit' => '23:05'],
        ]);
        self::assertSame([
            ['tage' => [1, 3, 5], 'zeit' => '06:30'],
            ['tage' => [7], 'zeit' => '23:05'],
        ], SyncZeitplan::parse($json));
    }

    public function testParseVerwirftUngueltiges(): void
    {
        $json = json_encode([
            ['tage' => [], 'zeit' => '06:30'],          // keine Tage
            ['tage' => [1], 'zeit' => '25:00'],          // ungültige Stunde
            ['tage' => [1], 'zeit' => 'abc'],            // keine Zeit
            ['tage' => [0, 8], 'zeit' => '06:30'],       // Tage ausserhalb 1..7
            'quatsch',
        ]);
        self::assertSame([], SyncZeitplan::parse($json));
        self::assertSame([], SyncZeitplan::parse('kein json'));
        self::assertSame([], SyncZeitplan::parse(''));
    }

    public function testFaelligWennPunktImFensterLiegt(): void
    {
        // 2026-07-20 ist ein Montag.
        $plan = [['tage' => [1], 'zeit' => '06:30']];
        self::assertTrue(SyncZeitplan::faellig($plan, self::ts('2026-07-20 06:25'), self::ts('2026-07-20 06:35')));
        // Punkt bereits vorbei → nicht erneut fällig.
        self::assertFalse(SyncZeitplan::faellig($plan, self::ts('2026-07-20 06:31'), self::ts('2026-07-20 06:40')));
        // Falscher Wochentag (Dienstag) → nicht fällig.
        self::assertFalse(SyncZeitplan::faellig($plan, self::ts('2026-07-21 06:25'), self::ts('2026-07-21 06:35')));
    }

    public function testFaelligUeberMitternachtUndMehrereTage(): void
    {
        // Montag 00:05 liegt im Fenster Sonntag 23:50 → Montag 00:10.
        $plan = [['tage' => [1], 'zeit' => '00:05']];
        self::assertTrue(SyncZeitplan::faellig($plan, self::ts('2026-07-19 23:50'), self::ts('2026-07-20 00:10')));
        // Mehrtägiges Fenster (Ausfall): der Mittwoch-Punkt dazwischen zählt.
        $planMi = [['tage' => [3], 'zeit' => '12:00']];
        self::assertTrue(SyncZeitplan::faellig($planMi, self::ts('2026-07-20 08:00'), self::ts('2026-07-24 08:00')));
        self::assertFalse(SyncZeitplan::faellig($planMi, self::ts('2026-07-23 13:00'), self::ts('2026-07-24 08:00')));
    }

    public function testLeererPlanIstNieFaellig(): void
    {
        self::assertFalse(SyncZeitplan::faellig([], self::ts('2026-07-20 00:00'), self::ts('2026-07-27 00:00')));
    }

    /**
     * Default, wenn kein Zeitplan konfiguriert ist: zwei Einträge mit ALLEN
     * Wochentagen, um 10:00 und um 18:00 Uhr.
     */
    public function testStandardSindZweiEintraegeAlleTage1000Und1800(): void
    {
        self::assertSame([
            ['tage' => [1, 2, 3, 4, 5, 6, 7], 'zeit' => '10:00'],
            ['tage' => [1, 2, 3, 4, 5, 6, 7], 'zeit' => '18:00'],
        ], SyncZeitplan::standard());
    }

    public function testMitStandardLiefertStandardBeiLeeremPlan(): void
    {
        self::assertSame(SyncZeitplan::standard(), SyncZeitplan::mitStandard('[]'));
        self::assertSame(SyncZeitplan::standard(), SyncZeitplan::mitStandard('kein json'));
        self::assertSame(SyncZeitplan::standard(), SyncZeitplan::mitStandard(''));
    }

    public function testMitStandardBehaeltKonfiguriertenPlan(): void
    {
        $json = json_encode([['tage' => [1], 'zeit' => '06:30']]);
        self::assertSame([['tage' => [1], 'zeit' => '06:30']], SyncZeitplan::mitStandard($json));
    }
}
