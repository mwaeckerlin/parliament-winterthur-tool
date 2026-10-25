import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Die beiden Felder einer Karte sehen gleich aus.
 *
 * Befund aus dem Design-Test vom 16.10.2026, gesehen auf dem Telefon: In der
 * Karte eines Geschäfts steht das Auswahlfeld «Priorität» auf rund drei
 * Vierteln der Breite, das Feld «Beschluss» darunter auf voller Breite. Und
 * die Beschriftung «Priorität» steht klein und gemischt, während «TYP»,
 * «DATUM» und «ZUSTÄNDIGKEIT» darüber die Beschriftungsschrift tragen.
 *
 * Beides sind Felder derselben Karte, also tragen sie dieselbe Breite und
 * dieselbe Beschriftungsschrift.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

function block(selektor) {
  const start = CSS.indexOf(`${selektor} {`)
  expect(start, `Regel ${selektor} fehlt in style.scss`).toBeGreaterThan(-1)
  return CSS.slice(start, CSS.indexOf('}', start))
}

describe('Felder einer Geschäftskarte', () => {
  it('beide Felder nehmen die ganze Breite der Karte', () => {
    const regel = block('.pw-card-prio .v-select,\n.pw-card-beschluss .v-select')
    expect(regel, 'die Felder richten sich nicht an derselben Breite aus')
      .toMatch(/inline-size:\s*100%/)
  })

  it('die Beschriftung eines Feldes sieht aus wie die daneben', () => {
    const regel = block('.pw-card-prio label,\n.pw-card-beschluss label')
    expect(regel, 'die Beschriftung des Feldes trägt eine eigene Schrift')
      .toMatch(/text-transform:\s*uppercase/)
  })
})
