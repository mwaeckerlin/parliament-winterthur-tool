import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

// style.scss relativ zu dieser Testdatei (parlwin/src/js/tests).
const stylePath = resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss')

// Regression: Die Beschluss-Spalte der Geschäfte-Tabelle war mit 4em so schmal,
// dass das Beschluss-Eingabefeld (width:100%) den Text abschnitt – «Zustimmung»
// erschien nur als «Zu». Die Mindestbreite muss den gängigen Beschluss-Text
// lesbar machen (z.B. «Zustimmung», «Ablehnung», «Rückweisung»).
describe('Beschluss-Spalte ist breit genug für den Beschluss-Text', () => {
  it('--pw-col-beschluss ist breit genug (>= 13em) für längere Beschlüsse wie «Stimmfreigabe»', () => {
    const css = readFileSync(stylePath, 'utf8')
    const m = css.match(/--pw-col-beschluss:\s*([\d.]+)em/)
    expect(m, '--pw-col-beschluss nicht gefunden').toBeTruthy()
    expect(parseFloat(m[1])).toBeGreaterThanOrEqual(13)
  })
})
