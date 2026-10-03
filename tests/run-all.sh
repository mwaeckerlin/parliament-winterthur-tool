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

# Die PHP-Suiten laufen IM Abbild, also mit dem PHP der Anwendung
# (tests/php-suite.sh). Ein PHP des Wirts ist eine andere Fassung und fehlt auf
# dem GitHub-Runner ganz: Dort liegt PHP 8.3, PHPUnit 13 braucht mindestens
# 8.4.1, und keine PHP-Suite startete (Lauf 37097532261 vom 2026-10-03).
# PHPUnit selbst steckt in `parlwin/vendor`, git-ignoriert und aus dem Bau
# geholt: `npm run composer:update`, das `npm run test:ci` vorher ausführt.
if [[ ! -x "${ROOT}/parlwin/vendor/bin/phpunit" ]]; then
  echo "FEHLER: parlwin/vendor/bin/phpunit fehlt. Zuerst 'npm run composer:update' laufen lassen." >&2
  exit 1
fi
PHP_SUITE="${ROOT}/tests/php-suite.sh"

# Pfade relativ zu parlwin/, weil dieselben Argumente im Container gelten.
PHPUNIT_FLAGS=(
  --bootstrap tests/bootstrap.php
  --fail-on-warning --fail-on-risky --fail-on-deprecation
  --fail-on-notice --fail-on-skipped --fail-on-incomplete
  # Eine Verwerfung von PHPUnit selbst zählt ebenso: was PHPUnit 13 verwirft,
  # fällt in PHPUnit 14 aus, und bis dahin bleibt sie sonst unbemerkt stehen.
  --fail-on-phpunit-deprecation
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
# Aussen vor bleiben «generate» (schreibt Referenzdaten), «pruefung» (das
# Werkzeug der Prüfung von Hand, `npm run test:pruefung`) und «messung» (zeigt
# eine Buchzeile mit ihren x-Positionen, `npm run test:messung`) — keines davon
# ist ein Test. «live» läuft weiter unten gegen die echten Endpunkte.
bash "$PHP_SUITE" php-unit "${JUNIT_DIR}/php-unit.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --exclude-group live --exclude-group pdf --exclude-group generate \
    --exclude-group pruefung --exclude-group messung tests
ensure_junit "php-unit" "${JUNIT_DIR}/php-unit.xml" "$?"

section "Budget-Parser (PHPUnit, Gruppe pdf)"
# Diese Gruppe lief früher NUR von Hand über `npm run test:pdf` und fehlte im
# Regressionslauf. Damit blieb der ganze Weg «Budget-Geschäft → Drehbuch →
# Budgetbuch» ungeprüft: Ein Aufruf mit falscher Argumentzahl kam so bis in die
# laufende Instanz und liess jede Budget-Ansicht mit Serverfehler enden
# (2026-08-28). Früher gemessen: acht Minuten und 300 MB; am 2026-10-02 unter
# PHP 8.5 und PHPUnit 13, bei gleichzeitigen Bauläufen anderer Projekte auf
# demselben Rechner, 56 Minuten und 731 MB.
bash "$PHP_SUITE" php-pdf "${JUNIT_DIR}/php-pdf.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --group pdf tests
ensure_junit "php-pdf" "${JUNIT_DIR}/php-pdf.xml" "$?"

section "Komponenten-/JS-Tests (Vitest)"
( cd "${ROOT}" && npx vitest run --reporter=default --reporter=junit \
    --outputFile="${JUNIT_DIR}/js.xml" )
ensure_junit "js" "${JUNIT_DIR}/js.xml" "$?"

section "Live-Tests (PHPUnit, externe Endpunkte)"
bash "$PHP_SUITE" php-live "${JUNIT_DIR}/php-live.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --group live tests/Service/ScraperLiveEndpointTest.php
ensure_junit "php-live" "${JUNIT_DIR}/php-live.xml" "$?"

section "Image-Contract (ausgelieferte Images ohne Shell)"
( cd "${ROOT}" && npm run test:image )
IMAGE_RC=$?
if [[ "$IMAGE_RC" -eq 0 ]]; then
  printf '<testsuite name="image-contract" tests="1" failures="0"><testcase classname="image" name="images-ohne-shell"/></testsuite>\n' \
    >"${JUNIT_DIR}/image-contract.xml"
else
  printf '<testsuite name="image-contract" tests="1" failures="1"><testcase classname="image" name="images-ohne-shell"><failure message="image-contract.sh Exit-Code %s"/></testcase></testsuite>\n' \
    "$IMAGE_RC" >"${JUNIT_DIR}/image-contract.xml"
fi

section "End-to-End-Tests (Docker + Playwright)"
# Auf dem GitHub-Runner bleiben sie aussen vor (PARLWIN_TESTS_OHNE_E2E=1 im
# Befehl `test:ci`). Gemessen am 2026-10-03 im Lauf 37097532261: Der Stack
# synchronisiert die echte Webseite des Parlaments, und zwar ohne Limit auf den
# Geschäften — jedes Limit schneidet die Weisung weg, aus der die ganze
# Budget-Familie ihr Budgetbuch holt. Das sind 1236 Geschäfte mit je einer
# Detailseite; nach 34 Minuten war der Abgleich auf dem Runner nicht fertig,
# und jeder Push würde diese Last an die Stadt schicken. Auf dem Rechner läuft
# er vor dem Commit. Dauerhaft gehört dem Abgleich eine lokale Gegenstelle im
# Compose (TODO.md).
if [[ "${PARLWIN_TESTS_OHNE_E2E:-0}" == "1" ]]; then
  echo "Nicht gelaufen: PARLWIN_TESTS_OHNE_E2E=1 — der Abgleich holt die echte"
  echo "Webseite des Parlaments, siehe Kommentar oben und README."
  node "${ROOT}/tests/junit-summary.mjs" "${JUNIT_DIR}"
  exit $?
fi

# Der e2e-Lauf legt den Playwright-Report direkt hier ab. Er wird bewusst NICHT
# aus dem Arbeitsverzeichnis gelesen: dort lag früher ein Report fester Ablage,
# der als Ergebnis des aktuellen Laufs gezählt wurde, obwohl er Wochen alt war.
PW_JUNIT_OUT="${JUNIT_DIR}/e2e-browser.xml" "${ROOT}/tests/e2e/run-compose-e2e.sh"
E2E_RC=$?
# Die Bash-Integrationsprüfungen als einen Testfall abbilden (Exit-Code).
if [[ "$E2E_RC" -eq 0 ]]; then
  printf '<testsuite name="e2e-integration" tests="1" failures="0"><testcase classname="e2e" name="integrationspruefungen"/></testsuite>\n' \
    >"${JUNIT_DIR}/e2e-integration.xml"
else
  printf '<testsuite name="e2e-integration" tests="1" failures="1"><testcase classname="e2e" name="integrationspruefungen"><failure message="run-compose-e2e.sh Exit-Code %s"/></testcase></testsuite>\n' \
    "$E2E_RC" >"${JUNIT_DIR}/e2e-integration.xml"
fi

node "${ROOT}/tests/junit-summary.mjs" "${JUNIT_DIR}"
