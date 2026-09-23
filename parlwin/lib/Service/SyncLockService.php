<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Prozessübergreifende Sperre, damit niemals mehr als ein Sync gleichzeitig läuft,
 * und das ebenso prozessübergreifende Abbruch-Signal.
 */
class SyncLockService {
    private const DEFAULT_LOCK_FILE = '/tmp/parlwin-sync.lock';

    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $lockFile = self::DEFAULT_LOCK_FILE,
    ) {
    }

    /**
     * Der laufende Sync ist EIN Aufruf, der Abbruch kommt aus einem zweiten. Über
     * die App-Konfiguration erreicht ihn das Signal nicht: Nextcloud hält die
     * Werte pro Aufruf im Speicher, und der laufende Sync liest bis zu seinem Ende
     * denselben Stand von seinem Beginn. Der Abbruch-Knopf blieb deshalb wirkungslos
     * und der Sync lief zu Ende. Eine Datei neben der Sperrdatei kennt keinen
     * solchen Zwischenspeicher.
     */
    public function abbruchAnfordern(): void {
        @file_put_contents($this->abbruchDatei(), (string) microtime(true));
    }

    public function abbruchAufheben(): void {
        @unlink($this->abbruchDatei());
    }

    public function abbruchAngefordert(): bool {
        return @file_exists($this->abbruchDatei());
    }

    /**
     * Wann der Abbruch verlangt wurde (Unix-Zeit mit Bruchteilen), oder null ohne
     * Signal. Daran erkennt ein startender Lauf, ob das Signal ihm gilt: Zwischen
     * dem Start seines Prozesses und dem Greifen der Sperre vergehen ein bis zwei
     * Sekunden, und ein Abbruch aus diesem Fenster meint ihn. Ein Signal ohne
     * Zeitangabe stammt aus einem früheren Lauf und gilt als beliebig alt.
     */
    public function abbruchZeitpunkt(): ?float {
        $roh = @file_get_contents($this->abbruchDatei());
        if (!is_string($roh)) {
            return null;
        }
        $zeit = (float) trim($roh);
        return $zeit > 1000000.0 ? $zeit : 0.0;
    }

    private function abbruchDatei(): string {
        return $this->lockFile . '.abbruch';
    }

    /**
     * Die Prozessnummer des laufenden Syncs, ebenfalls neben der Sperrdatei: Sie
     * muss von einem anderen Aufruf gelesen werden, um den Prozess zu beenden, und
     * über die App-Konfiguration kam sie dort nicht zuverlässig an.
     */
    public function pidSetzen(?int $pid): void {
        if ($pid !== null && $pid > 1) {
            @file_put_contents($this->pidDatei(), (string) $pid);
            return;
        }
        @unlink($this->pidDatei());
    }

    public function pidLesen(): ?int {
        $roh = @file_get_contents($this->pidDatei());
        if (!is_string($roh)) {
            return null;
        }
        $pid = (int) trim($roh);
        return $pid > 1 ? $pid : null;
    }

    private function pidDatei(): string {
        return $this->lockFile . '.pid';
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
