# ERZEUGTE DATEIEN — NICHT BEARBEITEN

Dieses Verzeichnis enthält, was der Build erzeugt. Jede `*.js`- und `*.js.map`-Datei hier entsteht automatisch, ebenso das Verzeichnis `chunks/`; alle stehen in `parlwin/.gitignore`.

- Quellen: [`parlwin/src/js/`](../src/js/) (Einstieg `main.js`)
- Bauen: `npm run build:app`
- Aufräumen: `npm run clean`

Bearbeitet wird die Quelle unter `parlwin/src/js/`, nie eine Datei in diesem Verzeichnis. Was hier von Hand geändert wird, überschreibt der nächste Build.
