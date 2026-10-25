#!/usr/bin/env bash
# Vertrag der lokalen Gegenstelle des Abgleichs (Dockerfile.quelle).
#
# Sie liefert die gespeicherten Seiten und PDF des Parlaments aus, damit der
# E2E-Lauf die echte Webseite der Stadt nicht befragt. Geprüft wird jeder Weg,
# den der Abgleich geht: die fünf Listen, die Detailseite eines Geschäfts mit
# und ohne eigene Aufnahme, die Detailseiten der Behörden und Sitzungen, und
# die Dokumente — auch das, was ein unbekannter Weg antwortet.
#
# Der Lauf braucht nur diesen einen Container, keinen Stack.
set -uo pipefail

cd "$(dirname "$0")/.."

ABBILD="mwaeckerlin/parliament-winterthur-tool:quelle"
CONTAINER="parlwin-quelle-vertrag-$$"
FEHLER=0

aufraeumen() {
  docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
}
trap aufraeumen EXIT

echo "[quelle] Baue die Gegenstelle"
docker build --file Dockerfile.quelle --tag "$ABBILD" . || exit 1

echo "[quelle] Starte sie"
docker run -d --name "$CONTAINER" "$ABBILD" >/dev/null || exit 1

# Warten, bis sie antwortet: Der Container bringt kein curl mit, darum fragt
# ein zweiter Container im Netz des ersten.
for _ in $(seq 1 30); do
  if docker run --rm --network "container:${CONTAINER}" curlimages/curl:latest \
      -fsS -o /dev/null "http://127.0.0.1:8080/politbusiness" 2>/dev/null; then
    break
  fi
  sleep 1
done

# status <erwartet> <pfad> [<muster im Rumpf>]
status() {
  local erwartet="$1" pfad="$2" muster="${3:-}"
  local antwort
  antwort="$(docker run --rm --network "container:${CONTAINER}" curlimages/curl:latest \
    -sS -o /tmp/rumpf -w '%{http_code}' "http://127.0.0.1:8080${pfad}" 2>/dev/null)"
  if [[ "$antwort" != "$erwartet" ]]; then
    echo "[quelle] FEHLER ${pfad}: Status ${antwort}, erwartet ${erwartet}"
    FEHLER=$((FEHLER + 1))
    return
  fi
  if [[ -n "$muster" ]]; then
    # Der Rumpf geht in eine Datei und wird dort gesucht: In einer Pipe
    # beendet `grep -q` den Lauf beim ersten Treffer, curl stirbt am
    # geschlossenen Rohr, und `pipefail` macht aus dem Treffer einen
    # Fehlschlag — bei grossen Seiten und PDF reproduzierbar.
    local rumpf
    rumpf="$(mktemp)"
    docker run --rm --network "container:${CONTAINER}" curlimages/curl:latest \
      -sS "http://127.0.0.1:8080${pfad}" >"$rumpf" 2>/dev/null
    if ! grep -qa "$muster" "$rumpf"; then
      echo "[quelle] FEHLER ${pfad}: «${muster}» steht nicht in der Antwort"
      rm -f "$rumpf"
      FEHLER=$((FEHLER + 1))
      return
    fi
    rm -f "$rumpf"
  fi
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

if [[ "${QUELLE_DIAGNOSE:-0}" == "1" ]]; then
  echo "[quelle] Diagnose: Kopfzeilen und Anfang der Antwort"
  for pfad in /sitzung /stadtparlament/27428 /_rte/anlass/6866848 /_doc/7242604; do
    echo "--- ${pfad}"
    docker run --rm --network "container:${CONTAINER}" curlimages/curl:latest \
      -sS -D - -o /tmp/rumpf "http://127.0.0.1:8080${pfad}" 2>/dev/null | head -8 || true
    docker run --rm --network "container:${CONTAINER}" curlimages/curl:latest \
      -sS "http://127.0.0.1:8080${pfad}" 2>/dev/null | head -c 200 | tr -d '\0' || true
    echo
  done
fi

echo "[quelle] Das Zugriffsprotokoll nennt jeden Weg"
PROTOKOLL="$(docker logs "$CONTAINER" 2>&1)"
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
