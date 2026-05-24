# Changelog

## Unreleased

### Bugfix: Auto-Sync entfernte NC-Gruppe-Mitglieder ohne Admin-Einwilligung

`MitgliedService::aktualisiereNextcloudGruppe()` entfernte automatisch alle
NC-Gruppe-Mitglieder, die nicht mehr aktive Parlamentsmitglieder waren — ohne
Admin-Einwilligung. Dieser Block wurde entfernt. Entfernen aus der Gruppe
erfolgt jetzt ausschliesslich über «Ausgewählte abgleichen» im Admin-UI
(Requirement: nur selektierte Orphans werden deaktiviert/entfernt).

### Bugfix: Orphan-Zeile zeigte «aktiv» statt Gruppen in Spalte 4

In «Fraktionsmitglieder ↔ Nextcloud-User» zeigte die Gruppen-Spalte bei
verwaisten Usern «aktiv» statt der tatsächlichen NC-Gruppen. Ausserdem war
der Username (Spalte 3) fälschlicherweise durchgestrichen.

Fix: `findeVerwaisteGruppenmitglieder()` liefert jetzt `lokaleGruppen` pro
Orphan-Eintrag. Im Frontend: Username-Spalte ohne Strikethrough, Gruppen-Spalte
via `aktualisiereGruppenZelle()` wie bei normalen Mitgliedern.

### Dokumentation: README komplett nach Template A umstrukturiert

Neue Abschnitt-Reihenfolge: Purpose → Warum dieses Tool? → Funktionen →
Bedienung → Administration → Entwicklung → Internas → Lizenz.
Deployment-Anleitung verschoben von Abschnitt 2 nach Administration (Abschnitt 5).
Doppelter «Beschreibung»-Abschnitt entfernt (Inhalt in Purpose eingearbeitet).

### Bugfix: `bemerkungen`-Feld in `TraktandumController::update()` wiederhergestellt

Das `bemerkungen`-Feld wurde in einer früheren Session versehentlich aus `update()` entfernt.
Es wird wieder korrekt aus dem Request gelesen und gespeichert (neben `notizen`).

### Tests: TraktandumController

Neue Testklasse `parlwin/tests/Controller/TraktandumControllerTest.php` mit 3 Tests:
- `testUpdateSpeichertBemerkungen`
- `testUpdateSpeichertNotizenOhneBemerkungen`
- `testUpdateSpeichertBeidesGleichzeitig`

### Tests: Orphan-User-Verhalten in SettingsControllerTest

2 neue Tests für die «Fraktionsmitglieder ↔ Nextcloud-User»-Logik:
- `testFraktionMitgliederZeigtVerwaisteNCGruppenUserOhneParlamentseintrag`:
  Verwaiste NC-Gruppe-User (in Gruppe, aber kein Parlamentseintrag) erscheinen
  in `verwaiste` und werden im Frontend durchgestrichen dargestellt.
- `testProvisionVerarbeitetNurSelektierteOrphans`:
  Nur selektierte verwaiste User werden bei «Ausgewählte abgleichen» verarbeitet;
  nicht selektierte bleiben unberührt.

### Cleanup: createMock → createStub in allen betroffenen Testdateien

Mock-Objekte ohne `expects()`-Aufrufe konsequent auf `createStub` umgestellt
in: BackgroundJob/SyncJobTest, Command/SyncCancelCommandTest,
Command/SyncCommandTest, Service/FraktionsarbeitServiceTest,
Service/GeschaeftServiceTest, Service/MitgliedServiceTest,
Service/RealtimePublisherServiceTest, Service/ScraperHtmlSyncTest,
Service/ScraperServiceTest.
Eliminiert alle verbleibenden 49 PHPUnit-Notices («No expectations configured»);
Testergebnis jetzt: 104 Tests, 0 Notices.

### Cleanup: README bereinigt

Abschnitte «Zu Erledigen», «Erledigt» und «Bugs» aus dem README entfernt
(alles umgesetzt). Alle nicht mehr referenzierten `image*.png`-Dateien gelöscht.
Abschnitt «Fraktionsmitglieder ↔ Nextcloud-User» (verwaiste User) im README dokumentiert.

## Unreleased (seit Commit 28c7034)

### Auto-Save – keine Speichern-Knöpfe mehr

