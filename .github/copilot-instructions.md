# Copilot / AI Assistant Instructions — parliament-winterthur-tool

## Generated artefacts (NEVER edit)

The following paths are **build outputs**, regenerated from source files.
Editing them is pointless because the next build overwrites the changes.
All of them are listed in `parlwin/.gitignore`.

| Generated path                       | Source                          | Build command          |
| ------------------------------------ | ------------------------------- | ---------------------- |
| `parlwin/css/parlwin-style.css`      | `parlwin/src/css/style.scss`    | `npm run build:css`    |
| `parlwin/css/parlwin-style.css.map`  | `parlwin/src/css/style.scss`    | `npm run build:css`    |
| `parlwin/js/parlwin-main.js`         | `parlwin/src/js/**` (entry `main.js`) | `npm run build:app` |
| `parlwin/js/parlwin-main.js.map`     | `parlwin/src/js/**`             | `npm run build:app`    |
| `parlwin/js/chunks/`                 | `parlwin/src/js/**`             | `npm run build:app`    |

Rule: when a fix is needed in CSS or JS, **edit the file under `parlwin/src/`**,
then run `npm run build:app` (or `npm start` for the full Docker rebuild).
Each generated directory also contains a `README.md` marker repeating this rule.

## Useful scripts

- `npm run build:app` — full webpack + sass build (runs `clean` first)
- `npm run build:css` — sass only
- `npm run clean` — remove all generated frontend artefacts
- `npm run dev` — webpack watch mode for JS
- `npm start` — Docker compose rebuild & launch

## Language / style

Project conventions follow [`../AI-RULES.md`](../AI-RULES.md): Swiss German for
chat, English for code/comments/identifiers.
