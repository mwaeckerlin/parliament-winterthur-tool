#!/usr/bin/env bash
#
# Die Befehle der Testgruppen dieses Projekts, je Gruppe einzeln aufrufbar.
# Den Lauf über alle Gruppen führt `npm test` (tests/regression.mjs): Er ruft
# jede Gruppe über dieses Skript auf, hält den Stand jedes Tests im Journal und
# setzt nach einem Absturz fort.
#
#   tests/run-all.sh --list
#   tests/run-all.sh <gruppe> [<gruppe> …] [-- <argumente für das Werkzeug>]
#
# Die Argumente nach `--` gehen an PHPUnit, vitest oder image-contract.sh, etwa
# `--filter` für eine Reparaturrunde. Jede Gruppe legt ihren JUnit-Bericht in
# PARLWIN_JUNIT_DIR ab (Standard tests/.junit). Der Exit-Code ist 0 nur, wenn
# jede gewählte Gruppe grün ist; eine rote Gruppe hält die übrigen nicht auf.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JUNIT_DIR="${PARLWIN_JUNIT_DIR:-${ROOT}/tests/.junit}"
mkdir -p "$JUNIT_DIR"

GRUPPEN=(php-unit php-pdf js php-live compose image quelle lint audit composer-audit e2e)

GEWAEHLT=()
WERKZEUG=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --list) printf '%s\n' "${GRUPPEN[@]}"; exit 0 ;;
    --) shift; WERKZEUG=("$@"); break ;;
    *) GEWAEHLT+=("$1"); shift ;;
  esac
