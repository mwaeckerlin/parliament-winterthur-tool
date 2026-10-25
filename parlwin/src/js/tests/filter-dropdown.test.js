import { describe, it, expect } from 'vitest'
import { dropdownLage } from '../filter-dropdown'

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
