# Parliament Winterthur Tool

Nextcloud-Plugin für die Fraktionsarbeit im Winterthurer Parlament.

## Beschreibung

Dieses Plugin synchronisiert täglich die öffentlich zugänglichen Daten des
[Parlaments Winterthur](https://parlament.winterthur.ch/) in eine Nextcloud-Datenbank
und stellt diese der konfigurierten Fraktion als strukturierte Arbeitsoberfläche zur Verfügung.

---

## Anforderungen & Features

### Datensynchronisation (Cron-Job)

- Ein täglicher Cron-Job lädt alle relevanten Daten von der Parlamentswebseite
  herunter und speichert sie in der Nextcloud-Datenbank.
- Es werden **keine Einträge gelöscht**. Elemente, die auf der Webseite verschwinden,
  werden als `gelöscht` markiert (Spalte `geloescht = true`), bleiben aber in der
  Datenbank erhalten.
- Die Daten werden aus den HTML-Attributen `data-entities="..."` der jeweiligen
  Seiten extrahiert (JSON-Format).

### Datenquellen

| Datenquelle      | URL                                                  |
|------------------|------------------------------------------------------|
| Geschäfte        | https://parlament.winterthur.ch/politbusiness        |
| Sitzungen        | https://parlament.winterthur.ch/sitzung              |
| Mitglieder       | https://parlament.winterthur.ch/stadtparlament/27428 |
| Kommissionen     | https://parlament.winterthur.ch/kommissionen         |
| Fraktionen       | https://parlament.winterthur.ch/fraktionen           |
| Parteien         | https://parlament.winterthur.ch/fraktionen           |

### Datenstrukturen

#### Geschäfte (Politische Geschäfte)

Felder aus der Parlamentswebseite:
- `extern_id` – ID auf der Parlamentswebseite
- `titel` – Bezeichnung des Geschäfts
- `nummer` – Geschäftsnummer
- `typ` – Art des Geschäfts
- `status` – aktueller Stand
- `datum` – Eingangsdatum
- `url` – direkter Link auf der Webseite
- `roh_daten` – alle `data-entities`-Felder als JSON

Fraktionsinterne Zusatzfelder (nur intern, nicht öffentlich):
- `bemerkungen` – freie Bemerkungen der Fraktion
- `zustaendige_person` – zuständiges Fraktionsmitglied
- `antrag_fraktion` – Antrag, den die Fraktion stellen will
- `entscheid_fraktion` – Entscheid der Fraktion
- `notizen` – weitere interne Notizen

#### Sitzungen

Felder aus der Parlamentswebseite:
- `extern_id`, `titel`, `datum`, `zeit_von`, `zeit_bis`, `ort`, `url`

Für jede Sitzung werden automatisch **Kalendereinträge** in Nextcloud Calendar erstellt.

#### Traktanden

Jedem Traktandum einer Sitzung können folgende Zusatzfelder bearbeitet werden:
- `bemerkungen` – Bemerkungen zum Traktandum
- `notizen` – beliebig viele Notizen (als JSON-Array gespeichert)

Traktanden sind in der Regel Geschäfte aus der Geschäftsliste und werden
entsprechend verknüpft (`geschaeft_id`).

#### Mitglieder

- 60 aktive Mitglieder + ehemalige
- Felder: Name, Vorname, Partei, Fraktion, E-Mail, Foto-URL, aktiv

#### Kommissionen & Fraktionen

- Name, Beschreibung, Mitgliederliste (extern_id)

### Konfiguration

In den Plugin-Einstellungen kann folgendes konfiguriert werden:

- **Fraktion**: Für welche Fraktion ist das Tool konfiguriert?
- **Nextcloud-Gruppe**: Automatisches Erstellen und Synchronisieren einer
  Nextcloud-Gruppe für die Fraktionsmitglieder (Einladung per E-Mail)
- **Cron-Intervall**: Wie oft sollen die Daten synchronisiert werden?

### Fraktionsarbeit – Vorschläge

Folgende Features unterstützen eine Fraktion bei der Vorbereitung und Bearbeitung
von Parlamentsgeschäften:

1. **Geschäftsübersicht**: Tabellarische Darstellung aller Geschäfte mit
   Filtermöglichkeiten nach Status, Typ, Datum und Zuständigkeit.
2. **Sitzungsvorbereitung**: Für jede Sitzung werden die Traktanden angezeigt.
   Pro Traktandum können Bemerkungen und Notizen erfasst werden.
3. **Zuständigkeiten**: Jedem Geschäft kann ein Fraktionsmitglied als zuständige
   Person zugewiesen werden.
4. **Fraktionsentscheide**: Für jedes Geschäft kann ein Antrag und ein
   Fraktionsentscheid festgehalten werden.
5. **Kalenderintegration**: Alle Sitzungen werden als Nextcloud-Kalendereinträge
   gespeichert.
6. **Mitgliederverwaltung**: Automatische Synchronisation der Fraktionsmitglieder
   als Nextcloud-Gruppe mit E-Mail-Einladung.
7. **Benachrichtigungen**: Optional können Mitglieder über neue Geschäfte oder
   bevorstehende Sitzungen benachrichtigt werden.

### Datenbankmodell

```
geschaefte           sitzungen            traktanden
─────────────────    ─────────────────    ─────────────────────
id                   id                   id
extern_id            extern_id            sitzung_id → sitzungen
titel                titel                geschaeft_id → geschaefte
nummer               datum                nummer
typ                  zeit_von             titel
status               zeit_bis             beschreibung
datum                ort                  bemerkungen
url                  url                  notizen (JSON)
roh_daten (JSON)     geloescht            geloescht
bemerkungen          erstellt_am          erstellt_am
zustaendige_person   aktualisiert_am      aktualisiert_am
antrag_fraktion
entscheid_fraktion
notizen
geloescht
erstellt_am
aktualisiert_am

mitglieder           kommissionen         fraktionen
─────────────────    ─────────────────    ─────────────────
id                   id                   id
extern_id            extern_id            extern_id
name                 name                 name
vorname              beschreibung         beschreibung
partei               mitglieder (JSON)    mitglieder (JSON)
fraktion             geloescht            geloescht
email                erstellt_am          erstellt_am
foto_url             aktualisiert_am      aktualisiert_am
aktiv
geloescht
erstellt_am
aktualisiert_am
```

---

## Installation

### Voraussetzungen

- Nextcloud ≥ 25
- PHP ≥ 8.0
- Composer
- Node.js ≥ 16 & npm

### Installation im Nextcloud-Apps-Verzeichnis

```bash
cd /path/to/nextcloud/apps
cp -r parliamentwinterthur/ .
cd parliamentwinterthur
composer install --no-dev
npm ci
npm run build
```

In der Nextcloud-Administrationsoberfläche unter **Apps** das Plugin
**Parliament Winterthur Tool** aktivieren.

### Konfiguration

Nach der Aktivierung unter **Einstellungen → Parliament Winterthur** die
gewünschte Fraktion und Nextcloud-Gruppe konfigurieren.

### Manuelle Synchronisation

```bash
php /path/to/nextcloud/occ parliamentwinterthur:sync
```

### Automatischer Cron-Job (via Nextcloud-Cron)

Das Plugin registriert automatisch einen täglichen Hintergrund-Job in Nextcloud.
Voraussetzung ist, dass der Nextcloud-Cron korrekt konfiguriert ist:

```
*/5 * * * * php /path/to/nextcloud/cron.php
```

---

## Entwicklung

```bash
cd parliamentwinterthur
composer install
npm install
npm run dev   # Frontend im Watch-Modus
```

### Tests ausführen

```bash
composer test
```

---

## Lizenz

AGPL-3.0-or-later
