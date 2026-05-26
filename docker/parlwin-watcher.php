<?php
declare(strict_types=1);

// parlwin enable-watcher: läuft als eigener PHP-Sub-Prozess, gestartet vom
// parlwin-bootstrap.php-Entrypoint.  Wartet bis Nextcloud sich selbst
// installiert hat, aktiviert dann die App und führt occ upgrade durch.
//
// Bei einem Migrations-Fehler wird der Parent-Prozess (PHP-FPM) via SIGTERM
// beendet, damit Docker den Container neu startet und den Fehler sichtbar macht.

function pwLog(string $message): void
{
  fwrite(STDERR, "parlwin-watcher: {$message}\n");
}

/**
 * @return array{0:int, 1:string}
 */
function pwOcc(array $args): array
{
  $cmd = array_merge(['/usr/bin/php', '/app/occ'], $args);
  $proc = proc_open(
    $cmd,
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
  );
  if (!is_resource($proc)) {
    return [1, 'failed to spawn occ'];
  }
  $out = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  $code = proc_close($proc);
  $combined = trim(((string) $out) . "\n" . ((string) $err));
  return [is_int($code) ? $code : 1, $combined];
}

/**
 * Stoppt den Container indem SIGTERM an den Parent-Prozess (PHP-FPM) geschickt wird.
 * Docker erkennt den Exit und gibt den Fehler im Log aus.
 */
function pwAbortContainer(string $reason): never
{
  pwLog('FATAL: ' . $reason);
  pwLog('Stopping container so Docker can report the problem.');
  $parentPid = function_exists('posix_getppid') ? posix_getppid() : null;
  if ($parentPid !== null && $parentPid > 1 && function_exists('posix_kill')) {
    pwLog("Sending SIGTERM to parent process {$parentPid}");
    posix_kill($parentPid, SIGTERM);
  }
  exit(1);
}

$maxAttempts = 300;   // 300 * 2s = 10 min
$sleepSeconds = 2;

for ($i = 0; $i < $maxAttempts; $i++) {
  [, $status] = pwOcc(['status', '--no-ansi']);
  if (strpos($status, 'installed: true') !== false) {
    // Legacy-App stilllegen (best effort).
    pwOcc(['app:disable', 'parliamentwinterthur', '--no-interaction', '--no-ansi']);

    // Federated/Loopback-Requests müssen den internen nginx erreichen.
    $internal = getenv('PARLWIN_INTERNAL_URL');
    if (!is_string($internal) || $internal === '') {
      $internal = 'http://nextcloud-nginx:8080';
    }
    pwOcc([
      'config:system:set',
      'overwrite.cli.url',
      '--value=' . $internal,
      '--no-ansi',
    ]);

    // App aktivieren.
    [$code, $output] = pwOcc(['app:enable', 'parlwin', '--no-interaction', '--no-ansi']);
    if ($code === 0) {
      pwLog('parlwin enabled');
    } elseif (stripos($output, 'already enabled') !== false) {
      pwLog('parlwin already enabled');
    } else {
      pwAbortContainer("app:enable parlwin failed (exit={$code}):\n{$output}");
    }

    // Migrationen ausführen. Schlägt eine Migration fehl, bricht occ upgrade mit
    // Exit-Code != 0 ab — dann stoppt der Container damit Docker den Fehler meldet.
    pwLog('Running occ upgrade to apply pending migrations...');
    [$code, $output] = pwOcc(['upgrade', '--no-interaction', '--no-ansi']);
    if ($code !== 0) {
      pwAbortContainer(
        "occ upgrade failed (exit={$code}) — a migration likely could not achieve its desired state.\n" .
        "Output:\n{$output}\n" .
        'Fix the database or the migration, then restart the container.'
      );
    }
    pwLog('occ upgrade completed successfully');
    pwLog("upgrade output:\n{$output}");

    exit(0);
  }
  sleep($sleepSeconds);
}

pwLog('timeout waiting for Nextcloud installation');
exit(0);
