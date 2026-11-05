#!/usr/bin/env bash
# Vertrag des Auslieferungs-Stacks: Neustart, Speicher und Zeitgrenzen.
#
# Füllt PHP den Speicher des Servers, beendet der Kernel den grössten einzelnen
# Prozess, und solange sich PHP auf viele Prozesse verteilt, ist das die
# Datenbank. Darum verlangt der Stack: jeder Dienst startet von selbst wieder,
# der Container von PHP hat eine eigene Speichergrenze, die Zahl der
# PHP-Prozesse ist klein, und jede Variable steht mit ihrem Standard in
# example/.env.sample.
#
# Geprüft wird der Text der Datei, ohne Docker. Die Variablen sind die des Images
# mwaeckerlin/nextcloud:php-fpm: MAX_REQUEST_WORKERS setzt pm.max_children,
# MAX_INPUT_TIME setzt max_execution_time; MAX_CHILDREN und MAX_EXECUTION_TIME
# des Basis-Images überschreibt es und lässt sie wirkungslos.
#
# Aufruf: tests/compose-contract.sh [COMPOSE-DATEI [MUSTER-DATEI]]

set -uo pipefail

cd "$(dirname "$0")/.."

COMPOSE="${1:-example/docker-compose.yml}"
MUSTER="${2:-example/.env.sample}"
PHP_DIENST="nextcloud-php-fpm"
NGINX_DIENST="nextcloud-nginx"

PASS=0
FAIL=0
declare -a FAILED_NAMES=()

_pass() { PASS=$((PASS + 1)); echo "  PASS  $1"; }
_fail() { FAIL=$((FAIL + 1)); FAILED_NAMES+=("$1"); echo "  FAIL  $1: $2"; }

