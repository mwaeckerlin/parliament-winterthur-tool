#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-parlwin_e2e}"
export COMPOSE_FILE="${ROOT_DIR}/docker-compose.yml:${ROOT_DIR}/tests/e2e/docker-compose.e2e.yml"
export NEXTCLOUD_DB_PASSWORD="${NEXTCLOUD_DB_PASSWORD:-parlwin_db_local_ChangeMe_2026}"
export NEXTCLOUD_ADMIN_PASSWORD="${NEXTCLOUD_ADMIN_PASSWORD:-parlwin_admin_local_ChangeMe_2026}"
export NEXTCLOUD_HTTP_PORT="${NEXTCLOUD_HTTP_PORT:-29824}"
export HOST="${HOST:-nextcloud-nginx:8080}"
export PROTOCOL="${PROTOCOL:-http}"
# Frontend-WS-URL: muss vom BROWSER erreichbar sein, nicht der interne Broker-Host.
# Der Browser spricht nextcloud-nginx:8080 an; nginx proxyt /ws/parlwin/ zum Broker.
export PARLWIN_REALTIME_WS_URL="${PARLWIN_REALTIME_WS_URL:-ws://nextcloud-nginx:8080/ws/parlwin/}"
export PARLWIN_REALTIME_PUBLISH_URL="${PARLWIN_REALTIME_PUBLISH_URL:-http://parlwin-realtime:3001/publish}"
export PARLWIN_REALTIME_SECRET="${PARLWIN_REALTIME_SECRET:-parlwin_realtime_local_ChangeMe_2026}"
export PARLWIN_REALTIME_AUTH_REQUIRED="${PARLWIN_REALTIME_AUTH_REQUIRED:-1}"
export PARLWIN_NEXTCLOUD_BASE_URL="${PARLWIN_NEXTCLOUD_BASE_URL:-http://nextcloud-nginx:8080}"
export PARLWIN_SYNC_LIMIT_GESCHAEFTE="${PARLWIN_SYNC_LIMIT_GESCHAEFTE:-30}"
export PARLWIN_SYNC_LIMIT_SITZUNGEN="${PARLWIN_SYNC_LIMIT_SITZUNGEN:-60}"
export PARLWIN_SYNC_LIMIT_MITGLIEDER="${PARLWIN_SYNC_LIMIT_MITGLIEDER:-80}"
export PARLWIN_SYNC_LIMIT_KOMMISSIONEN="${PARLWIN_SYNC_LIMIT_KOMMISSIONEN:-30}"
export PARLWIN_SYNC_LIMIT_FRAKTIONEN="${PARLWIN_SYNC_LIMIT_FRAKTIONEN:-20}"

BASE_URL="http://nextcloud-nginx:8080/index.php/apps/parlwin"
STATUS_URL="http://nextcloud-nginx:8080/status.php"
TEMP_DIR="$(mktemp -d)"
LAST_STATUS=""
LAST_BODY=""
TABLE_PREFIX=""

