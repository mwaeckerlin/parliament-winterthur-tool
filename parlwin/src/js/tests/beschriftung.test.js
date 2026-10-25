import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Eine Beschriftung ist leise, der Inhalt laut.
 *
 * Befund aus dem Design-Test vom 16.10.2026: Auf jeder Mitgliederkarte standen
 * die sechs Kommissionen in Grossbuchstaben, grau und in Beschriftungsgrösse,
 * also genau wie das Wort «Kommission» darüber. Das Lauteste auf der Karte war
 * damit die Beschriftung, und der Name der Kommission las sich wie eine.
 *
 * Ursache ist der Nachfahren-Selektor: `.pw-data-pair span` trifft auch die
 * Zeilen im `strong` daneben. Die Beschriftung ist das direkte Kind, und nur
 * das bekommt die Beschriftungsschrift.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

describe('Beschriftung eines Datenpaares', () => {
  it('die Beschriftungsschrift trifft nur das direkte Kind', () => {
    const zeilen = CSS.split('\n')
      .map((zeile, i) => [i + 1, zeile.trim()])
      .filter(([, zeile]) => /^\.pw-data-pair\s+span\b/.test(zeile))
      .map(([nr, zeile]) => `style.scss:${nr}: ${zeile}`)
    expect(zeilen, '.pw-data-pair span trifft auch den Inhalt — > span nehmen').toEqual([])
  })
})
