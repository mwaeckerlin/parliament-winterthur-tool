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
        parent::tearDown();
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

