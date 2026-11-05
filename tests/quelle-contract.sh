#!/usr/bin/env bash
# Vertrag der lokalen Gegenstelle des Abgleichs (Dockerfile.quelle).
#
# Sie liefert die gespeicherten Seiten und PDF des Parlaments aus, damit der
# E2E-Lauf die echte Webseite der Stadt nicht befragt. Geprüft wird jeder Weg,
# den der Abgleich geht: die fünf Listen, die Detailseite eines Geschäfts mit
# und ohne eigene Aufnahme, die Detailseiten der Behörden und Sitzungen, und
# die Dokumente — auch das, was ein unbekannter Weg antwortet.
#
# Ohne Angabe startet der Lauf die Gegenstelle als einzelnen Container und
# daneben einen einzigen Container mit curl, der jede Anfrage stellt.
#
# Mit PARLWIN_QUELLE_PROJEKT=<compose-projekt> prüft er die Gegenstelle eines
# laufenden E2E-Stacks und startet keinen Container: Die Anfragen stellt PHP im
# Container nextcloud-php-fpm desselben Stacks, so wie der Abgleich selbst.
set -uo pipefail

cd "$(dirname "$0")/.."

ABBILD="mwaeckerlin/parliament-winterthur-tool:quelle"
CONTAINER="parlwin-quelle-vertrag-$$"
HOLER="parlwin-quelle-holer-$$"
FEHLER=0
PROJEKT="${PARLWIN_QUELLE_PROJEKT:-}"

aufraeumen() {
  if [[ -z "$PROJEKT" ]]; then
    docker rm -f "$CONTAINER" "$HOLER" >/dev/null 2>&1 || true
  fi
}
trap aufraeumen EXIT

if [[ -n "$PROJEKT" ]]; then
  echo "[quelle] Prüfe die Gegenstelle des Stacks ${PROJEKT}"
  BASIS="http://parlwin-quelle:8080"
  # holen <pfad> <ziel-datei> [<methode>]: schreibt den Rumpf in die Datei und
  # gibt den Status aus. Ohne Methode ist es GET.
  holen() {
    docker compose -p "$PROJEKT" exec -T -e Q_URL="${BASIS}$1" -e Q_METHODE="${3:-GET}" nextcloud-php-fpm php -r '
      $methode = getenv("Q_METHODE");
      $ctx = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 30, "method" => $methode, "content" => $methode === "PUT" ? "500" : ""]]);
      $rumpf = @file_get_contents(getenv("Q_URL"), false, $ctx);
      $code = "000";
      if (isset($http_response_header[0]) && preg_match("#\s(\d{3})\s#", $http_response_header[0], $m)) { $code = $m[1]; }
      fwrite(STDERR, $code);
      echo $rumpf === false ? "" : $rumpf;
    ' 2>"$2.status" >"$2"
    cat "$2.status"
  }
  protokoll() { docker compose -p "$PROJEKT" logs --no-color parlwin-quelle 2>&1; }
else
  echo "[quelle] Baue die Gegenstelle"
  docker build --file Dockerfile.quelle --tag "$ABBILD" . || exit 1

  echo "[quelle] Starte sie"
  docker run -d --name "$CONTAINER" "$ABBILD" >/dev/null || exit 1
  # Ein einziger Container mit curl im Netz der Gegenstelle; jede Anfrage
  # läuft darin über `docker exec`. Bis zum 2026-11-04 startete jede Anfrage
  # einen eigenen Container, 41 je Lauf.
  docker run -d --name "$HOLER" --network "container:${CONTAINER}" \
    --entrypoint sleep curlimages/curl:latest 3600 >/dev/null || exit 1
  BASIS="http://127.0.0.1:8080"
  holen() {
    local methode="${3:-GET}"
    if [[ "$methode" == "PUT" ]]; then
      docker exec "$HOLER" curl -sS -X PUT --data 500 -o /tmp/rumpf -w '%{http_code}' "${BASIS}$1" 2>/dev/null
    else
      docker exec "$HOLER" curl -sS -X "$methode" -o /tmp/rumpf -w '%{http_code}' "${BASIS}$1" 2>/dev/null
    fi
    docker exec "$HOLER" cat /tmp/rumpf >"$2" 2>/dev/null
  }
  protokoll() { docker logs "$CONTAINER" 2>&1; }

  # Warten, bis sie antwortet: Der Container bringt kein curl mit, darum fragt
  # der Container mit curl im Netz des ersten.
  for _ in $(seq 1 30); do
    if docker exec "$HOLER" curl -fsS -o /dev/null "${BASIS}/politbusiness" 2>/dev/null; then
      break
    fi
    sleep 1
  done
fi

