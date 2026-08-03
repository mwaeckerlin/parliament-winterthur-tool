import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const WURZEL = dirname(fileURLToPath(import.meta.url))
const sitzungsliste = readFileSync(resolve(WURZEL, '../components/Sitzungsliste.vue'), 'utf8')
const style = readFileSync(resolve(WURZEL, '../../css/style.scss'), 'utf8')

/**
 * Bug: Im Dialog «Neue Sitzung» blieb die Auswahl «Verknüpfen mit» leer —
 * aufgeklappt war nichts zu sehen. Die Liste wird an den Seitenkörper gehängt
 * und liegt dort auf einer festgelegten Ebene knapp über dem gemeinsamen
 * Dialog-Hintergrund. Dieser Dialog brachte aber seinen eigenen Hintergrund auf
 * einer viel höheren Ebene mit und deckte die Liste damit zu.
 *
 * Deshalb: derselbe Dialog-Hintergrund wie überall — keine eigene Ebene.
 */
describe('Neue Sitzung: aufgeklappte Auswahl bleibt sichtbar', () => {
  it('der Dialog nutzt den gemeinsamen Dialog-Hintergrund', () => {
    // Genau der Hintergrund dieses Dialogs — nicht irgendeiner in der Datei.
    const nutztGemeinsamen = /class="[^"]*\bpw-modal-overlay\b[^"]*\bpw-neue-sitzung-overlay\b[^"]*"/.test(sitzungsliste)
    expect(nutztGemeinsamen, 'Der Dialog bringt einen eigenen Hintergrund mit').toBe(true)
  })

  it('der Dialog legt keine eigene Ebene über die Auswahlliste', () => {
    // Ebene der Auswahlliste aus dem gemeinsamen Stil lesen — nicht raten.
    const listenEbene = Number((style.match(/\.vs__dropdown-menu[^{]*\{[^}]*z-index:\s*(\d+)/) || [])[1])
    expect(listenEbene, 'Ebene der Auswahlliste nicht gefunden').toBeGreaterThan(0)

    const zuHoch = [...sitzungsliste.matchAll(/z-index:\s*(\d+)/g)]
      .map(t => Number(t[1]))
      .filter(ebene => ebene >= listenEbene)
    expect(zuHoch, 'Der Dialog liegt über der Auswahlliste').toEqual([])
  })
})
