import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

/**
 * Die Budget-Seite am Telefon.
 *
 * Gemessen am 19.10.2026 im Aufnahmelauf bei 360 Punkten Breite: Der Klick auf
 * den Umschalter der Grafik traf nicht ihn, sondern die Übersicht darüber —
 * «<th class="pw-vergleich-zeile">Stadtratsbudget</th> from <div
 * class="pw-budget-sticky"> subtree intercepts pointer events». Auf dem Telefon
 * bricht die Übersicht in je einen Block pro Budget um und wird dadurch so
 * hoch wie der ganze Bildschirm; als haftender Block liegt sie dann über allem,
 * was darunter steht.
 *
 * Auf dem Telefon haftet darum die Tab-Leiste allein — sie ist das, was man
 * beim Scrollen braucht —, und die Übersicht scrollt mit dem Inhalt weg.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')

/** Der Inhalt der Medienabfrage für schmale Bildschirme. */
function schmal() {
  const start = CSS.indexOf('@media (max-width: 46rem)')
  expect(start, 'keine Medienabfrage für schmale Bildschirme').toBeGreaterThan(-1)
  let tiefe = 0
  for (let i = CSS.indexOf('{', start); i < CSS.length; i += 1) {
    if (CSS[i] === '{') { tiefe += 1 }
    if (CSS[i] === '}') {
      tiefe -= 1
      if (tiefe === 0) { return CSS.slice(start, i + 1) }
    }
  }
  return ''
}

describe('Budget am Telefon', () => {
  it('die Übersicht haftet am Telefon nicht', () => {
    expect(schmal(), 'der haftende Block bleibt am Telefon haften')
      .toMatch(/\.pw-budget-sticky\s*\{[^}]*position:\s*static/)
  })

  it('eine Zeile ohne Wert zeigt am Telefon auch keine Beschriftung', () => {
    // Befund am Bild vom 19.10.2026: Unter «Differenz» standen «AUSGABEN»,
    // «EINNAHMEN», «ERTRAG» und «STELLEN» ohne Wert — die Beschriftung kommt
    // aus `td::before` und stand auch dort, wo die Differenz null und die
    // Zelle leer ist.
    expect(schmal(), 'eine leere Zelle zeigt am Telefon ihre Beschriftung')
      .toMatch(/\.pw-budget-vergleich\s+td:empty\s*\{[^}]*display:\s*none/)
  })

  it('die Tab-Leiste haftet am Telefon für sich', () => {
    expect(schmal(), 'die Tabs haften am Telefon nicht')
      .toMatch(/\.pw-budget-tabs\s*\{[^}]*position:\s*sticky/)
  })
})
