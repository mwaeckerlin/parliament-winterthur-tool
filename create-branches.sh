#!/bin/bash
set -euo pipefail

# Erzeugt je Nextcloud-Hauptversion einen Zweig «nc<version>» aus «master» und
# setzt darin die FROM-Zeilen auf die Marken des Basis-Abbilds. Die Zweige
# tragen keine eigene Geschichte: Jeder Lauf erzeugt sie aus dem aktuellen Stand
# von master neu und schiebt sie mit «push -f» nach GitHub, worauf Docker Hub
# die Marken «…-nc<version>» baut. Ein Release einer Linie entsteht über einen
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

BASE_BRANCH="${BASE_BRANCH:-master}"
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

git fetch origin
git checkout "$BASE_BRANCH"
git pull --ff-only origin "$BASE_BRANCH"

for version in "${VERSIONS[@]}"; do
    branch="nc$version"

    git checkout -B "$branch" "origin/$BASE_BRANCH"

    sed -Ei "s|^FROM mwaeckerlin/nextcloud:php-fpm.*$|FROM mwaeckerlin/nextcloud:php-fpm-${version}|" Dockerfile.php-fpm
    sed -Ei "s|^FROM mwaeckerlin/nextcloud:nginx.*$|FROM mwaeckerlin/nextcloud:nginx-${version}|" Dockerfile.nginx

    # Geprüft wird, dass die Zeile wirklich steht: Ändert sich die Schreibweise
    # des FROM, ersetzt sed nichts, der Zweig wäre eine Kopie von master, und
    # das Abbild trüge ein anderes Nextcloud, als seine Marke verspricht. Genau
    # daran stand die Instanz am 23.09.2026 still.
    grep -q "^FROM mwaeckerlin/nextcloud:php-fpm-${version}$" Dockerfile.php-fpm \
        || { echo "FROM-Zeile in Dockerfile.php-fpm nicht gesetzt" >&2; exit 1; }
    grep -q "^FROM mwaeckerlin/nextcloud:nginx-${version}$" Dockerfile.nginx \
        || { echo "FROM-Zeile in Dockerfile.nginx nicht gesetzt" >&2; exit 1; }

    date > rebuilt

    git add Dockerfile.php-fpm Dockerfile.nginx rebuilt
    if ! git diff --cached --quiet; then
        git commit -m "Build parlwin images for Nextcloud ${version}"
    fi
    git push -f origin "$branch"
done

git checkout "$BASE_BRANCH"
