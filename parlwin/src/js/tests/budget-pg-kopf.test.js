import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Budgetliste from '../components/Budgetliste.vue'
import BudgetAntragForm from '../components/BudgetAntragForm.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))
vi.mock('@nextcloud/dialogs', () => ({
  showError: () => {}, showSuccess: () => {}, showWarning: () => {}, showInfo: () => {},
}))

// Ansicht in der Struktur der Budget-API (BudgetService::ansicht): die
// Produktegruppe trägt neben dem Wert des Stadtrats den Wert der Fraktion.
function ansicht() {
  return {
    jahr: { jahr: 2026, steuerfuss: 125, steuerertrag: 250000000, steuerfussAutomatik: false },
    departemente: ['Präsidiales'],
    produktegruppen: [
      {
        id: 1, code: '121', name: 'Personalamt', departement: 'Präsidiales',
        globalkredit: { ist: 4061060, sollVorjahr: 4395510, soll: 4950828, sollFraktion: 3713121 },
        aufwand: { ist: 0, sollVorjahr: 0, soll: 4950828 },
        ertrag: { ist: 0, sollVorjahr: 0, soll: 0 },
        stellen: { ist: 17.2, sollVorjahr: 16.85, soll: 19.5, sollFraktion: 18 },
        produkte: [], zielvorgaben: [], kostenzeilen: [],
      },
      {
        id: 2, code: '142', name: 'Stadtentwicklung', departement: 'Präsidiales',
        globalkredit: { ist: 0, sollVorjahr: 5451141, soll: 6206839, sollFraktion: 6206839 },
        aufwand: { ist: 0, sollVorjahr: 0, soll: 6206839 },
        ertrag: { ist: 0, sollVorjahr: 0, soll: 0 },
        stellen: { ist: 0, sollVorjahr: 8, soll: 8, sollFraktion: 8 },
        produkte: [], zielvorgaben: [], kostenzeilen: [],
      },
    ],
    investitionen: [],
    antraege: [
      {
        id: 10, bereich: 'globalbudget', zielRef: '121', betragDelta: -247541, prozentDelta: -5,
        stellenDelta: 0, antragsteller: 'SVP', begruendung: 'Sparen', automatisch: false,
        entscheid: 'offen', phase: 'fraktion', herkunft: 'eigene', haltung: 'einreichen',
        unterstuetzer: [{ key: 'svp', name: 'SVP' }], zielAenderungen: [], aufteilung: [],
      },
      {
        id: 11, bereich: 'globalbudget', zielRef: '121', betragDelta: -990166, prozentDelta: -20,
        stellenDelta: 0, antragsteller: '', begruendung: 'Pauschalkürzung', automatisch: true,
        entscheid: 'offen', phase: 'fraktion', herkunft: 'eigene', haltung: 'einreichen',
        unterstuetzer: [], zielAenderungen: [], aufteilung: [],
      },
    ],
    pauschalantraege: [],
    summen: { ausgaben: 11157667, einnahmen: 0, ergebnis: -11157667, stellen: 27.5 },
    summenStadtrat: { ausgaben: 11157667, einnahmen: 0, ergebnis: -11157667, stellen: 27.5 },
    standardBetragProStelle: 200000,
    kommissionZuordnung: [],
    eigeneFraktion: 'SVP',
  }
}

async function geladen() {
  const w = shallowMount(Budgetliste)
  await flushPromises()
  return w
}

// Alle Tabs stehen gleichzeitig im Dokument (v-show), darum die Karte immer im
// Bereich des gemeinten Tabs suchen: 0 = Globalbudgets, 1 = Personalbestand.
function karte(w, code, tab = 0) {
  return w.findAll('.pw-budget-tabpanel')[tab]
    .findAll('.pw-data-card')
    .find(k => k.text().includes('Produktegruppe ' + code))
}

describe('F116 — Produktegruppen-Karte: Stadtrat und Fraktion mit ihrer Differenz zum Vorjahr', () => {
  beforeEach(() => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      return Promise.resolve({ data: ansicht() })
    })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: {} })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  it('zeigt bei einer Produktegruppe mit Anträgen beide Werte, den Betrag der Fraktion nach Abzug der Anträge', async () => {
    const w = await geladen()
    const kopf = karte(w, '121').find('.pw-budget-betrag')

    const zeilen = kopf.findAll('.pw-budget-wert')
    expect(zeilen.length, 'zwei Zeilen: Stadtrat und Fraktion').toBe(2)
    expect(zeilen[0].text()).toContain('Stadtrat')
    expect(zeilen[0].text()).toContain('4’950’828')
    expect(zeilen[1].text()).toContain('Fraktion')
    expect(zeilen[1].text()).toContain('3’713’121')
  })

  it('zeigt für beide Werte die Differenz zum Vorjahr', async () => {
    const w = await geladen()
    const zeilen = karte(w, '121').findAll('.pw-budget-wert')

    // Stadtrat: 4'950'828 − 4'395'510 = +555'318
    expect(zeilen[0].find('.pw-summe-diff').text()).toBe('+555’318')
    // Fraktion: 3'713'121 − 4'395'510 = −682'389
    expect(zeilen[1].find('.pw-summe-diff').text()).toBe('−682’389')
  })

  it('zeigt ohne Wirkung unserer Anträge nur den einen Wert', async () => {
    const w = await geladen()
    const kopf = karte(w, '142').find('.pw-budget-betrag')

    expect(kopf.findAll('.pw-budget-wert').length).toBe(1)
    expect(kopf.text()).not.toContain('Stadtrat')
    expect(kopf.text()).toContain('6’206’839')
    expect(kopf.find('.pw-summe-diff').text()).toBe('+755’698')
  })

  it('zeigt im Personalbestand dieselben zwei Zeilen mit den Stellen', async () => {
    const w = await geladen()
    w.vm.aktiverTab = 'personal'
    await w.vm.$nextTick()

    const zeilen = karte(w, '121', 1).findAll('.pw-budget-wert')
    expect(zeilen.length).toBe(2)
    expect(zeilen[0].text()).toContain('19.5')
    expect(zeilen[1].text()).toContain('18')
  })
})