# Je Dienst unter «services:» eine Zeile «Dienst TAB - TAB -», je Eigenschaft
# «Dienst TAB Schlüssel TAB Wert», je Eintrag unter «environment:» den
# Schlüssel «environment.NAME». Kommentare zählen nicht.
_eigenschaften() {
    awk '
        function wert(zeile,    q) {
            q = "\047"
            sub(/^[^:]*:[ \t]*/, "", zeile)
            sub(/[ \t]+$/, "", zeile)
            gsub("^[\"" q "]|[\"" q "]$", "", zeile)
            return zeile
        }
        /^[^ #]/ { oben = $0; sub(/:.*/, "", oben); dienst = ""; block = ""; next }
        oben != "services" { next }
        /^  [a-z0-9][a-z0-9_.-]*:[ \t]*$/ {
            dienst = $0; sub(/^  /, "", dienst); sub(/:.*/, "", dienst)
            print dienst "\t-\t-"; block = ""; next
        }
        dienst == "" { next }
        /^    [a-z_]+:/ {
            schluessel = $0; sub(/^    /, "", schluessel); sub(/:.*/, "", schluessel)
            print dienst "\t" schluessel "\t" wert($0); block = schluessel; next
        }
        block == "environment" && /^      [A-Za-z_][A-Za-z0-9_]*:/ {
            name = $0; sub(/^ +/, "", name); sub(/:.*/, "", name)
            print dienst "\tenvironment." name "\t" wert($0); next
        }
    ' "$1"
}

# Der Wert, der gilt, wenn nichts gesetzt ist: «${NAME:-x}» ergibt x, ein Wert
# ohne Variable sich selbst, alles andere nichts.
_vorgabe() {
    local wert="$1" muster='^\$\{[A-Za-z_][A-Za-z0-9_]*:-(.*)\}$'
    if [[ "${wert}" =~ ${muster} ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
    elif [[ "${wert}" != *'$'* ]]; then
        printf '%s' "${wert}"
    fi
}

# Die Erkennung selbst: Ohne diese Prüfung bliebe jeder Vertrag unten grün,
# sobald die Zerlegung einen Dienst oder eine Eigenschaft übersieht.
_selbsttest() {
    local probe erwartet
    probe="$(_eigenschaften <(printf '%s\n' \
        'services:' \
        '  a:' \
        '    image: x' \
        '    # restart: unless-stopped' \
        '    environment:' \
        '      MAX_REQUEST_WORKERS: ${MAX_REQUEST_WORKERS:-8}' \
        '  b:' \
        '    restart: "${RESTART_POLICY:-unless-stopped}"' \
        'volumes:' \
        '  c:' \
        '    restart: unless-stopped'))"
    erwartet=$'a\t-\t-\na\timage\tx\na\tenvironment\t\na\tenvironment.MAX_REQUEST_WORKERS\t${MAX_REQUEST_WORKERS:-8}\nb\t-\t-\nb\trestart\t${RESTART_POLICY:-unless-stopped}'
    if [[ "${probe}" == "${erwartet}" ]]; then
        _pass "selbsttest_zerlegung"
    else
        _fail "selbsttest_zerlegung" "erwartet «${erwartet}», erhalten «${probe}»"
    fi
    if [[ "$(_vorgabe '${A:-8}')" == "8" && "$(_vorgabe 'unless-stopped')" == "unless-stopped" \
        && -z "$(_vorgabe '${A}')" && -z "$(_vorgabe '${A-8}')" && -z "$(_vorgabe '')" ]]; then
        _pass "selbsttest_vorgabe"
    else
        _fail "selbsttest_vorgabe" "die Vorgabe eines Werts wird falsch gelesen"
    fi
}

echo "==> Compose-Vertrag: ${COMPOSE}"

_selbsttest

if [[ ! -r "${COMPOSE}" || ! -r "${MUSTER}" ]]; then
    _fail "dateien_lesbar" "${COMPOSE} oder ${MUSTER} fehlt"
    echo ""
    echo "==> Compose-Vertrag: ${PASS} bestanden, ${FAIL} fehlgeschlagen"
    exit 1
fi

EIGENSCHAFTEN="$(_eigenschaften "${COMPOSE}")"

# Wert einer Eigenschaft eines Dienstes, leer, wenn sie fehlt.
_eigenschaft() {
    awk -F '\t' -v d="$1" -v s="$2" '$1 == d && $2 == s { print $3; exit }' <<<"${EIGENSCHAFTEN}"
}

mapfile -t DIENSTE < <(awk -F '\t' '$2 == "-" { print $1 }' <<<"${EIGENSCHAFTEN}")

echo "==> Jeder Dienst startet von selbst wieder"

for pflicht in "${PHP_DIENST}" "${NGINX_DIENST}" nextcloud-db; do
    if [[ " ${DIENSTE[*]} " == *" ${pflicht} "* ]]; then
        _pass "dienst_${pflicht}_vorhanden"
    else
        _fail "dienst_${pflicht}_vorhanden" "${COMPOSE} nennt den Dienst nicht"
    fi
done

for dienst in "${DIENSTE[@]}"; do
    wert="$(_eigenschaft "${dienst}" restart)"
    if [[ "$(_vorgabe "${wert}")" == "unless-stopped" ]]; then
        _pass "${dienst}_neustart"
    else
        _fail "${dienst}_neustart" "restart ist «${wert:-nicht gesetzt}», verlangt ist unless-stopped als Standard"
    fi
done

echo "==> PHP hat eine eigene Speichergrenze"

wert="$(_eigenschaft "${PHP_DIENST}" mem_limit)"
vorgabe="$(_vorgabe "${wert}")"
if [[ "${vorgabe}" =~ ^[1-9][0-9]*[bkmgBKMG]?$ ]]; then
    _pass "${PHP_DIENST}_mem_limit"
else
    _fail "${PHP_DIENST}_mem_limit" "mem_limit ist «${wert:-nicht gesetzt}», verlangt ist eine Grösse als Standard"
fi

# Ein einzelner Prozess darf PARLWIN_PHP_MEMORY_LIMIT belegen; daneben laufen
# der Hauptprozess von php-fpm und die wartenden Prozesse. Liegt mem_limit
# nicht um RESERVE_MIB darüber, beendet der Kernel einen grossen Import, bevor
# PHP seine Meldung schreibt. Die 256MiB sind geschätzt wie die 100MiB je
# Prozess in example/.env.sample.
RESERVE_MIB=256

# Eine Grösse in MiB: «1024M», «1280m», «1G», «524288k» oder Byte ohne Einheit.
_mib() {
    local muster='^([0-9]+)([bBkKmMgG]?)$'
    [[ "$1" =~ ${muster} ]] || return 1
    local zahl="${BASH_REMATCH[1]}" einheit="${BASH_REMATCH[2],,}"
    case "${einheit}" in
        g) echo $((zahl * 1024)) ;;
        m) echo "${zahl}" ;;
        k) echo $((zahl / 1024)) ;;
        *) echo $((zahl / 1048576)) ;;
    esac
}

