---
name: sitzungstyp-feature
description: Laufende Arbeit «Neue Sitzung aus Vorlage» — Entscheid zum Aufbau, Stand und offene Punkte
metadata:
  type: project
---

## Ziel

Die Schaltfläche «+ Neue Sitzung» in `Sitzungsliste.vue` öffnet eine Liste der
Sitzungstypen, und danach öffnet sich die Terminmaske des Kalenders von Nextcloud mit
vorausgefüllten Feldern: Titel, Ort, Datum, Zeit von und bis, Beschreibung (Zweck und
Traktandenliste), Teilnehmer und Zielkalender.

## Feste Vorgaben

- Kein PUT über CalDAV und kein Schreiben in die Datenbank, bevor der Benutzer in der
  Maske gespeichert hat
- Kein eigener Dialog, sondern die eingebaute Terminmaske von Nextcloud
- Einladungen entstehen auf dem üblichen Weg von CalDAV, sobald der Termin in der Maske
  gespeichert wird
- Alles läuft automatisch, nichts von Hand

## Aufbau in drei Schichten

### 1. PHP: `SitzungstypService.php`

- `materialisiereTeilnehmer()` löst die Teilnehmerregeln auf zu
  `[{email, displayName, ncUid, gruppe:false}]`
- Alle Arten werden bis zu **einzelnen Personen** aufgelöst, auch `ncGruppe` und
  `eigeneFraktion`
- Die Gruppen von Nextcloud lassen sich in der Teilnehmersuche des Kalenders NICHT als
  `CUTYPE=GROUP` finden, deshalb immer auflösen
- Neue Route: `GET /apps/parlwin/sitzungstypen/{id}/vorschau`

### 2. `Sitzungsliste.vue`: Schaltfläche und Übergabe über `sessionStorage`

- `NcActions` «+ Neue Sitzung» öffnet die Datumsauswahl und ruft `erstelleNeueSession()`
- Adresse des Kalenders:
  `/apps/calendar/dayGridMonth/YYYY-MM-DD/new/popover/0/{unixStart}/{unixEnd}`
- **`dtStart` und `dtEnd` sind Unix-Zeit in SEKUNDEN** (im gebauten JavaScript des
  Kalenders nachgelesen: `new Date(1e3*t)`)
- `Math.floor(new Date('YYYY-MM-DDTHH:MM:00').getTime() / 1000)`
- Das kurze ISO-Format `YYYYMMDDTHHmmss` war FALSCH:
  `parseInt('20260526T100000') = 20260526` Sekunden ergibt August 1970

### 3. `calendar-prefill.js` (lädt auf jeder Seite)

- Liest `sessionStorage`, wartet mit einem `MutationObserver` auf das Eingabefeld für
  den Titel und ruft dann `tryPrefill()`

## Stand (2026-05-24) — was funktioniert

- **Titel** ✅ `input[placeholder="Titel"]`
- **Datum und Uhrzeit** ✅ Unix-Zeit in der Adresse
- **Ort** ✅ Der Kalender erzeugt in `.property-location` ein `<textarea>`, kein
  `<input>` → Selektor `.property-location textarea`
- **Beschreibung** ✅ `.property-description textarea` (ebenfalls ein `<textarea>` aus
  `PropertyText.vue`)
- **Hinweis zum Kalender** ✅ erscheint, wenn die Auswahl misslingt: «Kalender bitte
  manuell wählen: ‹Name›»
- **Hinweis zu den Teilnehmern** ✅ erscheint als Ausweichweg mit den E-Mail-Adressen

## Offen (läuft noch nicht automatisch)

### 1. Den Kalender automatisch auswählen

**Problem:** Die Schaltfläche der Kalenderauswahl wird nicht gefunden, und die Einträge
der Auswahlliste ändern sich nicht.

**Bisher erfolglos versucht:**

- `[class*="calendar-picker"]` — nichts gefunden
- `[role="option"], [role="menuitem"], .option` — Zeitüberschreitung
- `.edit-calendar-picker .vs__dropdown-toggle` — noch nicht gemessen (steht im Code, das
  Ergebnis fehlt)

**Was bekannt ist:**

- Der Pinia-Speicher `calendars` liefert die Kalender mit ihrem `displayName`:
  `document.querySelector('#content').__vue_app__.config.globalProperties.$pinia._s.get('calendars').calendars`
