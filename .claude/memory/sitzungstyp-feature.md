---
name: sitzungstyp-feature
description: Laufendes Feature «Neue Sitzung aus Vorlage» – Architekturentscheid, Status und offene TODOs
metadata:
  type: project
---

## Ziel

Button «+ Neue Sitzung» in `Sitzungsliste.vue` → Dropdown der Sitzungstypen → NC-Calendar-Editor öffnet sich mit vorausgefüllten Feldern:
- Titel, Ort, Datum, Von/Bis, Beschreibung (zweck + Traktanden-Liste), Teilnehmer, Ziel-Kalender

## Hard Constraints

- Kein CalDAV-PUT / DB-Insert vor manuellem Speichern im Editor
- Kein eigener Dialog – nativer NC-Calendar-Editor
- Einladungen via Standard-CalDAV-SCHEDULE (beim NC-Editor-Save)
- Alles automatisch – nichts manuell

## Architektur (drei Schichten)

### 1. PHP: `SitzungstypService.php`
- `materialisiereTeilnehmer()` löst Teilnehmer-Regeln auf zu `[{email, displayName, ncUid, gruppe:false}]`
- Alle Typen expandieren auf **Einzelpersonen** (inkl. ncGruppe + eigeneFraktion)
- NC-Systemgruppen können NICHT als CUTYPE=GROUP in NC Calendar Attendee-Suche gesucht werden → immer expandieren
- Neuer Endpoint: `GET /apps/parlwin/sitzungstypen/{id}/vorschau`

### 2. Sitzungsliste.vue: Button + sessionStorage-Bridge
- NcActions «+ Neue Sitzung» → Datum-Overlay → `erstelleNeueSession()`
- URL-Format NC Calendar: `/apps/calendar/dayGridMonth/YYYY-MM-DD/new/popover/0/{unixStart}/{unixEnd}`
- **dtStart/dtEnd = Unix-Timestamp in SEKUNDEN** (verifiziert im NC Calendar Bundle: `new Date(1e3*t)`)
- `Math.floor(new Date('YYYY-MM-DDTHH:MM:00').getTime() / 1000)`
- Compact ISO `YYYYMMDDTHHmmss` war FALSCH → `parseInt('20260526T100000') = 20260526` Sek. = Aug 1970!

### 3. calendar-prefill.js (lädt auf jeder Seite)
- Prüft sessionStorage → MutationObserver wartet auf Titel-Input → `tryPrefill()`

## Status (2026-05-24) — WAS FUNKTIONIERT

- **Titel** ✅ `input[placeholder="Titel"]`
- **Datum/Uhrzeit** ✅ Unix-Timestamps in URL
- **Ort** ✅ NC Calendar rendert `<textarea>` in `.property-location` (nicht `<input>`!) → Selector: `.property-location textarea`
- **Beschreibung** ✅ `.property-description textarea` (ebenfalls `<textarea>` via `PropertyText.vue`)
- **Kalender-Warnung** ✅ Popup bei Misserfolg: «Kalender bitte manuell wählen: ‹Name›»
- **Teilnehmer-Warnung** ✅ Popup als Fallback mit E-Mails

## TODO (noch nicht automatisch)

### 1. Kalender-Auswahl automatisch
**Problem**: Kalender-Picker-Button noch nicht gefunden / Dropdown-Optionen ändern sich nicht.
**Bisherige erfolglose Versuche**:
- `[class*="calendar-picker"]` → nichts gefunden
- `[role="option"], [role="menuitem"], .option` → Timeout
- `.edit-calendar-picker .vs__dropdown-toggle` → nicht getestet (noch im Code, Ergebnis ausstehend)
**Was bekannt ist**:
- Pinia `calendars`-Store funktioniert: `document.querySelector('#content').__vue_app__.config.globalProperties.$pinia._s.get('calendars').calendars` liefert Kalender mit `displayName`
- NC Calendar rendert Kalender-Picker als `NcSelect` → sollte `.vs__dropdown-toggle` haben
- Ziel-Kalender: URI `parlwin-fraktion-kalender`, Display-Name aus Pinia holen
**Nächster Versuch**: Im Live-System DOM inspizieren: Welche Klassen hat der Kalender-Picker-Button? `document.querySelector('.property-calendar, [class*="calendar-picker"]')` in Devtools ausführen.

### 2. Teilnehmer automatisch eintragen
**Problem**: NC Calendar Attendee-Suche `NcSelect.invitees-search__vselect` + `input#uid` findet Nutzer, aber auto-inject funktioniert noch nicht.
**Bisherige erfolglose Versuche**:
- NC-Systemgruppen als CUTYPE=GROUP → NC Calendar Attendee-Suche sucht NUR Kontakte/User, KEINE NC-Systemgruppen
- Display-Name in Popup → nutzlos (Suche nach GID ergab «Keine Ergebnisse»)
- GID in Popup → nutzlos (gleicher Grund)
**Was bekannt ist**:
- NC Calendar Bundle: attendee search = `POST /v1/autocompletion/attendee` mit `{search: query}` → debounced 500ms → liefert Kontakte/User
- Attendee-Input-Selector: `.invitees-search__vselect input#uid` oder `.invitees-search__vselect input.vs__search`
- `addAttendee` Event via `@option:selected` auf dem NcSelect
- Aktueller Code `tryAddOneAttendee()`: setzt Input-Value, wartet auf `.vs__dropdown-option`, klickt ersten Treffer — noch nicht im Live-System getestet
**Nächster Schritt**: Testen ob `setInputValue(input, email)` den `@search`-Handler triggert. Falls nicht: `input.dispatchEvent(new InputEvent('input', {data: email, bubbles: true}))` oder `input._vei?.input?.({target: input})`.

## Implementierte Dateien

- `parlwin/appinfo/routes.php` → vorschau-Route
- `parlwin/lib/Service/SitzungstypService.php` → `materialisiereTeilnehmer()` + `vorschau()` + Helfer
- `parlwin/lib/Controller/SitzungstypController.php` → `vorschau()` Action
- `parlwin/lib/AppInfo/Application.php` → `boot()` registriert `calendar-prefill` global via `BeforeTemplateRenderedEvent`
- `parlwin/webpack.js` → `calendar-prefill` Entry
- `package.json` → `@nextcloud/dialogs: ^7.0.0`
- `parlwin/src/js/calendar-prefill.js` → Layer A + B + C
- `parlwin/src/js/components/Sitzungsliste.vue` → NcActions-Button + Datum-Overlay + Unix-Timestamp-URL
- `parlwin/src/js/components/Sitzungstypenliste.vue` → `einladungVersenden`-Toggle entfernt (CalDavBackend sendet keine iTIP-Einladungen)

## Kalibrierte DOM-Selektoren (verifiziert im Live-System)

| Feld | Selector | Bemerkung |
|------|----------|-----------|
| Titel | `input[placeholder="Titel"]` | ✅ stabil |
| Ort | `.property-location textarea` | ✅ PropertyText.vue rendert textarea |
| Beschreibung | `.property-description textarea` | ✅ PropertyText.vue rendert textarea |
| Kalender-Picker | `.edit-calendar-picker .vs__dropdown-toggle` | ❓ noch nicht verifiziert |
| Kalender-Optionen | `.vs__dropdown-option` | ❓ noch nicht verifiziert |
| Attendee-Input | `.invitees-search__vselect input#uid` | ❓ noch nicht verifiziert |
| Pinia-Mount | `#content` | ✅ verifiziert |
| Pinia-Store Kalender | `pinia._s.get('calendars').calendars` | ✅ verifiziert |
