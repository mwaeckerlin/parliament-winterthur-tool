import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Farb-Guard (globale Regel): Farben kommen IMMER aus dem CSS als Variablen —
 * NIE als harter Farbcode. Erlaubt ist ein Hex ausschliesslich als Fallback in
 * `var(--token, #xxx)` oder `color-mix(…, #xxx, …)`; ein Hex als direkter Wert
 * (z.B. `color: #0a7d33`, `border: 1px solid #ccc`) ist verboten.
 *
 * vitest läuft im Projekt-Root.
 */
const WURZELN = ['parlwin/src/js/components', 'parlwin/src/css']

function dateien(pfad, endungen, treffer = []) {
  for (const eintrag of readdirSync(pfad)) {
    const voll = join(pfad, eintrag)
    if (statSync(voll).isDirectory()) {
      dateien(voll, endungen, treffer)
    } else if (endungen.some((e) => eintrag.endsWith(e))) {
      treffer.push(voll)
    }
  }
  return treffer
}

// Nur der Style-Anteil zählt: bei .vue der Inhalt der <style>-Blöcke, bei .scss
// die ganze Datei. So schlagen keine Hex-Strings aus dem <script> (z.B. SVG) an.
function styleInhalt(datei) {
  const text = readFileSync(datei, 'utf8')
  if (datei.endsWith('.scss')) { return text }
  return [...text.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n')
}

// Entfernt var(…) und color-mix(…) (eine Verschachtelungsebene), damit die dort
// erlaubten Hex-Fallbacks nicht als Verstoss zählen.
function ohneFunktionsfallbacks(css) {
  let vorher
  let s = css
  do {
    vorher = s
    s = s.replace(/(?:var|color-mix)\((?:[^()]|\([^()]*\))*\)/g, ' ')
  } while (s !== vorher)
  return s
}

describe('CSS-Farben: nur Variablen, keine harten Farbcodes', () => {
  it('kein Hex-Farbcode als direkter Wert (nur als var()/color-mix()-Fallback erlaubt)', () => {
    const verstoesse = []
    for (const wurzel of WURZELN) {
      for (const datei of dateien(wurzel, ['.vue', '.scss'])) {
        const css = ohneFunktionsfallbacks(styleInhalt(datei))
        const zeilen = css.split('\n')
        zeilen.forEach((zeile, i) => {
          if (/#[0-9a-fA-F]{3,8}\b/.test(zeile)) {
            verstoesse.push(`${datei}: ${zeile.trim()}`)
          }
        })
      }
    }
    expect(verstoesse, 'Harte Farbcodes gefunden — als CSS-Variable ausdrücken').toEqual([])
  })
})
