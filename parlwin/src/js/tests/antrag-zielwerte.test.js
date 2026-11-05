import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Ein Antrag auf Zielwerte zeigt die Zielwerte.
 *
 * Der Antrag auf die Produktegruppe Immobilien (240) im Budget 2027 ändert
 * drei Zielwerte. Die Karte und die Antragsliste nennen sie, denn die
 * Begründung allein sagt nicht, worüber abgestimmt wird.
 *
 * Gezeigt wird je Änderung die Messgrösse, der heutige Soll und der neue.
 *
 * vitest läuft im Projekt-Root.
 */
const ZIELVORGABEN = [
  { zielNummer: 1, zielTitel: '1 Wirtschaftlichkeit', unterTitel: 'Minimale Nettorendite', messgroesse: 'Wohnliegenschaften (in %)', soll: '1.00' },
  { zielNummer: 1, zielTitel: '1 Wirtschaftlichkeit', unterTitel: 'Minimale Nettorendite', messgroesse: 'Gewerbeliegenschaften (in %)', soll: '1.00' },
  { zielNummer: 1, zielTitel: '1 Wirtschaftlichkeit', unterTitel: 'Durchschnittliche Nettorendite', messgroesse: 'Wohnliegenschaften (in %)', soll: '2.31' },
]

const ANTRAG = {
  id: 7,
  bereich: 'globalbudget',
  zielRef: '240',
  phase: 'fraktion',
  betragDelta: 0,
  antragsteller: 'Marc Wäckerlin',
  begruendung: 'Die bisherigen Zielwerte von 1 % liegen deutlich unter den erzielten Nettorenditen.',
  entscheid: 'offen',
  haltung: 'einreichen',
  herkunft: 'eigene',
  automatisch: false,
  zielAenderungen: [
    { zielNummer: 1, unterTitel: 'Durchschnittliche Nettorendite', messgroesse: 'Wohnliegenschaften (in %)', neuerWert: '4' },
    { zielNummer: 1, unterTitel: 'Minimale Nettorendite', messgroesse: 'Gewerbeliegenschaften (in %)', neuerWert: '5' },
  ],
  aufteilung: [],
}

const SUMMEN = { aufwand: 1000, ertrag: 1000, ergebnis: 0, stellen: 0, steuerfuss: 125 }

const ANSICHT = {
  jahr: { jahr: 2027, steuerfuss: 125, steuerfussEffektiv: 125, steuerfussAutomatik: false },
  departemente: ['Finanzen'],
  summen: SUMMEN,
  summenStadtrat: SUMMEN,
  verteilung: { automatikEin: false, zielModus: 'schwarze_null', zielBetrag: 0, haltung: 'einreichen', ausnahmen: [] },
  eigeneFraktion: 'Musterpartei-Fraktion (MPW)',
  produktegruppen: [{
    id: 1, code: '240', name: 'Immobilien', departement: 'Finanzen',
    globalkredit: { ist: 0, sollVorjahr: 0, soll: 55764, sollFraktion: 55764, plan1: 0, plan2: 0, plan3: 0 },
    aufwand: { ist: 0, sollVorjahr: 0, soll: 1000 },
    ertrag: { ist: 0, sollVorjahr: 0, soll: 1000 },
    stellen: { ist: 0, sollVorjahr: 0, soll: 0, sollFraktion: 0 },
    zielvorgaben: ZIELVORGABEN, kostenzeilen: [], produkte: [],
  }],
  investitionen: [],
  antraege: [ANTRAG],
  pauschalantraege: [],
}

async function liste(antraege = ANSICHT.antraege) {
  axios.get.mockImplementation((url) => {
    const u = String(url)
    if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [2027], importierbar: [] } }) }
    if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
    return Promise.resolve({ data: { ...ANSICHT, antraege } })
  })
  const w = mount(Budgetliste, { global: { stubs: { Teleport: true } } })
  await flushPromises()
  return w
}

