import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve, join } from 'node:path'

/**
 * Test-Integrität ist absolut: kein Test wird übersprungen, isoliert oder
 * ausgesetzt. Ein übersprungener Test zählt als nicht gelaufen — er verdeckt
 * ein echtes Versagen, statt es zu zeigen. Statt zu überspringen wird die
 * Voraussetzung hergestellt oder hart geprüft.
 *
 * Dieser Guard durchsucht ALLE Test- und Spec-Dateien des Projekts nach den
 * verbotenen Formen und wird rot, sobald eine auftaucht.
 */
const projektRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')

// Verbotene Muster: skip/only/fixme in allen Schreibweisen, plus xit/xdescribe.
const VERBOTEN = [
  /\b(test|it|describe)\s*\.\s*(skip|only|fixme)\b/,
  /\b(test|it|describe)\s*\.\s*(skipIf|runIf)\b/,
  /\bx(it|describe)\s*\(/,
  /\b(it|describe)\s*\.\s*each\b[\s\S]{0,80}\.\s*skip\b/,
]

// Diese Guard-Datei selbst nennt die Muster als Text — sie ist ausgenommen.
const DIESE_DATEI = fileURLToPath(import.meta.url)

// Verzeichnisse ohne Testquellen — «parlwin/js» ist Build-Ausgabe, wird aber
// über den vollen Pfad ausgeschlossen, damit nicht jedes Verzeichnis namens
// «js» (z.B. das Quellverzeichnis parlwin/src/js) mit erfasst wird.
const AUSGESCHLOSSEN_NAME = new Set(['node_modules', '.git', 'test-results', 'vendor', '.junit'])
const AUSGESCHLOSSEN_PFAD = [join('parlwin', 'js'), join('parlwin', 'css')]

/** Alle Test-/Spec-Dateien einsammeln (ohne node_modules und Build-Artefakte). */
function testDateien(verzeichnis, treffer = []) {
  for (const eintrag of readdirSync(verzeichnis)) {
    if (AUSGESCHLOSSEN_NAME.has(eintrag)) continue
    const pfad = join(verzeichnis, eintrag)
    if (AUSGESCHLOSSEN_PFAD.some((teil) => pfad.endsWith(teil))) continue
    const info = statSync(pfad)
    if (info.isDirectory()) {
      testDateien(pfad, treffer)
    } else if (/\.(test|spec)\.[jt]s$/.test(eintrag) && pfad !== DIESE_DATEI) {
      treffer.push(pfad)
    }
  }
  return treffer
}

const dateien = testDateien(projektRoot)

describe('Kein Test wird übersprungen, isoliert oder ausgesetzt', () => {
  it('es gibt Test-/Spec-Dateien zu prüfen', () => {
    expect(dateien.length, 'keine Test-Dateien gefunden — der Guard prüft nichts').toBeGreaterThan(0)
  })

  it.each(dateien.map((d) => [d.slice(projektRoot.length + 1), d]))(
    '%s enthält kein skip/only/fixme',
    (_kurz, pfad) => {
      const inhalt = readFileSync(pfad, 'utf8')
      const gefunden = VERBOTEN.filter((muster) => muster.test(inhalt)).map(String)
      expect(
        gefunden,
        `In ${pfad.slice(projektRoot.length + 1)} steht ein übersprungener/isolierter Test — `
        + 'Voraussetzung herstellen oder hart prüfen, nie überspringen',
      ).toEqual([])
    },
  )
})
