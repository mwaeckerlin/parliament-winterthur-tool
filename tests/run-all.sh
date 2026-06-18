#!/usr/bin/env bash
#
# Führt alle Testsuiten aus (Unit, Komponenten/JS, Live, End-to-End) und gibt am
# Ende eine objektive Gesamt-Zusammenfassung aus. Jede Suite zeigt ihren eigenen
# Fortschritt (Test x von y). Der Exit-Code ist nur 0, wenn JEDER Test erfolgreich
# war – schon ein einziger fehlgeschlagener oder übersprungener Test führt zu
# Exit-Code 1.
#
# Bewusst OHNE `set -e`: Es sollen immer ALLE Suiten laufen, damit die
# Zusammenfassung vollständig ist. Über Erfolg/Misserfolg entscheidet allein die
# Auswertung der JUnit-Berichte.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JUNIT_DIR="${ROOT}/tests/.junit"
rm -rf "$JUNIT_DIR"
mkdir -p "$JUNIT_DIR"

PHPUNIT_FLAGS=(
  --bootstrap "${ROOT}/parlwin/tests/bootstrap.php"
  --fail-on-warning --fail-on-risky --fail-on-deprecation
  --fail-on-notice --fail-on-skipped --fail-on-incomplete
)

section() { printf '\n========== %s ==========\n' "$1"; }

# Stellt sicher, dass für eine Suite ein JUnit-Bericht existiert. Fehlt er (z.B.
# weil das Werkzeug abgestürzt ist), wird ein synthetischer Fehlerfall erzeugt,
# damit der Absturz in der Zusammenfassung sichtbar bleibt.
ensure_junit() {
  local name="$1" out="$2" rc="$3"
  if [[ ! -s "$out" ]]; then
    printf '<testsuite name="%s" tests="1" failures="1"><testcase classname="%s" name="suite-konnte-nicht-starten"><failure message="Keine JUnit-Ausgabe (Exit-Code %s)"/></testcase></testsuite>\n' \
      "$name" "$name" "$rc" >"$out"
  fi
}

section "Unit-Tests (PHPUnit)"
( cd "${ROOT}/parlwin" && phpunit "${PHPUNIT_FLAGS[@]}" --exclude-group live \
    --log-junit "${JUNIT_DIR}/php-unit.xml" tests )
ensure_junit "php-unit" "${JUNIT_DIR}/php-unit.xml" "$?"

section "Komponenten-/JS-Tests (Vitest)"
( cd "${ROOT}" && npx vitest run --reporter=default --reporter=junit \
    --outputFile="${JUNIT_DIR}/js.xml" )
ensure_junit "js" "${JUNIT_DIR}/js.xml" "$?"

section "Live-Tests (PHPUnit, externe Endpunkte)"
( cd "${ROOT}/parlwin" && phpunit "${PHPUNIT_FLAGS[@]}" --group live \
    --log-junit "${JUNIT_DIR}/php-live.xml" tests/Service/ScraperLiveEndpointTest.php )
ensure_junit "php-live" "${JUNIT_DIR}/php-live.xml" "$?"

section "End-to-End-Tests (Docker + Playwright)"
rm -f "${ROOT}/tests/e2e/.junit/e2e.xml"
"${ROOT}/tests/e2e/run-compose-e2e.sh"
E2E_RC=$?
# Browser-Szenarien (Playwright-JUnit) übernehmen, falls erzeugt.
if [[ -f "${ROOT}/tests/e2e/.junit/e2e.xml" ]]; then
  cp "${ROOT}/tests/e2e/.junit/e2e.xml" "${JUNIT_DIR}/e2e-browser.xml"
fi
# Die Bash-Integrationsprüfungen als einen Testfall abbilden (Exit-Code).
if [[ "$E2E_RC" -eq 0 ]]; then
  printf '<testsuite name="e2e-integration" tests="1" failures="0"><testcase classname="e2e" name="integrationspruefungen"/></testsuite>\n' \
    >"${JUNIT_DIR}/e2e-integration.xml"
else
  printf '<testsuite name="e2e-integration" tests="1" failures="1"><testcase classname="e2e" name="integrationspruefungen"><failure message="run-compose-e2e.sh Exit-Code %s"/></testcase></testsuite>\n' \
    "$E2E_RC" >"${JUNIT_DIR}/e2e-integration.xml"
fi

node "${ROOT}/tests/junit-summary.mjs" "${JUNIT_DIR}"
