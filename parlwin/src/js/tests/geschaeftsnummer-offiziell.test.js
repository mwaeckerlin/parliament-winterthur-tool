import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const WURZEL = dirname(fileURLToPath(import.meta.url))
const KOMPONENTEN = resolve(WURZEL, '../components')

const dateien = readdirSync(KOMPONENTEN)
  .filter(name => name.endsWith('.vue'))
  .map(name => ({ name, text: readFileSync(resolve(KOMPONENTEN, name), 'utf8') }))

/**
 * Bug vom 28.09.2026: Der Ausdruck des Votums nannte als Geschäftsnummer
 * «2504299», die Kennung der Parlamentswebseite. Das Parlament führt das
 * Geschäft als «2025.82», und nur diese Bezeichnung kennen die Leser.
 *
 * Deshalb: Was die Oberfläche als Bezeichnung eines Geschäfts zeigt, ist seine
 * Nummer beim Parlament. Die Kennung der Webseite und die Zeilennummer der
 * Datenbank dienen dem Verknüpfen und erscheinen nirgends im Text.
 */
describe('Oberfläche: Geschäfte tragen die Nummer des Parlaments', () => {
  it('keine Komponente zeigt die Kennung der Parlamentswebseite an', () => {
    // Angezeigt wird, was zwischen {{ }} steht. externId darf dort nie stehen.
    const treffer = []
    for (const { name, text } of dateien) {
      for (const stelle of text.matchAll(/\{\{([^}]*)\}\}/g)) {
        if (/\bextern_?[iI]d\b/.test(stelle[1])) {
          treffer.push(`${name}: {{${stelle[1].trim()}}}`)
        }
      }
    }
    expect(treffer, 'Die interne Kennung steht in der Anzeige').toEqual([])
  })

  it('keine Komponente zeigt die Zeilennummer der Datenbank als Bezeichnung', () => {
    // «#» direkt vor einer id ist die Zeilennummer, die niemand kennt.
    const treffer = []
    for (const { name, text } of dateien) {
      for (const stelle of text.matchAll(/'#'\s*\+\s*\w*[iI]d\b/g)) {
        treffer.push(`${name}: ${stelle[0]}`)
      }
    }
    expect(treffer, 'Die Zeilennummer der Datenbank steht in der Anzeige').toEqual([])
  })
})
