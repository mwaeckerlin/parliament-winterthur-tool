#!/usr/bin/env bash
# Führt eine PHPUnit-Suite im Image aus, also mit dem PHP der Anwendung.
#
#   tests/php-suite.sh <name> [<junit-ziel>] -- <phpunit-argumente…>
#
# <name> benennt den Lauf (nur für Meldungen und den Containernamen),
# <junit-ziel> ist die Datei auf dem Rechner, in die der JUnit-Bericht kommt
# (leer: kein Bericht). Der Exit-Code ist der von PHPUnit.
#
# Warum im Container: Der GitHub-Runner bringt PHP 8.3 mit, PHPUnit 13 braucht
# mindestens 8.4.1, und die Anwendung läuft auf dem PHP ihres Images. Ein PHP
# des Wirts prüft die falsche Laufzeit und fehlt auf dem Runner ganz.
#
# Kein Bind-Mount: Das Image bringt das Projekt über COPY mit
# (Dockerfile.php-test), und der Bericht kommt mit `docker cp` heraus.
set -uo pipefail

NAME="${1:?Name der Suite fehlt}"
shift
JUNIT_ZIEL=""
if [[ "${1:-}" != "--" ]]; then
  JUNIT_ZIEL="${1:-}"
  shift
fi
[[ "${1:-}" == "--" ]] && shift

cd "$(dirname "$0")/.."

IMAGE="parlwin-php-test:lokal"
CONTAINER="parlwin-php-test-${NAME}-$$"

docker build --file Dockerfile.php-test --tag "$IMAGE" . || exit 1

JUNIT_IM_CONTAINER="/test/parlwin/junit.xml"
ARGS=("$@")
if [[ -n "$JUNIT_ZIEL" ]]; then
  ARGS+=(--log-junit "$JUNIT_IM_CONTAINER")
fi

# `--init`: Ohne ihn wäre PHPUnit selbst Prozess 1, und `getmypid()` gäbe 1
# zurück. Die Sperre der Synchronisation schreibt nur eine PID über 1
# (SyncLockService::pidSetzen), also hielte sie den laufenden Prozess für keinen
# und drei Tests des Abbruchs im Startfenster schlügen fehl.
docker run --init --name "$CONTAINER" "$IMAGE" "${ARGS[@]}"
RC=$?

if [[ -n "$JUNIT_ZIEL" ]]; then
  mkdir -p "$(dirname "$JUNIT_ZIEL")"
  docker cp "${CONTAINER}:${JUNIT_IM_CONTAINER}" "$JUNIT_ZIEL" >/dev/null 2>&1 || true
fi

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
exit "$RC"
