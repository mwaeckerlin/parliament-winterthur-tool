#!/usr/bin/env bash
# Richtet `parlwin/vendor` für den Testlauf ein; mit `--lock` erzeugt es zuerst
# `parlwin/composer.lock` neu und prüft sie mit `composer audit`.
#
# `vendor/` ist git-ignoriert und entsteht im Bau: Die Stufe «php-deps» von
# Dockerfile.php-fpm installiert die Laufzeit-Abhängigkeiten aus
# `parlwin/composer.lock` für das ausgelieferte Image, die Stufe «php-deps-test»
# dieselben samt PHPUnit. Die Tests laufen auf dem Rechner, nicht im Image, und
# brauchen beides: ohne diesen Schritt kennt PHPUnit eine neu aufgenommene
# Bibliothek nicht, und auf einem GitHub-Runner gibt es überhaupt kein PHPUnit
# im Pfad.
#
# Herausgeholt werden die Stufen «composer-lock» und «php-vendor» (FROM scratch,
# enthalten nur die Datei oder das Verzeichnis) direkt über die Ausgabe des
# Baus. Das braucht keinen laufenden Container und keine Shell im Image — das
# ausgelieferte Image hat bewusst keine (Image-Vertrag). Auf dem Rechner gibt es
# kein Composer.
#
#   npm run composer:update              vendor aus der vorhandenen composer.lock
#   npm run composer:update -- --lock    composer.lock neu auflösen, prüfen, vendor
set -euo pipefail

cd "$(dirname "$0")/.."

lock=nein
for arg in "$@"; do
  case "$arg" in
    --lock) lock=ja ;;
    *)
      echo "[vendor] FEHLER: unbekannte Option «${arg}», erlaubt ist nur --lock" >&2
      exit 2
      ;;
  esac
done

ablage="$(mktemp -d)"
trap 'rm -rf "$ablage"' EXIT

if [ "$lock" = ja ]; then
  # --no-cache-filter: ohne ihn liefert der Cache die alte Auflösung, solange
  # composer.json gleich bleibt.
  echo "[vendor] Baue die Stufe composer-lock (composer update)"
  docker build --file Dockerfile.php-fpm --target composer-lock \
    --no-cache-filter composer-lock-bau \
    --output "type=local,dest=${ablage}/lock" .
  if [ ! -s "$ablage/lock/composer.lock" ]; then
    echo "[vendor] FEHLER: in der Stufe composer-lock fehlt composer.lock" >&2
    exit 1
  fi
  echo "[vendor] Ersetze parlwin/composer.lock"
  mv "$ablage/lock/composer.lock" parlwin/composer.lock

  echo "[vendor] Prüfe parlwin/composer.lock mit composer audit (Stufe php-audit)"
  if ! docker build --file Dockerfile.php-fpm --target php-audit \
    --no-cache-filter php-audit --progress=plain \
    --output type=cacheonly .; then
    echo "[vendor] FEHLER: composer audit meldet eine Lücke in den ausgelieferten Abhängigkeiten (Ausgabe oben)" >&2
    exit 1
  fi
fi

if [ ! -s parlwin/composer.lock ]; then
  echo "[vendor] FEHLER: parlwin/composer.lock fehlt. Zuerst 'npm run composer:update -- --lock' laufen lassen." >&2
  exit 1
fi

echo "[vendor] Baue die Stufe php-vendor (composer mit Entwicklungswerkzeugen)"
docker build --file Dockerfile.php-fpm --target php-vendor \
  --output "type=local,dest=${ablage}/vendor-stufe" .

if [ ! -f "$ablage/vendor-stufe/vendor/autoload.php" ]; then
  echo "[vendor] FEHLER: in der Stufe php-vendor fehlt vendor/autoload.php" >&2
  exit 1
fi

if [ ! -x "$ablage/vendor-stufe/vendor/bin/phpunit" ]; then
  echo "[vendor] FEHLER: in der Stufe php-vendor fehlt vendor/bin/phpunit" >&2
  exit 1
fi

echo "[vendor] Ersetze parlwin/vendor"
rm -rf parlwin/vendor
mv "$ablage/vendor-stufe/vendor" parlwin/vendor

echo "[vendor] Fertig — $(find parlwin/vendor -maxdepth 1 -mindepth 1 -type d | wc -l) Pakete"
