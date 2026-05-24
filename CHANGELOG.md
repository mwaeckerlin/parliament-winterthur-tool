# Changelog

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
