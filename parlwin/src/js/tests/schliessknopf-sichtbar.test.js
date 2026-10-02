import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const WURZEL = dirname(fileURLToPath(import.meta.url))
const style = readFileSync(resolve(WURZEL, '../../css/style.scss'), 'utf8')

/**
 * Bug vom 28.09.2026: Im geöffneten Geschäft deckte die Werkzeugleiste des
 * Texteditors den Schliessknopf ✕ zur Hälfte zu. Beide kleben am oberen Rand
 * desselben scrollenden Dialogs, und bei gleicher Ebene gewinnt das Element,
 * das später im Dokument steht — das ist der Editor.
 *
 * Deshalb: Der Kopf des Dialogs liegt über allem, was im Dialog scrollt, und er
 * trägt eine eigene Fläche, damit darunter nichts durchscheint.
 */
describe('Dialog: der Schliessknopf bleibt sichtbar', () => {
  /** Liest den Wert einer Eigenschaft aus genau einem Regelblock. */
  const wert = (selektor, eigenschaft) => {
    const block = style.match(new RegExp(`\\${selektor}\\s*\\{([^}]*)\\}`))
    if (!block) return null
    const treffer = block[1].match(new RegExp(`${eigenschaft}:\\s*([^;]+);`))
    return treffer ? treffer[1].trim() : null
  }

  it('der Dialogkopf liegt über der Werkzeugleiste des Editors', () => {
    const kopf = Number(wert('.pw-modal-kopf', 'z-index'))
    const werkzeugleiste = Number(wert('.pw-wysiwyg__toolbar', 'z-index'))

    expect(kopf, 'Der Dialogkopf hat keine Ebene').toBeGreaterThan(0)
    expect(werkzeugleiste, 'Die Werkzeugleiste hat keine Ebene').toBeGreaterThan(0)
    expect(kopf, 'Die Werkzeugleiste deckt den Schliessknopf zu').toBeGreaterThan(werkzeugleiste)
  })

  it('der Kopf ohne Titel trägt trotzdem eine Fläche', () => {
    const flaeche = wert('.pw-modal-kopf-leer', 'background')

    expect(flaeche, 'Der Kopf ohne Titel hat keine Angabe zur Fläche').not.toBeNull()
    expect(flaeche, 'Durch den Kopf scheint der Inhalt hindurch').not.toBe('transparent')
  })
})
