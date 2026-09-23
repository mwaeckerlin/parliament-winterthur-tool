# ERZEUGTE DATEIEN — NICHT BEARBEITEN

Dieses Verzeichnis enthält, was der Build erzeugt. Jede `*.css`- und `*.css.map`-Datei hier entsteht automatisch und steht in `parlwin/.gitignore`.

- Quelle: [`parlwin/src/css/style.scss`](../src/css/style.scss)
- Bauen: `npm run build:css` (oder `npm run build:app`)
- Aufräumen: `npm run clean`

Bearbeitet wird die SCSS-Quelle, nie eine Datei in diesem Verzeichnis. Was hier von Hand geändert wird, überschreibt der nächste Build.
