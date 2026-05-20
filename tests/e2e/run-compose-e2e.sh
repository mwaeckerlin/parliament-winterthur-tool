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
export PARLWIN_REALTIME_WS_URL="${PARLWIN_REALTIME_WS_URL:-ws://parlwin-realtime:3001/ws}"
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

TODAY="$(date +%F)"
api_expect_status POST "parlwin_praesidium" "$PRAESIDIUM_TOKEN" "/settings/protokollfuehrer-stellvertretung" "200" \
  --data-urlencode "uid=parlwin_mitglied" \
  --data-urlencode "name=Mitglied E2E" \
  --data-urlencode "gueltig_von=${TODAY}" \
  --data-urlencode "gueltig_bis=${TODAY}"

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
  --data-urlencode "gueltig_von=${TODAY}" \
  --data-urlencode "gueltig_bis=${TODAY}"

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

echo "E2E erfolgreich: frische DB, Live-Sync, Plausibilität, API-/Frontend-nahe Writes, Rechteprüfung und DB-Verifikation abgeschlossen."
