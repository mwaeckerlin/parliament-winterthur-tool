#!/bin/bash
set -euo pipefail

# Erzeugt je Nextcloud-Hauptversion einen Zweig «nc<version>» aus «master» und
# setzt darin die FROM-Zeilen auf die Marken des Basis-Images
# («mwaeckerlin/nextcloud:php-fpm-nc<version>»; die Marken ohne «nc»
# gehoeren den alten Zweigen jenes Projekts und tragen einen anderen Stand). Die Zweige
# tragen keine eigene Geschichte: Jeder Lauf erzeugt sie aus dem aktuellen Stand
# von master neu und schiebt sie mit «push -f» nach GitHub, worauf der Bau auf
# GitHub die Marken «…-nc<version>» baut; nach einem Push auf master ruft er
# dieses Skript selbst auf (version-branches in .github/workflows/docker.yml). Ein Release einer Linie entsteht über einen
# Tag «nc<version>-vX.Y.Z» auf dem Zweig.
#
# Nextcloud lässt beim Upgrade keine Hauptversion aus, eine Instanz braucht also
# die Linie ihres Standes und jede folgende. Die Vorgabe reicht von 33 bis zur
# heute aktuellen 35. Andere Versionen stehen als Argumente:
#
#   ./create-branches.sh 34
#
# Dockerfile.realtime bleibt unberührt: Es baut auf mwaeckerlin/nodejs und
# enthält kein Nextcloud. Seine Marke «realtime-nc<version>» entsteht trotzdem
# und trägt denselben Inhalt wie «realtime».

# Der Stand, aus dem die Zweige entstehen. In der Pipeline steht in BASE_COMMIT
# der Commit des Pushes, der den Lauf ausgelöst hat: Ohne ihn würde der Lauf
# nehmen, worauf «origin/master» gerade zeigt, und bei zwei Pushes kurz
# hintereinander trügen die Linien-Zweige des ersten den Code des zweiten. Von
# Hand aufgerufen bleibt es beim Zweig.
BASE_BRANCH="${BASE_BRANCH:-master}"
BASE_COMMIT="${BASE_COMMIT:-}"

# Das Skript arbeitet auf Wegwerf-Zweigen. Bricht es mitten darin ab — eine
# Wache schlägt an, ein Push scheitert —, stünde die Arbeitskopie auf einem
# dieser Zweige mit geänderten Dateien. Darum führt jeder Ausgang zurück.
trap 'git checkout "$BASE_BRANCH" >/dev/null 2>&1 || true' EXIT
START_VERSION="${START_VERSION:-33}"
END_VERSION="${END_VERSION:-35}"

if [[ "$#" -gt 0 ]]; then
    VERSIONS=("$@")
else
    VERSIONS=()
    for ((v=START_VERSION; v<=END_VERSION; v++)); do
        VERSIONS+=("$v")
    done
fi

# Ohne Identität bricht der Commit unten ab. In der Pipeline setzt der Lauf sie
# selbst; dieser Rückfall greift, wo das Skript sonst ohne eine läuft. Eine
# gesetzte Identität bleibt unberührt.
if ! git config user.email > /dev/null; then
    git config user.email "noreply@github.com"
    git config user.name "github-actions"
fi

git fetch origin
if [[ -z "$BASE_COMMIT" ]]; then
    git checkout "$BASE_BRANCH"
    git pull --ff-only origin "$BASE_BRANCH"
    BASE_COMMIT="origin/$BASE_BRANCH"
fi

for version in "${VERSIONS[@]}"; do
    branch="nc$version"

    git checkout -B "$branch" "$BASE_COMMIT"

    sed -Ei "s|^FROM mwaeckerlin/nextcloud:php-fpm.*$|FROM mwaeckerlin/nextcloud:php-fpm-nc${version}|" Dockerfile.php-fpm
    sed -Ei "s|^FROM mwaeckerlin/nextcloud:nginx.*$|FROM mwaeckerlin/nextcloud:nginx-nc${version}|" Dockerfile.nginx

    # Geprüft wird, dass die Zeile wirklich steht: Ändert sich die Schreibweise
    # des FROM, ersetzt sed nichts, der Zweig wäre eine Kopie von master, und
    # das Image trüge ein anderes Nextcloud, als seine Marke verspricht. Genau
    # daran stand die Instanz am 23.09.2026 still.
    grep -q "^FROM mwaeckerlin/nextcloud:php-fpm-nc${version}$" Dockerfile.php-fpm \
        || { echo "FROM-Zeile in Dockerfile.php-fpm nicht gesetzt" >&2; exit 1; }
    grep -q "^FROM mwaeckerlin/nextcloud:nginx-nc${version}$" Dockerfile.nginx \
        || { echo "FROM-Zeile in Dockerfile.nginx nicht gesetzt" >&2; exit 1; }

    date > rebuilt

    git add Dockerfile.php-fpm Dockerfile.nginx rebuilt
    if ! git diff --cached --quiet; then
        git commit -m "Build parlwin images for Nextcloud ${version}"
    fi
    git push -f origin "$branch"
done

git checkout "$BASE_BRANCH"
