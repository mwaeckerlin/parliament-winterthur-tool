<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Docker;

use PHPUnit\Framework\TestCase;

/**
 * Guard für den Container-Abbruch des Init-Watchers.
 *
 * Bug: Nach einem fehlgeschlagenen occ upgrade blieb der Container still im
 * Wartungsmodus hängen, weil pwAbortContainer den Prozess PID 1 (den
 * Container-Hauptprozess) NICHT signalisierte — der frühere Guard `> 1` sprang
 * genau bei PID 1 (dem Watcher-Parent) heraus. Zudem stammen die Konstanten
 * SIGTERM/SIGKILL aus pcntl, das im Basis-Image nicht geladen ist.
 */
class WatcherAbortTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // In einer Methode (nicht global) laden: der debug_backtrace()-Guard im
        // Script erkennt den Nicht-Direktaufruf und startet pwMain() NICHT.
        require_once __DIR__ . '/../../../docker/parlwin-watcher.php';
    }

    public function testSignalZielFaelltAufPid1Zurueck(): void
    {
        self::assertSame(1, pwSignalZiel(1), 'PID 1 (Watcher-Parent) muss signalisiert werden, nicht übersprungen');
        self::assertSame(1, pwSignalZiel(null));
        self::assertSame(1, pwSignalZiel(0));
    }

    public function testSignalZielBevorzugtEchtenParent(): void
    {
        self::assertSame(42, pwSignalZiel(42));
    }

    public function testSignalNutztNumerischePosixWerte(): void
    {
        // Ohne pcntl-Konstanten müssen die POSIX-Standardwerte greifen.
        self::assertSame(defined('SIGTERM') ? SIGTERM : 15, pwSignal('SIGTERM'));
        self::assertSame(defined('SIGKILL') ? SIGKILL : 9, pwSignal('SIGKILL'));
        self::assertGreaterThan(0, pwSignal('SIGTERM'));
        self::assertGreaterThan(0, pwSignal('SIGKILL'));
    }
}
