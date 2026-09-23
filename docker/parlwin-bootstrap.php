<?php
declare(strict_types=1);

// parlwin php-fpm entrypoint wrapper.
//
// Die Basis (mwaeckerlin/nextcloud:php-fpm) hat KEINE Shell — nur /usr/bin/php
// und php-fpm.  Deshalb ist dies ein PHP-Skript.
//
// Ablauf:
//   0. Speichergrenze von PHP setzen (siehe pwbWritePhpIni).
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
// Das Verzeichnis, das `PHP_INI_SCAN_DIR` im Image zusaetzlich zum Standard
// nennt (Dockerfile.php-fpm).  /etc/php* ist dem Laufzeit-Benutzer nicht
// schreibbar, und die Basis hat keine Shell, um das zu aendern; /tmp traegt das
// Sticky-Bit, und das hier angelegte Verzeichnis gehoert dem Laufzeit-Benutzer
// mit 0755 — schreiben kann darin nur, wer ohnehin als dieser Benutzer laeuft
// und damit auch den App-Code unter /app/custom_apps aendern koennte.
const PARLWIN_PHP_INI_DIR = '/tmp/parlwin-php.d';
const PARLWIN_MEMORY_LIMIT_DEFAULT = '1024M';

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

// Speichergrenze von PHP: Das Basis-Image liefert 512 MB, ein Budgetbuch
// braucht beim Einlesen mehr (gemessen am Buch 2027: 574,7 MB).  Der Wert steht
// in `PARLWIN_PHP_MEMORY_LIMIT` und gilt fuer alles, was in diesem Container
// laeuft: php-fpm, `occ` und der Hintergrundauftrag.  Geschrieben wird er,
// bevor php-fpm und der Watcher starten — beide lesen das Verzeichnis beim
// eigenen Start.
function pwbWritePhpIni(): void
{
  $grenze = (string) (getenv('PARLWIN_PHP_MEMORY_LIMIT') ?: PARLWIN_MEMORY_LIMIT_DEFAULT);
  if (!preg_match('/^-?\d+[KMG]?$/i', $grenze)) {
    pwbLog("ignoring invalid PARLWIN_PHP_MEMORY_LIMIT «{$grenze}»");
    $grenze = PARLWIN_MEMORY_LIMIT_DEFAULT;
  }
  if (!is_dir(PARLWIN_PHP_INI_DIR) && !@mkdir(PARLWIN_PHP_INI_DIR, 0755, true) && !is_dir(PARLWIN_PHP_INI_DIR)) {
    pwbLog('failed to create ' . PARLWIN_PHP_INI_DIR . ' — memory limit stays at ' . ini_get('memory_limit'));
    return;
  }
  $datei = PARLWIN_PHP_INI_DIR . '/99-parlwin.ini';
  if (@file_put_contents($datei, "memory_limit = {$grenze}\n") === false) {
    pwbLog('failed to write ' . $datei . ' — memory limit stays at ' . ini_get('memory_limit'));
    return;
  }
  @chmod($datei, 0644);
  pwbLog("memory_limit = {$grenze} ({$datei})");
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

pwbWritePhpIni();
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