cleanup() {
  docker compose down -v --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

fail() {
  echo "E2E FEHLER: $1" >&2
  exit 1
}

assert_eq() {
  local actual="$1"
  local expected="$2"
  local msg="$3"
  if [[ "$actual" != "$expected" ]]; then
    fail "$msg (ist=$actual erwartet=$expected)"
  fi
}

assert_non_empty() {
  local value="$1"
  local msg="$2"
  if [[ -z "$value" ]]; then
    fail "$msg"
  fi
}

assert_int_ge() {
  local value="$1"
  local minimum="$2"
  local msg="$3"
  if (( value < minimum )); then
    fail "$msg (ist=$value minimum=$minimum)"
  fi
}

assert_json() {
  local jq_expr="$1"
  local msg="$2"
  if ! jq -e "$jq_expr" <<<"$LAST_BODY" >/dev/null 2>&1; then
    fail "$msg; Antwort: $LAST_BODY"
  fi
}

occ() {
  docker compose exec -T nextcloud-php-fpm php occ "$@"
}

sql() {
  local query="$1"
  docker compose exec -T nextcloud-db mariadb -N -B \
    -unextcloud "-p${NEXTCLOUD_DB_PASSWORD}" nextcloud -e "$query"
}

api_request() {
  local method="$1"
  local user="$2"
  local token="$3"
  local path="$4"
  shift 4

  local body_file="${TEMP_DIR}/body.$RANDOM.$RANDOM.json"
  local marker="__PARLWIN_HTTP_STATUS__:"
  local data_json='[]'
  local data_pairs=()
  local response

  while (($#)); do
    case "$1" in
      --data-urlencode)
        [[ $# -ge 2 ]] || fail "Fehlender Wert für --data-urlencode"
        data_pairs+=("$2")
        shift 2
        ;;
      *)
        fail "Nicht unterstütztes API-Argument im E2E-Skript: $1"
        ;;
    esac
  done

  if ((${#data_pairs[@]} > 0)); then
    data_json="$(printf '%s\n' "${data_pairs[@]}" | jq -R . | jq -sc .)"
  fi

  response="$(
    docker compose exec -T nextcloud-php-fpm php -r '
$method = $argv[1] ?? "GET";
$url = $argv[2] ?? "";
$user = $argv[3] ?? "";
$token = $argv[4] ?? "";
$pairs = json_decode($argv[5] ?? "[]", true);
if (!is_array($pairs)) {
    $pairs = [];
}
$payload = [];
foreach ($pairs as $entry) {
    $parts = explode("=", (string)$entry, 2);
    $key = $parts[0] ?? "";
    $value = $parts[1] ?? "";
    if ($key === "") {
        continue;
    }
    $payload[$key] = $value;
}
$headers = [
    "OCS-APIRequest: true",
    "Accept: application/json",
];
if ($user !== "" || $token !== "") {
    $headers[] = "Authorization: Basic " . base64_encode($user . ":" . $token);
}
$options = [
    "http" => [
        "method" => $method,
        "header" => implode("\r\n", $headers) . "\r\n",
        "ignore_errors" => true,
        "timeout" => 120,
    ],
];
if (!in_array($method, ["GET", "HEAD", "DELETE"], true) && count($payload) > 0) {
    $options["http"]["header"] .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $options["http"]["content"] = http_build_query($payload, "", "&", PHP_QUERY_RFC3986);
}
$context = stream_context_create($options);
$body = @file_get_contents($url, false, $context);
if ($body === false) {
    $body = "";
}
$status = 0;
if (isset($http_response_header[0]) && preg_match("/\s(\d{3})\s/", $http_response_header[0], $m)) {
    $status = (int)$m[1];
}
echo "__PARLWIN_HTTP_STATUS__:" . $status . "\n";
echo $body;
' -- "$method" "$BASE_URL$path" "$user" "$token" "$data_json"
  )"
  LAST_STATUS="$(sed -n '1s/^'"${marker}"'//p' <<<"$response" | tr -d '\r')"
  LAST_BODY="$(tail -n +2 <<<"$response")"
  printf '%s' "$LAST_BODY" >"$body_file"
}

api_expect_status() {
  local method="$1"
  local user="$2"
  local token="$3"
  local path="$4"
  local status="$5"
  shift 5
  api_request "$method" "$user" "$token" "$path" "$@"
  assert_eq "$LAST_STATUS" "$status" "HTTP-Status falsch für ${method} ${path}"
}

create_user_if_missing() {
  local uid="$1"
  local pass="$2"
  if ! occ user:info "$uid" >/dev/null 2>&1; then
    docker compose exec -T -e "OC_PASS=${pass}" nextcloud-php-fpm \
      php occ user:add "$uid" --display-name="$uid" --password-from-env >/dev/null
  else
    docker compose exec -T -e "OC_PASS=${pass}" nextcloud-php-fpm \
      php occ user:resetpassword "$uid" --password-from-env >/dev/null
  fi
}

create_app_password() {
  local uid="$1"
  local name="$2"
  local raw
  raw="$(occ user:auth-tokens:add "$uid" --name="$name" --no-interaction)"
  awk '/^app password:/{getline; gsub(/^[ \t]+|[ \t]+$/, ""); print; exit}' <<<"$raw"
}

# WebDAV/OCS-Request als Nutzer (Basic-Auth via App-Passwort), liefert den
# HTTP-Statuscode auf stdout. Läuft im PHP-FPM-Container gegen den internen Host
# (im E2E-Stack ist kein Host-Port veröffentlicht).
# Aufruf: http_status_as METHODE UID TOKEN PFAD [BODY] [CONTENT_TYPE] [EXTRA_HEADER]
http_status_as() {
  docker compose exec -T \
    -e HX_M="$1" -e HX_U="$2" -e HX_TOK="$3" -e HX_PATH="$4" \
    -e HX_BODY="${5:-}" -e HX_CT="${6:-application/octet-stream}" -e HX_EXTRA="${7:-}" \
    nextcloud-php-fpm php -r '
    $h  = "Authorization: Basic ".base64_encode(getenv("HX_U").":".getenv("HX_TOK"))."\r\n";
    $h .= "OCS-APIRequest: true\r\n";
    $h .= "Content-Type: ".getenv("HX_CT")."\r\n";
    $extra = getenv("HX_EXTRA"); if ($extra !== false && $extra !== "") { $h .= $extra."\r\n"; }
    $opts = ["method"=>getenv("HX_M"), "header"=>$h, "ignore_errors"=>true, "timeout"=>20];
    $body = getenv("HX_BODY"); if ($body !== false && $body !== "") { $opts["content"]=$body; }
    $ctx = stream_context_create(["http"=>$opts]);
    @file_get_contents("http://nextcloud-nginx:8080".getenv("HX_PATH"), false, $ctx);
    $code = "000";
    if (isset($http_response_header[0]) && preg_match("#\s(\d{3})\s#", $http_response_header[0], $m)) { $code = $m[1]; }
    echo $code;
  '
}

websocket_expect_denied() {
  docker compose exec -T parlwin-realtime /usr/bin/node -e '
    const WebSocket = require("ws");
    const ws = new WebSocket("ws://127.0.0.1:3001/ws", { handshakeTimeout: 2500 });
    let opened = false;
    ws.on("open", () => { opened = true; ws.close(); });
    ws.on("error", () => {});
    ws.on("close", () => process.exit(opened ? 1 : 0));
    setTimeout(() => process.exit(opened ? 1 : 0), 4000);
  '
}

websocket_expect_authorized() {
  local auth_b64="$1"
  docker compose exec -T -e "WS_AUTH_B64=${auth_b64}" parlwin-realtime /usr/bin/node -e '
    const WebSocket = require("ws");
    const auth = process.env.WS_AUTH_B64 || "";
    if (!auth) process.exit(2);
    const ws = new WebSocket("ws://127.0.0.1:3001/ws", {
      handshakeTimeout: 3000,
      headers: { Authorization: `Basic ${auth}` },
    });
    let connected = false;
    ws.on("message", (raw) => {
      try {
        const parsed = JSON.parse(raw.toString());
        if (parsed.type === "realtime.connected") {
          connected = true;
          ws.close();
        }
      } catch (_err) {}
    });
    ws.on("error", () => process.exit(1));
    ws.on("close", () => process.exit(connected ? 0 : 1));
    setTimeout(() => {
      ws.terminate();
      process.exit(connected ? 0 : 1);
    }, 5000);
  '
}

realtime_runtime_expect_non_root_and_immutable() {
  docker compose exec -T parlwin-realtime /usr/bin/node -e '
    const fs = require("fs");
    const uid = typeof process.getuid === "function" ? process.getuid() : -1;
    if (uid === 0) {
      console.error("parlwin-realtime läuft als root");
      process.exit(1);
    }
    try {
      fs.appendFileSync("/app/server.js", "\n// write test\n");
      console.error("server.js ist im Runtime-Container schreibbar");
      process.exit(1);
    } catch (err) {
      if (err && (err.code === "EACCES" || err.code === "EROFS")) {
        process.exit(0);
      }
      console.error("unerwarteter Fehler beim Schreibtest", err && err.code);
      process.exit(1);
    }
  '
}

realtime_health_ok_internal() {
  docker compose exec -T parlwin-realtime /usr/bin/node -e '
    const http = require("http");
    const req = http.get("http://127.0.0.1:3001/health", (res) => {
      const chunks = [];
      res.on("data", (c) => chunks.push(c));
      res.on("end", () => {
        if (res.statusCode !== 200) {
          process.exit(1);
          return;
        }
        try {
          const parsed = JSON.parse(Buffer.concat(chunks).toString("utf8"));
          const ok = parsed && parsed.ok === true && parsed.authRequired === true && parsed.authUrlConfigured === true;
          process.exit(ok ? 0 : 1);
        } catch (_err) {
          process.exit(1);
        }
      });
    });
    req.on("error", () => process.exit(1));
    req.setTimeout(1500, () => {
      req.destroy();
      process.exit(1);
    });
  '
}

assert_compose_project_isolation() {
  local dev_project="${PARLWIN_DEV_COMPOSE_PROJECT:-parlwin_dev}"
  if [[ "${COMPOSE_PROJECT_NAME}" == "${dev_project}" ]]; then
    fail "E2E darf nicht im Dev-Compose-Projekt laufen (COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME})"
  fi
}

assert_package_scripts_use_direct_compose() {
  grep -q '"start": "docker compose up -d --build --force-recreate --remove-orphans"' package.json \
    || fail "npm start muss direkt docker compose up mit --force-recreate --remove-orphans ausführen"
  grep -q '"stop": "docker compose down --remove-orphans"' package.json \
    || fail "npm stop muss direkt docker compose down --remove-orphans ausführen"
}

assert_admin_sync_error_messages_are_meaningful() {
  grep -q 'Sicherheitsprüfung fehlgeschlagen (HTTP 412)' parlwin/templates/admin.php \
    || fail "Admin-UI muss 412 mit aussagekräftiger Meldung statt nur Statuscode anzeigen"
}

assert_admin_sync_uses_tokened_headers() {
  grep -q "headers\\['OCS-APIRequest'\\] = 'true'" parlwin/templates/admin.php \
    || fail "Admin-UI muss OCS-APIRequest Header für Settings-API setzen"
  grep -q "headers\\['X-Requesttoken'\\] = token" parlwin/templates/admin.php \
    || fail "Admin-UI muss X-Requesttoken Header setzen"
  grep -q 'headers.Requesttoken = token' parlwin/templates/admin.php \
    || fail "Admin-UI muss Requesttoken Header setzen"
  grep -q "OC\\.Util\\.getRequestToken" parlwin/templates/admin.php \
    || fail "Admin-UI muss Request-Token über OC.Util.getRequestToken auflösen"
  grep -q "input\\[name=\"requesttoken\"\\]" parlwin/templates/admin.php \
    || fail "Admin-UI muss Hidden-Input requesttoken als Fallback auslesen"
  if grep -q 'authHeaders({}, false)' parlwin/templates/admin.php; then
    fail "Admin-UI darf bei Admin-Settings-API keinen tokenlosen Header-Pfad verwenden"
  fi
}

echo "[E2E] Starte frischen Stack"
assert_compose_project_isolation
assert_package_scripts_use_direct_compose
assert_admin_sync_error_messages_are_meaningful
assert_admin_sync_uses_tokened_headers
cleanup
TEMP_DIR="$(mktemp -d)"
docker compose up -d --build --remove-orphans --force-recreate

echo "[E2E] Warte auf Nextcloud-Initialisierung"
for _ in $(seq 1 180); do
  STATUS_BODY="$(docker compose exec -T nextcloud-php-fpm php -r 'echo @file_get_contents("http://nextcloud-nginx:8080/status.php");')"
  if jq -e '.installed == true' <<<"$STATUS_BODY" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
STATUS_BODY="$(docker compose exec -T nextcloud-php-fpm php -r 'echo @file_get_contents("http://nextcloud-nginx:8080/status.php");')"
jq -e '.installed == true' <<<"$STATUS_BODY" >/dev/null || fail "Nextcloud wurde nicht rechtzeitig initialisiert"

echo "[E2E] Prüfe Realtime-Broker"
REALTIME_OK=0
for _ in $(seq 1 60); do
  if realtime_health_ok_internal >/dev/null 2>&1; then
    REALTIME_OK=1
    break
  fi
  sleep 1
done
[[ "$REALTIME_OK" -eq 1 ]] || fail "Realtime-Broker ist nicht erreichbar"
realtime_runtime_expect_non_root_and_immutable || fail "parlwin-realtime läuft nicht mit sicherem Runtime-User oder Build-Artefakte sind schreibbar"

echo "[E2E] Prüfe automatische App-Aktivierung und lege Testnutzer an"
APP_ENABLED=0
for _ in $(seq 1 90); do
  if occ app:list --enabled | grep -q 'parlwin:'; then
    APP_ENABLED=1
    break
  fi
  sleep 2
done
[[ "$APP_ENABLED" -eq 1 ]] || fail "parlwin wurde nach docker compose up nicht automatisch aktiviert"
if occ app:list --enabled | grep -q 'parliamentwinterthur:'; then
  fail "Legacy-App parliamentwinterthur darf nicht mehr aktiviert sein"
fi

CUSTOM_APP_IDS="$(docker compose exec -T nextcloud-php-fpm php -r 'foreach (glob("/app/custom_apps/*", GLOB_ONLYDIR) as $dir) { echo basename($dir), PHP_EOL; }')"
grep -q '^parlwin$' <<<"$CUSTOM_APP_IDS" || fail "custom_apps/parlwin fehlt im Container"
if grep -q '^parliamentwinterthur$' <<<"$CUSTOM_APP_IDS"; then
  fail "Legacy-App-Verzeichnis custom_apps/parliamentwinterthur darf nicht mehr vorhanden sein"
fi

create_user_if_missing "parlwin_praesidium" "PwtP4ss!Praesidium"
create_user_if_missing "parlwin_protokoll" "PwtP4ss!Protokoll"
create_user_if_missing "parlwin_mitglied" "PwtP4ss!Mitglied"

ADMIN_TOKEN="$(create_app_password "admin" "e2e-admin")"
PRAESIDIUM_TOKEN="$(create_app_password "parlwin_praesidium" "e2e-praesidium")"
PROTOKOLL_TOKEN="$(create_app_password "parlwin_protokoll" "e2e-protokoll")"
MITGLIED_TOKEN="$(create_app_password "parlwin_mitglied" "e2e-mitglied")"

assert_non_empty "$ADMIN_TOKEN" "Admin-App-Passwort konnte nicht erzeugt werden"
assert_non_empty "$PRAESIDIUM_TOKEN" "Praesidium-App-Passwort konnte nicht erzeugt werden"
assert_non_empty "$PROTOKOLL_TOKEN" "Protokoll-App-Passwort konnte nicht erzeugt werden"
assert_non_empty "$MITGLIED_TOKEN" "Mitglied-App-Passwort konnte nicht erzeugt werden"

# Automatischen Cron-Sync während der Tests verhindern: Der parlwin-Watcher tickt
# den Nextcloud-Cron; fällt ein Tick auf eine Sync-Stunde (Standard 03/15 Uhr),
# würde ein echter Sync die reproduzierten Testdaten überschreiben. Daher
# sync_stunden auf eine Stunde setzen, die garantiert NICHT die aktuelle ist.
# Der dedizierte Cron-Job-Test am Ende setzt sync_stunden explizit auf "alle".
SAFE_SYNC_HOUR=$(( ($(TZ=Europe/Zurich date +%-H) + 6) % 24 ))
occ config:app:set parlwin sync_stunden --value="$SAFE_SYNC_HOUR" >/dev/null 2>&1 || true

# Admin-Sprache auf Deutsch: Nextcloud lädt die App-Übersetzung l10n/de.js
# (OC.L10N.register) nur bei deutscher Sprache. Genau diese Datei löst
# «OC is not defined» aus, wenn sie vor dem Core lädt. Ohne deutsche Sprache
# würde der Browser-Test (0b) den Fehler nie sehen.
occ user:setting admin core lang de >/dev/null 2>&1 || true

echo "[E2E] Prüfe WebSocket-Authentisierung"
websocket_expect_denied || fail "WebSocket ohne Login wurde nicht blockiert"
ADMIN_AUTH_B64="$(printf '%s' "admin:${ADMIN_TOKEN}" | base64 | tr -d '\n')"
websocket_expect_authorized "$ADMIN_AUTH_B64" || fail "WebSocket mit gültigem Login wurde nicht akzeptiert"

echo "[E2E] Führe Sync über den gleichen Endpoint wie im Frontend aus"
api_expect_status POST "admin" "$ADMIN_TOKEN" "/sync" "202"
assert_json '.erfolg == true' "Sync-Start meldet keinen Erfolg"
assert_json '.asynchron == true' "Sync-Start läuft nicht asynchron"
assert_json '.zeitpunkt | type == "string"' "Sync-Start liefert keinen Zeitstempel"

api_expect_status POST "admin" "$ADMIN_TOKEN" "/sync" "202"
assert_json '.erfolg == true' "Zweiter Sync-Start meldet keinen Erfolg"
assert_json '.bereits_laufend == true' "Zweiter Sync-Start wurde nicht an laufenden Sync angehängt"

SAW_RUNNING=0
SAW_FINISHED=0
for _ in $(seq 1 1800); do
  api_expect_status GET "admin" "$ADMIN_TOKEN" "/sync/status" "200"
  if jq -e '.running == true and (.current.processed|type=="number") and (.current.total|type=="number")' <<<"$LAST_BODY" >/dev/null 2>&1; then
    SAW_RUNNING=1
  fi
  if jq -e '.running == false' <<<"$LAST_BODY" >/dev/null 2>&1; then
    SAW_FINISHED=1
    break
  fi
  sleep 1
done
[[ "$SAW_RUNNING" -eq 1 ]] || fail "Während des Syncs wurde kein laufender Fortschritt beobachtet"
[[ "$SAW_FINISHED" -eq 1 ]] || fail "Sync wurde nicht rechtzeitig abgeschlossen"

assert_json '.running == false' "Sync-Status ist nach Abschluss noch running=true"
assert_json '.phase == "abgeschlossen"' "Sync-Status endet nicht im Erfolgs-Status"
assert_json '.error == null' "Sync-Status enthält einen Fehler"
assert_json '.sections.mitglieder.total >= 0' "Sync-Status enthält keine Mitglieder-Totals"
assert_json '.sections.geschaefte.total >= 0' "Sync-Status enthält keine Geschäfts-Totals"
assert_json '.sections.sitzungen.total >= 0' "Sync-Status enthält keine Sitzungs-Totals"
assert_json '.statistik.mitglieder.mitglieder.neu >= 0' "Mitglieder-Statistik fehlt im finalen Status"
assert_json '.statistik.geschaefte.neu >= 0' "Geschäfte-Statistik fehlt im finalen Status"
assert_json '.statistik.sitzungen.neu >= 0' "Sitzungs-Statistik fehlt im finalen Status"

TABLE_PREFIX="$(sql "SELECT configvalue FROM oc_appconfig WHERE appid='core' AND configkey='dbtableprefix' LIMIT 1;")"
TABLE_PREFIX="${TABLE_PREFIX:-oc_}"

echo "[E2E] Prüfe Frontend-Startseite"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/" "200"
FRONTEND_HTML="$LAST_BODY"
grep -q 'parlwin-root' <<<"$FRONTEND_HTML" || fail "Frontend-Root nicht gefunden"
grep -q 'realtimeWsUrl' <<<"$FRONTEND_HTML" || fail "Frontend-Realtime-Konfiguration fehlt"
grep -q 'parlwin-main.js' <<<"$FRONTEND_HTML" || fail "Frontend-Bundle wurde nicht eingebunden"

echo "[E2E] Prüfe Responsive-CSS (Desktop + Mobile)"
FRONTEND_CSS="$(docker compose exec -T nextcloud-php-fpm php -r 'echo file_get_contents("/app/custom_apps/parlwin/css/parlwin-style.css");')"
grep -Eq '@media[[:space:]]*\([[:space:]]*max-width:[[:space:]]*80rem[[:space:]]*\)' <<<"$FRONTEND_CSS" || fail "Responsive-Breakpoint 80rem (Desktop) fehlt"
grep -Eq '@media[[:space:]]*\([[:space:]]*max-width:[[:space:]]*54rem[[:space:]]*\)' <<<"$FRONTEND_CSS" || fail "Responsive-Breakpoint 54rem (Mobile) fehlt"
grep -q '.pw-members-table td::before' <<<"$FRONTEND_CSS" || fail "Mobile Tabellen-Kartenansicht fehlt"
grep -q '.pw-admin-card' <<<"$FRONTEND_CSS" || fail "Admin-Card-Layout fehlt"

echo "[E2E] Prüfe Collabora-Integration (richdocuments + WOPI)"
RICH_ENABLED="$(docker compose exec -T nextcloud-php-fpm php occ --no-ansi --no-warnings config:app:get richdocuments enabled 2>/dev/null | tr -d '\r' | tail -n1)"
[[ "$RICH_ENABLED" == "yes" ]] || fail "richdocuments-App ist nicht aktiviert (Status='${RICH_ENABLED}')"

WOPI_INTERNAL="$(docker compose exec -T nextcloud-php-fpm php occ --no-ansi --no-warnings config:app:get richdocuments wopi_url 2>/dev/null | tr -d '\r' | tail -n1)"
WOPI_PUBLIC="$(docker compose exec -T nextcloud-php-fpm php occ --no-ansi --no-warnings config:app:get richdocuments public_wopi_url 2>/dev/null | tr -d '\r' | tail -n1)"
WOPI_CALLBACK="$(docker compose exec -T nextcloud-php-fpm php occ --no-ansi --no-warnings config:app:get richdocuments wopi_callback_url 2>/dev/null | tr -d '\r' | tail -n1)"
[[ "$WOPI_INTERNAL" == http://collabora:9980* ]] || fail "wopi_url falsch konfiguriert: '${WOPI_INTERNAL}'"
[[ "$WOPI_PUBLIC" == ${PROTOCOL}://${HOST}* ]] || fail "public_wopi_url falsch konfiguriert: '${WOPI_PUBLIC}' (erwartet Prefix ${PROTOCOL}://${HOST})"
[[ "$WOPI_CALLBACK" == http://nextcloud-nginx:8080* ]] || fail "wopi_callback_url falsch konfiguriert: '${WOPI_CALLBACK}'"

WOPI_WEBROOT="${WOPI_CALLBACK#http://nextcloud-nginx:8080}"
WOPI_WEBROOT="${WOPI_WEBROOT%/}"

DISCOVERY_XML="$(docker compose exec -T nextcloud-php-fpm php -r 'echo @file_get_contents("http://collabora:9980/hosting/discovery");')"
grep -q '<wopi-discovery>' <<<"$DISCOVERY_XML" || fail "Collabora /hosting/discovery liefert kein wopi-discovery"
grep -Eq '(name="edit"[^>]*ext="odt")|(ext="odt"[^>]*name="edit")' <<<"$DISCOVERY_XML" || fail "Collabora bietet keine edit-Action für odt an"
grep -Eq '(name="edit"[^>]*ext="docx")|(ext="docx"[^>]*name="edit")' <<<"$DISCOVERY_XML" || fail "Collabora bietet keine edit-Action für docx an"

CAPS_JSON="$(docker compose exec -T nextcloud-php-fpm php -r 'echo @file_get_contents("http://collabora:9980/hosting/capabilities");')"
jq -e '.hasMobileSupport == true' <<<"$CAPS_JSON" >/dev/null || fail "Collabora capabilities ohne hasMobileSupport"
jq -e '."convert-to".available == true' <<<"$CAPS_JSON" >/dev/null || fail "Collabora capabilities ohne convert-to"

WOPI_TEST_DOC="parlwin-e2e-collabora.odt"
WOPI_DAV_URL="${PROTOCOL}://${HOST}${WOPI_WEBROOT}/remote.php/dav/files/admin/${WOPI_TEST_DOC}"
WOPI_UPLOAD_STATUS="$(docker compose exec -T -e WOPI_URL="${WOPI_DAV_URL}" -e WOPI_USER="admin" -e WOPI_PASS="${NEXTCLOUD_ADMIN_PASSWORD}" nextcloud-php-fpm php -r '
$ctx = stream_context_create(["http" => [
  "method" => "PUT",
  "header" => "Authorization: Basic ".base64_encode(getenv("WOPI_USER").":".getenv("WOPI_PASS"))."\r\nContent-Type: application/octet-stream\r\n",
  "content" => "",
  "ignore_errors" => true,
  "timeout" => 30,
]]);
@file_get_contents(getenv("WOPI_URL"), false, $ctx);
if (isset($http_response_header[0]) && preg_match("/\s(\d{3})\s/", $http_response_header[0], $m)) {
  echo $m[1];
}')"
[[ "$WOPI_UPLOAD_STATUS" =~ ^(201|204)$ ]] || fail "WebDAV-Upload des Testdokuments fehlgeschlagen (Status='${WOPI_UPLOAD_STATUS}')"

WOPI_FILE_ID="$(docker compose exec -T -e WOPI_URL="${WOPI_DAV_URL}" -e WOPI_USER="admin" -e WOPI_PASS="${NEXTCLOUD_ADMIN_PASSWORD}" nextcloud-php-fpm php -r '
$ctx = stream_context_create(["http" => [
  "method" => "PROPFIND",
  "header" => "Authorization: Basic ".base64_encode(getenv("WOPI_USER").":".getenv("WOPI_PASS"))."\r\nDepth: 0\r\nContent-Type: application/xml\r\n",
  "content" => "<d:propfind xmlns:d=\"DAV:\" xmlns:oc=\"http://owncloud.org/ns\"><d:prop><oc:fileid/></d:prop></d:propfind>",
  "ignore_errors" => true,
  "timeout" => 30,
]]);
$body = @file_get_contents(getenv("WOPI_URL"), false, $ctx) ?: "";
if (preg_match("#<oc:fileid>(\d+)</oc:fileid>#", $body, $m)) { echo $m[1]; }')"
assert_non_empty "$WOPI_FILE_ID" "Konnte fileid des WOPI-Testdokuments nicht ermitteln"

WOPI_EDITOR_URL="${PROTOCOL}://${HOST}${WOPI_WEBROOT}/index.php/apps/richdocuments/index?fileId=${WOPI_FILE_ID}"
EDITOR_HTML="$(docker compose exec -T -e WOPI_URL="${WOPI_EDITOR_URL}" -e WOPI_USER="admin" -e WOPI_PASS="${NEXTCLOUD_ADMIN_PASSWORD}" nextcloud-php-fpm php -r '
$ctx = stream_context_create(["http" => [
  "method" => "GET",
  "header" => "Authorization: Basic ".base64_encode(getenv("WOPI_USER").":".getenv("WOPI_PASS"))."\r\nOCS-APIRequest: true\r\n",
  "follow_location" => 1,
  "ignore_errors" => true,
  "timeout" => 30,
]]);
echo @file_get_contents(getenv("WOPI_URL"), false, $ctx);')"
grep -Eq 'richdocuments-document\.js|initial-state-richdocuments|collabora[^"]*:?9980|/cool/|wopi(Src|_src|_token|_url)' <<<"$EDITOR_HTML" \
  || fail "richdocuments-Editor-Seite enthält keine Collabora-/WOPI-Hinweise"
grep -Eq "fileId=${WOPI_FILE_ID}|initial-state-richdocuments" <<<"$EDITOR_HTML" \
  || fail "richdocuments-Editor-Seite referenziert FileID ${WOPI_FILE_ID} nicht"

echo "[E2E] Plausibilitätschecks nach Sync"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=200" "200"
GESCHAEFTE_COUNT_DEFAULT="$(jq 'length' <<<"$LAST_BODY")"

api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=200&show_erledigt=1" "200"
GESCHAEFTE_COUNT="$(jq 'length' <<<"$LAST_BODY")"
assert_int_ge "$GESCHAEFTE_COUNT" 20 "Zu wenige Geschäfte nach Sync"
if (( GESCHAEFTE_COUNT_DEFAULT > GESCHAEFTE_COUNT )); then
  fail "Default-Filter zeigt mehr Geschäfte als inkl. erledigte (default=${GESCHAEFTE_COUNT_DEFAULT}, inkl=${GESCHAEFTE_COUNT})"
fi
jq -e 'all(.[]; (.id|type=="number") and .id>0 and ((.externId|tostring) == (.id|tostring)) and ((.url|tostring|contains("/_rte/information/"))))' <<<"$LAST_BODY" >/dev/null \
  || fail "Geschäftsliste hat unplausible IDs/URLs"
FIRST_G_ID="$(jq -r '.[0].id' <<<"$LAST_BODY")"
SECOND_G_ID="$(jq -r '.[1].id' <<<"$LAST_BODY")"
assert_non_empty "$FIRST_G_ID" "Kein erstes Geschäft gefunden"
assert_non_empty "$SECOND_G_ID" "Kein zweites Geschäft gefunden"

api_expect_status GET "admin" "$ADMIN_TOKEN" "/sitzungen?limit=60" "200"
SITZUNGEN_JSON="$LAST_BODY"
SITZUNGEN_COUNT="$(jq 'length' <<<"$LAST_BODY")"
assert_int_ge "$SITZUNGEN_COUNT" 10 "Zu wenige Sitzungen nach Sync"
FIRST_S_ID="$(jq -r '.[0].id' <<<"$LAST_BODY")"

api_expect_status GET "admin" "$ADMIN_TOKEN" "/mitglieder?aktiv=1" "200"
MITGLIEDER_COUNT="$(jq 'length' <<<"$LAST_BODY")"
assert_int_ge "$MITGLIEDER_COUNT" 10 "Zu wenige aktive Mitglieder nach Sync"
AKTIVE_MITGLIEDER_JSON="$LAST_BODY"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/mitglieder" "200"
MITGLIEDER_TOTAL_COUNT="$(jq 'length' <<<"$LAST_BODY")"
assert_int_ge "$MITGLIEDER_TOTAL_COUNT" "$MITGLIEDER_COUNT" "Aktive Mitglieder dürfen nicht mehr als Total sein"
MITGLIED_EXTERN_1="$(jq -r '.[0].externId' <<<"$AKTIVE_MITGLIEDER_JSON")"
MITGLIED_NAME_1="$(jq -r '.[0].vorname + " " + .[0].name' <<<"$AKTIVE_MITGLIEDER_JSON")"
MITGLIED_EXTERN_2="$(jq -r '.[1].externId' <<<"$AKTIVE_MITGLIEDER_JSON")"
MITGLIED_NAME_2="$(jq -r '.[1].vorname + " " + .[1].name' <<<"$AKTIVE_MITGLIEDER_JSON")"

api_expect_status GET "admin" "$ADMIN_TOKEN" "/kommissionen" "200"
assert_int_ge "$(jq 'length' <<<"$LAST_BODY")" 5 "Zu wenige Kommissionen nach Sync"

api_expect_status GET "admin" "$ADMIN_TOKEN" "/fraktionen" "200"
assert_int_ge "$(jq 'length' <<<"$LAST_BODY")" 3 "Zu wenige Fraktionen nach Sync"

echo "[E2E] Geschäft-Detail + Aktionen"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte/${FIRST_G_ID}" "200"
assert_json ".id == ${FIRST_G_ID}" "Geschäft-Detail passt nicht zur ID"
ERSTER_BESCHLUSS_CODE="$(jq -r '.erlaubteBeschluesse[0].code' <<<"$LAST_BODY")"
assert_non_empty "$ERSTER_BESCHLUSS_CODE" "Kein erlaubter Beschlusscode gefunden"

api_expect_status POST "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte/${FIRST_G_ID}/notizen" "200" \
  --data-urlencode "text=E2E Notiz via API $(date +%s)"
assert_json '.aktionTyp == "notiz"' "Notiz-Aktion wurde nicht gespeichert"

api_expect_status POST "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte/${FIRST_G_ID}/voten" "200" \
  --data-urlencode "text=E2E Votum via API $(date +%s)"
assert_json '.aktionTyp == "votum"' "Votum-Aktion wurde nicht gespeichert"

api_expect_status POST "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte/${FIRST_G_ID}/beschluesse" "200" \
  --data-urlencode "code=${ERSTER_BESCHLUSS_CODE}" \
  --data-urlencode "text=E2E Beschluss im Normalmodus"
assert_json '.aktionTyp == "beschluss"' "Beschluss im Normalmodus wurde nicht gespeichert"
assert_json ".aktionCode == \"${ERSTER_BESCHLUSS_CODE}\"" "Beschlusscode wurde nicht korrekt gespeichert"

echo "[E2E] Zuständigkeiten setzen und prüfen"
api_expect_status PUT "admin" "$ADMIN_TOKEN" "/geschaefte/${SECOND_G_ID}" "200" \
  --data-urlencode "zustaendigkeiten[0][mitgliedExternId]=${MITGLIED_EXTERN_1}" \
  --data-urlencode "zustaendigkeiten[0][personName]=${MITGLIED_NAME_1}" \
  --data-urlencode "zustaendigkeiten[1][mitgliedExternId]=${MITGLIED_EXTERN_2}" \
  --data-urlencode "zustaendigkeiten[1][personName]=${MITGLIED_NAME_2}" \
  --data-urlencode "haupt_person_key=mitglied:${MITGLIED_EXTERN_2}"
assert_json '.zustaendigkeiten | length == 2' "Zuständigkeiten wurden nicht gespeichert"
assert_json '.zustaendigkeiten[] | select(.mitgliedExternId == "'"${MITGLIED_EXTERN_2}"'") | .istHaupt == true' "Hauptzuständigkeit fehlt"

echo "[E2E] Rechte- und Fraktionssitzungsmodus testen"
api_expect_status POST "admin" "$ADMIN_TOKEN" "/settings/fraktionspraesident" "200" \
  --data-urlencode "uid=parlwin_praesidium" \
  --data-urlencode "name=Praesidium E2E"
api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/fraktionssitzung" "200" \
  --data-urlencode "aktiv=1"
assert_json '.modusAktiv == true' "Fraktionssitzungsmodus wurde nicht aktiviert"

api_expect_status POST "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte/${FIRST_G_ID}/beschluesse" "403" \
  --data-urlencode "code=${ERSTER_BESCHLUSS_CODE}" \
  --data-urlencode "text=Soll im Modus blockiert werden"
assert_json '.fehler | contains("Protokollführer")' "Falsche Fehlermeldung bei gesperrtem Beschluss"

api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/protokollfuehrer" "200" \
  --data-urlencode "uid=parlwin_protokoll" \
  --data-urlencode "name=Protokoll E2E"

# Gültigkeit mit Puffer (gestern–morgen): eine reine "heute"-Gültigkeit wäre um
# Mitternacht nicht zuverlässig, weil das Host-Datum (date) und die Container-Zeit
# (UTC, mit der hasAktiveRolle vergleicht) im Zeitzonenversatz auseinanderliegen.
STV_VON="$(date -u -d 'yesterday' +%F 2>/dev/null || date -u +%F)"
STV_BIS="$(date -u -d 'tomorrow' +%F 2>/dev/null || date -u +%F)"
api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/protokollfuehrer-stellvertretung" "200" \
  --data-urlencode "uid=parlwin_mitglied" \
  --data-urlencode "name=Mitglied E2E" \
  --data-urlencode "gueltig_von=${STV_VON}" \
  --data-urlencode "gueltig_bis=${STV_BIS}"

api_expect_status POST "parlwin_protokoll" "$PROTOKOLL_TOKEN" "/geschaefte/${FIRST_G_ID}/beschluesse" "200" \
  --data-urlencode "code=${ERSTER_BESCHLUSS_CODE}" \
  --data-urlencode "text=Beschluss durch Protokollfuehrung"
assert_json '.autorUid == "parlwin_protokoll"' "Beschluss-Autor Protokollführer fehlt"

api_expect_status POST "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte/${FIRST_G_ID}/beschluesse" "200" \
  --data-urlencode "code=${ERSTER_BESCHLUSS_CODE}" \
  --data-urlencode "text=Beschluss durch Protokoll-Stellvertretung"
assert_json '.autorUid == "parlwin_mitglied"' "Beschluss-Autor Stellvertretung fehlt"

api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/kommissionsmitglied" "200" \
  --data-urlencode "uid=parlwin_mitglied" \
  --data-urlencode "name=Mitglied E2E" \
  --data-urlencode "gueltig_von=${STV_VON}" \
  --data-urlencode "gueltig_bis=${STV_BIS}"

echo "[E2E] Sitzung/Traktandum-Updates testen"
api_expect_status PUT "admin" "$ADMIN_TOKEN" "/sitzungen/${FIRST_S_ID}" "200" \
  --data-urlencode "bemerkungen=E2E Sitzungskommentar $(date +%s)"
assert_json '.bemerkungen | startswith("E2E Sitzungskommentar")' "Sitzungs-Bemerkung nicht gespeichert"

TRAKT_S_ID="$(sql "SELECT sitzung_id FROM ${TABLE_PREFIX}pw_traktanden ORDER BY sitzung_id DESC LIMIT 1;")"
assert_non_empty "$TRAKT_S_ID" "Keine Traktanden in der DB gefunden"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/sitzungen/${TRAKT_S_ID}/traktanden" "200"
TRAKTANDEN_COUNT="$(jq 'length' <<<"$LAST_BODY")"
if (( TRAKTANDEN_COUNT <= 0 )); then
  fail "Sitzung ${TRAKT_S_ID} hat trotz DB-Eintrag keine Traktanden via API"
fi
FIRST_T_ID="$(jq -r '.[0].id' <<<"$LAST_BODY")"

api_expect_status PUT "admin" "$ADMIN_TOKEN" "/sitzungen/${TRAKT_S_ID}/traktanden/${FIRST_T_ID}" "200" \
  --data-urlencode "bemerkungen=E2E Traktandumskommentar $(date +%s)" \
  --data-urlencode "notizen=[{\"text\":\"E2E-Notiz\"}]"
assert_json '.bemerkungen | startswith("E2E Traktandumskommentar")' "Traktandums-Bemerkung nicht gespeichert"
assert_json '.notizen | contains("E2E-Notiz")' "Traktandums-Notiz nicht gespeichert"

echo "[E2E] API-Filter prüfen"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?letzter_beschluss=${ERSTER_BESCHLUSS_CODE}&show_erledigt=1&limit=1000" "200"
jq -e 'map(.id) | index('"${FIRST_G_ID}"') != null' <<<"$LAST_BODY" >/dev/null \
  || fail "Filter nach letztem Beschluss liefert Testgeschäft nicht"

echo "[E2E] DB-Tabellen prüfen"
GESCHAEFTE_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaefte WHERE geloescht=0;")"
SITZUNGEN_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_sitzungen WHERE geloescht=0;")"
MITGLIEDER_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_mitglieder WHERE geloescht=0;")"
AKTIONEN_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaeft_aktionen WHERE geschaeft_id=${FIRST_G_ID};")"
ZUST_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaeft_zustaendigkeiten WHERE geschaeft_id=${SECOND_G_ID};")"
EREIGNIS_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaeft_ereignisse;")"
ROLLEN_DB_COUNT="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_fraktionsrollen;")"
GESCHAEFT_TITEL_TYP="$(sql "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'nextcloud' AND TABLE_NAME = '${TABLE_PREFIX}pw_geschaefte' AND COLUMN_NAME = 'titel' LIMIT 1;")"
SITZUNG_TITEL_TYP="$(sql "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'nextcloud' AND TABLE_NAME = '${TABLE_PREFIX}pw_sitzungen' AND COLUMN_NAME = 'titel' LIMIT 1;")"
TRAKT_TITEL_TYP="$(sql "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'nextcloud' AND TABLE_NAME = '${TABLE_PREFIX}pw_traktanden' AND COLUMN_NAME = 'titel' LIMIT 1;")"
ENTWURF_TITEL_TYP="$(sql "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'nextcloud' AND TABLE_NAME = '${TABLE_PREFIX}pw_vorstoss_entwuerfe' AND COLUMN_NAME = 'titel' LIMIT 1;")"
AKTION_TITEL_TYP="$(sql "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'nextcloud' AND TABLE_NAME = '${TABLE_PREFIX}pw_geschaeft_aktionen' AND COLUMN_NAME = 'titel' LIMIT 1;")"

assert_int_ge "$GESCHAEFTE_DB_COUNT" 20 "DB: zu wenige Geschäfte"
assert_int_ge "$SITZUNGEN_DB_COUNT" 10 "DB: zu wenige Sitzungen"
assert_int_ge "$MITGLIEDER_DB_COUNT" 20 "DB: zu wenige Mitglieder"
assert_int_ge "$AKTIONEN_DB_COUNT" 4 "DB: zu wenige Aktionen im Testgeschäft"
assert_eq "$ZUST_DB_COUNT" "2" "DB: Zuständigkeiten-Anzahl falsch"
assert_int_ge "$EREIGNIS_DB_COUNT" 1 "DB: keine importierten Geschäftsereignisse"
assert_int_ge "$ROLLEN_DB_COUNT" 3 "DB: Fraktionsrollen wurden nicht gespeichert"
[[ "$GESCHAEFT_TITEL_TYP" =~ text$ ]] || fail "DB: pw_geschaefte.titel ist nicht als TEXT-Typ angelegt (${GESCHAEFT_TITEL_TYP})"
[[ "$SITZUNG_TITEL_TYP" =~ text$ ]] || fail "DB: pw_sitzungen.titel ist nicht als TEXT-Typ angelegt (${SITZUNG_TITEL_TYP})"
[[ "$TRAKT_TITEL_TYP" =~ text$ ]] || fail "DB: pw_traktanden.titel ist nicht als TEXT-Typ angelegt (${TRAKT_TITEL_TYP})"
[[ "$ENTWURF_TITEL_TYP" =~ text$ ]] || fail "DB: pw_vorstoss_entwuerfe.titel ist nicht als TEXT-Typ angelegt (${ENTWURF_TITEL_TYP})"
[[ "$AKTION_TITEL_TYP" =~ text$ ]] || fail "DB: pw_geschaeft_aktionen.titel ist nicht als TEXT-Typ angelegt (${AKTION_TITEL_TYP})"

TRAKT_BEM_DB="$(sql "SELECT bemerkungen FROM ${TABLE_PREFIX}pw_traktanden WHERE id=${FIRST_T_ID} LIMIT 1;")"
[[ "$TRAKT_BEM_DB" == E2E\ Traktandumskommentar* ]] || fail "DB: Traktandums-Bemerkung nicht persistiert"

echo "[E2E] Status-Verteilung der synchronisierten Geschäfte (Diagnose):"
sql "SELECT COALESCE(NULLIF(status,''),'(leer)') AS status, COUNT(*) AS anzahl FROM ${TABLE_PREFIX}pw_geschaefte WHERE geloescht=0 GROUP BY status;" || true
ALLE_DB="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaefte WHERE geloescht=0;")"
PENDENT_DB="$(sql "SELECT COUNT(*) FROM ${TABLE_PREFIX}pw_geschaefte WHERE geloescht=0 AND (status IS NULL OR (LOWER(status) NOT LIKE '%erledigt%' AND LOWER(status) NOT LIKE '%abgeschlossen%' AND LOWER(status) NOT LIKE '%aufgehoben%'));")"
echo "[E2E] DB: gesamt=${ALLE_DB} pendent=${PENDENT_DB}"

echo "[E2E] Prüfung 1/4: Geschäftsliste (inkl. erledigte) stimmt mit Datenbank überein"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=1000&show_erledigt=1" "200"
ALLE_API="$(jq 'length' <<<"$LAST_BODY")"
assert_int_ge "$ALLE_API" 1 "API liefert keine Geschäfte trotz erfolgtem Sync"
assert_eq "$ALLE_API" "$ALLE_DB" "API-Gesamtliste weicht von der DB ab"

echo "[E2E] Prüfung 2/4: Standardfilter zeigt genau die pendenten Geschäfte"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=1000" "200"
DEFAULT_API="$(jq 'length' <<<"$LAST_BODY")"
assert_eq "$DEFAULT_API" "$PENDENT_DB" "Standardansicht zeigt nicht exakt die pendenten Geschäfte (API=$DEFAULT_API DB-pendent=$PENDENT_DB)"
jq -e 'all(.[]; (.status==null) or ((.status|ascii_downcase|contains("erledigt")|not) and (.status|ascii_downcase|contains("abgeschlossen")|not) and (.status|ascii_downcase|contains("aufgehoben")|not)))' <<<"$LAST_BODY" >/dev/null \
  || fail "Standardansicht enthält erledigte/abgeschlossene/aufgehobene Geschäfte"

echo "[E2E] Prüfung 3/4: Pflichtfelder vorhanden und Detailabruf möglich"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=1000&show_erledigt=1" "200"
jq -e 'all(.[]; .id and .titel and .status)' <<<"$LAST_BODY" >/dev/null || fail "Geschäfte fehlen erforderliche Felder"
FIRST_ID="$(jq -r '.[0].id' <<<"$LAST_BODY")"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte/${FIRST_ID}" "200"
jq -e '.id and .titel' <<<"$LAST_BODY" >/dev/null || fail "Geschäft-Detail fehlen Felder"

echo "[E2E] Prüfung 4/4: Alle Mitglieder sehen dieselbe Geschäftsliste"
api_expect_status GET "admin" "$ADMIN_TOKEN" "/geschaefte?limit=200" "200"
ADMIN_COUNT="$(jq 'length' <<<"$LAST_BODY")"
api_expect_status GET "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/geschaefte?limit=200" "200"
PRAESIDENT_COUNT="$(jq 'length' <<<"$LAST_BODY")"
api_expect_status GET "parlwin_mitglied" "$MITGLIED_TOKEN" "/geschaefte?limit=200" "200"
MITGLIED_COUNT="$(jq 'length' <<<"$LAST_BODY")"

if [[ "$ADMIN_COUNT" != "$PRAESIDENT_COUNT" ]] || [[ "$ADMIN_COUNT" != "$MITGLIED_COUNT" ]]; then
  fail "Mitglieder sehen unterschiedliche Anzahl Geschäfte (admin=$ADMIN_COUNT praesident=$PRAESIDENT_COUNT mitglied=$MITGLIED_COUNT)"
fi

echo "[E2E] Fraktionsraum einrichten (Gruppe + geteilter Ordner + Kalender)"
FRAKTION_GRUPPE="parlwin-fraktion"
occ group:add "$FRAKTION_GRUPPE" >/dev/null 2>&1 || true
occ group:adduser "$FRAKTION_GRUPPE" parlwin_praesidium >/dev/null 2>&1 || true
occ group:adduser "$FRAKTION_GRUPPE" parlwin_protokoll >/dev/null 2>&1 || true
occ group:adduser "$FRAKTION_GRUPPE" parlwin_mitglied >/dev/null 2>&1 || true
# --- Migrations-Ausgangslage: ein MITGLIED hat bereits einen eigenen "Fraktion"-
# Ordner mit der Gruppe geteilt (mit Daten), BEVOR der offizielle Admin-Ordner
# existiert. Beim Einrichten muss der Service diesen sauber übernehmen:
#   a (praesidium) ist Besitzer und teilt; b (protokoll) und c (mitglied) arbeiten mit.
DAV="/remote.php/dav/files"
MIG_A="parlwin_praesidium"; MIG_B="parlwin_protokoll"; MIG_C="parlwin_mitglied"
MIG_GESCH="20_Gesch%C3%A4fte" # 20_Geschäfte URL-kodiert

echo "[E2E] Migrations-Setup: a legt eigenen Fraktion-Ordner an und teilt ihn mit der Gruppe"
http_status_as MKCOL "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion" >/dev/null
http_status_as MKCOL "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion/$MIG_GESCH" >/dev/null
S=$(http_status_as PUT "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion/$MIG_GESCH/a.txt" "Datei von a")
[[ "$S" =~ ^2 ]] || fail "a konnte erste Datei nicht anlegen (HTTP $S)"
S=$(http_status_as POST "$MIG_A" "$PRAESIDIUM_TOKEN" "/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json" \
  "path=/Fraktion&shareType=1&shareWith=$FRAKTION_GRUPPE&permissions=31" "application/x-www-form-urlencoded")
[[ "$S" == "200" ]] || fail "a konnte Fraktion nicht mit der Gruppe teilen (HTTP $S)"

echo "[E2E] Migrations-Setup: b ergänzt eine zweite Datei und einen Nicht-Schema-Ordner Test"
S=$(http_status_as PUT "$MIG_B" "$PROTOKOLL_TOKEN" "$DAV/$MIG_B/Fraktion/$MIG_GESCH/b.txt" "Datei von b")
[[ "$S" =~ ^2 ]] || fail "b konnte zweite Datei nicht anlegen (HTTP $S)"
http_status_as MKCOL "$MIG_B" "$PROTOKOLL_TOKEN" "$DAV/$MIG_B/Fraktion/Test" >/dev/null
S=$(http_status_as PUT "$MIG_B" "$PROTOKOLL_TOKEN" "$DAV/$MIG_B/Fraktion/Test/test.txt" "Testdatei von b")
[[ "$S" =~ ^2 ]] || fail "b konnte Test-Datei nicht anlegen (HTTP $S)"

echo "[E2E] Migrations-Setup: c liest die Datei in Fraktion/Test (geteilter Zugriff)"
S=$(http_status_as GET "$MIG_C" "$MITGLIED_TOKEN" "$DAV/$MIG_C/Fraktion/Test/test.txt")
[[ "$S" == "200" ]] || fail "c kann Datei in Fraktion/Test nicht lesen (HTTP $S)"

# Reproduktion des Bugs «Fraktionsordner fehlt beim Mitglied»: Dieses Mitglied
# akzeptiert eingehende Freigaben NICHT automatisch (default_accept=no) – erst JETZT
# setzen, nachdem c den geteilten Ordner gelesen hat. Der Auto-Accept-Listener von
# Nextcloud lässt den offiziellen Gruppen-Share damit auf STATUS_PENDING; in diesem
# Zustand mountet Nextcloud den Ordner nicht. Der Fraktionsraum-Service muss den
# Share bei jedem Lauf für ALLE Mitglieder explizit akzeptieren.
occ user:setting parlwin_mitglied files_sharing default_accept no >/dev/null 2>&1 || true

# Gruppe über die Settings-API setzen (läuft in PHP-FPM, gleicher APCu-Cache wie
# der Fraktionsraum-Endpoint). occ config:app:set würde nur den CLI-Cache treffen,
# FPM sähe den Wert nicht. Das Speichern triggert sicherstellen() bereits selbst.
api_expect_status POST "admin" "$ADMIN_TOKEN" "/settings" "200" \
  --data-urlencode "nextcloud_gruppe=$FRAKTION_GRUPPE"
api_expect_status POST "admin" "$ADMIN_TOKEN" "/sitzungstypen/fraktionsraum-sicherstellen" "200"
echo "[E2E] Fraktionsraum-Bericht: ${LAST_BODY}"
assert_json '.erfolg == true' "Fraktionsraum konnte nicht eingerichtet werden"

echo "[E2E] Migrations-Erwartungen prüfen (Übernahme des Mitglied-Ordners in den offiziellen)"
# 1. a hat den offiziellen Fraktion-Ordner UND die Sicherung Fraktion.bak.
S=$(http_status_as PROPFIND "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion" "" "application/xml" "Depth: 0")
[[ "$S" == "207" ]] || fail "a sieht den offiziellen Fraktion-Ordner nicht (HTTP $S)"
S=$(http_status_as PROPFIND "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion.bak" "" "application/xml" "Depth: 0")
[[ "$S" == "207" ]] || fail "a hat keine Sicherung Fraktion.bak (HTTP $S)"
# 2. b und c haben NUR den neuen Fraktion-Ordner, kein Fraktion.bak.
for ut in "$MIG_B:$PROTOKOLL_TOKEN" "$MIG_C:$MITGLIED_TOKEN"; do
  u="${ut%%:*}"; t="${ut##*:}"
  S=$(http_status_as PROPFIND "$u" "$t" "$DAV/$u/Fraktion" "" "application/xml" "Depth: 0")
  [[ "$S" == "207" ]] || fail "$u sieht den offiziellen Fraktion-Ordner nicht (HTTP $S)"
  S=$(http_status_as PROPFIND "$u" "$t" "$DAV/$u/Fraktion.bak" "" "application/xml" "Depth: 0")
  [[ "$S" == "404" ]] || fail "$u sieht fälschlich Fraktion.bak (HTTP $S, erwartet 404)"
done
# 3. Ordner Test und alle drei zuvor erzeugten Dateien sind im offiziellen Ordner
#    für a, b und c lesbar.
for ut in "$MIG_A:$PRAESIDIUM_TOKEN" "$MIG_B:$PROTOKOLL_TOKEN" "$MIG_C:$MITGLIED_TOKEN"; do
  u="${ut%%:*}"; t="${ut##*:}"
  for f in "$MIG_GESCH/a.txt" "$MIG_GESCH/b.txt" "Test/test.txt"; do
    S=$(http_status_as GET "$u" "$t" "$DAV/$u/Fraktion/$f")
    [[ "$S" == "200" ]] || fail "$u kann Fraktion/$f nicht lesen (HTTP $S)"
  done
done
# 4. Die Sicherung Fraktion.bak bei a enthält weiterhin die drei alten Dateien.
for f in "$MIG_GESCH/a.txt" "$MIG_GESCH/b.txt" "Test/test.txt"; do
  S=$(http_status_as GET "$MIG_A" "$PRAESIDIUM_TOKEN" "$DAV/$MIG_A/Fraktion.bak/$f")
  [[ "$S" == "200" ]] || fail "Fraktion.bak/$f fehlt bei a (HTTP $S)"
done
echo "[E2E] Migrations-Erwartungen erfüllt"

echo "[E2E] Diagnose: Ordner-Share + Kalender + Kalender-Shares"
sql "SELECT id,share_type,share_with,item_type,file_target FROM ${TABLE_PREFIX}share WHERE share_type=1;" || true
sql "SELECT id,principaluri,uri,displayname FROM ${TABLE_PREFIX}calendars WHERE uri LIKE '%parlwin%';" || true
sql "SELECT id,principaluri,type,access,resourceid FROM ${TABLE_PREFIX}dav_shares;" || true
echo "[E2E] Diagnose: parlwin-Logzeilen (Sharing-Fehler):"
docker compose exec -T nextcloud-php-fpm php -r '
$cands=["/app/data/nextcloud.log","/var/www/html/data/nextcloud.log","/data/nextcloud.log"];
foreach($cands as $c){ if(is_file($c)){ echo "Log: $c\n"; foreach(array_slice(file($c),-300) as $l){ if(stripos($l,"parlwin")!==false){ echo mb_substr(trim($l),0,700),"\n"; } } } }
' || true
echo "[E2E] Diagnose: parlwin-Meldungen aus Container-Logs:"
docker compose logs --tail=800 nextcloud-php-fpm 2>&1 | grep -iE 'parlwin|sharing fehlgeschlagen|not allowed to share|share' | tail -40 || true

echo "[E2E] First-Run-Wizard deaktivieren (Modal würde Browser-Klicks blockieren)"
occ app:disable firstrunwizard >/dev/null 2>&1 || true

echo "[E2E] Fraktionssitzungsmodus zurücksetzen (sonst Beschluss für Nicht-Protokollführer gesperrt)"
api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/fraktionssitzung" "200" \
  --data-urlencode "aktiv=0"

# Reproduktion des Bugs «Keine Geschäfte gefunden»: ein pendentes Geschäft mit
# NULL in quelle_aktualisiert_am. Beim Mapping einer NULL-Spalte auf die als
# string typisierte Entity-Property bricht sonst die GESAMTE Liste mit HTTP 500 ab.
# Die synchronisierten E2E-Daten sind sonst alle erledigt, daher zusätzlich auf
# Pendent setzen, damit das Geschäft in der Standardansicht erscheinen muss.
echo "[E2E] Reproduktion: pendentes Geschäft mit NULL-Quelldatum erzeugen"
sql "UPDATE ${TABLE_PREFIX}pw_geschaefte SET status='Pendent', quelle_aktualisiert_am=NULL WHERE id=(SELECT id FROM (SELECT id FROM ${TABLE_PREFIX}pw_geschaefte WHERE geloescht=0 ORDER BY datum DESC LIMIT 1) AS x);"

echo "[E2E] Multi-User-Browser-Test (Playwright, 3 gleichzeitige Nutzer)"
export PW_U1="parlwin_praesidium" PW_P1="PwtP4ss!Praesidium"
export PW_U2="parlwin_protokoll"  PW_P2="PwtP4ss!Protokoll"
export PW_U3="parlwin_mitglied"   PW_P3="PwtP4ss!Mitglied"
export PW_ADMIN_PASS="${NEXTCLOUD_ADMIN_PASSWORD}"
docker compose run --rm playwright || fail "Multi-User-Browser-E2E (Playwright) fehlgeschlagen"

# --- Cron-/Background-Job-Test -------------------------------------------------
# Prüft den ECHTEN automatischen Mechanismus, der in Produktion fehlte: der
# parlwin-Watcher tickt selbst den Nextcloud-Cron (PARLWIN_CRON_INTERVAL, im Test
# 5s). Es wird NICHT manuell getriggert. Ablauf: sicherstellen dass kein Sync
# läuft, den SyncJob fällig machen, dann abwarten, bis der automatische Cron-Tick
# einen Sync mit Quelle "background-job" startet. Bewusst zuletzt, damit ein
# ausgelöster Sync die übrigen Prüfungen nicht beeinflusst.
echo "[E2E] Cron-Job-Test: automatischer Cron-Tick löst die Synchronisation aus"

# 1. Sicherstellen, dass kein Sync läuft; Fortschritt über die DB prüfen (der Job
#    läuft per CLI, daher nicht über den FPM-APCu-Cache der /sync/status-API).
api_expect_status POST "admin" "$ADMIN_TOKEN" "/sync/cancel" "200" || true
CRON_CLEAN=0
for _ in $(seq 1 60); do
  CRON_PROG="$(sql "SELECT configvalue FROM ${TABLE_PREFIX}appconfig WHERE appid='parlwin' AND configkey='sync_progress';")"
  if ! grep -q '"running":true' <<<"$CRON_PROG"; then CRON_CLEAN=1; break; fi
  sleep 1
done
[[ "$CRON_CLEAN" == "1" ]] || fail "Vor dem Cron-Test läuft noch eine Synchronisation"
# Fortschritt zurücksetzen, damit ein neuer Lauf eindeutig erkennbar ist.
sql "DELETE FROM ${TABLE_PREFIX}appconfig WHERE appid='parlwin' AND configkey='sync_progress';" || true

# 2. Sync-Stunden für den Test auf jede Stunde setzen (sonst synchronisiert der Job
#    nur um 03:00/15:00 und der Test wäre nicht deterministisch).
occ config:app:set parlwin sync_stunden --value="$(seq -s, 0 23)" >/dev/null

# 3. Den registrierten Background-Job ermitteln und fällig machen. Der nächste
#    automatische Cron-Tick des Watchers führt ihn dann aus.
CRON_JOB_ID="$(sql "SELECT id FROM ${TABLE_PREFIX}jobs WHERE class LIKE '%ParliamentWinterthur%SyncJob%' ORDER BY id LIMIT 1;")"
[[ -n "$CRON_JOB_ID" ]] || fail "SyncJob ist nicht im JobList registriert – Cron würde ihn nie ausführen"
sql "UPDATE ${TABLE_PREFIX}jobs SET last_run=0, last_checked=0, reserved_at=0 WHERE id=${CRON_JOB_ID};"

# 4. KEIN manueller Trigger: abwarten, bis der automatische Cron-Tick des Watchers
#    einen Sync mit Quelle "background-job" startet (Tick alle PARLWIN_CRON_INTERVAL=5s).
CRON_TRIGGERED=0
for _ in $(seq 1 40); do
  CRON_PROG="$(sql "SELECT configvalue FROM ${TABLE_PREFIX}appconfig WHERE appid='parlwin' AND configkey='sync_progress';")"
  if grep -q '"source":"background-job"' <<<"$CRON_PROG"; then CRON_TRIGGERED=1; break; fi
  sleep 1
done
[[ "$CRON_TRIGGERED" == "1" ]] || fail "Der automatische Cron-Tick hat keinen Sync ausgelöst (sync_progress ohne source=background-job): ${CRON_PROG}"
echo "[E2E] Cron-Job-Test bestanden: Background-Job hat die Synchronisation ausgelöst"

# 6. Aufräumen: laufenden Sync stoppen, Test-Konfiguration entfernen.
api_expect_status POST "admin" "$ADMIN_TOKEN" "/sync/cancel" "200" || true
occ config:app:delete parlwin sync_stunden >/dev/null 2>&1 || true

echo "[E2E] Abgeschlossen: Integrationsprüfungen und Multi-User-Browser-Test bestanden."