- **Notiz**: Kein «Notiz speichern»-Knopf. Speichert automatisch bei Fokusverlust (blur) und nach 5 Sekunden ohne Eingabe (Debounce). Während einer Eingabe-Session wird dieselbe Zeitleisten-Aktion aktualisiert (PUT statt POST), nicht eine neue angelegt. Feld wird erst nach blur geleert.
- **Beschluss**: Kein «Beschluss speichern»-Knopf und kein «Beschluss zurücknehmen»-Knopf. Speichert sofort nach Auswahl aus der Liste. Freitext-Textarea speichert auf blur + Debounce. Löschen der Auswahl = Beschluss zurücknehmen.

### Kein Flimmern / keine Popup-Probleme beim Speichern

Alle Speichermethoden rufen kein `ladeDetail()` mehr auf. Stattdessen chirurgische lokale State-Updates:

- `_aktionHinzufuegen` / `_aktionAktualisieren` / `_aktionEntfernen` – mutieren `geschaeft.aktionen` in-place
- `geschaeft.letzterBeschluss`, `geschaeft.zustaendigkeiten` werden gezielt aktualisiert
- Offene Dropdowns / Popups bleiben offen, andere Widgets bleiben unberührt

Realtime-Events von anderen Benutzenden ebenfalls gezielt:
- `geschaefte.action` → nur `_ladeAktionenNur()` (Timeline, kein beschlussWert-Touch)
- `geschaefte.updated` → `_ladeZustaendigkeitenUndAktionen()` (kein beschlussWert-Touch)
- `fraktionssitzung.updated` / `fraktion.roles.updated` → Full-Reload (seltene Konfigurationsänderungen)

### Zeitleiste: «Von → Nach» bei Zuständigkeitsänderungen

- PHP `FraktionsarbeitService::zustaendigkeitenSetzen()`: Audit-Text neu als `Von: X → Nach: Y`
- Vue: `e.text` wird zusätzlich zum `e.titel` in der Zeitleiste angezeigt (`.pw-timeline-detail`, kleiner, gedimmt)

### Responsive Traktanden-Karten in Sitzungsliste

- Unterhalb von 52 em Container-Breite wechselt die Traktandentabelle vollständig in ein Karten-Layout
- Container-Query `@container pw-sitzungen (max-inline-size: 52em)` – kein Spalten-Verstecken, sondern vollständiges Umformatieren
- Dokument-URL (`t.url`) auch im Karten-Layout als ↗-Link sichtbar

### Protokoll-Dokument-URL

- `ScraperService::extrahiereTraktandenAusHtml()`: HTML-Titel ab erstem `<br>` abschneiden; Dokument-Link (PDF) aus `<a href>` extrahieren, wenn kein Geschäft-Link vorhanden
- Neues Feld `pw_traktanden.url` (Migration `Version000012Date20260524190000`)
- `Traktandum.php`: Property `$url`, Getter/Setter, `jsonSerialize()`
- `SitzungService`: liest `traktandumUrl` aus Scraper-Daten, ruft `setUrl()` auf
- Tabellenansicht und Karten-Layout: ↗-Link zeigt direkt auf Dokument

### Beschluss-Migration (v000011)
i
- Bestehende Beschluss-Aktionen mit `titel` + `text` werden zu einem einzigen `text`-Feld zusammengeführt

### Popup schliesst sich nicht mehr bei Speichern (Kommissionsliste)

- `nachSpeichern()` in `Kommissionsliste.vue` ruft kein `schliesseDetail()` mehr auf

### Cursor-Position beim Notiz-Bearbeiten

- Klick auf Notiztext öffnet Textarea mit Cursor genau an der geklickten Textstelle (`caretPositionFromPoint` / `caretRangeFromPoint`)

### Beschluss-Freitext Auto-Reset

- Leerer Freitext-Textarea → automatisch zurück zur NcSelect-Liste

### Tests

- **Vitest-Infrastruktur** neu aufgesetzt (`vitest.config.js`, `src/js/tests/`, Mocks für `@nextcloud/*`)
- **31 JS-Tests** in `GeschaeftDetail.test.js` und `Kommissionsliste.test.js`
- **3 neue PHP-Tests** in `FraktionsarbeitServiceTest.php` (Von→Nach-Format für Zuständigkeiten)
- **PHPUnit 13 / PHP 8.5-Fixes**: `isType()` → `isArray()`/`isString()`/`isCallable()`; `setAccessible()` entfernt
- **Alle 99 PHP-Tests und 31 JS-Tests grün**

### Sonstiges

- `NotizenListe.vue`: Überarbeitungen
- `templates/admin.php`: Anpassungen
- `README.md`: Dokumentation aller neuen Features (v1.1.1-Einträge)
