<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\SyncLockService;
use PHPUnit\Framework\TestCase;

class SyncLockServiceTest extends TestCase {
    private string $lockFile;

    protected function setUp(): void {
        parent::setUp();
        $this->lockFile = '/tmp/parlwin-sync-lock-test-' . uniqid('', true) . '.lock';
    }

    protected function tearDown(): void {
        @unlink($this->lockFile);
        @unlink($this->lockFile . '.abbruch');
        parent::tearDown();
    }

    /**
     * Der laufende Sync und der Abbruch sind zwei verschiedene Aufrufe. Über die
     * App-Konfiguration erreichte das Signal den laufenden Sync nie — Nextcloud
     * hält deren Werte pro Aufruf im Speicher, und er las bis zu seinem Ende den
     * Stand von seinem Beginn: Der Abbruch-Knopf blieb wirkungslos, der Sync lief
     * zu Ende. Über die Datei sieht ein zweites Objekt das Signal sofort.
     */
    public function testAbbruchSignalErreichtEinenZweitenAufruf(): void {
        $laufenderSync = new SyncLockService($this->lockFile);
        $abbrechenderAufruf = new SyncLockService($this->lockFile);

        $this->assertFalse($laufenderSync->abbruchAngefordert(), 'ohne Anforderung kein Abbruch');

        $abbrechenderAufruf->abbruchAnfordern();
        $this->assertTrue($laufenderSync->abbruchAngefordert(), 'der laufende Sync sieht den Abbruch nicht');

        $abbrechenderAufruf->abbruchAufheben();
        $this->assertFalse($laufenderSync->abbruchAngefordert(), 'der aufgehobene Abbruch wirkt weiter');
    }

    public function testAcquireReleaseUndProbeStatus(): void {
        $lock = new SyncLockService($this->lockFile);

        $this->assertFalse($lock->isLocked());
        $this->assertTrue($lock->acquire());
        $this->assertTrue($lock->isLocked());

        $lock->release();
        $this->assertFalse($lock->isLocked());
    }

    public function testZweiterLockKannNichtParallelErwerben(): void {
        $first = new SyncLockService($this->lockFile);
        $second = new SyncLockService($this->lockFile);

        $this->assertTrue($first->acquire());
        $this->assertFalse($second->acquire());
        $this->assertTrue($second->isLocked());

        $first->release();
        $this->assertTrue($second->acquire());
        $second->release();
    }
}