- Der Kalender zeigt die Auswahl als `NcSelect`, sie sollte also
  `.vs__dropdown-toggle` haben
- Zielkalender: URI `parlwin-fraktion-kalender`, den Anzeigenamen aus Pinia holen

**Nächster Versuch:** an der laufenden Instanz das DOM ansehen — welche Klassen hat die
Schaltfläche der Kalenderauswahl? In den Entwicklerwerkzeugen
`document.querySelector('.property-calendar, [class*="calendar-picker"]')` ausführen.

### 2. Die Teilnehmer automatisch eintragen

**Problem:** Die Teilnehmersuche des Kalenders
(`NcSelect.invitees-search__vselect` mit `input#uid`) findet die Benutzer, aber das
automatische Eintragen funktioniert noch nicht.

**Bisher erfolglos versucht:**

- Gruppen von Nextcloud als `CUTYPE=GROUP` — die Teilnehmersuche findet NUR Kontakte und
  Benutzer, KEINE Gruppen von Nextcloud
- Anzeigename im Hinweis — nutzlos, die Suche nach der Gruppenkennung ergab «Keine
  Ergebnisse»
- Gruppenkennung im Hinweis — nutzlos, aus demselben Grund

**Was bekannt ist:**

- Im gebauten JavaScript des Kalenders läuft die Teilnehmersuche über
  `POST /v1/autocompletion/attendee` mit `{search: query}`, erst 500 ms nach der letzten
  Eingabe, und liefert Kontakte und Benutzer
- Selektor des Eingabefelds: `.invitees-search__vselect input#uid` oder
  `.invitees-search__vselect input.vs__search`
- Der Kalender fügt den Teilnehmer über das Ereignis `@option:selected` des `NcSelect`
  hinzu
- Der bestehende `tryAddOneAttendee()` setzt den Wert des Eingabefelds, wartet auf
  `.vs__dropdown-option` und klickt den ersten Treffer — an der laufenden Instanz noch
  nicht gemessen

**Nächster Schritt:** messen, ob `setInputValue(input, email)` die Suche über `@search`
auslöst. Wenn nicht:
`input.dispatchEvent(new InputEvent('input', {data: email, bubbles: true}))` oder
`input._vei?.input?.({target: input})`.

## Geänderte Dateien

- `parlwin/appinfo/routes.php` — die Route `vorschau`
- `parlwin/lib/Service/SitzungstypService.php` — `materialisiereTeilnehmer()`,
  `vorschau()` und Hilfsmethoden
- `parlwin/lib/Controller/SitzungstypController.php` — die Methode `vorschau()`
- `parlwin/lib/AppInfo/Application.php` — `boot()` registriert `calendar-prefill` über
  `BeforeTemplateRenderedEvent` für jede Seite
- `parlwin/webpack.js` — `calendar-prefill` als eigener Einstiegspunkt
- `package.json` — `@nextcloud/dialogs: ^7.0.0`
- `parlwin/src/js/calendar-prefill.js` — Weg A, B und C
- `parlwin/src/js/components/Sitzungsliste.vue` — `NcActions`, Datumsauswahl und die
  Adresse mit Unix-Zeit
- `parlwin/src/js/components/Sitzungstypenliste.vue` — der Schalter
  `einladungVersenden` ist entfernt, weil `CalDavBackend` keine Einladungen nach iTIP
  verschickt

## Geprüfte Selektoren im DOM (an der laufenden Instanz gemessen)

| Feld | Selektor | Bemerkung |
|------|----------|-----------|
| Titel | `input[placeholder="Titel"]` | ✅ stabil |
| Ort | `.property-location textarea` | ✅ `PropertyText.vue` erzeugt ein `textarea` |
| Beschreibung | `.property-description textarea` | ✅ `PropertyText.vue` erzeugt ein `textarea` |
| Kalenderauswahl | `.edit-calendar-picker .vs__dropdown-toggle` | ❓ noch nicht gemessen |
| Einträge der Kalenderliste | `.vs__dropdown-option` | ❓ noch nicht gemessen |
| Eingabefeld für Teilnehmer | `.invitees-search__vselect input#uid` | ❓ noch nicht gemessen |
| Einhängepunkt von Pinia | `#content` | ✅ gemessen |
| Pinia-Speicher der Kalender | `pinia._s.get('calendars').calendars` | ✅ gemessen |
