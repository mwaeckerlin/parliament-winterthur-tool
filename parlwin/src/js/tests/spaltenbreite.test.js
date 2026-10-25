import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Status und Zuständigkeit stehen in Wörtern, die niemand trennt.
 *
 * Befund aus dem Design-Test vom 16.10.2026, gesehen auf jedem Format von
 * 1024 bis 3840: Die Spalte «Status» wird auf rund zweieinhalb Zeichen
 * schmal, und «Bei der Aufsichtskommission pendent» steht darin als
 * «Bei der / Auf- / sichts- / kom- / mission / pen- / dent» — sieben Zeilen,
 * die die ganze Zeile hoch machen, während die Titel-Spalte zur Hälfte leer
 * bleibt.
 *
 * Ursache ist die Silbentrennung in der Zelle: Mit `hyphens: auto` darf die
 * Spalte bis auf eine Silbe schrumpfen, also nimmt der Umbruch der Tabelle
 * ihr den Platz und gibt ihn dem Titel. Ohne Trennung ist die kleinste
 * mögliche Breite das längste Wort, und die Tabelle muss sie geben.
 *
 * Ein Status ist ein fester Satz des Parlaments und ein Name ist ein Name:
 * Beide werden an Leerzeichen und Bindestrich umbrochen, nie innerhalb einer
 * Silbe und nie mit einem Trennstrich, den die Sprache errechnet
 * (wortumbruch.test.js hält die allgemeine Regel).
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

function block(selektor) {
  // Mit der öffnenden Klammer, sonst trifft `.vs__selected` auch
  // `.vs__selected-options`.
  const start = CSS.indexOf(`${selektor} {`)
  expect(start, `Regel ${selektor} fehlt in style.scss`).toBeGreaterThan(-1)
  return CSS.slice(start, CSS.indexOf('}', start))
}

describe('Spalten für Status und Zuständigkeit', () => {
  it('der Status wird nicht getrennt', () => {
    for (const selektor of ['.pw-tabelle-geschaefte .pw-col-status', '.pw-status-text']) {
      const regel = block(selektor)
      expect(regel, `${selektor}: Silbentrennung lässt die Spalte auf eine Silbe schrumpfen`)
        .toMatch(/hyphens:\s*none/)
      expect(regel, `${selektor}: der Umbruch darf nur an Leerzeichen und Bindestrich greifen`)
        .toMatch(/overflow-wrap:\s*normal/)
    }
  })

  it('die Status-Spalte ist so breit wie ihr längstes Wort', () => {
    // «Aufsichtskommission», 19 Zeichen, gesetzt in der Chip-Schrift.
    const zeile = CSS.split('\n').find((z) => z.includes('--pw-col-status:'))
    expect(zeile, '--pw-col-status fehlt').toBeTruthy()
    expect(zeile, '--pw-col-status ist eine Zahl ohne Herleitung').toMatch(/19ch/)
  })

  it('ein Name wird nicht getrennt', () => {
    for (const selektor of [
      '.pw-tabelle-geschaefte .pw-col-zustaendig',
      '.pw-tabelle-geschaefte .pw-inline-select.v-select .vs__selected',
    ]) {
      expect(block(selektor), `${selektor}: ein Personenname bekommt keinen errechneten Trennstrich`)
        .toMatch(/hyphens:\s*none/)
    }
  })

  it('der Titel hört bei der Lesebreite auf', () => {
    // Auf 3440 nahm die Titel-Spalte die halbe Tabelle und füllte sie nicht:
    // Die Titel enden nach rund 80 Zeichen, und rechts davon stand ein leerer
    // Streifen, während die vier rechten Spalten ins letzte Viertel gedrängt
    // waren (Befund vom 16.10.2026). Mehr als 90 Zeichen je Zeile liest
    // ohnehin niemand mehr bequem.
    expect(block('.pw-tabelle-geschaefte .pw-col-titel'), 'die Titel-Spalte wächst ohne Grenze')
      .toMatch(/max-inline-size:\s*90ch/)
  })

  it('den übrigen Platz bekommt der Beschluss', () => {
    // Was der Titel nicht braucht, geht an die Spalte, in die der Anwender
    // selbst schreibt, statt als leere Fläche stehen zu bleiben.
    const regel = block('.pw-tabelle-geschaefte .pw-col-beschluss')
    expect(regel, 'der Beschluss nimmt den übrigen Platz nicht').toMatch(/inline-size:\s*auto/)
    expect(regel, 'eine Höchstbreite hält den Beschluss klein').not.toMatch(/max-inline-size/)
  })

  it('der Titel hat die grössere Mindestbreite als der Beschluss', () => {
    // Nachgemessen am Bild vom 16.10.2026: Auf 1024 steht jede Spalte auf
    // ihrer Mindestbreite, zusammen 944 von 1024, und der Titel bricht auf
    // vier Zeilen. Das ist die Grenze der Breite, kein Fehler — die Regel
    // dahinter hält aber nur, solange der Titel die grössere Mindestbreite
    // behält: Der Titel ist das, wonach der Anwender sucht, der Beschluss
    // bekommt den Platz, der übrig bleibt.
    const wert = (name) => Number(/([\d.]+)em/.exec(CSS.split('\n').find((z) => z.includes(`${name}:`)))[1])
    expect(wert('--pw-col-beschluss'), 'der Beschluss verlangt mehr Platz als der Titel')
      .toBeLessThan(wert('--pw-col-titel'))
  })

  it('die Spalte «Zuständig» fasst einen Namen', () => {
    const zeile = CSS.split('\n').find((z) => z.includes('--pw-col-zustaendig:'))
    const em = Number(/([\d.]+)em/.exec(zeile)[1])
    expect(em, '--pw-col-zustaendig ist schmaler als ein Nachname').toBeGreaterThanOrEqual(9)
  })
})
