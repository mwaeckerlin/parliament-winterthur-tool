#!/usr/bin/env bash
# Holt die PHP-Abhängigkeiten aus dem Bau auf den Rechner.
#
# Installiert werden sie im Abbild (Stufe «php-deps» mit composer); `vendor/`
# ist git-ignoriert und entsteht dort. Die Tests laufen aber auf dem Rechner und
# brauchen denselben Stand — ohne diesen Schritt kennt PHPUnit eine neu
# aufgenommene Bibliothek nicht, obwohl die Anwendung im Container sie hat.
#
# Kopiert wird mit `docker compose cp`: Das ausgelieferte Abbild hat bewusst
# keine Shell (Abbild-Vertrag), also lässt sich darin kein tar aufrufen.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "[vendor] Baue das PHP-Abbild neu (composer läuft in der Stufe php-deps)"
docker compose build nextcloud-php-fpm

ablage="$(mktemp -d)"
trap 'rm -rf "$ablage"' EXIT

echo "[vendor] Hole die installierten Abhängigkeiten aus dem Abbild"
docker compose cp \
  nextcloud-php-fpm:/usr/local/share/nextcloud/seed/custom_apps/parlwin/vendor \
  "$ablage/vendor"

if [ ! -f "$ablage/vendor/autoload.php" ]; then
  echo "[vendor] FEHLER: im Abbild fehlt vendor/autoload.php" >&2
  exit 1
fi

echo "[vendor] Ersetze parlwin/vendor"
rm -rf parlwin/vendor
mv "$ablage/vendor" parlwin/vendor

echo "[vendor] Fertig — $(find parlwin/vendor -maxdepth 1 -mindepth 1 -type d | wc -l) Pakete"
