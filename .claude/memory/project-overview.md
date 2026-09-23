---
name: project-overview
description: Technik, wichtigste Dateien und Aufbau von parlwin
metadata:
  type: project
---

## Technik

- Nextcloud 33.0.3, PHP 8, AppFramework
- Vue 3 mit @nextcloud/vue 9.8.0 (Options API, nicht Composition API)
- Pinia wird in parlwin NICHT verwendet, nur im Kalender von Nextcloud
- Gebaut wird mit Webpack über `npm start` oder `npm run build:app`
- Quellen: `parlwin/src/js/`, erzeugt wird `parlwin/js/parlwin-main.js` (nie von Hand ändern)

## Wichtigste Dateien

| Datei | Zweck |
|-------|-------|
| `parlwin/src/js/App.vue` | oberste Komponente, Navigation, lädt Mitglieder, Fraktionen und Kommissionen |
| `parlwin/src/js/components/Sitzungsliste.vue` | Sitzungsliste mit Traktanden und Notizen |
| `parlwin/src/js/components/Sitzungstypenliste.vue` | Sitzungsvorlagen anlegen, ändern und löschen |
| `parlwin/lib/Service/SitzungstypService.php` | Vorlagen; **fehlt noch**: `materialisiereTeilnehmer()` |
| `parlwin/lib/Service/KalenderService.php` | nutzt `CalDavBackend` direkt und umgeht damit Sabre |
| `parlwin/lib/Controller/SitzungstypController.php` | Schnittstelle für Sitzungstypen |
| `parlwin/appinfo/routes.php` | alle Routen der Schnittstelle |

## Datenmodell (Sitzungstyp)

- `name`, `zweck`, `standardOrt`, `standardZeitVon`, `standardZeitBis`, `einladungVersenden`
- `traktanden`: [{titel, beschreibung, position}]
- `teilnehmer`: [{art, referenzId, referenzName}] — Regeln, noch nicht aufgelöst
  - Arten: `mitglied`, `fraktion`, `kommission`, `rolle`, `eigeneFraktion`, `ncGruppe`, `ncUser`
- Zielkalender: `parlwin-fraktion-kalender` (URI), der zugehörige Benutzer steht in den
  Einstellungen der App unter `kalender_nutzer`

## Sprache der Dokumentation

CHANGELOG, README und alle weiteren Dokumente dieses Projekts sind **deutsch**
(Schweizer Rechtschreibung), ebenso die Kommentare im Code und die Namen der Tests.
Die Bezeichner im Code sind englisch, ausser den Begriffen des Parlaments
(Traktandum, Vorstoss, Geschäft, Fraktion). Commit-Meldungen sind englisch.

## Wichtige Einschränkung

`KalenderService` spricht `CalDavBackend` direkt an, also läuft kein Versand nach
iTIP: parlwin verschickt selbst keine Einladungen. Einladungen entstehen NUR, wenn
ein Termin im Kalender von Nextcloud gespeichert wird — so verhält sich Nextcloud
von Haus aus, und dabei soll es bleiben.
