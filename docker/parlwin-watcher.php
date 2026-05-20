<?php
declare(strict_types=1);

// parlwin enable-watcher: laeuft als eigener PHP-Sub-Prozess, gestartet vom
// parlwin-bootstrap.php-Entrypoint.  Wartet bis Nextcloud sich selbst
// installiert hat und aktiviert dann die App.

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

$maxAttempts = 300;   // 300 * 2s = 10 min
$sleepSeconds = 2;

for ($i = 0; $i < $maxAttempts; $i++) {
  [, $status] = pwOcc(['status', '--no-ansi']);
  if (strpos($status, 'installed: true') !== false) {
    // Legacy-App stilllegen (best effort).
    pwOcc(['app:disable', 'parliamentwinterthur', '--no-interaction', '--no-ansi']);

    // Federated/Loopback-Requests (Circles, Group-Hooks, ...) muessen
    // den internen nginx erreichen, nicht den externen HOST:PORT.
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

    [$code, $output] = pwOcc(['app:enable', 'parlwin', '--no-interaction', '--no-ansi']);
    if ($code === 0) {
      pwLog('parlwin enabled');
    } elseif (stripos($output, 'already enabled') !== false) {
      pwLog('parlwin already enabled');
    } else {
      pwLog("app:enable parlwin failed (exit={$code}): {$output}");
    }
    exit(0);
  }
  sleep($sleepSeconds);
}

pwLog('timeout waiting for Nextcloud installation');
exit(0);
