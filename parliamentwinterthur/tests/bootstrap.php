<?php

declare(strict_types=1);

/**
 * Test-Bootstrap für das Parliament Winterthur Plugin.
 *
 * Stellt minimale Stubs für Nextcloud- und PSR-Interfaces bereit,
 * sodass Unit-Tests ohne eine vollständige Nextcloud-Installation
 * ausgeführt werden können.
 *
 * Verwendung:
 *   phpunit --bootstrap tests/bootstrap.php tests/
 */

$appDir = dirname(__DIR__);

// ── PSR-Stubs ────────────────────────────────────────────────────────────────

if (!interface_exists('Psr\Log\LoggerInterface')) {
    // phpcs:ignore
    eval('namespace Psr\Log; interface LoggerInterface {
        public function debug(string $message, array $context = []): void;
        public function info(string $message, array $context = []): void;
        public function notice(string $message, array $context = []): void;
        public function warning(string $message, array $context = []): void;
        public function error(string $message, array $context = []): void;
        public function critical(string $message, array $context = []): void;
        public function alert(string $message, array $context = []): void;
        public function emergency(string $message, array $context = []): void;
        public function log($level, string $message, array $context = []): void;
    }');
}

// ── Nextcloud-Stubs ──────────────────────────────────────────────────────────

if (!interface_exists('OCP\Http\Client\IClientService')) {
    // phpcs:ignore
    eval('namespace OCP\Http\Client; interface IClientService {}');
}

// ── PSR-4 Autoloader ─────────────────────────────────────────────────────────

// Produktiver Code: lib/
spl_autoload_register(static function (string $class) use ($appDir): void {
    $prefix = 'OCA\\ParliamentWinterthur\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = $appDir . '/lib/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Tests: tests/
spl_autoload_register(static function (string $class) use ($appDir): void {
    $prefix = 'OCA\\ParliamentWinterthur\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = $appDir . '/tests/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