# status <erwartet> <pfad> [<muster im Rumpf>] [<methode>]
status() {
  local erwartet="$1" pfad="$2" muster="${3:-}" methode="${4:-GET}"
  local antwort rumpf
  rumpf="$(mktemp)"
  antwort="$(holen "$pfad" "$rumpf" "$methode")"
  if [[ "$antwort" != "$erwartet" ]]; then
    echo "[quelle] FEHLER ${pfad}: Status ${antwort}, erwartet ${erwartet}"
    FEHLER=$((FEHLER + 1))
    rm -f "$rumpf" "$rumpf.status"
    return
  fi
  if [[ -n "$muster" ]]; then
    # Der Rumpf liegt in einer Datei und wird dort gesucht: In einer Pipe
    # beendet `grep -q` den Lauf beim ersten Treffer, der Absender stirbt am
    # geschlossenen Rohr, und `pipefail` macht aus dem Treffer einen
    # Fehlschlag — bei grossen Seiten und PDF reproduzierbar.
    if ! grep -qa "$muster" "$rumpf"; then
      echo "[quelle] FEHLER ${pfad}: «${muster}» steht nicht in der Antwort"
      rm -f "$rumpf" "$rumpf.status"
      FEHLER=$((FEHLER + 1))
      return
    fi
  fi
  rm -f "$rumpf" "$rumpf.status"
  echo "[quelle] ok ${pfad} (${antwort})"
}

echo "[quelle] Die fünf Listen"
status 200 /politbusiness 'data-entities'
status 200 /sitzung 'data-entities'
status 200 /kommissionen 'data-entities'
status 200 /fraktionen 'data-entities'
status 200 /stadtparlament/27428 'data-entities'

echo "[quelle] Detailseiten"
# Mit eigener Aufnahme: die Weisung zum Budget 2027.
status 200 /_rte/information/2990959 'Budget 2027'
# Ohne eigene Aufnahme: die Ersatzseite, damit der Abgleich seinen Weg geht.
status 200 /_rte/information/999999 'Kategorie:'
# Die Weisung eines älteren Budgetjahres: Aus der Vorlage von 2027 erzeugt, mit
# den Dokumenten des Jahres. Ohne sie fand der Import kein Teil A und endete
# mit 500 — 36 Tests der Budget-Familie.
status 200 /_rte/information/2572570 'Budget 2026'
status 200 /_rte/information/1408642 'Budget 2017'
status 200 /_rte/anlass/6866848 'Drehbuch'
status 200 /_rte/anlass/999999 'Keine Aufnahme'
status 200 /_rte/behoerde/27446 'data-entities'
# Eine Fraktion aus der Liste ohne eigene Aufnahme: Sie bekommt die Kopie der
# Fraktions-Aufnahme, damit ihre Mitglieder eine Funktion tragen. Ohne das
# zeigt die Mitgliederansicht keine einzige Fraktionsrolle.
status 200 /_rte/behoerde/43405 'data-entities'
status 200 /_rte/behoerde/999999 'Keine Aufnahme'

echo "[quelle] Dokumente"
status 200 /_doc/7242604 '%PDF'
status 200 /_doc/7242610 '%PDF'
status 200 /_doc/6378680 '%PDF'
status 200 /_doc/2026604 '%PDF'
status 200 /_doc/2017610 '%PDF'
status 200 /_doc/999999 '%PDF'

echo "[quelle] Ein unbekannter Weg endet mit 404"
status 404 /gibt-es-nicht

echo "[quelle] Die Stadtratsbeschlüsse und ihr Steuerweg"
STEUERUNG=/_steuerung/stadtratsbeschluesse-fehler
status 200 /stadtratsbeschluesse '/stadtratsbeschluesse/2026-10-21/20261279'
status 404 "$STEUERUNG"
# PUT legt den Fehler an: Die Liste antwortet mit 500, ihre Detailseiten nicht.
status 201 "$STEUERUNG" '' PUT
status 200 "$STEUERUNG"
status 500 /stadtratsbeschluesse
status 500 '/stadtratsbeschluesse?page=2'
status 200 /stadtratsbeschluesse/2026-10-21/20261279 'resolution-document'
# Ein anderes Verfahren als GET, PUT und DELETE ist gesperrt.
status 403 "$STEUERUNG" '' POST
# DELETE nimmt den Fehler zurück, und die Liste antwortet wieder.
status 204 "$STEUERUNG" '' DELETE
status 404 "$STEUERUNG"
status 200 /stadtratsbeschluesse '/stadtratsbeschluesse/2026-10-21/20261279'

if [[ "${QUELLE_DIAGNOSE:-0}" == "1" ]]; then
  echo "[quelle] Diagnose: Anfang der Antwort"
  for pfad in /sitzung /stadtparlament/27428 /_rte/anlass/6866848 /_doc/7242604; do
    echo "--- ${pfad}"
    rumpf="$(mktemp)"
    echo "Status $(holen "$pfad" "$rumpf")"
    head -c 200 "$rumpf" | tr -d '\0' || true
    rm -f "$rumpf" "$rumpf.status"
    echo
  done
fi

echo "[quelle] Das Zugriffsprotokoll nennt jeden Weg"
PROTOKOLL="$(protokoll)"
for weg in /politbusiness /_rte/information/ /_doc/; do
  if ! grep -q "$weg" <<<"$PROTOKOLL"; then
    echo "[quelle] FEHLER: ${weg} steht nicht im Zugriffsprotokoll"
    FEHLER=$((FEHLER + 1))
  fi
done
echo "[quelle] Erste Zeile des Protokolls, damit die Form bekannt ist:"
grep -m 1 "politbusiness" <<<"$PROTOKOLL" || echo "[quelle] (keine Zeile mit politbusiness)"

if [[ "$FEHLER" -gt 0 ]]; then
  echo "==> quelle-contract: ${FEHLER} Fehler"
  exit 1
fi
echo "==> quelle-contract: alle Wege in Ordnung"
