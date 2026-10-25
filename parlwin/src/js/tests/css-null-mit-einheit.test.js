import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Eine CSS-Variable, die eine Länge trägt, wird nie auf eine einheitenlose Null
 * gesetzt.
 *
 * Gemessen am 24.10.2026: Die Regel für den flachen Schirm setzte
 * `--body-container-margin: 0`. Nextcloud rechnet mit diesem Wert die Breite
 * und die Höhe seiner Behälter («calc(100% - var(--body-container-margin) *
 * 2)»), und in einer solchen Rechnung ist eine einheitenlose Null keine Länge:
 * Die Rechnung wird ungültig, die Angabe fällt weg, und der Behälter schrumpft
 * auf seinen Inhalt. Bei 1024×768 war der Inhaltsbereich 24 Pixel breit, die
 * Navigationsleiste lag darüber, und von der Anwendung war nichts zu sehen —
 * auf jedem Schirm, der höchstens 56rem hoch ist, also auf jedem Laptop.
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

function styleInhalt(datei) {
  const text = readFileSync(datei, 'utf8')
  if (datei.endsWith('.scss')) { return text }
  return [...text.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n')
}

function ohneKommentare(css) {
  return css.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/(^|\s)\/\/[^\n]*/g, '$1')
}

describe('CSS-Variablen: eine Null trägt ihre Einheit', () => {
  it('keine CSS-Variable wird auf eine einheitenlose Null gesetzt', () => {
    const verstoesse = []
    for (const wurzel of WURZELN) {
      for (const datei of dateien(wurzel, ['.vue', '.scss'])) {
        const zeilen = ohneKommentare(styleInhalt(datei)).split('\n')
        zeilen.forEach((zeile, i) => {
          if (/^\s*--[\w-]+\s*:\s*0\s*(?:!important\s*)?;/.test(zeile)) {
            verstoesse.push(`${datei}:${i + 1}: ${zeile.trim()}`)
          }
        })
      }
    }
    expect(
      verstoesse,
      'Eine Länge ohne Einheit macht jede calc()-Rechnung ungültig, in der sie steht — '
      + 'schreibe «0px»:\n' + verstoesse.join('\n'),
    ).toEqual([])
  })
})
