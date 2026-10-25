import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Die Seite im Budgetbuch (F129).
 *
 * Marc am 24.10.2026: «Zu jedem Punkt, zu jeder Produktegruppe in jeder Ansicht
 * schreibe die Seitennummer der PG im Budgetbuch auf die Karte, damit man die
 * Position jederzeit auch im Buch schnell finden kann.»
 *
 * vitest läuft im Projekt-Root.
 */
const SUMMEN = { ausgaben: 1000, einnahmen: 900, ergebnis: -100, stellen: 1 }

function ansicht(buchseite) {
  return {
    jahr: { jahr: 2027, steuerfuss: 125, steuerfussEffektiv: 125 },
    summen: SUMMEN,
    summenStadtrat: SUMMEN,
    departemente: ['Finanzen'],
    eigeneFraktion: 'SVP-Fraktion',
    produktegruppen: [{
      code: '240', name: 'Immobilien', departement: 'Finanzen', buchseite,
      globalkredit: { soll: 1000, sollFraktion: 900, vorjahr: 1000 },
      stellen: { soll: 1, sollFraktion: 1, vorjahr: 1 },
      zielvorgaben: [], kostenzeilen: [], produkte: [],
    }],
    investitionen: [],
    antraege: [{
      id: 5, bereich: 'globalbudget', zielTyp: 'produktegruppe', zielRef: '240',
      betragDelta: -100, begruendung: 'Weniger Unterhalt', entscheid: 'offen', aktionen: [],
    }],
    pauschalantraege: [],
  }
}

function mounten(buchseite) {
  axios.get.mockImplementation((url) => {
    const u = String(url)
    if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
    if (u.includes('nc/users') || u.includes('/dokumente')) { return Promise.resolve({ data: [] }) }
    return Promise.resolve({ data: ansicht(buchseite) })
  })
  return mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
}

describe('Seite im Budgetbuch', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('steht auf der Karte der Produktegruppe', async () => {
    const w = mounten(75)
    await flushPromises()
    const seite = w.find('.pw-buchseite')
    expect(seite.exists(), 'die Seitenzahl fehlt auf der Karte').toBe(true)
    expect(seite.text()).toContain('75')
  })

  it('steht in der Antragsliste bei der Position', async () => {
    const w = mounten(75)
    await flushPromises()
    const zeile = w.vm.antraegeNachDepartement[0].antraege[0]
    expect(zeile.posName).toContain('Buch S.')
    expect(zeile.posName).toContain('75')
  })

  it('bleibt weg, wo das Buch keine Seite liefert', async () => {
    const w = mounten(0)
    await flushPromises()
    expect(w.find('.pw-buchseite').exists(), 'eine Null steht als Seitenzahl da').toBe(false)
    expect(w.vm.antraegeNachDepartement[0].antraege[0].posName).not.toContain('Buch S.')
  })
})
