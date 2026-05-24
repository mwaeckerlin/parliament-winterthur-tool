---
name: nc-calendar-integration
description: Erkenntnisse zur NC Calendar Integration – was geht, was nicht, warum
metadata:
  type: project
---

## Offizielle NC-Calendar-API: Keine

Kein `window.OCA.Calendar`, kein Event-Bus, keine URL-Parameter für Titel/Ort/Beschreibung/Teilnehmer/Kalender.
URL-Schema `/apps/calendar/dayGridMonth/{datum}/new/popover/0/{dtStart}/{dtEnd}` → nur Datum/Zeit setzbar.

## DOM-Prefill (Layer A) – stabil für Text-Felder

**Funktioniert für:** `NcTextField` (title, location) und `NcRichContenteditable` (description)

**Korrekte Strategie:**
- Script muss auf der Calendar-Seite laufen, nicht auf der parlwin-Seite. `OCP\Util::addScript` registriert nur für die aktuelle Seite. Beim Navigieren via `window.location` = neue HTTP-Anfrage.
- `MutationObserver` auf `document.body` (Teleport rendert direkt in body, nicht in parent-Container)
- NcTextField: `Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, val); el.dispatchEvent(new Event('input', {bubbles:true}))` – Vue 3 v-model hört auf native `input`-Events
- NcRichContenteditable: `el.focus(); document.execCommand('selectAll'); document.execCommand('insertText', false, text)`

**Warum vorheriger Versuch scheiterte:**
1. Selektoren-Scope falsch (Teleport schiebt aus parent raus)
2. Script lief auf parlwin-Seite, nicht auf Calendar-Seite
3. Keine console.debug-Logs erschienen = Script hat nicht ausgeführt

**Funktioniert NICHT für:** Attendees (async autocomplete), Kalender-Selektor (NcSelect)

## Pinia-Store-Injektion (Layer B) – fragil, einzige Option für Attendees/Kalender

**Zugriff:** `document.querySelector('#app-content').__vue_app__.config.globalProperties.$pinia`
- `__vue_app__` wird von Vue 3 auch in Production-Builds gesetzt
- Store-IDs: müssen einmal im Live-System via Browser-DevTools ermittelt werden
  (`pinia._s.forEach((v,k) => console.log(k))`)
- `pinia._s` ist private Pinia-API (nicht öffentlich dokumentiert)

**Bruchstellen:**
1. `pinia._s` – private API, kann in Pinia v3 ändern
2. Store-IDs (Magic Strings) – können bei NC-Calendar-Refactoring ändern
3. Draft-Event-Datenstruktur (Verschachtelung `vevent.*`) – kann ändern
4. Vue-Reaktivität: muss in-place mutiert werden, nicht ersetzt

**Kein stabiler Fallback für Attendees:** Wenn Layer B versagt, gibt es keine automatische Alternative – nur "Liste anzeigen, User tippt manuell" (inakzeptabel als primäre Lösung).

## iTIP / CalDAV-SCHEDULE

`KalenderService::erstelleOderAktualisiere()` nutzt `CalDavBackend::createCalendarObject()` **direkt** → bypassed Sabre-Stack → **keine iTIP-Einladungen**.

Für Einladungen via Code wäre HTTP PUT auf `/remote.php/dav/calendars/{user}/...` nötig (= voller Sabre-Stack). Erfordert App-Token für den Kalender-User.

**Deshalb:** Einladungen sollen über den NC-Calendar-Editor-Save laufen (Standard-Verhalten, keine eigene Implementierung nötig).
