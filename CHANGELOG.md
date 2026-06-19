# Changelog

- 2026-06-19 **1.6.1**
    - Weitere Anpassungen an Nextcloud 34 (Fortsetzung von 1.6.0):
        - Geschäfts- und Sitzungslisten laden wieder vollständig (Nextcloud 34 wies grosse Listenabfragen mit «Interner Serverfehler» ab; betraf auch die Admin-Seite und die Status-Kürzel)
        - Auch die Admin-Seite öffnet wieder fehlerfrei (gleiche in Nextcloud 34 entfernte interne Schnittstelle wie bei der Startseite)
        - Seitenleiste sieht wieder genau wie die übrigen Nextcloud-Apps (z.B. Dateien) aus: durchgängig Nextclouds Standard-Aufbau und -Symbole übernommen (statt eigener Darstellung mit Emoji), inklusive korrektem Hintergrund und Layout nach dem Update

- 2026-06-19 **1.6.0**
    - Kompatibilität mit Nextcloud 34 wiederhergestellt: Nextcloud wurde kurz nach dem letzten Release von Version 33 auf 34 angehoben. Dieser Versionssprung brachte mehrere Änderungen, mit denen das bisherige Tool nicht mehr zusammenpasste – es wurde unter Nextcloud 34 sogar automatisch abgeschaltet. Die folgenden Anpassungen stellen die Kompatibilität wieder her (fortgesetzt in 1.6.1):
        - Die App bleibt nach Nextcloud-Updates aktiv: die obere Nextcloud-Versionsgrenze wurde aufgehoben, sodass ein Nextcloud-Upgrade die App nicht mehr automatisch deaktiviert
        - Lauffähig unter Nextcloud 34: die Startseite lädt wieder (eine in Nextcloud 34 entfernte interne Schnittstelle wird nicht mehr verwendet; vorher «Interner Serverfehler»)
    - Die Uhrzeiten der automatischen Synchronisation sind konfigurierbar (Standard 03:00 und 15:00 Uhr)
    - Bugfix: Automatische Synchronisation lief nicht

- 2026-06-18 **1.5.3**
    - Hatte ein Mitglied selbst schon einen «Fraktion»-Ordner mit der Gruppe geteilt, wird dieser jetzt sauber in den offiziellen Ordner überführt: der bisherige Ordner bleibt beim Eigentümer als «Fraktion.bak» erhalten, sein Inhalt (auch eigene Unterordner und Dateien) wird in den offiziellen Ordner übernommen, und alle anderen Mitglieder sehen nur noch den offiziellen Ordner
    - Beim Zusammenführen gehen keine Dateien verloren: bei gleichem Namen wird die übernommene Datei als «name.migrated» abgelegt
    - Der offizielle Fraktionsordner erscheint bei allen Mitgliedern zuverlässig unter «Fraktion» (nicht mehr versehentlich als «Fraktion (2)»)

- 2026-06-18 **1.5.2**
    - Der geteilte Fraktionsordner erscheint jetzt zuverlässig bei allen Mitgliedern – auch wenn die Freigabe zuvor nicht automatisch angenommen wurde oder ein Mitglied erst später dazukam (die Freigabe wird beim Öffnen und bei jeder Gruppenänderung für alle Mitglieder bestätigt)

- 2026-06-17 **1.5.1**
    - Status-Kürzel-Verwaltung: Suchtext-Feld nutzt jetzt die volle verfügbare Breite, das Kürzel-Feld behält eine passende Breite und der Löschen-Knopf beansprucht nur den nötigen Platz

