import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FilterPanel from '../components/FilterPanel.vue'

/**
 * Ein angetipptes Filterfeld rückt an den Anfang des Filterbereichs.
 *
 * Befund von Marc am 19.10.2026 vom iPhone, hier nachgestellt und gemessen:
 * Tippt man im Filterbereich ein Auswahlfeld an, bekommt sein Suchfeld den
 * Fokus, die Bildschirmtastatur fährt hoch und lässt rund die Hälfte der Fläche
 * übrig. Die Liste öffnet sich nach unten — gemessen lag ihr erster Eintrag bei
 * 481 Punkten, während nur 439 sichtbar blieben. Sie stand also hinter der
 * Tastatur, und das Feld sah aus, als liesse es sich nicht bedienen.
 *
 * Darum rückt das Feld beim Fokus an den Anfang des scrollenden
 * Filterbereichs: Darunter ist dann Platz für seine Liste.
 *
 * vitest läuft im Projekt-Root.
 */
describe('Fokus im Filterbereich', () => {
  it('rückt das angetippte Feld an den Anfang', async () => {
    const w = mount(FilterPanel, {
      slots: { default: '<div class="v-select"><input class="vs__search"></div>' },
      global: { stubs: { NcButton: true } },
    })
    const feld = w.find('.v-select').element
    const gerueckt = vi.fn()
    feld.scrollIntoView = gerueckt

    await w.find('.vs__search').trigger('focusin')

    expect(gerueckt, 'das Feld rückt beim Fokus nicht an den Anfang').toHaveBeenCalled()
    expect(gerueckt.mock.calls[0][0], 'es rückt nicht an den Anfang, sondern irgendwohin')
      .toMatchObject({ block: 'start' })
  })

  it('rührt nichts an, wenn der Fokus kein Feld trifft', async () => {
    const w = mount(FilterPanel, {
      slots: { default: '<div class="pw-text">kein Feld</div>' },
      global: { stubs: { NcButton: true } },
    })
    // Ohne Feld darf der Griff ins Leere nichts auslösen und nichts werfen.
    await expect(w.find('.pw-text').trigger('focusin')).resolves.toBeUndefined()
  })
})
