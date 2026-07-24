<?php
declare(strict_types=1);

// parlwin enable-watcher: läuft als eigener PHP-Sub-Prozess, gestartet vom
// parlwin-bootstrap.php-Entrypoint.  Wartet bis Nextcloud sich selbst
// installiert hat, aktiviert dann die App und führt occ upgrade durch.
//
// Bei einem Migrations-Fehler wird der Container-Hauptprozess (PID 1, php-fpm
// master) beendet, damit Docker den Fehler meldet und – mit restart-policy –
// den Container neu startet, statt still im Wartungsmodus zu hängen.

function pwLog(string $message): void
{
  fwrite(STDERR, "parlwin-watcher: {$message}\n");
}

/**
 * @return array{0:int, 1:string}
 */
function pwPhp(array $args): array
{
  $cmd = array_merge(['/usr/bin/php'], $args);
  // stderr → stdout: avoids pipe deadlock when the command produces large output
  $proc = proc_open(
    $cmd,
    [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
    $pipes
  );
  if (!is_resource($proc)) {
    return [1, 'failed to spawn php'];
  }
  $out = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $code = proc_close($proc);
  return [is_int($code) ? $code : 1, trim((string) $out)];
}

/**
 * @return array{0:int, 1:string}
 */
function pwOcc(array $args): array
{
  return pwPhp(array_merge(['/app/occ'], $args));
}

/**
 * Bestimmt den Prozess, dessen Beendigung den Container stoppt.
 *
 * Im Container ist der Hauptprozess PID 1 (Entrypoint → php-fpm master); ihn zu
 * beenden stoppt den Container. Ein gültiger direkter Elternprozess (> 1) wird
 * bevorzugt, sonst fällt es auf PID 1 zurück. Der frühere Guard `> 1` OHNE
 * Fallback verhinderte genau den PID-1-Fall — dann wurde kein Signal gesendet
 * und der Container blieb still im Wartungsmodus hängen.
 */
function pwSignalZiel(?int $ppid): int
{
  return ($ppid !== null && $ppid > 1) ? $ppid : 1;
}

/**
 * Signal-Nummer robust auflösen: die Konstanten SIGTERM/SIGKILL stammen aus der
 * pcntl-Extension, die im Basis-Image NICHT geladen ist. posix_kill akzeptiert
 * aber die numerischen POSIX-Standardwerte (TERM=15, KILL=9).
 */
function pwSignal(string $name): int
{
  if (defined($name)) {
    return (int) constant($name);
  }
  return $name === 'SIGKILL' ? 9 : 15;
}

/**
 * Stoppt den Container, indem der Hauptprozess beendet wird.  Docker erkennt den
 * Exit und gibt den Fehler im Log aus.
 */
function pwAbortContainer(string $reason): never
{
  pwLog('FATAL: ' . $reason);
  pwLog('Stopping container so Docker can report the problem and restart it.');
  $ppid = function_exists('posix_getppid') ? posix_getppid() : null;
  $ziel = pwSignalZiel(is_int($ppid) ? $ppid : null);
  if (function_exists('posix_kill')) {
    pwLog("Sending SIGTERM to process {$ziel}");
    posix_kill($ziel, pwSignal('SIGTERM'));
    // Falls der Hauptprozess SIGTERM nicht befolgt (z.B. hängender Upgrade),
    // nach kurzer Frist hart beenden, damit der Container sicher stoppt.
    sleep(5);
    pwLog("Escalating to SIGKILL for process {$ziel}");
    posix_kill($ziel, pwSignal('SIGKILL'));
  } else {
    pwLog('posix_kill unavailable — cannot stop the container process reliably.');
  }
  exit(1);
}

/**
 * Haupt-Schleife: wartet auf die Installation, aktiviert die App, führt das
 * Upgrade aus und übernimmt danach den Cron-Tick.
 */
function pwMain(): void
{
  $maxAttempts = 300;   // 300 * 2s = 10 min
  $sleepSeconds = 2;

  for ($i = 0; $i < $maxAttempts; $i++) {
    [, $status] = pwOcc(['status', '--no-ansi']);
    if (strpos($status, 'installed: true') !== false) {
      // Wartungsmodus aufheben falls er von einem abgebrochenen Upgrade übrig
      // ist — sonst lehnt Nextcloud die folgenden Schreib-Kommandos ab.
      [, $vorher] = pwOcc(['status', '--no-ansi']);
      if (strpos($vorher, 'maintenance: true') !== false) {
        pwLog('Maintenance mode active (leftover from aborted upgrade) — disabling first...');
        pwOcc(['maintenance:mode', '--off', '--no-ansi']);
      }

      // Migrationen zuerst ausführen: Steht ein Core-/App-Upgrade an, sind nur
      // eingeschränkte occ-Kommandos erlaubt (app:enable würde fehlschlagen).
      // Schlägt eine Migration fehl, bricht occ upgrade mit Exit != 0 ab — dann
      // stoppt der Container, damit Docker den Fehler meldet.
      pwLog('Running occ upgrade to apply pending migrations...');
      [$code, $output] = pwOcc(['upgrade', '--no-interaction', '--no-ansi']);
      // Exit 0 = erfolgreich aktualisiert, 3 = kein Upgrade nötig (bereits aktuell).
      if ($code !== 0 && $code !== 3) {
        pwAbortContainer(
          "occ upgrade failed (exit={$code}) — a migration likely could not achieve its desired state.\n" .
          "Output:\n{$output}\n" .
          'Fix the database or the migration, then restart the container.'
        );
      }
      pwLog('occ upgrade finished (exit=' . $code . ')');

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

      // App aktivieren. Ein erneutes app:enable triggert parlwins eigene
      // Migrationen, die beim occ upgrade oben bereits gelaufen sein sollten.
      [$code, $output] = pwOcc(['app:enable', 'parlwin', '--no-interaction', '--no-ansi']);
      if ($code === 0) {
        pwLog('parlwin enabled');
      } elseif (stripos($output, 'already enabled') !== false) {
        pwLog('parlwin already enabled');
      } else {
        pwAbortContainer("app:enable parlwin failed (exit={$code}):\n{$output}");
      }

      // Nach app:enable kann eine parlwin-Migration angestanden haben — noch ein
      // Upgrade, damit der Wartungsmodus garantiert aufgehoben ist.
      [$code, $output] = pwOcc(['upgrade', '--no-interaction', '--no-ansi']);
      if ($code !== 0 && $code !== 3) {
        pwAbortContainer(
          "occ upgrade (after app:enable) failed (exit={$code}).\nOutput:\n{$output}"
        );
      }
      [, $status] = pwOcc(['status', '--no-ansi']);
      if (strpos($status, 'maintenance: true') !== false) {
        pwLog('Maintenance mode still active after upgrade — disabling.');
        pwOcc(['maintenance:mode', '--off', '--no-ansi']);
      }
      pwLog('parlwin ready');

      // Cron: Ohne externen Tick führt Nextcloud keine Background-Jobs aus — die
      // automatische Synchronisation (SyncJob) liefe nie. Die Basis bringt keinen
      // Cron-Daemon mit, deshalb übernimmt dieser Watcher-Prozess den Tick selbst.
      pwOcc(['config:app:set', 'core', 'backgroundjobs_mode', '--value=cron', '--no-ansi']);

      $cronInterval = (int) (getenv('PARLWIN_CRON_INTERVAL') ?: '300');
      if ($cronInterval < 5) {
        $cronInterval = 5;
      }
      pwLog("Entering cron loop (php /app/cron.php every {$cronInterval}s)...");
      while (true) {
        sleep($cronInterval);
        [$code, $cronOut] = pwPhp(['/app/cron.php']);
        if ($code !== 0) {
          pwLog("cron tick failed (exit={$code}): {$cronOut}");
        }
      }
    }
    sleep($sleepSeconds);
  }

  pwLog('timeout waiting for Nextcloud installation');
  exit(0);
}

// Nur ausführen, wenn das Script direkt gestartet wurde (Entrypoint), nicht wenn
// es aus einem Test via require geladen wird — dann sind nur die Funktionen für
// den Test verfügbar (leerer Backtrace = direkte Ausführung im globalen Scope).
if (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) === []) {
  pwMain();
}
