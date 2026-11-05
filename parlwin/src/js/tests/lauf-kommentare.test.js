// @vitest-environment node
/**
 * Die Kommentare der Dateien des Testlaufs sagen, was die Zeile tut. Sie nennen
 * kein Datum aus der Vorgeschichte, keinen Namen einer Person, keinen Wortlaut
 * eines Auftrags und keinen Verweis auf eine Ablage ausserhalb des Projekts;
 * das gehört in CHANGELOG, TESTS.md und die Beschreibung des Commits.
 */
import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')

const DATEIEN = [
  'tests/regression.mjs',
  'tests/regression/hash.mjs',
  'tests/regression/hilfen.mjs',
  'tests/regression/journal.mjs',
  'tests/regression/speicher.mjs',
  'tests/regression/stack.mjs',
  'tests/regression/vitest-reporter.mjs',
  'tests/regression/docker-compose.composer-audit.yml',
  'tests/e2e/sperre.js',
  'tests/e2e/journal-reporter.js',
  'tests/e2e/globale-anmeldung.js',
  'tests/e2e/anmeldung.js',
]

const VERBOTEN = [
  [/\b20\d\d-\d\d-\d\d\b|\b\d\d?\.\d\d?\.20\d\d\b/, 'ein Datum'],
  [/\bMarcs?\b|\bClaude\b/, 'ein Name'],
  [/scratchpad/i, 'ein Verweis auf den Scratchpad'],
  [/\bAuftrag\b|«Nimm /, 'der Wortlaut eines Auftrags'],
]

/** Die Kommentarzeilen einer Datei mit ihrer Nummer. */
function kommentare(datei) {
  return fs.readFileSync(path.join(WURZEL, datei), 'utf8').split('\n')
    .map((text, i) => ({ nr: i + 1, text }))
    .filter(({ text }) => /^\s*(\/\/|\*|\/\*\*?|#)/.test(text))
}

describe('Kommentare der Dateien des Testlaufs', () => {
  for (const datei of DATEIEN) {
    it(`${datei}: kein Datum, kein Name, kein Auftrag, kein Scratchpad`, () => {
      const funde = kommentare(datei).flatMap(({ nr, text }) => VERBOTEN.filter(([m]) => m.test(text)).map(([, was]) => `${datei}:${nr} ${was}: ${text.trim()}`))
      expect(funde).toEqual([])
    })
  }

  // Eine Zeilennummer in einem Kommentar stimmt nach der nächsten Änderung der
  // genannten Datei nicht mehr; der Kommentar nennt die Funktion oder die
  // Variable.
  for (const datei of [...DATEIEN, 'tests/e2e/run-compose-e2e.sh', 'tests/e2e/playwright.config.js', 'tests/e2e/fixtures.js', 'tests/e2e/budget-datenfluss.spec.js', 'tests/e2e/multi-user-sharing.spec.js']) {
    it(`${datei}: keine Zeilennummer einer Datei im Kommentar`, () => {
      const funde = kommentare(datei).filter(({ text }) => /[\w.-]+\.(js|mjs|sh|php|vue|yml|json):\d+/.test(text)).map(({ nr, text }) => `${datei}:${nr} ${text.trim()}`)
      expect(funde).toEqual([])
    })
  }

  // Die Sperre aus sperre.js heisst in den Kommentaren nach dem, was sie ist:
  // das erste `beforeAll` einer Datei. Geprüft wird jede Datei tests/e2e/*.spec.js.
  const SPEC_DATEIEN = fs.readdirSync(path.join(WURZEL, 'tests', 'e2e')).filter((n) => n.endsWith('.spec.js')).sort().map((n) => `tests/e2e/${n}`)
  const SPERRE_SATZ = 'Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei, weil schon ein `beforeAll` globale Einstellungen schreiben kann.'

  /**
   * Die Zeile von `test.beforeAll(sperreVorAllem)` und der Kommentar aus den
   * `//`-Zeilen direkt darüber, zu einem Satz verbunden.
   */
  function kommentarZurSperre(datei) {
    const zeilen = fs.readFileSync(path.join(WURZEL, datei), 'utf8').split('\n')
    const sperre = zeilen.indexOf('test.beforeAll(sperreVorAllem)')
    const kopf = []
    for (let i = sperre - 1; i >= 0 && zeilen[i].startsWith('//'); i--) kopf.unshift(zeilen[i].replace(/^\/\/\s?/, ''))
    return { zeile: sperre + 1, satz: kopf.join(' ') }
  }

  it('tests/e2e enthält Spec-Dateien', () => {
    expect(SPEC_DATEIEN.length).toBeGreaterThan(0)
  })

  for (const datei of SPEC_DATEIEN) {
    it(`${datei}: der Kommentar zur Sperre am Anfang nennt sie das erste \`beforeAll\``, () => {
      const funde = kommentare(datei).filter(({ nr, text }) => nr <= 10 && /gleichzeitige Dateien|\bHaken\b/.test(text)).map(({ nr, text }) => `${datei}:${nr} ${text.trim()}`)
      expect(funde).toEqual([])
      const { zeile, satz } = kommentarZurSperre(datei)
      expect(zeile, 'test.beforeAll(sperreVorAllem) steht in den ersten zehn Zeilen').toBeGreaterThan(0)
      expect(zeile, 'test.beforeAll(sperreVorAllem) steht in den ersten zehn Zeilen').toBeLessThanOrEqual(10)
      expect(satz).toBe(SPERRE_SATZ)
    })
  }

  for (const datei of ['tests/e2e/fixtures.js', 'tests/e2e/anmeldung.js']) {
    it(`${datei}: die Sperre heisst nicht «Haken», kein Fremdwort «Default», keine Vorgeschichte`, () => {
      const funde = kommentare(datei).filter(({ text }) => /gleichzeitige Dateien|\bHaken\b|\bDefaults?\b|wie früher/.test(text)).map(({ nr, text }) => `${datei}:${nr} ${text.trim()}`)
      expect(funde).toEqual([])
    })
  }

  it('tests/e2e/playwright.config.js: kein Name, kein Wortlaut eines Auftrags', () => {
    const funde = kommentare('tests/e2e/playwright.config.js').filter(({ text }) => VERBOTEN.slice(1).some(([m]) => m.test(text)))
    expect(funde).toEqual([])
  })
})
