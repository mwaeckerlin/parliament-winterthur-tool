import { describe, it, expect } from 'vitest'
import { dropdownLage, filterDropdownPosition } from '../filter-dropdown'

/**
 * Die Liste eines Filterfeldes bleibt im sichtbaren Bereich.
 *
 * Gemessen am 19.10.2026 auf der iPhone-Maschine: Mit hochgefahrener
 * Bildschirmtastatur blieben 439 Punkte sichtbar. Das Feld selbst lag dann bei
 * 513 bis 547, also bereits unter der Tastatur, und seine Liste bei 481. Das
 * Fenster schrumpft dabei nicht, nur der sichtbare Teil; danach rechnet diese
 * Funktion — und zwar so, dass die Liste im sichtbaren Teil landet, auch wenn
 * das Feld es nicht mehr ist.
 *
 * vitest läuft im Projekt-Root.
 */

/** Was in jedem Fall gelten muss: Die Liste liegt ganz im sichtbaren Bereich. */
function imBild(lage, sichtbareHoehe, versatz = 0) {
  expect(lage.top, 'die Liste beginnt über dem sichtbaren Bereich').toBeGreaterThanOrEqual(versatz)
  expect(lage.top + lage.maxHoehe, 'die Liste endet unter dem sichtbaren Bereich')
    .toBeLessThanOrEqual(versatz + sichtbareHoehe)
}

describe('Lage der Filterliste', () => {
  it('öffnet nach unten, wo unten Platz ist', () => {
    const lage = dropdownLage({ top: 200, bottom: 240 }, 300, 844)
    expect(lage.oben).toBe(false)
    expect(lage.top).toBe(240)
    expect(lage.maxHoehe).toBe(300)
    imBild(lage, 844)
  })

  it('bleibt im Bild, wenn die Tastatur das Feld selbst verdeckt', () => {
    // Der gemessene Fall: Feld bei 513 bis 547, sichtbar 439, Liste 47 hoch.
    const lage = dropdownLage({ top: 513, bottom: 547 }, 47, 439)
    imBild(lage, 439)
  })

  it('öffnet nach oben, wenn die Tastatur den Platz darunter nimmt', () => {
    const lage = dropdownLage({ top: 300, bottom: 420 }, 200, 439)
    expect(lage.oben, 'die Liste bleibt unter der Tastatur').toBe(true)
    imBild(lage, 439)
  })

  it('kürzt die Liste auf den sichtbaren Bereich', () => {
    const lage = dropdownLage({ top: 100, bottom: 140 }, 600, 400)
    expect(lage.maxHoehe, 'die Liste ist höher als der sichtbare Bereich').toBeLessThanOrEqual(400)
    imBild(lage, 400)
  })

  it('rechnet den Versatz des sichtbaren Bereichs mit', () => {
    // Scrollt die Seite unter der Tastatur, beginnt der sichtbare Teil weiter
    // unten im Fenster; Feld und Liste bewegen sich mit.
    const lage = dropdownLage({ top: 500, bottom: 540 }, 200, 400, 300)
    expect(lage.oben).toBe(false)
    // Unter dem Feld begänne sie bei 540 und endete bei 740 — der sichtbare
    // Teil endet bei 700, also rückt sie so weit hoch, wie sie muss.
    expect(lage.top, 'die Liste steht tiefer als nötig').toBeLessThanOrEqual(540)
    imBild(lage, 400, 300)
  })
})

describe('Die Filterliste folgt ihrem Inhalt', () => {
  /**
   * jsdom rechnet kein Layout; die Liste meldet darum die Höhen, die der
   * Telefonlauf vom 01.11.2026 am iPhone gemessen hat: leer 31 Punkte, mit
   * den acht Departementen rund 310.
   */
  function aufbauen() {
    const feld = document.createElement('div')
    feld.getBoundingClientRect = () => ({ top: 200, bottom: 240, left: 12, width: 276 })
    const liste = document.createElement('ul')
    let hoehe = 31
    Object.defineProperty(liste, 'scrollHeight', { get: () => hoehe })
    const leer = document.createElement('li')
    leer.className = 'vs__no-options'
    liste.appendChild(leer)
    document.body.appendChild(liste)
    const komponente = { $refs: { toggle: feld }, $el: document.createElement('div') }
    return { liste, komponente, leer, setzeHoehe: (h) => { hoehe = h } }
  }

  it('wächst mit, wenn die Einträge erst nach dem Öffnen ankommen', async () => {
    const { liste, komponente, leer, setzeHoehe } = aufbauen()
    const aufraeumen = filterDropdownPosition(liste, komponente)
    expect(liste.style.maxHeight).toBe('31px')

    // Die Einträge kommen an, so wie vue-select sie setzt: Die leere Zeile
    // geht, die Optionen kommen. Kein Fenster- oder Scroll-Ereignis.
    setzeHoehe(310)
    leer.remove()
    for (let i = 0; i < 8; i++) {
      const option = document.createElement('li')
      option.className = 'vs__dropdown-option'
      option.textContent = `Departement ${i + 1}`
      liste.appendChild(option)
    }
    await new Promise((fertig) => setTimeout(fertig, 0))

    expect(liste.style.maxHeight, 'die Liste behält die Höhe der leeren Liste').toBe('310px')
    aufraeumen()
    liste.remove()
  })

  it('lässt die Liste nach dem Schliessen in Ruhe', async () => {
    const { liste, komponente, setzeHoehe } = aufbauen()
    const aufraeumen = filterDropdownPosition(liste, komponente)
    aufraeumen()
    setzeHoehe(310)
    liste.appendChild(document.createElement('li'))
    await new Promise((fertig) => setTimeout(fertig, 0))
    expect(liste.style.maxHeight, 'ein Beobachter läuft nach dem Schliessen weiter').toBe('31px')
    liste.remove()
  })
})
