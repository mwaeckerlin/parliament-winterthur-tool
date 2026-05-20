<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Prozessübergreifende Sperre, damit niemals mehr als ein Sync gleichzeitig läuft.
 */
class SyncLockService {
    private const DEFAULT_LOCK_FILE = '/tmp/parlwin-sync.lock';

    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $lockFile = self::DEFAULT_LOCK_FILE,
    ) {
    }

    public function acquire(): bool {
        if (is_resource($this->handle)) {
            return true;
        }

        $handle = @fopen($this->lockFile, 'c');
        if ($handle === false) {
            return false;
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return false;
        }

        $this->handle = $handle;
        return true;
    }

    public function release(): void {
        if (!is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }

    public function isLocked(): bool {
        $probe = @fopen($this->lockFile, 'c');
        if ($probe === false) {
            return false;
        }

        $acquired = @flock($probe, LOCK_EX | LOCK_NB);
        if ($acquired) {
            @flock($probe, LOCK_UN);
        }
        @fclose($probe);

        return !$acquired;
    }

    public function __destruct() {
        $this->release();
    }
}
