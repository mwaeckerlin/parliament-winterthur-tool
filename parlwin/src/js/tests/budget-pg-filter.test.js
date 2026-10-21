import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Budgetliste from '../components/Budgetliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))
vi.mock('@nextcloud/dialogs', () => ({
  showError: () => {},
  showSuccess: () => {},
  showWarning: () => {},
  showInfo: () => {},
}))

/**
 * Rückmeldung der Fraktion: Die Investitionsrechnung führt so viele Vorhaben,
 * dass sie ohne Filter unübersichtlich ist. Gewünscht sind zwei Filter
 * hintereinander: zuerst das Departement, danach die Produktegruppe, und diese
 * bietet nur die Produktegruppen des gewählten Departements an.
 */
function ansicht() {
  const gruppe = (id, code, name, departement) => ({
    id, code, name, departement,
    globalkredit: { ist: 0, sollVorjahr: 0, soll: 1000, plan1: 0, plan2: 0, plan3: 0 },
    aufwand: { ist: 0, sollVorjahr: 0, soll: 1000 },
    ertrag: { ist: 0, sollVorjahr: 0, soll: 0 },
    stellen: { ist: 0, sollVorjahr: 0, soll: 1 },
    produkte: [],
  })
  const projekt = (id, departement, cluster, projekt) => ({
    id, departement, cluster, projekt,
    bu: 1000, fap1: 0, fap2: 0, fap3: 0,
    gesamtkosten: 0, bereitsGetaetigt: 0, planungskosten: 0, konten: [],
  })
  return {
    jahr: { jahr: 2026, steuerfuss: 125, steuerertrag: 0, personalsteuer: 24, novemberbriefImportiert: false, steuerfussAutomatik: true },
    departemente: ['Bau und Mobilität', 'Finanzen'],
    produktegruppen: [
      gruppe(1, '520', 'Tiefbau', 'Bau und Mobilität'),
      gruppe(2, '530', 'Stadtgrün', 'Bau und Mobilität'),
      gruppe(3, '263', 'Städtische Allgemeinkosten/Erlöse', 'Finanzen'),
    ],
    investitionen: [
      projekt(5, 'Bau und Mobilität', 'Tiefbau', '5001240 Tösstalstrasse'),
      projekt(6, 'Bau und Mobilität', 'Stadtgrün', '5002000 Parkanlage'),
      projekt(7, 'Finanzen', 'Städtische Allgemeinkosten/Erlöse', '5009000 Beteiligung'),
    ],
    antraege: [],
    verteilung: { automatikEin: false, zielModus: 'schwarze_null', zielBetrag: 0, haltung: 'einreichen', ausnahmen: [] },
    pauschalantraege: [],
    summen: { ausgaben: 0, einnahmen: 0, ergebnis: 0, stellen: 0 },
    standardBetragProStelle: 200000,
    kommissionZuordnung: [],
    eigeneFraktion: 'GLP',
  }
}

async function mitAnsicht() {
  const wrapper = shallowMount(Budgetliste)
  await flushPromises()
  return wrapper
}

describe('Budget: Filter nach Produktegruppe', () => {
  beforeEach(() => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      return Promise.resolve({ data: ansicht() })
    })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: ansicht() })
  })

  it('bietet ohne Departement alle vorkommenden Produktegruppen an', async () => {
    const w = await mitAnsicht()
    expect(w.vm.produktegruppeOptionen.map(o => o.value)).toEqual([
      'Stadtgrün', 'Städtische Allgemeinkosten/Erlöse', 'Tiefbau',
    ])
  })

  it('bietet nach der Wahl eines Departements nur dessen Produktegruppen an', async () => {
    const w = await mitAnsicht()
    w.vm.departementOption = { value: 'Bau und Mobilität', label: 'Bau und Mobilität' }
    await flushPromises()
    expect(w.vm.produktegruppeOptionen.map(o => o.value)).toEqual(['Stadtgrün', 'Tiefbau'])
  })

  it('grenzt die Globalbudgets auf die gewählte Produktegruppe ein', async () => {
    const w = await mitAnsicht()
    w.vm.produktegruppeOption = { value: 'Tiefbau', label: 'Tiefbau' }
    await flushPromises()
    const gruppen = w.vm.gruppenNachDepartement.flatMap(d => d.gruppen.map(g => g.name))
    expect(gruppen).toEqual(['Tiefbau'])
  })

  it('grenzt die Investitionsprojekte auf die gewählte Produktegruppe ein', async () => {
    const w = await mitAnsicht()
    w.vm.produktegruppeOption = { value: 'Stadtgrün', label: 'Stadtgrün' }
    await flushPromises()
    const projekte = w.vm.investitionenNachDepartement.flatMap(d => d.projekte.map(p => p.projekt))
    expect(projekte).toEqual(['5002000 Parkanlage'])
  })

  it('leert die Produktegruppe, sobald sie nicht mehr zum Departement gehört', async () => {
    const w = await mitAnsicht()
    w.vm.produktegruppeOption = { value: 'Tiefbau', label: 'Tiefbau' }
    await flushPromises()
    w.vm.departementOption = { value: 'Finanzen', label: 'Finanzen' }
    await flushPromises()
    expect(w.vm.produktegruppeOption, 'Produktegruppe blieb trotz fremdem Departement stehen').toBeNull()
  })

  it('«Filter zurücksetzen» leert auch die Produktegruppe', async () => {
    const w = await mitAnsicht()
    w.vm.produktegruppeOption = { value: 'Tiefbau', label: 'Tiefbau' }
    await flushPromises()
    w.vm.filterZuruecksetzen()
    await flushPromises()
    expect(w.vm.produktegruppeOption).toBeNull()
  })
})