- 2026-06-17 **1.5.0**
    - Geschäfteliste zeigt wieder zuverlässig alle Geschäfte: eine einzelne unvollständige Datenzeile (fehlendes Quell-Datum) blendete bisher die gesamte Liste aus («Keine Geschäfte gefunden»)
    - Synchronisation bricht nicht mehr ab, wenn Miteinreicher importiert werden
    - Namen von Einreichenden werden korrekt dargestellt (keine «&#39;»-Zeichen mehr bei Apostrophen)
    - Geschäfte lassen sich serverseitig nach Status filtern
    - Geteilter Fraktionsordner erscheint jetzt zuverlässig bei allen Mitgliedern und bleibt erhalten (wurde vorher teils wieder entfernt)
    - Geteilter Fraktionskalender erscheint und ist bearbeitbar bei allen Mitgliedern
    - Fraktionsordner und Kalender werden automatisch beim Öffnen und bei Wechsel der Fraktionsgruppe geprüft und ergänzt – der manuelle Knopf und die Einstellung «Kalender-Benutzer» entfallen (es wird immer das Admin-Konto verwendet)
    - Eigene Geschäfte lassen sich wieder anlegen (Speicherfehler behoben)
    - Status-Kürzel bleiben gespeichert, werden automatisch gespeichert und in der Verwaltung korrekt angezeigt (vorher «[object Object]» bzw. in Firefox eine leere Liste durch zwei sich überschreibende Implementierungen)
    - Beschluss- und Notizänderungen erscheinen bei allen Mitgliedern sofort ohne Neuladen
    - Zusammenarbeit mehrerer Mitglieder durchgehend per Mehrnutzer-Test abgesichert (Ordner, Kalender, Dokumente, Echtzeit)

- 2026-06-15 **1.4.1**
    - Fraktionsordner und Kalender funktionieren jetzt korrekt mit Gruppenmitgliedern
        - Admin-Account erstellt Infrastruktur, Gruppe teilt korrekt
        - Alle Member sehen Ordner und Kalender
        - Dateien und Termine für alle Mitglieder lesbar und bearbeitbar

- 2026-06-14 **1.3.4**
    - Status-Kürzel in Admin-Einstellungen
        - Textersetzungen für lange Statusbeschriftungen (z.B. «BSKK»)
        - Auto-Save nach 5s oder beim Feldverlust
    - Dokumente hochladen: bestehende Dateien direkt hochladen
    - Eigene Geschäfte erstellen: Geschäfte ausserhalb Parlamentsregister
    - Fraktions-Infrastruktur automatisch beim Start prüfen

- 2026-05-29 **1.3.3**
    - Miteinreicher aus Parlamentswebseite einlesen
        - Alle Einreichenden mit Rolle in Detailansicht
        - Namen als Zusatzzeile in Geschäftsliste
    - Bugfix: Freier Text im Fraktionsentscheid korrekt anzeigen
    - Geschäftslisten: Nr., Datum, Typ untereinander in Nr.-Spalte
    - Sitzungsliste: Status-Spalte entfernt
    - Aktionszeitleiste: Uhrzeit unter Datum

- 2026-05-27 **1.3.2**
    - Versionsnummer in Navigationsleiste (aus info.xml)
    - Synchronisation: 2x täglich (03:00 + 15:00 Uhr)
    - Beschluss-Widget überall identisch
        - Kartensicht, Tabellenansicht, Detailansicht gleich
        - Freitext-Beschlüsse korrekt gespeichert

- 2026-05-27 **1.3.1**
    - Notizen Auto-Save in Sitzungsliste
        - Bei Fokusverlust oder nach 5s Pause
        - «+»-Knopf entfernt
    - Gemeinsame Hilfsfunktionen (vollerName, personKey, parseNotizen)
        - Zentrale utils.js
        - Copy-Paste beseitigt

- 2026-05-27 **1.3.0**
    - Ungültige HTML-Struktur in Geschäfts-Detail behoben
    - Fehler beim Laden der Kommissionsliste behoben
    - Teststabilität verbessert (console.error → Testfehler)
    - PHPUnit-Notices in Live-Scraper-Tests behoben

- 2026-05-27 **1.2.9**
    - WebSocket-Verbindung funktioniert wieder
        - Nginx-Pfad korrekt konfiguriert
        - Container-Servicename angepasst
    - Nextcloud bleibt nicht im Wartungsmodus hängen
    - Beschlusstext speichert beim Feldverlust, nicht sofort

- 2026-05-26 **1.2.8**
    - Synchronisations-Zuverlässigkeit verbessert
        - Fehler während Sync führen keine falschen Löschungen
    - Traktanden einzeln abgeglichen statt neu angelegt
        - Bestehende Notizen bleiben
    - Gelöschte Objekte automatisch bei nächster Sync wiederhergestellt
    - Migrationen beim Start zuverlässig ausgeführt
    - Notizen/Beschlüsse mit Maus verschiebbar (nur über ⠿-Symbol)
    - Beschluss-Widget überall gleich, Freitext speichert automatisch
    - Formular-Beschriftungen einheitlich

- 2026-05-24 **1.2.7**
    - Interne Fraktionssitzungen in Liste
        - Mit Titel, Zweck, Traktanden, Notizen
    - Erstellungsformular im NC-Kalender-Stil
    - Interne Sitzungen gekennzeichnet
    - Parlamentssync löscht interne Sitzungen nicht
    - «Fraktionsmitglieder ↔ NC-User»: auch inaktive Personen anzeigen

- 2026-05-22 **1.2.6**
    - Fehler beim Erstellen Sitzung aus Vorlage behoben

- 2026-05-21 **1.2.5**
    - Sitzungstyp-Formular vollständig funktionsfähig
    - Notizen pro Sitzung (Protokollführung)
    - Kommissionen per Suchfeld findbar
    - Dokumente direkt aus Tool erstellen

- 2026-05-24 **1.2.4**
    - Notizen und Beschlüsse speichern automatisch
        - Kein «Speichern»-Knopf mehr
        - Bei Fokusverlust oder nach 5s Pause
    - Beim Speichern flimmert nichts
    - Offene Popups bleiben offen bei gleichzeitiger Arbeit
    - Zeitleiste zeigt «Von: X → Nach: Y»
    - Traktanden-Tabelle: Karten-Layout auf schmalen Bildschirmen
    - Dokument-Links (PDF) direkt beim Traktandum

---

### Ältere Versionen

Siehe Git-History für Details zu Versionen vor 1.2.4.
