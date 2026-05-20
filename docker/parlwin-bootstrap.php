<?php
declare(strict_types=1);

// parlwin php-fpm entrypoint wrapper.
//
// Die Basis (mwaeckerlin/nextcloud:php-fpm) hat KEINE Shell — nur /usr/bin/php
// und php-fpm.  Deshalb ist dies ein PHP-Skript.
//
// Ablauf:
//   1. parlwin-Quelle aus /usr/local/share/nextcloud/seed/custom_apps/parlwin
//      ueber /app/custom_apps/parlwin spiegeln.  Damit propagieren neue
//      Image-Versionen ins persistente apps-Volume — die Basis-Funktion
//      seedCustomAppsIfEmpty() seedet nur bei komplett leerem Zielordner.
//   2. Legacy-Verzeichnis /app/custom_apps/parliamentwinterthur entfernen,
//      falls aus einer alten Stack-Version noch vorhanden.
//   3. parlwin-watcher.php als unabhaengigen Sub-Prozess starten.  Er pollt
//      `occ status` bis Nextcloud installiert ist und aktiviert dann die App.
//   4. Basis-Bootstrap office-bootstrap.php inline via `require` ausfuehren.

const PARLWIN_SEED_SRC = '/usr/local/share/nextcloud/seed/custom_apps/parlwin';
const PARLWIN_TARGET_DIR = '/app/custom_apps';
const PARLWIN_LEGACY_NAME = 'parliamentwinterthur';

function pwbLog(string $message): void
{
  fwrite(STDERR, "parlwin-bootstrap: {$message}\n");
}

function pwbRemoveTree(string $path): void
{
  if (is_link($path) || (is_file($path) && !is_dir($path))) {
    @unlink($path);
    return;
  }
  if (!is_dir($path)) {
    return;
  }
  foreach (scandir($path) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
      continue;
    }
    pwbRemoveTree($path . '/' . $entry);
  }
  @rmdir($path);
}

function pwbCopyTree(string $src, string $dst): void
{
  if (is_link($src)) {
    $target = readlink($src);
    if ($target !== false) {
      @symlink($target, $dst);
    }
    return;
  }
  if (is_dir($src)) {
    if (!is_dir($dst) && !@mkdir($dst, 0775, true) && !is_dir($dst)) {
      pwbLog("failed to create directory {$dst}");
      return;
    }
    @chmod($dst, 0775);
    foreach (scandir($src) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      pwbCopyTree($src . '/' . $entry, $dst . '/' . $entry);
    }
    return;
  }
  if (is_file($src)) {
    @copy($src, $dst);
    @chmod($dst, 0664);
  }
}

function pwbSyncParlwin(): void
{
  if (!is_dir(PARLWIN_SEED_SRC)) {
    pwbLog('seed directory ' . PARLWIN_SEED_SRC . ' is missing — cannot sync');
    return;
  }

  if (
    !is_dir(PARLWIN_TARGET_DIR)
    && !@mkdir(PARLWIN_TARGET_DIR, 0775, true)
    && !is_dir(PARLWIN_TARGET_DIR)
  ) {
    pwbLog('failed to create ' . PARLWIN_TARGET_DIR);
    return;
  }

  $dst = PARLWIN_TARGET_DIR . '/parlwin';
  pwbRemoveTree($dst);
  pwbCopyTree(PARLWIN_SEED_SRC, $dst);
  pwbLog('synced parlwin into ' . $dst);

  $legacy = PARLWIN_TARGET_DIR . '/' . PARLWIN_LEGACY_NAME;
  if (is_dir($legacy)) {
    pwbRemoveTree($legacy);
    pwbLog('removed legacy directory ' . $legacy);
  }
}

pwbSyncParlwin();

// Watcher als unabhaengigen Sub-Prozess starten.  Er aktiviert die App,
// sobald Nextcloud installiert ist.
$watcher = proc_open(
  ['/usr/bin/php', '/usr/local/bin/parlwin-watcher.php'],
  [
    0 => ['file', '/dev/null', 'r'],
    1 => STDERR,
    2 => STDERR,
  ],
  $pipes
);
if (!is_resource($watcher)) {
  pwbLog('failed to spawn parlwin-watcher.php');
} else {
  // Handle freigeben — Watcher laeuft selbststaendig weiter.
  unset($watcher);
}

// SIGCHLD ignorieren: Kernel reapt beendete Kinder automatisch, der Watcher
// wird nach exit(0) nicht zum Zombie.  SIG_IGN bleibt ueber spaeteres
// proc_open hinweg erhalten.
if (function_exists('pcntl_signal') && defined('SIGCHLD')) {
  pcntl_signal(SIGCHLD, SIG_IGN);
}

// Basis-Entrypoint inline ausfuehren.  Funktionsnamen in office-bootstrap.php
// (logMessage, runOcc, isInstalled, ...) kollidieren nicht mit den hier
// definierten pwb*-Funktionen.  office-bootstrap.php endet selbst mit exit().
require '/usr/local/bin/office-bootstrap.php';
