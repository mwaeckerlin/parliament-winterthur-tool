#!/usr/bin/env bash
# Rendert die Oberfläche von parlwin über alle Formate und holt die Bilder her.
#
# Ein Lauf, von der leeren Maschine bis zu den Bildern: Stack hoch (mit der
# lokalen Gegenstelle als Datenquelle, damit echte Geschäfte, Mitglieder und
# Kommissionen darin stehen), Abgleich, ein Wegwerf-Konto für die Ansicht,
# Scan über die Formate, Bilder heraus, Konto weg, Stack runter.
#
# Aufruf: bash .claude/design-scan.sh [<ziel-verzeichnis>]
# Ergebnis: <ziel>/out mit den Bildern, dazu <ziel>/lauf.log
set -uo pipefail

PROJEKT="$(cd "$(dirname "$0")/.." && pwd)"
ZIEL="${1:-/tmp/parlwin-design}"
SCANNER="${HOME}/git/mwaeckerlin/webdesign-scanner"
PORT="${PARLWIN_DESIGN_PORT:-29824}"
export COMPOSE_PROJECT_NAME=parlwin_design
export COMPOSE_FILE="docker-compose.yml:tests/e2e/docker-compose.e2e.yml"
export PARLWIN_QUELLE_BASE_URL="http://parlwin-quelle:8080"
export NEXTCLOUD_HTTP_PORT="$PORT"
export HOST="localhost:${PORT}"

cd "$PROJEKT"
mkdir -p "$ZIEL"

occ() { docker compose exec -T nextcloud-php-fpm php /app/occ --no-ansi "$@"; }

