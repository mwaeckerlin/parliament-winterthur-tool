import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Abdeckungs-Guard: JEDE Funktion der Feature-Liste muss mindestens einen Test
 * haben, und jeder Test muss zu einer existierenden Funktion gehören.
 *
 * Das ist die Abdeckung, auf die es hier ankommt — nicht der Anteil
 * durchlaufener Codezeilen. Eine Funktion ohne Test fällt damit sofort auf,
 * statt erst dann, wenn sie im Betrieb fehlt.
 *
 * vitest läuft im Projekt-Root.
 */
const features = readFileSync('FEATURES.md', 'utf8')
const tests = readFileSync('TESTS.md', 'utf8')

/**
 * Alle Funktionsnummern der Feature-Liste (Zeilen der Form «- **F12** …»).
 * Untergeordnete Funktionen sind eingerückt und zählen genauso.
 */
function featureNummern() {
  return [...features.matchAll(/^\s*- \*\*F(\d+)\*\*/gm)].map((m) => Number(m[1]))
}

/** Alle in der Testliste referenzierten Funktionsnummern (z.B. «**F8, F18**»). */
function referenzierteNummern() {
  const treffer = new Set()
  for (const zeile of tests.split('\n')) {
    const marke = zeile.match(/^- \*\*([^*]+)\*\*/)
    if (!marke) continue
    for (const n of marke[1].matchAll(/F(\d+)/g)) treffer.add(Number(n[1]))
  }
  return treffer
}

describe('Abdeckung: Feature-Liste gegen Testliste', () => {
  it('die Feature-Liste ist lückenlos und ohne doppelte Nummern', () => {
    const nummern = featureNummern()
    expect(nummern.length, 'Die Feature-Liste enthält keine nummerierten Funktionen').toBeGreaterThan(0)

    const doppelt = nummern.filter((n, i) => nummern.indexOf(n) !== i)
    expect([...new Set(doppelt)], 'Doppelt vergebene Funktionsnummern').toEqual([])
  })

  it('zu JEDER Funktion gibt es mindestens einen Test', () => {
    const referenziert = referenzierteNummern()
    const ohneTest = featureNummern().filter((n) => !referenziert.has(n))
    expect(
      ohneTest.map((n) => `F${n}`),
      'Diese Funktionen haben keinen einzigen Test — sofort nachholen',
    ).toEqual([])
  })

  it('jeder Test verweist auf eine existierende Funktion', () => {
    const vorhanden = new Set(featureNummern())
    const unbekannt = [...referenzierteNummern()].filter((n) => !vorhanden.has(n))
    expect(
      unbekannt.map((n) => `F${n}`),
      'Diese Funktionsnummern werden in der Testliste referenziert, existieren aber nicht',
    ).toEqual([])
  })
})
