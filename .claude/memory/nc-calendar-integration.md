---
name: nc-calendar-integration
description: Anbindung an den Kalender von Nextcloud — was geht, was nicht, warum
metadata:
  type: project
---

## Eine offizielle Schnittstelle gibt es nicht

Es gibt kein `window.OCA.Calendar`, keinen Kanal für Ereignisse und keine Parameter in
der URL für Titel, Ort, Beschreibung, Teilnehmer oder Kalender. Über die Adresse
`/apps/calendar/dayGridMonth/{datum}/new/popover/0/{dtStart}/{dtEnd}` lassen sich nur
Datum und Zeit setzen.

## Weg A: die Felder im DOM vorbelegen — stabil für Textfelder

**Funktioniert für:** `NcTextField` (Titel, Ort) und `NcRichContenteditable` (Beschreibung)

**So geht es:**

- Das Skript muss auf der Kalenderseite laufen, nicht auf der Seite von parlwin.
  `OCP\Util::addScript` registriert es nur für die aktuelle Seite, und der Wechsel über
  `window.location` ist eine neue HTTP-Anfrage.
- `MutationObserver` auf `document.body` ansetzen: Teleport hängt den Inhalt direkt an
  `body`, nicht in das übergeordnete Element.
- `NcTextField`:
  `Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, val); el.dispatchEvent(new Event('input', {bubbles:true}))`
  — `v-model` von Vue 3 hört auf das native Ereignis `input`.
- `NcRichContenteditable`:
  `el.focus(); document.execCommand('selectAll'); document.execCommand('insertText', false, text)`

**Warum der erste Versuch scheiterte:**

1. Die Selektoren suchten im falschen Element (Teleport hängt den Inhalt nach aussen).
2. Das Skript lief auf der Seite von parlwin statt auf der Kalenderseite.
3. Es erschien keine Ausgabe von `console.debug`, also lief das Skript gar nicht.

**Funktioniert NICHT für:** die Teilnehmer (die Vorschläge kommen asynchron nach) und
die Kalenderauswahl (`NcSelect`).

## Weg B: in den Pinia-Speicher schreiben — anfällig, aber die einzige Möglichkeit für Teilnehmer und Kalender

**Zugriff:** `document.querySelector('#app-content').__vue_app__.config.globalProperties.$pinia`

- `__vue_app__` setzt Vue 3 auch in der gebauten Fassung für den Betrieb.
- Die Namen der Speicher müssen einmal in der laufenden Instanz mit den
  Entwicklerwerkzeugen des Browsers ermittelt werden
  (`pinia._s.forEach((v,k) => console.log(k))`).
- `pinia._s` gehört nicht zum öffentlich dokumentierten Teil von Pinia.

**Was daran brechen kann:**

1. `pinia._s` ist nicht öffentlich und kann sich in Pinia 3 ändern.
2. Die Namen der Speicher stehen als feste Zeichenketten im Code und können sich bei
   einem Umbau des Kalenders ändern.
3. Die Datenstruktur des noch nicht gespeicherten Termins (verschachtelt unter
   `vevent.*`) kann sich ändern.
4. Vue verfolgt Änderungen nur an Ort und Stelle: der Wert muss geändert, nicht ersetzt
   werden.

**Für die Teilnehmer gibt es keinen stabilen Ausweichweg:** versagt Weg B, bleibt nur
«Liste anzeigen, der Benutzer tippt von Hand» — als Hauptweg nicht brauchbar.

## iTIP und CalDAV

`KalenderService::erstelleOderAktualisiere()` ruft `CalDavBackend::createCalendarObject()`
**direkt** auf und umgeht damit Sabre, also verschickt parlwin keine Einladungen nach
iTIP.

Einladungen aus dem Code heraus bräuchten ein HTTP PUT auf
`/remote.php/dav/calendars/{user}/…`, also den vollen Weg über Sabre, und dafür ein
App-Passwort des Kalenderbenutzers.

**Deshalb:** Einladungen entstehen, wenn ein Termin im Kalender von Nextcloud
gespeichert wird — so verhält sich Nextcloud von Haus aus, und eigener Code ist dafür
nicht nötig.
