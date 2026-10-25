import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Budgetliste from '../components/Budgetliste.vue'
import axios from '@nextcloud/axios'

/**
 * F123: Über der Grafik steht, was die Kreise messen — Franken oder Stellen.
 * Der Stellen-Modus zeigt den Stellenplan je Produktegruppe, rot wo Stellen
 * dazukommen, grün wo welche wegfallen.
 *
 * vitest läuft im Projekt-Root.
 */
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))
vi.mock('@nextcloud/dialogs', () => ({
  showError: () => {}, showSuccess: () => {}, showWarning: () => {}, showInfo: () => {},
}))

const ansicht = () => ({
  jahr: { jahr: 2027, steuerfuss: 125, steuerertrag: 250000000, personalsteuer: 24, novemberbriefImportiert: false, steuerfussAutomatik: true },
  departemente: ['Präsidiales'],
  produktegruppen: [
    {
      id: 1, code: '121', name: 'Personalamt', departement: 'Präsidiales',
      globalkredit: { ist: 0, sollVorjahr: 0, soll: 1000, plan1: 0, plan2: 0, plan3: 0 },
      aufwand: { ist: 0, sollVorjahr: 0, soll: 2000 }, ertrag: { ist: 0, sollVorjahr: 0, soll: 1000 },
      stellen: { ist: 19.5, sollVorjahr: 19.5, soll: 19.5 }, produkte: [],
    },
  ],
  investitionen: [], antraege: [], pauschalantraege: [],
  verteilung: { automatikEin: true, zielModus: 'schwarze_null', zielBetrag: 0, haltung: 'einreichen', ausnahmen: [] },
  summen: { ausgaben: 2000, einnahmen: 1000, ergebnis: -1000, stellen: 19.5, ausgabenDiff: 0, einnahmenDiff: 0, ergebnisDiff: 0, stellenDiff: 0 },
  standardBetragProStelle: 200000, kommissionZuordnung: [], eigeneFraktion: 'SVP',
})

describe('Umschalter der Grafik (F123)', () => {
  beforeEach(() => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
      return Promise.resolve({ data: ansicht() })
    })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: ansicht() })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  it('beginnt bei den Franken', async () => {
    const w = shallowMount(Budgetliste)
    await flushPromises()
    expect(w.vm.grafikMasseinheit).toBe('franken')
    expect(w.findComponent({ name: 'BudgetGrafik' }).props('masseinheit')).toBe('franken')
  })

  it('bietet die beiden Masse zur Wahl und reicht die Wahl an die Grafik weiter', async () => {
    const w = shallowMount(Budgetliste)
    await flushPromises()
    const knoepfe = w.findAll('.pw-grafik-mass button')
    expect(knoepfe.map(k => k.text())).toEqual(['Franken', 'Stellen'])
    await knoepfe[1].trigger('click')
    expect(w.vm.grafikMasseinheit).toBe('stellen')
    expect(w.findComponent({ name: 'BudgetGrafik' }).props('masseinheit')).toBe('stellen')
  })

  it('hebt das gewählte Mass lesbar hervor', () => {
    // Befund am Bild vom 19.10.2026: «Stellen» stand als blasses Grau auf
    // blassem Grau und war kaum zu lesen. Nextcloud setzt auf jeden Knopf
    // seinen eigenen Grund; die Hervorhebung braucht darum Nachdruck und eine
    // Schriftfarbe, die zum Grund gehört. vitest läuft im Projekt-Root.
    const css = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')
    const regel = css.slice(css.indexOf('.pw-grafik-mass-gewaehlt'))
    expect(css, 'keine Regel für das gewählte Mass').toMatch(/\.pw-grafik-mass-gewaehlt/)
    expect(regel.slice(0, 400), 'der Grund des gewählten Masses setzt sich nicht durch')
      .toMatch(/background:\s*var\(--color-primary-element\)\s*!important/)
    expect(regel.slice(0, 400), 'die Schrift des gewählten Masses setzt sich nicht durch')
      .toMatch(/color:\s*var\(--color-primary-element-text\)\s*!important/)
  })

  it('sagt am gewählten Mass, dass es gewählt ist', async () => {
    const w = shallowMount(Budgetliste)
    await flushPromises()
    const knoepfe = w.findAll('.pw-grafik-mass button')
    expect(knoepfe[0].attributes('aria-pressed')).toBe('true')
    await knoepfe[1].trigger('click')
    expect(w.findAll('.pw-grafik-mass button')[1].attributes('aria-pressed')).toBe('true')
  })
})
