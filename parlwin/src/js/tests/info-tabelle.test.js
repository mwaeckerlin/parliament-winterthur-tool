import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const css = readFileSync(
  resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss'),
  'utf8',
)

// Regression: Die Detail-Info-Tabelle (z.B. lange «Einreicher»-Liste) sprengte
// mit table-layout:auto die Breite, weil die Zelle nicht umbrach. Sie muss feste
// Spaltenbreiten (fixed) haben und die Zellen müssen umbrechen.
describe('Info-Tabelle bricht um statt zu breit zu werden', () => {
  it('.pw-info-tabelle nutzt table-layout: fixed', () => {
    const block = css.match(/\.pw-info-tabelle\s*\{[^}]*\}/)
    expect(block, '.pw-info-tabelle-Regel nicht gefunden').toBeTruthy()
    expect(block[0]).toMatch(/table-layout:\s*fixed/)
  })

  // Gebrochen wird an den Trennstellen des Wortes, nicht an jedem Buchstaben:
  // `anywhere` stand hier zuerst und schnitt mitten in die Silbe («Marc…
  // ckerlin» in der Spalte «Zuständig», «Informationsverordnun / g» bei den
  // Kommissionen, Befunde vom 14.09.2026 und 11.09.2026). `break-word` mit
  // `hyphens: auto` bricht an Leerzeichen, Bindestrich und Trennstelle und
  // nimmt die Mitte des Wortes nur, wenn es allein nicht in die Zeile passt —
  // die Zelle läuft also weiterhin nicht über (wortumbruch.test.js).
  it('.pw-info-tabelle-Zellen brechen um, an den Trennstellen', () => {
    const block = css.match(/\.pw-info-tabelle th,\s*\n\s*\.pw-info-tabelle td\s*\{[^}]*\}/)
    expect(block, '.pw-info-tabelle th/td Umbruch-Regel nicht gefunden').toBeTruthy()
    expect(block[0]).toMatch(/overflow-wrap:\s*break-word/)
    expect(block[0]).toMatch(/hyphens:\s*auto/)
  })
})