done
if [[ ${#GEWAEHLT[@]} -eq 0 ]]; then
  echo "Aufruf: tests/run-all.sh <gruppe> [<gruppe> …] [-- <argumente>]; Gruppen: ${GRUPPEN[*]}" >&2
  echo "Den ganzen Lauf startet npm test." >&2
  exit 2
fi
for g in "${GEWAEHLT[@]}"; do
  if [[ " ${GRUPPEN[*]} " != *" ${g} "* ]]; then
    echo "tests/run-all.sh: unbekannte Gruppe «${g}»; bekannt sind: ${GRUPPEN[*]}" >&2
    exit 2
  fi
done

# Die PHP-Suiten laufen IM Image, also mit dem PHP der Anwendung
# (tests/php-suite.sh). Ein PHP des Wirts ist eine andere Fassung und fehlt auf
# dem GitHub-Runner ganz: Dort liegt PHP 8.3, PHPUnit 13 braucht mindestens
# 8.4.1, und keine PHP-Suite startete (Lauf 37097532261 vom 2026-10-03).
# PHPUnit selbst steckt in `parlwin/vendor`, git-ignoriert und aus dem Bau
# geholt: `npm run composer:update`, das `npm run test:ci` vorher ausführt.
PHP_SUITE="${ROOT}/tests/php-suite.sh"
php_vorhanden() {
  if [[ ! -x "${ROOT}/parlwin/vendor/bin/phpunit" ]]; then
    echo "FEHLER: parlwin/vendor/bin/phpunit fehlt. Zuerst 'npm run composer:update' laufen lassen." >&2
    return 1
  fi
}

# Pfade relativ zu parlwin/, weil dieselben Argumente im Container gelten.
PHPUNIT_FLAGS=(
  --bootstrap tests/bootstrap.php
  # Jede Meldung zählt. PHPUnit 13 teilt Verwerfungen in eigene, direkte und
  # indirekte, und `--fail-on-deprecation` erfasst nicht alle: Am 16.10.2026
  # meldete der Lauf sieben Mal «curl_close() is deprecated since 8.5» und
  # endete trotzdem mit «OK, but there were issues» und Rückgabewert 0.
  --fail-on-all-issues
  --fail-on-warning --fail-on-risky --fail-on-deprecation
  --fail-on-notice --fail-on-skipped --fail-on-incomplete
  # Eine Verwerfung von PHPUnit selbst zählt ebenso: was PHPUnit 13 verwirft,
  # fällt in PHPUnit 14 aus, und bis dahin bleibt sie sonst unbemerkt stehen.
  --fail-on-phpunit-deprecation
)

section() { printf '\n========== %s ==========\n' "$1"; }

# Ein Bericht mit einem einzigen Testfall für Gruppen, die nur einen
# Rückgabewert kennen.
ein_fall() {
  local datei="$1" suite="$2" fall="$3" rc="$4"
  if [[ "$rc" -eq 0 ]]; then
    printf '<testsuite name="%s" tests="1" failures="0"><testcase classname="%s" name="%s"/></testsuite>\n' \
      "$suite" "$suite" "$fall" >"$datei"
  else
    printf '<testsuite name="%s" tests="1" failures="1"><testcase classname="%s" name="%s"><failure message="Exit-Code %s"/></testcase></testsuite>\n' \
      "$suite" "$suite" "$fall" "$rc" >"$datei"
  fi
}

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

gruppe_php-unit() {
  section "Unit-Tests (PHPUnit)"
  php_vorhanden || return 1
  # Aussen vor bleiben «generate» (schreibt Referenzdaten), «pruefung» (das
  # Werkzeug der Prüfung von Hand, `npm run test:pruefung`) und «messung» (zeigt
  # eine Buchzeile mit ihren x-Positionen, `npm run test:messung`) — keines davon
  # ist ein Test. «live» läuft in der Gruppe php-live gegen die echten Endpunkte.
  rm -f "${JUNIT_DIR}/php-unit.xml"
  bash "$PHP_SUITE" php-unit "${JUNIT_DIR}/php-unit.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --exclude-group live --exclude-group pdf --exclude-group generate \
    --exclude-group pruefung --exclude-group messung "${WERKZEUG[@]}" tests
  local rc=$?
  ensure_junit "php-unit" "${JUNIT_DIR}/php-unit.xml" "$rc"
  return "$rc"
}

gruppe_php-pdf() {
  section "Budget-Parser (PHPUnit, Gruppe pdf)"
  php_vorhanden || return 1
  # Diese Gruppe lief früher NUR von Hand über `npm run test:pdf` und fehlte im
  # Regressionslauf. Damit blieb der ganze Weg «Budget-Geschäft → Drehbuch →
  # Budgetbuch» ungeprüft: Ein Aufruf mit falscher Argumentzahl kam so bis in die
  # laufende Instanz und liess jede Budget-Ansicht mit Serverfehler enden
  # (2026-08-28). Früher gemessen: acht Minuten und 300 MB; am 2026-10-02 unter
  # PHP 8.5 und PHPUnit 13, bei gleichzeitigen Bauläufen anderer Projekte auf
  # demselben Rechner, 56 Minuten und 731 MB.
  rm -f "${JUNIT_DIR}/php-pdf.xml"
  bash "$PHP_SUITE" php-pdf "${JUNIT_DIR}/php-pdf.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --group pdf "${WERKZEUG[@]}" tests
  local rc=$?
  ensure_junit "php-pdf" "${JUNIT_DIR}/php-pdf.xml" "$rc"
  return "$rc"
}

gruppe_js() {
  section "Komponenten-/JS-Tests (Vitest)"
  # Höchstens zwei Worker (vitest.config.js): Die Prozesse laufen in der
  # Speichergrenze der Entwicklungsumgebung.
  rm -f "${JUNIT_DIR}/js.xml"
  ( cd "${ROOT}" && npx vitest run --reporter=default --reporter=junit \
      --outputFile.junit="${JUNIT_DIR}/js.xml" "${WERKZEUG[@]}" )
  local rc=$?
  ensure_junit "js" "${JUNIT_DIR}/js.xml" "$rc"
  return "$rc"
}

gruppe_php-live() {
  section "Live-Tests (PHPUnit, externe Endpunkte)"
  php_vorhanden || return 1
  rm -f "${JUNIT_DIR}/php-live.xml"
  bash "$PHP_SUITE" php-live "${JUNIT_DIR}/php-live.xml" -- "${PHPUNIT_FLAGS[@]}" \
    --group live "${WERKZEUG[@]}" tests/Service/ScraperLiveEndpointTest.php
  local rc=$?
  ensure_junit "php-live" "${JUNIT_DIR}/php-live.xml" "$rc"
  return "$rc"
}

gruppe_compose() {
  section "Compose-Vertrag (Auslieferungs-Stack: Neustart, Speicher, Zeitgrenze)"
  ( cd "${ROOT}" && bash tests/compose-contract.sh )
  local rc=$?
  ein_fall "${JUNIT_DIR}/compose-contract.xml" compose-contract auslieferung-neustart-speicher-zeitgrenze "$rc"
  return "$rc"
}

gruppe_image() {
  section "Image-Contract (ausgelieferte Images ohne Shell)"
  # Ohne Argumente baut die Gruppe die drei Images selbst (`npm run
  # test:image`). Der Lauf über alle Gruppen nennt nach `--` die Images, die er
  # schon gebaut hat, und es wird nichts ein zweites Mal gebaut.
  local rc
  if [[ ${#WERKZEUG[@]} -gt 0 ]]; then
    ( cd "${ROOT}" && bash tests/image-contract.sh "${WERKZEUG[@]}" )
    rc=$?
  else
    ( cd "${ROOT}" && npm run test:image )
    rc=$?
  fi
  ein_fall "${JUNIT_DIR}/image-contract.xml" image images-ohne-shell "$rc"
  return "$rc"
}

gruppe_quelle() {
  section "Vertrag der lokalen Gegenstelle (parlwin-quelle)"
  ( cd "${ROOT}" && bash tests/quelle-contract.sh )
  local rc=$?
  ein_fall "${JUNIT_DIR}/quelle-contract.xml" quelle-contract alle-wege "$rc"
  return "$rc"
}

gruppe_lint() {
  section "Lint (eslint über parlwin/src)"
  ( cd "${ROOT}" && npm run lint )
  local rc=$?
  ein_fall "${JUNIT_DIR}/lint.xml" lint eslint "$rc"
  return "$rc"
}

gruppe_audit() {
  section "Abhängigkeiten (npm audit, ausgelieferter Teil)"
  # Geprüft wird, was in das ausgelieferte Bündel gelangt. Die Werkzeuge der
  # Entwicklung stehen in der Ausgabe von `npm audit` ohne `--omit=dev`.
  # `--prefix` hält npm in diesem Projekt: Mit `cd` allein findet npm das
  # Verzeichnis darüber als Wurzel eines Arbeitsbereichs und prüft dessen
  # Pakete.
  npm --prefix "${ROOT}" audit --omit=dev
  local rc=$?
  ein_fall "${JUNIT_DIR}/audit.xml" audit npm-audit-ausgeliefert "$rc"
  return "$rc"
}

gruppe_composer-audit() {
  section "Abhängigkeiten (composer audit, ausgelieferter Teil)"
  # Geprüft wird die composer.lock der App ohne die Werkzeuge der Entwicklung.
  # Der Rechner und das PHP-Testimage haben kein composer; der Dienst nimmt die
  # Stufe php-deps von Dockerfile.php-fpm, die composer mitbringt und in /deps
  # composer.json und composer.lock der App hält. Die Prüfung läuft beim Start
  # des Containers, nicht beim Bau: Eine Schicht des Bau-Caches hielte ein
  # altes Ergebnis fest.
  local datei="tests/regression/docker-compose.composer-audit.yml"
  local rc
  ( cd "${ROOT}" && docker compose --file "$datei" build composer-audit )
  rc=$?
  if [[ "$rc" -eq 0 ]]; then
    ( cd "${ROOT}" && docker compose --file "$datei" run --rm composer-audit )
    rc=$?
  fi
  ein_fall "${JUNIT_DIR}/composer-audit.xml" composer-audit composer-audit-ausgeliefert "$rc"
  return "$rc"
}

gruppe_e2e() {
  section "End-to-End-Tests (Docker + Playwright)"
  # Auf dem GitHub-Runner bleiben sie aussen vor (PARLWIN_TESTS_OHNE_E2E=1 im
  # Befehl `test:ci`); sie laufen auf dem Rechner vor dem Commit. Der Abgleich im
  # Stack fragt die lokale Gegenstelle `parlwin-quelle`, nicht die Webseiten des
  # Parlaments und der Stadt (tests/e2e/docker-compose.e2e.yml,
  # PARLWIN_QUELLE_BASE_URL und PARLWIN_STADT_BASE_URL).
  if [[ "${PARLWIN_TESTS_OHNE_E2E:-0}" == "1" ]]; then
    echo "Nicht gelaufen: PARLWIN_TESTS_OHNE_E2E=1; die e2e-Tests laufen auf dem"
    echo "Rechner vor dem Commit, siehe README."
    return 0
  fi
  # Diese Gruppe startet einen eigenen, frischen Stack. Der Lauf über alle
  # Gruppen (npm test) hält stattdessen je Browser einen Stack über alle Phasen
  # und ruft die Abschnitte von run-compose-e2e.sh einzeln auf.
  rm -f "${JUNIT_DIR}/e2e-browser.xml" "${JUNIT_DIR}/e2e-integration.xml"
  PW_JUNIT_OUT="${JUNIT_DIR}/e2e-browser.xml" "${ROOT}/tests/e2e/run-compose-e2e.sh"
  local rc=$?
  ein_fall "${JUNIT_DIR}/e2e-integration.xml" e2e integrationspruefungen "$rc"
  return "$rc"
}

ROT=()
for g in "${GEWAEHLT[@]}"; do
  "gruppe_${g}" || ROT+=("$g")
done
if [[ ${#ROT[@]} -gt 0 ]]; then
  echo "==> rot: ${ROT[*]}"
  exit 1
fi
echo "==> grün: ${GEWAEHLT[*]}"
