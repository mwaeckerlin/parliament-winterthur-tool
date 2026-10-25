import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Eine Kachel ist so hoch wie ihr Inhalt.
 *
 * Befund aus dem Design-Test vom 11.09.2026: Bei den Kommissionen laufen
 * kurze Karten bis zur Höhe der längsten Karte leer; bei einer Spalte tritt
 * das nicht auf. Das ist die Vorgabe von `grid`: Ohne Angabe streckt es jedes
 * Element auf die Höhe seiner Zeile (`align-items: stretch`), und bei einer
 * Spalte ist jede Karte ihre eigene Zeile — darum fällt es dort nicht auf.
 *
 * `align-items: start` lässt jede Karte bei ihrer eigenen Höhe.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

/** Der Block einer Regel, an ihrem Selektor gefunden. */
function block(selektor) {
  const start = CSS.indexOf(selektor)
  if (start < 0) { return '' }
  const auf = CSS.indexOf('{', start)
  const zu = CSS.indexOf('}', auf)
  return CSS.slice(auf + 1, zu)
}

describe('Kachelhöhe', () => {
  it('die Kachelanordnung streckt ihre Karten nicht', () => {
    const regel = block('.pw-mitglieder-grid,\n.pw-card-grid {')
    expect(regel, 'Regel der Kachelanordnung nicht gefunden').not.toBe('')
    expect(regel, 'Ohne align-items: start laufen kurze Karten leer').toMatch(/align-items:\s*start/)
  })
})
