#!/usr/bin/env bash
# Erzeugt `package-lock.json` neu aus `package.json` und seinen `overrides`
# und installiert diesen Stand in `node_modules`.
#
# Aufgelöst wird mit dem npm des Images mwaeckerlin/nodejs-build, demselben,
# das die Oberfläche im Bau installiert: npm 9 bricht beim Auflösen dieses
# Baums mit «Cannot read properties of null (reading 'edgesOut')» ab, beim
# Erzeugen wie beim Installieren. Herausgeholt wird die Stufe «npm-lock» von
# Dockerfile.php-fpm (FROM scratch, enthält die Datei und die Fassung von npm)
# über die Ausgabe des Baus, ohne laufenden Container. Installiert wird auf dem
# Rechner mit derselben Fassung von npm, damit die Pakete für das
# Betriebssystem des Rechners gewählt werden.
set -euo pipefail

cd "$(dirname "$0")/.."

ablage="$(mktemp -d)"
trap 'rm -rf "$ablage"' EXIT

echo "[lock] Baue die Stufe npm-lock"
docker build --file Dockerfile.php-fpm --target npm-lock \
  --output "type=local,dest=${ablage}" .

if [ ! -s "$ablage/package-lock.json" ] || [ ! -s "$ablage/npm-version" ]; then
  echo "[lock] FEHLER: in der Stufe npm-lock fehlt package-lock.json oder npm-version" >&2
  exit 1
fi
npm_version="$(cat "$ablage/npm-version")"

echo "[lock] Ersetze package-lock.json (npm ${npm_version})"
mv "$ablage/package-lock.json" package-lock.json

# npm wird in ein eigenes Verzeichnis installiert und von dort gestartet: npx
# im Projekt liest den Baum des Projekts mit dem alten npm und scheitert daran.
echo "[lock] Installiere den Stand mit npm ${npm_version} ci"
npm install --prefix "$ablage/npm" --no-audit --no-fund "npm@${npm_version}"
# --prefix hält npm in diesem Projekt; sonst nimmt es das Verzeichnis darüber,
# wenn dessen package.json dieses Projekt als Workspace führt.
"$ablage/npm/node_modules/.bin/npm" --prefix "$(pwd)" ci --no-audit --no-fund

echo "[lock] Fertig"
