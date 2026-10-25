import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

/**
 * Die Kopfzeile einer Ansicht am Telefon.
 *
 * Befund am Bild vom 19.10.2026 bei 360 Punkten Breite: Im Kopf der
 * Budget-Seite standen die beiden Knöpfe als «B·» und «-» — aus «Budget neu
 * einlesen» und «+ Neu» war je ein Zeichen übrig. Die Kopfzeile ist eine
 * Flex-Zeile, und ihre Knöpfe schrumpfen darin, bis nichts mehr zu lesen ist.
 *
 * Ein Knopf trägt seine Beschriftung oder er ist keiner: Die Zeile bricht
 * darum um, und die Knöpfe behalten ihre Breite.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

function regel(selektor) {
  const start = CSS.indexOf(`${selektor} {`)
  expect(start, `Regel ${selektor} fehlt in style.scss`).toBeGreaterThan(-1)
  return CSS.slice(start, CSS.indexOf('}', start))
}

describe('Kopfzeile am Telefon', () => {
  it('die Kopfzeile bricht um, statt ihre Knöpfe zu quetschen', () => {
    expect(regel('.pw-view-header'), 'die Kopfzeile bricht nicht um')
      .toMatch(/flex-wrap:\s*wrap/)
  })

  it('ein Knopf im Kopf behält seine Beschriftung', () => {
    const r = regel('.pw-view-header button')
    expect(r, 'der Knopf schrumpft unter seine Beschriftung').toMatch(/flex-shrink:\s*0/)
    expect(r, 'die Beschriftung bricht im Knopf um').toMatch(/white-space:\s*nowrap/)
  })
})