php_grenze="$(_vorgabe "$(_eigenschaft "${PHP_DIENST}" environment.PARLWIN_PHP_MEMORY_LIMIT)")"
if php_mib="$(_mib "${php_grenze}")" && container_mib="$(_mib "${vorgabe}")" \
        && (( container_mib - php_mib >= RESERVE_MIB )); then
    _pass "${PHP_DIENST}_mem_limit_ueber_php"
else
    _fail "${PHP_DIENST}_mem_limit_ueber_php" "mem_limit «${vorgabe:-nicht gesetzt}» liegt nicht ${RESERVE_MIB}MiB über PARLWIN_PHP_MEMORY_LIMIT «${php_grenze:-nicht gesetzt}»"
fi

echo "==> Zahl der PHP-Prozesse und Zeitgrenze über die Variablen des Images"

# _umgebung DIENST NAME STANDARD
_umgebung() {
    local dienst="$1" name="$2" erwartet="$3" wert
    wert="$(_eigenschaft "${dienst}" "environment.${name}")"
    if [[ "${wert}" == "\${${name}:-${erwartet}}" ]]; then
        _pass "${dienst}_${name}"
    else
        _fail "${dienst}_${name}" "${name} ist «${wert:-nicht gesetzt}», verlangt ist \${${name}:-${erwartet}}"
    fi
}

_umgebung "${PHP_DIENST}" MAX_REQUEST_WORKERS 8
_umgebung "${PHP_DIENST}" MAX_INPUT_TIME 3600
_umgebung "${NGINX_DIENST}" MAX_INPUT_TIME 3600

for name in MAX_CHILDREN MAX_EXECUTION_TIME; do
    if ! awk -F '\t' -v d="${PHP_DIENST}" -v s="environment.${name}" \
            '$1 == d && $2 == s { gefunden = 1 } END { exit !gefunden }' <<<"${EIGENSCHAFTEN}"; then
        _pass "${PHP_DIENST}_ohne_${name}"
    else
        _fail "${PHP_DIENST}_ohne_${name}" "${name} wirkt im Image mwaeckerlin/nextcloud:php-fpm nicht, es überschreibt den Wert"
    fi
done

echo "==> Jede Variable steht mit ihrem Standard in ${MUSTER}"

OHNE_KOMMENTARE="$(grep -vE '^[[:space:]]*#' "${COMPOSE}" | sed 's/\$\$//g')"
mapfile -t VARIABLEN < <(grep -oE '\$\{[A-Za-z_][A-Za-z0-9_]*' <<<"${OHNE_KOMMENTARE}" | sed 's/^\${//' | sort -u)

if [[ ${#VARIABLEN[@]} -gt 0 ]]; then
    _pass "variablen_gefunden"
else
    _fail "variablen_gefunden" "${COMPOSE} nennt keine einzige Variable"
fi

for name in "${VARIABLEN[@]}"; do
    zeile="$(grep -m 1 -E "^(# )?${name}=" "${MUSTER}")"
    if [[ -z "${zeile}" ]]; then
        _fail "muster_${name}" "${MUSTER} nennt ${name} nicht"
        continue
    fi
    standard="$(grep -oE "\\\$\\{${name}:-[^}\$]+\\}" <<<"${OHNE_KOMMENTARE}" | head -n 1)"
    standard="${standard#*:-}"
    standard="${standard%\}}"
    if [[ -n "${standard}" && "${zeile#*=}" != "${standard}" ]]; then
        _fail "muster_${name}" "${MUSTER} nennt «${zeile#*=}», der Standard in ${COMPOSE} ist «${standard}»"
        continue
    fi
    _pass "muster_${name}"
done

echo ""
echo "==> Compose-Vertrag: ${PASS} bestanden, ${FAIL} fehlgeschlagen"
if [[ ${FAIL} -gt 0 ]]; then
    echo "==> Fehlgeschlagen: ${FAILED_NAMES[*]}"
    exit 1
fi
