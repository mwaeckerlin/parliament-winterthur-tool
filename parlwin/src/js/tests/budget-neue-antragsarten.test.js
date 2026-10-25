import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Drei Antragsarten, die das Parlament kennt und das Werkzeug bisher nicht
 * (F135, F136, F137).
 *
 * Belegt in den Drehbüchern der Budgetberatungen:
 * - «Antrag SBK: Das Programm 2026 ist infolge Projektabbruch um CHF 263'000
 *   auf 0 zu kürzen.» (Verpflichtungskredit)
 * - «Antrag SBK: Beim Ausführungskredit ist das Bewilligungsorgan von S auf #
 *   anzupassen.» (Bewilligungsorgan)
 * - «Antrag SSK: Reduktion des Globalkredits um CHF 26'000 im
 *   Departementssekretariat Sicherheit und Umwelt. (Wird über Umlagen verteilt
 *   auf die Globalkredite der Produktegruppen).» (Departementssekretariat)
 *
 * vitest läuft im Projekt-Root.
 */
const SUMMEN = { ausgaben: 1000, einnahmen: 900, ergebnis: -100, stellen: 1 }

const ANSICHT = {
  jahr: {
    jahr: 2027,
    steuerfuss: 125,
    steuerfussEffektiv: 125,
    sekretariate: [
      { departement: 'Finanzen', saldo: 1616148, aufwand: 1623648, ertrag: -7500 },
    ],
  },
  summen: SUMMEN,
  summenStadtrat: SUMMEN,
  departemente: ['Finanzen'],
  eigeneFraktion: 'SVP-Fraktion',
  produktegruppen: [{
    code: '240', name: 'Immobilien', departement: 'Finanzen', buchseite: 75,
    globalkredit: { soll: 1000, sollFraktion: 900, vorjahr: 1000 },
    stellen: { soll: 1, sollFraktion: 1, vorjahr: 1 },
    zielvorgaben: [], kostenzeilen: [], produkte: [],
  }],
  investitionen: [{
    id: 7, projekt: 'Schulhaus', departement: 'Finanzen', bu: 300000,
    fap1: 0, fap2: 0, fap3: 0, bereitsGetaetigt: 0, gesamtkosten: 1296000, planungskosten: 0,
    konten: [{ konto: '520000', name: 'Software', betrag: 263000, kredit: 1296000, bewilligt: '02.07.2025' }],
  }],
  antraege: [],
  pauschalantraege: [],
}

function mounten() {
  axios.get.mockImplementation((url) => {
    const u = String(url)
    if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
    if (u.includes('nc/users') || u.includes('/dokumente')) { return Promise.resolve({ data: [] }) }
    return Promise.resolve({ data: ANSICHT })
  })
  axios.post.mockResolvedValue({ data: {} })
  return mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
}

describe('Antrag auf ein Departementssekretariat', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('zeigt das Sekretariat mit seinem Aufwand über den Produktegruppen', async () => {
    const w = mounten()
    await flushPromises()
    const karte = w.find('.pw-sekretariat')
    expect(karte.exists(), 'das Departementssekretariat fehlt').toBe(true)
    expect(karte.text()).toContain('Departementssekretariat')
    expect(karte.text()).toContain('Finanzen')
  })

  it('nennt die Umlage auf die Produktegruppen', async () => {
    const w = mounten()
    await flushPromises()
    expect(w.find('.pw-sekretariat').text()).toContain('Umlage')
  })

  it('schickt den Antrag mit dem Departement als Ziel', async () => {
    const w = mounten()
    await flushPromises()
    w.vm.neuSekretariat.Finanzen.betrag = '26000'
    await w.vm.antragSekretariat('Finanzen')
    const [, daten] = axios.post.mock.calls[0]
    expect(daten.bereich).toBe('departementssekretariat')
    expect(daten.zielRef).toBe('Finanzen')
    expect(daten.betragDelta).toBe(-26000)
  })

  it('steht im Tab «Globalbudgets», nicht beim Personalbestand', async () => {
    // Die Karte trägt einen Aufwand in Franken. Beim Personalbestand stehen
    // Stellen; zwei verschiedene Grössen nebeneinander sind keine Übersicht,
    // und die erste Karte dieses Tabs wäre dann eine ohne Stellen (gemessen
    // am 25.10.2026 im Browsertest F86).
    const w = mounten()
    await flushPromises()
    const panels = w.findAll('.pw-budget-tabpanel')
    const personal = panels[1]
    expect(personal.exists(), 'der Tab «Personalbestand» fehlt').toBe(true)
    expect(personal.find('.pw-sekretariat').exists(), 'die Sekretariatskarte steht beim Personalbestand').toBe(false)
    expect(panels[0].find('.pw-sekretariat').exists(), 'die Sekretariatskarte fehlt bei den Globalbudgets').toBe(true)
  })

  it('steht nicht da, wo das Buch kein Sekretariat liefert', async () => {
    const ohne = { ...ANSICHT, jahr: { ...ANSICHT.jahr, sekretariate: [] } }
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
      if (u.includes('nc/users') || u.includes('/dokumente')) { return Promise.resolve({ data: [] }) }
      return Promise.resolve({ data: ohne })
    })
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    expect(w.find('.pw-sekretariat').exists()).toBe(false)
  })
})

describe('Antrag auf einen Verpflichtungskredit', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('führt je Kredit eine Zeile mit Konto, Bezeichnung und Betrag', async () => {
    const w = mounten()
    await flushPromises()
    w.vm.aktiverTab = 'investition'
    await flushPromises()
    const kredit = w.find('.pw-kredit')
    expect(kredit.exists(), 'kein Verpflichtungskredit sichtbar').toBe(true)
    expect(kredit.text()).toContain('520000')
    expect(kredit.text()).toContain('Software')
  })

  it('schickt den Antrag mit Vorhaben und Konto als Ziel', async () => {
    const w = mounten()
    await flushPromises()
    w.vm.neuKredit['7|520000'].betrag = '263000'
    await w.vm.antragKredit({ id: 7 }, '520000')
    const [, daten] = axios.post.mock.calls[0]
    expect(daten.bereich).toBe('verpflichtungskredit')
    expect(daten.zielRef).toBe('7|520000')
    expect(daten.betragDelta).toBe(-263000)
  })
})

describe('Antrag auf das Bewilligungsorgan', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('schickt den neuen Wert statt eines Betrags', async () => {
    const w = mounten()
    await flushPromises()
    w.vm.neuKredit['7|520000'].neuerWert = '#'
    await w.vm.antragKredit({ id: 7 }, '520000')
    const [, daten] = axios.post.mock.calls[0]
    expect(daten.bereich).toBe('bewilligungsorgan')
    expect(daten.neuerWert).toBe('#')
    expect(daten.zielRef).toBe('7|520000')
  })

  it('legt ohne Betrag und ohne neuen Wert nichts an', async () => {
    const w = mounten()
    await flushPromises()
    await w.vm.antragKredit({ id: 7 }, '520000')
    expect(axios.post).not.toHaveBeenCalled()
  })
})