describe('Zielwerte eines Antrags', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('stehen auf der Karte der Produktegruppe', async () => {
    const w = await liste()
    const karte = w.find('.pw-budget-tabpanel')
    expect(karte.exists()).toBe(true)
    const text = w.text()
    expect(text, 'die Messgrösse fehlt auf der Karte').toContain('Wohnliegenschaften (in %)')
    // Der heutige Soll ist der der durchschnittlichen Nettorendite (2.31), nicht
    // der der minimalen (1.00): den Unterschied macht der Unterabschnitt.
    expect(text, 'der heutige Soll fehlt oder kommt von der falschen Vorgabe').toContain('2.31')
    expect(text, 'der neue Soll fehlt').toContain('4')
    expect(text, 'der Unterabschnitt fehlt im Namen').toContain('Durchschnittliche Nettorendite')
  })

  it('stehen in der Antragsliste', async () => {
    const w = await liste()
    await w.vm.tabWechseln('antraege')
    await flushPromises()
    const zeile = w.find('.pw-antrag-zeile')
    expect(zeile.exists(), 'die Antragsliste hat keine Zeile').toBe(true)
    expect(zeile.text(), 'die Zielwerte fehlen in der Antragsliste').toContain('Gewerbeliegenschaften (in %)')
    expect(zeile.text()).toContain('5')
  })

  /**
   * Ein Antrag auf Zielwerte hat keinen Betrag. Der geänderte Zielwert steht
   * darum dort, wo sonst der Betrag steht: in der Spalte, in der man liest,
   * worüber abgestimmt wird, und nicht am Ende der Begründung.
   */
  it('stehen in der Antragsliste in der Betragsspalte, nicht in der Begründung', async () => {
    const w = await liste()
    await w.vm.tabWechseln('antraege')
    await flushPromises()
    const zeile = w.find('.pw-antrag-zeile')

    const betrag = zeile.find('.pw-antrag-betrag')
    expect(betrag.exists(), 'die Antragszeile hat keine Betragsspalte').toBe(true)
    expect(betrag.text(), 'der geänderte Zielwert steht nicht in der Betragsspalte')
      .toContain('Durchschnittliche Nettorendite — Wohnliegenschaften (in %): 2.31 → 4')
    expect(betrag.text()).toContain('Minimale Nettorendite — Gewerbeliegenschaften (in %): 1.00 → 5')

    const grund = zeile.find('.pw-antrag-begruendung')
    expect(grund.text(), 'die Begründung wiederholt die Zielwerte')
      .not.toContain('Gewerbeliegenschaften (in %)')
    expect(grund.text(), 'die Begründung selbst fehlt')
      .toContain('Die bisherigen Zielwerte von 1 % liegen deutlich unter den erzielten Nettorenditen.')
  })

  /**
   * Ein Antrag mit Betrag UND Zielwerten verliert keinen der beiden: Der Betrag
   * bleibt in seiner Spalte, die Zielwerte folgen der Begründung, wie im PDF.
   */
  it('lassen den Betrag in seiner Spalte, wenn der Antrag auch einen trägt', async () => {
    const w = await liste([{ ...ANTRAG, betragDelta: -250000 }])
    await w.vm.tabWechseln('antraege')
    await flushPromises()
    const zeile = w.find('.pw-antrag-zeile')

    const betrag = zeile.find('.pw-antrag-betrag')
    expect(betrag.text(), 'der Betrag fehlt in der Betragsspalte').toMatch(/250/)
    expect(betrag.text(), 'die Zielwerte verdrängen den Betrag').not.toContain('Gewerbeliegenschaften')
    expect(zeile.find('.pw-antrag-begruendung').text(), 'die Zielwerte fehlen ganz')
      .toContain('Minimale Nettorendite — Gewerbeliegenschaften (in %): 1.00 → 5')
  })

  it('nennen je Änderung Messgrösse, heutigen und neuen Soll', async () => {
    const w = await liste()
    const zeilen = w.vm.zielAenderungZeilen(ANTRAG)
    expect(zeilen).toEqual([
      'Durchschnittliche Nettorendite — Wohnliegenschaften (in %): 2.31 → 4',
      'Minimale Nettorendite — Gewerbeliegenschaften (in %): 1.00 → 5',
    ])
  })
})