aufraeumen() {
  occ user:delete design-review >/dev/null 2>&1 || true
  occ app:enable firstrunwizard >/dev/null 2>&1 || true
  docker compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap aufraeumen EXIT

echo "[design] Stack hoch, mit der lokalen Gegenstelle als Quelle"
docker compose up -d --build --remove-orphans --force-recreate || exit 1

echo "[design] Warte auf Nextcloud"
for _ in $(seq 1 180); do
  if docker compose exec -T nextcloud-php-fpm php -r \
      'exit(@file_get_contents("http://nextcloud-nginx:8080/status.php") && strpos(@file_get_contents("http://nextcloud-nginx:8080/status.php"), "\"installed\":true") !== false ? 0 : 1);' 2>/dev/null; then
    break
  fi
  sleep 2
done

# Die Gegenstelle steht unter einem Namen im Compose-Netz, also an einer
# privaten Adresse. Nextcloud weist die ab («Host parlwin-quelle was not
# connected to because it violates local access rules»), und ohne diese
# Einstellung kam weder der Abgleich noch das Budgetbuch herein (gemessen
# 2026-10-19). Der E2E-Lauf setzt dieselbe Einstellung.
occ config:system:set allow_local_remote_servers --value=true --type=boolean >/dev/null 2>&1 || true

echo "[design] Abgleich, damit die Ansichten Daten haben"
occ parlwin:sync >/dev/null 2>&1 || occ parlwin:synchronisieren >/dev/null 2>&1 || true

# F123: Die Budget-Grafik braucht ein eingelesenes Budgetjahr; ohne das bleibt
# die Budget-Seite leer und die Aufnahme bricht an ihr ab. Das Buch liefert die
# lokale Gegenstelle.
# 2026, weil die Gegenstelle für dieses Jahr eine Budget-Weisung mit ihren
# beiden Büchern ausliefert; der E2E-Lauf nimmt dasselbe Jahr. Für 2027 fand
# der Import keine Weisung (gemessen 2026-10-19).
echo "[design] Budgetjahr 2026 einlesen, für die Grafik"
occ parlwin:budget-reimport 2026 || echo "[design] WARNUNG: das Budgetjahr 2026 kam nicht herein"

echo "[design] Wegwerf-Konto und Erstlauf-Assistent aus"
PASSWORT="$(head -c 24 /dev/urandom | base64 | tr -d '/+=' | head -c 24)"
printf '%s' "$PASSWORT" > "${ZIEL}/passwort"
chmod 600 "${ZIEL}/passwort"
# Die Ausgabe von `user:add` steht im Protokoll: Schlägt es fehl, meldet die
# Anmeldeseite später «Wrong login or password», und ohne diese Zeilen sieht
# das aus wie ein falsch ausgefülltes Formular.
docker compose exec -T -e OC_PASS="$PASSWORT" nextcloud-php-fpm \
  php /app/occ --no-ansi user:add --password-from-env --display-name="Design Review" design-review || true
echo "[design] Konto vorhanden?"
occ user:info design-review || echo "[design] WARNUNG: das Konto design-review gibt es nicht"
occ app:disable firstrunwizard >/dev/null 2>&1 || true
occ user:setting design-review core lang de >/dev/null 2>&1 || true

# Prüfen, ob das Kennwort wirklich gilt: Die Anmeldeseite sagt bei jedem Grund
# «Wrong login or password», und ohne diese Prüfung ist nicht zu unterscheiden,
# ob der Scanner falsch tippt oder das Konto ein anderes Kennwort trägt.
ANMELDUNG="$(docker compose exec -T -e OC_PASS="$PASSWORT" nextcloud-php-fpm php -r '
  $pass = getenv("OC_PASS");
  $ctx = stream_context_create(["http" => [
    "header" => "Authorization: Basic " . base64_encode("design-review:" . $pass) . "\r\nOCS-APIRequest: true\r\n",
    "ignore_errors" => true,
  ]]);
  $antwort = @file_get_contents("http://nextcloud-nginx:8080/ocs/v2.php/cloud/user?format=json", false, $ctx);
  echo is_string($antwort) && str_contains($antwort, "design-review") ? "gilt" : "gilt nicht";
' 2>/dev/null)"
echo "[design] Kennwort des Wegwerf-Kontos: ${ANMELDUNG}"

# Die Aufnahme läuft über das Playwright-Abbild des Projekts: Dessen Anmeldung
# an dieser Instanz ist erprobt, während der Ablauf des webdesign-scanner an
# der Anmeldemaske von Nextcloud scheiterte (sieben Läufe am 2026-10-16, Konto
# und Kennwort nachweislich richtig). Der Skill `design-test` lässt das Werkzeug
# des Projekts ausdrücklich zu.
if [[ "${PARLWIN_DESIGN_PLAYWRIGHT:-1}" == "1" ]]; then
  echo "[design] Aufnahme über das Playwright-Abbild des Projekts"
  docker compose build playwright >/dev/null || exit 1
  mkdir -p "${ZIEL}/aufnahme"
  cp "${PROJEKT}/.claude/design-capture.spec.js" "${ZIEL}/aufnahme/design-capture.spec.js"
  cat > "${ZIEL}/aufnahme/Dockerfile" <<ENDE
FROM mwaeckerlin/parliament-winterthur-tool:playwright
COPY design-capture.spec.js /work/design-capture.spec.js
# PARLWIN_DESIGN_GREP wählt einzelne Tests, etwa nur die Überlauf-Messung: Ein
# ganzer Lauf dauert elf Minuten, und eine Messung braucht keine 177 Bilder.
CMD ["npx", "playwright", "test", "--config", "playwright.config.js", "design-capture.spec.js", "--project", "chromium", "--reporter", "list"${PARLWIN_DESIGN_GREP:+, \"--grep\", \"${PARLWIN_DESIGN_GREP}\"}]
ENDE
  docker build --tag parlwin-design-capture:lokal "${ZIEL}/aufnahme" || exit 1
  docker rm -f parlwin-design-capture >/dev/null 2>&1 || true
  docker run --name parlwin-design-capture \
    --network "${COMPOSE_PROJECT_NAME}_nginx-php" \
    -e "PARLWIN_BASE_URL=http://nextcloud-nginx:8080" \
    -e "PW_DESIGN_USER=design-review" \
    -e "PW_DESIGN_PASS=${PASSWORT}" \
    -e "PW_DESIGN_OUT=/work/bilder" \
    -e CI=1 \
    parlwin-design-capture:lokal || echo "[design] Aufnahme endete mit Fehler, Bilder trotzdem holen"
  rm -rf "${ZIEL}/out"
  # Unter /work darf der unprivilegierte Nutzer des Abbilds schreiben; am
  # Wurzelverzeichnis scheiterte es mit «EACCES: mkdir '/bilder'».
  docker cp parlwin-design-capture:/work/bilder "${ZIEL}/out" >/dev/null 2>&1 || true
  docker rm -f parlwin-design-capture >/dev/null 2>&1 || true
  echo "[design] Bilder: $(find "${ZIEL}/out" -type f 2>/dev/null | wc -l)"
  find "${ZIEL}/out" -type f 2>/dev/null | sort | head -60
  exit 0
fi

echo "[design] Scanner bauen und laufen lassen"
mkdir -p "${ZIEL}/scanner"
# Welcher Ablauf: der normale, oder über PARLWIN_DESIGN_WORKFLOW ein anderer
# (etwa eine Messung, die zeigt, was in einem Feld ankommt).
cp "${PARLWIN_DESIGN_WORKFLOW:-${PROJEKT}/.claude/design-workflow.yaml}" "${ZIEL}/scanner/workflow.yaml"
cat > "${ZIEL}/scanner/Dockerfile" <<'ENDE'
FROM mwaeckerlin/webdesign-scanner
COPY workflow.yaml /etc/webdesign-scanner/workflow.yaml
ENV WORKFLOW_FILE=/etc/webdesign-scanner/workflow.yaml
ENDE
docker build --tag parlwin-design-scanner:lokal "${ZIEL}/scanner" || exit 1
docker rm -f parlwin-design-scanner >/dev/null 2>&1 || true
docker run --name parlwin-design-scanner --network host \
  -e "TARGET_URL=http://localhost:${PORT}/index.php/apps/parlwin/" \
  -e "SCAN_USER=design-review" \
  -e "SCAN_PASSWORD=${PASSWORT}" \
  -e ON_EXISTING=overwrite \
  parlwin-design-scanner:lokal || echo "[design] Scanner endete mit Fehler, Bilder trotzdem holen"
rm -rf "${ZIEL}/out"
docker cp parlwin-design-scanner:/out "${ZIEL}/out" >/dev/null 2>&1 || true
docker rm -f parlwin-design-scanner >/dev/null 2>&1 || true

echo "[design] Bilder: $(find "${ZIEL}/out" -type f 2>/dev/null | wc -l)"
find "${ZIEL}/out" -type f -name '*.png' 2>/dev/null | sort | head -40
