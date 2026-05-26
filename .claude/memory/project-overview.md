---
name: project-overview
description: Tech-Stack, wichtigste Dateien und Architektur-Überblick von parlwin
metadata:
  type: project
---

## Tech-Stack

- Nextcloud 33.0.3, PHP 8 AppFramework
- Vue 3 + @nextcloud/vue 9.8.0 (Options API, kein Composition API)
- Pinia ist NICHT in parlwin selbst – nur in NC Calendar
- Build: Webpack via `npm start` / `npm run build:app`
- Source: `parlwin/src/js/` → Build-Output: `parlwin/js/parlwin-main.js` (nie direkt editieren)

## Wichtigste Dateien

| Datei | Zweck |
|-------|-------|
| `parlwin/src/js/App.vue` | Root-Komponente, Navigation, lädt Mitglieder/Fraktionen/Kommissionen |
| `parlwin/src/js/components/Sitzungsliste.vue` | Sitzungsliste mit Traktanden, Notizen |
| `parlwin/src/js/components/Sitzungstypenliste.vue` | CRUD für Sitzungs-Vorlagen |
| `parlwin/lib/Service/SitzungstypService.php` | Vorlage-Logik; **fehlt noch**: `materialisiereTeilnehmer()` |
| `parlwin/lib/Service/KalenderService.php` | CalDAV-Integration via `CalDavBackend` direkt (bypassed Sabre-Stack) |
| `parlwin/lib/Controller/SitzungstypController.php` | REST-API für Sitzungstypen |
| `parlwin/appinfo/routes.php` | Alle API-Routen |

## Datenmodell (Sitzungstyp)

- `name`, `zweck`, `standardOrt`, `standardZeitVon`, `standardZeitBis`, `einladungVersenden`
- `traktanden`: [{titel, beschreibung, position}]
- `teilnehmer`: [{art, referenzId, referenzName}] – Regeln, noch nicht materialisiert
  - Arten: `mitglied`, `fraktion`, `kommission`, `rolle`, `eigeneFraktion`, `ncGruppe`, `ncUser`
- Ziel-Kalender: `parlwin-fraktion-kalender` (URI), Kalender-User aus App-Config `kalender_nutzer`

## Dokumentationssprache

CHANGELOG, README und alle Dokumentationsdateien in diesem Projekt: **Deutsch** (Schweizer Rechtschreibung).
Code, Variablen, Kommentare im Code: Englisch.

## Wichtige Constraints

**Why:** `KalenderService` nutzt `CalDavBackend` direkt → kein iTIP-Scheduling → keine automatischen Einladungen.
Einladungen sollen NUR über den NC-Calendar-Editor-Save laufen (Standard-Verhalten).
