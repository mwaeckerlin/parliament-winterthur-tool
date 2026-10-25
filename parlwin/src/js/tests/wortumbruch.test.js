import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Ein Wort bricht an seinen Trennstellen, nicht an jedem Buchstaben.
 *
 * Befunde aus dem Design-Test: In der Spalte «Zuständig» stand «Marc…ckerlin»
 * und «Mich… Gross» (14.09.2026), und bei den Kommissionen auf dem Telefon
 * «Informationsverordnun / g; InfV» (11.09.2026). Beides macht
 * `overflow-wrap: anywhere`: Es bricht an jeder Stelle, auch mitten in der
 * Silbe, obwohl `hyphens: auto` daneben steht.
 *
 * `overflow-wrap: break-word` bricht zuerst an den Stellen, die der Text
 * anbietet — Leerzeichen, Bindestrich, und mit `hyphens: auto` die
 * Trennstellen der Sprache. Nur ein Wort, das allein nicht in die Zeile
 * passt, bricht noch mitten durch. Genau das ist gewollt: Nichts läuft aus
 * der Spalte, und ein normaler Name bleibt lesbar.
 *
 * Damit das trägt, braucht der Text eine Sprache: `hyphens: auto` wirkt nur,
 * wo `lang` gesetzt ist.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

describe('Wortumbruch', () => {
  it('keine Regel bricht an jedem Buchstaben', () => {
    const zeilen = CSS.split('\n')
      .map((zeile, i) => [i + 1, zeile.trim()])
      .filter(([, zeile]) => /overflow-wrap:\s*anywhere/.test(zeile))
      .map(([nr, zeile]) => `style.scss:${nr}: ${zeile}`)
    expect(zeilen, 'overflow-wrap: anywhere bricht mitten im Wort — break-word nehmen').toEqual([])
  })

  it('wo ein Wort brechen darf, ist die Silbentrennung entschieden', () => {
    // Jede Regel mit `overflow-wrap: break-word` sagt, was sie mit der
    // Silbentrennung tut: `auto` für Fliesstext, der dann an seinen
    // Trennstellen bricht, `none` für einen Namen, einen Status und jede
    // andere feste Beschriftung, der die Sprache keine Trennstellen
    // errechnen darf (spaltenbreite.test.js). Fehlt beides, erbt die Regel
    // die Trennung des Elternelements, und niemand weiss welche.
    const bloecke = CSS.split('}')
    const unentschieden = bloecke
      .filter((block) => /overflow-wrap:\s*break-word/.test(block))
      .filter((block) => !/hyphens:\s*(auto|none)/.test(block))
    expect(unentschieden, 'break-word ohne eine Angabe zu hyphens').toEqual([])
  })
})
