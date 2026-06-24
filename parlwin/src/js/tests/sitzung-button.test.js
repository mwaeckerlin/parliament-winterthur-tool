import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const src = readFileSync(
  resolve(dirname(fileURLToPath(import.meta.url)), '../components/Sitzungsliste.vue'),
  'utf8',
)

// Regression (nur in Produktion mit EINEM Sitzungstyp reproduzierbar):
// NcActions rendert bei genau EINER enthaltenen Action diese inline statt als
// «+»-Menü. Mit nur einem Sitzungstyp («Fraktion») verschwand so der Button zum
// Anlegen einer neuen Sitzung. `force-menu` erzwingt das Menü unabhängig von der
// Anzahl Actions, damit das «+» und die Typ-Auswahl immer erscheinen.
describe('Neue-Sitzung-Button erscheint auch bei nur einem Sitzungstyp', () => {
  it('NcActions (pw-neue-sitzung-btn) setzt force-menu', () => {
    const m = src.match(/<NcActions\b[^>]*class="pw-neue-sitzung-btn"[^>]*>/)
    expect(m, 'NcActions mit Klasse pw-neue-sitzung-btn nicht gefunden').toBeTruthy()
    expect(m[0], 'force-menu fehlt – bei einem Typ rendert NcActions inline statt als «+»-Menü').toMatch(/force-menu/)
  })
})
