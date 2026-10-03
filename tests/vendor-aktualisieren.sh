#!/usr/bin/env bash
# Richtet `parlwin/vendor` für den Testlauf ein.
#
# `vendor/` ist git-ignoriert und entsteht im Bau: Die Stufe «php-deps» von
# Dockerfile.php-fpm installiert die Laufzeit-Abhängigkeiten für das
# ausgelieferte Image, die Stufe «php-deps-test» dieselben samt PHPUnit. Die
# Tests laufen auf dem Rechner, nicht im Image, und brauchen beides: ohne
# diesen Schritt kennt PHPUnit eine neu aufgenommene Bibliothek nicht, und auf
# einem GitHub-Runner gibt es überhaupt kein PHPUnit im Pfad.
#
# Herausgeholt wird die Stufe «php-vendor» (FROM scratch, enthält nur das
# Verzeichnis) direkt über die Ausgabe des Baus. Das braucht keinen laufenden
# Container und keine Shell im Image — das ausgelieferte Image hat bewusst
# keine (Image-Vertrag).
set -euo pipefail

cd "$(dirname "$0")/.."

ablage="$(mktemp -d)"
trap 'rm -rf "$ablage"' EXIT

echo "[vendor] Baue die Stufe php-vendor (composer mit Entwicklungswerkzeugen)"
docker build --file Dockerfile.php-fpm --target php-vendor \
  --output "type=local,dest=${ablage}" .

if [ ! -f "$ablage/vendor/autoload.php" ]; then
  echo "[vendor] FEHLER: in der Stufe php-vendor fehlt vendor/autoload.php" >&2
  exit 1
fi

if [ ! -x "$ablage/vendor/bin/phpunit" ]; then
  echo "[vendor] FEHLER: in der Stufe php-vendor fehlt vendor/bin/phpunit" >&2
  exit 1
fi

echo "[vendor] Ersetze parlwin/vendor"
rm -rf parlwin/vendor
mv "$ablage/vendor" parlwin/vendor

echo "[vendor] Fertig — $(find parlwin/vendor -maxdepth 1 -mindepth 1 -type d | wc -l) Pakete"
