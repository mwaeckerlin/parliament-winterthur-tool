# Edge-Proxy mit eingebackener Routing-Konfiguration.
#
# Die Konfiguration wird ins Abbild KOPIERT und nicht vom Host eingeblendet: ein
# Bind-Mount koppelt den Container an ein Verzeichnis des Wirts und macht die
# Auslieferung von dessen Zustand abhängig. Der Docker-Socket wird ebenfalls
# nicht eingeblendet — deshalb bleibt der Docker-Provider abgeschaltet
# (--providers.docker=false) und das Routing kommt aus dieser Datei.
#
# Wird das Routing geändert, muss das Abbild neu gebaut werden:
#   $ docker compose up -d --build traefik
FROM traefik

COPY dynamic.yml /etc/traefik/dynamic.yml
