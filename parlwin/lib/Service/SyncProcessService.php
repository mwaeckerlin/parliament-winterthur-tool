<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

class SyncProcessService {
    /** @var callable(int,int):bool */
    private $signalSender;

    /** @var callable(int):void */
    private $sleepMsFn;

    /**
     * @param callable(int,int):bool|null $signalSender
     * @param callable(int):void|null $sleepMsFn
     */
    public function __construct(?callable $signalSender = null, ?callable $sleepMsFn = null) {
        $this->signalSender = $signalSender ?? [$this, 'signalPidDefault'];
        $this->sleepMsFn = $sleepMsFn ?? [$this, 'sleepMsDefault'];
    }

    /**
     * @param callable():bool $isLocked
     * @return array{stopped: bool, forced: bool, signalled: bool}
     */
    public function ensureStopped(
        ?int $pid,
        callable $isLocked,
        int $graceMs = 1200,
        int $termWaitMs = 300,
        int $killWaitMs = 500,
        int $stepMs = 100,
    ): array {
        if (!$isLocked()) {
            return ['stopped' => true, 'forced' => false, 'signalled' => false];
        }

        if ($this->waitUntilUnlocked($isLocked, $graceMs, $stepMs)) {
            return ['stopped' => true, 'forced' => false, 'signalled' => false];
        }

        if ($pid === null || $pid <= 1) {
            return ['stopped' => !$isLocked(), 'forced' => false, 'signalled' => false];
        }

        $signalled = false;
        $forced = false;
        if (($this->signalSender)($pid, 15)) {
            $signalled = true;
            if ($this->waitUntilUnlocked($isLocked, $termWaitMs, $stepMs)) {
                return ['stopped' => true, 'forced' => false, 'signalled' => true];
            }
        }

        if (($this->signalSender)($pid, 9)) {
            $signalled = true;
            $forced = true;
        }

        $stopped = $this->waitUntilUnlocked($isLocked, $killWaitMs, $stepMs);
        return ['stopped' => $stopped, 'forced' => $forced, 'signalled' => $signalled];
    }

    /**
     * @param callable():bool $isLocked
     */
    private function waitUntilUnlocked(callable $isLocked, int $timeoutMs, int $stepMs): bool {
        if ($timeoutMs <= 0) {
            return !$isLocked();
        }
        $stepMs = max(10, $stepMs);
        $deadline = microtime(true) + ($timeoutMs / 1000.0);
        while (microtime(true) < $deadline) {
            if (!$isLocked()) {
                return true;
            }
            ($this->sleepMsFn)($stepMs);
        }
        return !$isLocked();
    }

    private function signalPidDefault(int $pid, int $signal): bool {
        if ($pid <= 1) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, $signal);
        }
        if (function_exists('exec')) {
            $output = [];
            $code = 1;
            @exec(sprintf('kill -%d %d', $signal, $pid), $output, $code);
            return $code === 0;
        }
        return false;
    }

    private function sleepMsDefault(int $milliseconds): void {
        $milliseconds = max(1, $milliseconds);
        usleep($milliseconds * 1000);
    }
}

