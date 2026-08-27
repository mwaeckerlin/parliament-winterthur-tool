import { describe, it, expect, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Protokoll from '../components/Protokoll.vue'
import axios from '@nextcloud/axios'

function fixture() {
  return {
    ereignisse: [
      { id: 3, zeitpunkt: 1756200000, art: 'fehler', bereich: 'budget', erfolg: false, titel: 'Budget 2026 neu einlesen fehlgeschlagen', meldung: 'Teil B lieferte keine Produktegruppen', ausgeloestVon: 'admin' },
      { id: 2, zeitpunkt: 1756100000, art: 'budget_reimport', bereich: 'budget', erfolg: true, titel: 'Budget 2026 neu eingelesen', meldung: '49 Produktegruppen, 120 Investitionen', ausgeloestVon: 'admin' },
      { id: 1, zeitpunkt: 1756000000, art: 'sync', bereich: '', erfolg: true, titel: 'Synchronisation abgeschlossen', meldung: '3 neu, 1 geändert', ausgeloestVon: 'auto' },
    ],
  }
}

// F105: das Protokoll zeigt die Historie der Synchronisationen und Budget-Importe
// (neueste zuerst) und markiert Fehler-Ereignisse (Parsing-Probleme).
describe('Protokoll', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: fixture() })
  })

  it('lädt das Protokoll und zeigt jedes Ereignis mit Titel und Meldung', async () => {
    const w = mount(Protokoll)
    await flushPromises()
    expect(axios.get.mock.calls.some(c => String(c[0]).includes('/protokoll'))).toBe(true)
    expect(w.vm.ereignisse.length).toBe(3)
    const html = w.html()
    expect(html).toContain('Synchronisation abgeschlossen')
    expect(html).toContain('49 Produktegruppen, 120 Investitionen')
    // Der Parsing-Fehler ist der dokumentierte Ort für Probleme.
    expect(html).toContain('Teil B lieferte keine Produktegruppen')
  })

  it('hebt ein Fehler-Ereignis hervor', async () => {
    const w = mount(Protokoll)
    await flushPromises()
    const fehler = w.findAll('.pw-protokoll-eintrag').find(e => e.classes().includes('pw-protokoll-fehler'))
    expect(fehler).toBeTruthy()
    expect(fehler.text()).toContain('Budget 2026 neu einlesen fehlgeschlagen')
  })

  it('übersetzt die Art in ein Label', async () => {
    const w = mount(Protokoll)
    await flushPromises()
    expect(w.vm.artLabel('budget_reimport')).toBe('Budget neu eingelesen')
    expect(w.vm.artLabel('sync')).toBe('Synchronisation')
  })

  // Ein chronologisches Protokoll gehört als einspaltige Liste (neueste oben),
  // nie als mehrspaltige Kachelwand — sonst liest die Zeitfolge links-rechts
  // statt oben-nach-unten. Diese Struktur wird festgenagelt.
  it('rendert die Ereignisse als einspaltige Liste, nicht als Card-Grid', async () => {
    const w = mount(Protokoll)
    await flushPromises()
    const liste = w.find('ul.pw-protokoll-liste')
    expect(liste.exists()).toBe(true)
    expect(w.find('.pw-card-grid').exists()).toBe(false)
    const eintraege = liste.findAll('li.pw-protokoll-eintrag')
    expect(eintraege.length).toBe(3)
    // Reihenfolge = Fixture-Reihenfolge (neueste zuerst, wie vom Server geliefert).
    expect(eintraege[0].text()).toContain('Budget 2026 neu einlesen fehlgeschlagen')
    expect(eintraege[2].text()).toContain('Synchronisation abgeschlossen')
  })
})
