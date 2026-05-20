<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\SyncProcessService;
use PHPUnit\Framework\TestCase;

class SyncProcessServiceTest extends TestCase {
    public function testEnsureStoppedIstSofortFertigWennNichtGesperrt(): void {
        $service = new SyncProcessService(
            static fn (int $pid, int $signal): bool => false,
            static function (int $ms): void {
            },
        );

        $result = $service->ensureStopped(
            1234,
            static fn (): bool => false,
            1200,
            300,
            500,
            10,
        );

        self::assertSame(
            ['stopped' => true, 'forced' => false, 'signalled' => false],
            $result
        );
    }

    public function testEnsureStoppedStopptGracefulOhneSignal(): void {
        $remainingLocks = 2;
        $service = new SyncProcessService(
            static fn (int $pid, int $signal): bool => false,
            static function (int $ms): void {
            },
        );

        $result = $service->ensureStopped(
            1234,
            static function () use (&$remainingLocks): bool {
                if ($remainingLocks > 0) {
                    $remainingLocks--;
                    return true;
                }
                return false;
            },
            1200,
            300,
            500,
            10,
        );

        self::assertSame(
            ['stopped' => true, 'forced' => false, 'signalled' => false],
            $result
        );
    }

    public function testEnsureStoppedSignalisiertTermUndStoppt(): void {
        $locked = true;
        $signals = [];
        $service = new SyncProcessService(
            static function (int $pid, int $signal) use (&$locked, &$signals): bool {
                $signals[] = $signal;
                if ($signal === 15) {
                    $locked = false;
                }
                return true;
            },
            static function (int $ms): void {
            },
        );

        $result = $service->ensureStopped(
            4242,
            static function () use (&$locked): bool {
                return $locked;
            },
            0,
            300,
            500,
            10,
        );

        self::assertSame([15], $signals);
        self::assertSame(
            ['stopped' => true, 'forced' => false, 'signalled' => true],
            $result
        );
    }

    public function testEnsureStoppedFaelltAufKillZurueck(): void {
        $locked = true;
        $signals = [];
        $service = new SyncProcessService(
            static function (int $pid, int $signal) use (&$locked, &$signals): bool {
                $signals[] = $signal;
                if ($signal === 9) {
                    $locked = false;
                }
                return true;
            },
            static function (int $ms): void {
            },
        );

        $result = $service->ensureStopped(
            4242,
            static function () use (&$locked): bool {
                return $locked;
            },
            0,
            0,
            300,
            10,
        );

        self::assertSame([15, 9], $signals);
        self::assertSame(
            ['stopped' => true, 'forced' => true, 'signalled' => true],
            $result
        );
    }
}