describe('F117 — ein Antrag wird durch Klick bearbeitbar', () => {
  beforeEach(() => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      return Promise.resolve({ data: ansicht() })
    })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: {} })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  it('öffnet beim Klick auf die Antragszeile das Formular mit den Werten des Antrags', async () => {
    const w = await geladen()
    const zeile = karte(w, '121').findAll('.pw-budget-antraege li')[0]

    await zeile.trigger('click')

    const form = karte(w, '121').findComponent(BudgetAntragForm)
    expect(form.exists(), 'das Formular steht am Antrag').toBe(true)
    expect(form.props('form').betrag).toBe('247541')
    expect(form.props('form').mehrausgabe).toBe(false)
    expect(form.props('form').antragsteller).toBe('SVP')
    expect(form.props('form').begruendung).toBe('Sparen')
  })

  it('speichert die Änderung über den Endpunkt des Antrags', async () => {
    const w = await geladen()
    await karte(w, '121').findAll('.pw-budget-antraege li')[0].trigger('click')

    const form = karte(w, '121').findComponent(BudgetAntragForm)
    form.props('form').betrag = '300000'
    form.props('form').begruendung = 'mehr sparen'
    await form.vm.$emit('save')
    await flushPromises()

    const put = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/10'))
    expect(put, 'die Änderung geht an den Antrag 10').toBeTruthy()
    expect(put[1].betragDelta).toBe(-300000)
    expect(put[1].begruendung).toBe('mehr sparen')
    // F109: was am Antrag hängt, geht beim Speichern mit.
    expect(put[1].zielAenderungen).toEqual([])
    expect(put[1].aufteilung).toEqual([])
  })

  // Dass ein Klick auf ein Bedienelement der Zeile das Formular NICHT öffnet,
  // lässt sich hier nicht messen: shallowMount ersetzt die Kindkomponenten durch
  // Stubs, an deren Wurzel @click.stop greift — im Browser klickt der Benutzer
  // in ein Kindelement darunter, und der Klick erreicht die Zeile trotzdem. Der
  // Nachweis steht darum in tests/e2e/budget-datenfluss.spec.js.

  it('behält eine begonnene Eingabe, wenn die Ansicht neu lädt', async () => {
    // Die Ansicht lädt im Hintergrund neu — nach einem Filterwechsel, auf eine
    // Echtzeit-Meldung hin, nach jedem gespeicherten Antrag. Wer gerade tippt,
    // darf seine Eingabe dabei nicht verlieren.
    const w = await geladen()
    w.vm.formOeffnen('g121')
    w.vm.neu['121'].betrag = '12000'
    w.vm.neu['121'].begruendung = 'Begonnen'

    await w.vm.ladeAnsicht(false)
    await flushPromises()

    expect(w.vm.neu['121'].betrag, 'die begonnene Eingabe wurde verworfen').toBe('12000')
    expect(w.vm.neu['121'].begruendung).toBe('Begonnen')
    expect(w.vm.formOffen.g121, 'das offene Formular wurde geschlossen').toBe(true)
  })

  it('lässt einen automatisch erzeugten Antrag unangetastet', async () => {
    const w = await geladen()
    const automatisch = karte(w, '121').findAll('.pw-budget-antraege li')[1]

    await automatisch.trigger('click')

    expect(karte(w, '121').findComponent(BudgetAntragForm).exists()).toBe(false)
  })

  it('schliesst das Formular ohne zu speichern, wenn abgebrochen wird', async () => {
    const w = await geladen()
    await karte(w, '121').findAll('.pw-budget-antraege li')[0].trigger('click')

    await karte(w, '121').findComponent(BudgetAntragForm).vm.$emit('abbrechen')
    await w.vm.$nextTick()

    expect(karte(w, '121').findComponent(BudgetAntragForm).exists()).toBe(false)
    expect(axios.put.mock.calls.length).toBe(0)
  })
})
