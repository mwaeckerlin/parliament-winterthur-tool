# Anweisungen für Copilot und andere Sprachmodelle — parliament-winterthur-tool

## Erzeugte Dateien (NIEMALS bearbeiten)

Die folgenden Pfade entstehen beim Bauen aus ihren Quellen. Sie zu bearbeiten bringt nichts, weil der nächste Build die Änderung überschreibt. Alle stehen in `parlwin/.gitignore`.

| Erzeugte Datei                       | Quelle                          | Befehl zum Bauen       |
| ------------------------------------ | ------------------------------- | ---------------------- |
| `parlwin/css/parlwin-style.css`      | `parlwin/src/css/style.scss`    | `npm run build:css`    |
| `parlwin/css/parlwin-style.css.map`  | `parlwin/src/css/style.scss`    | `npm run build:css`    |
| `parlwin/js/parlwin-main.js`         | `parlwin/src/js/**` (Einstieg `main.js`) | `npm run build:app` |
| `parlwin/js/parlwin-main.js.map`     | `parlwin/src/js/**`             | `npm run build:app`    |
| `parlwin/js/chunks/`                 | `parlwin/src/js/**`             | `npm run build:app`    |

Regel: Wo eine Änderung an CSS oder JavaScript nötig ist, wird **die Datei unter `parlwin/src/` bearbeitet** und danach `npm run build:app` ausgeführt (oder `npm start` für den vollständigen Neubau der Container). In jedem erzeugten Verzeichnis steht eine `README.md`, die diese Regel wiederholt.

## Nützliche Befehle

- `npm run build:app` — baut JavaScript und CSS vollständig (räumt vorher auf)
- `npm run build:css` — baut nur das CSS
- `npm run clean` — entfernt alle erzeugten Dateien der Oberfläche
- `npm run dev` — baut das JavaScript bei jeder Änderung neu
- `npm start` — baut die Container neu und startet sie

## Sprache

Der Chat ist Deutsch (Schweizer Rechtschreibung). Die Dokumentation dieses Projekts ist Deutsch, ebenso die Kommentare im Code und die Namen der Tests; die Bezeichner im Code sind Englisch, ausser den Begriffen des Parlaments (Traktandum, Vorstoss, Geschäft, Fraktion), die als Fachbegriffe Deutsch bleiben. Commit-Meldungen sind Englisch.

Jedes Wort in einem Dokument hat eine Quelle: einen Namen aus dem Code, eine Beschriftung aus der Oberfläche, einen Begriff des Parlaments oder gewöhnliches Deutsch. Ein englisches Wort bleibt nur dort stehen, wo die Fachwelt es spricht und kein deutsches dasselbe sagt; erfundene Wörter sind überall falsch.
