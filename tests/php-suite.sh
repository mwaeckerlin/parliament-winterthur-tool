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

# Der Container wird in jedem Fall entfernt, auch wenn dieses Skript von aussen
# beendet wird. Gemessen am 29.10.2026: vier Container
# parlwin-php-test-php-pdf-* standen mit Exit 143 bis zu zwei Tage auf dem
# Rechner, weil die Warteschlange den Lauf beendet hatte und das Skript seine
# letzte Zeile nie erreichte. `docker run --rm` löst das nicht, weil `docker cp`
# den beendeten Container für den JUnit-Bericht noch braucht.
aufraeumen() {
  docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
}
# Bei einem Signal endet das Skript mit dem üblichen Code (130 für INT, 143 für
# TERM) statt weiterzulaufen: ein Handler, der nur zurückkehrt, führt bash in
# die nächste Zeile, und dann meldete ein beendeter Lauf den Code von PHPUnit
# und sähe grün aus.
trap aufraeumen EXIT
trap 'aufraeumen; exit 130' INT
trap 'aufraeumen; exit 143' TERM HUP

# Das Image heisst in tests/docker-compose.php-test.yml genau "$IMAGE".
# Der Lauf über alle Gruppen (npm test) baut es einmal für alle drei
# PHP-Gruppen und setzt PARLWIN_PHP_TEST_GEBAUT=1.
if [[ "${PARLWIN_PHP_TEST_GEBAUT:-0}" != "1" ]]; then
docker compose --file tests/docker-compose.php-test.yml build php-test || exit 1
fi

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

exit "$RC"
