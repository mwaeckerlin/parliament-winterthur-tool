import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Ein Antragsteller trägt Vorname und Nachname.
 *
 * Das Feld `name` eines Mitglieds ist im Datenmodell der Nachname
 * (ScraperService: `'name' => $nachname`), der Vorname steht in `vorname`.
 * Die Auswahl und jeder Antrag nennen beide, nie den Nachnamen allein.
 *
 * vitest läuft im Projekt-Root.
 */
const ANSICHT = {
  jahr: { jahr: 2027, steuerfuss: 125, steuerfussEffektiv: 125 },
  eigeneFraktion: 'Musterpartei-Fraktion (MPW)',
  produktegruppen: [{
    code: '240', name: 'Immobilien', departement: 'Finanzen',
    globalkredit: { soll: 1000, sollFraktion: 1000 },
    zielvorgaben: [], kostenzeilen: [], produkte: [],
  }],
  investitionen: [],
  antraege: [],
  pauschalantraege: [],
}

const MITGLIEDER = [
  { id: 1, externId: 'e1', vorname: 'Marc', name: 'Wäckerlin', fraktion: 'Musterpartei-Fraktion (MPW)', aktiv: true },
  { id: 2, externId: 'e2', vorname: 'Anna', name: 'Müller', fraktion: 'Fraktion-Beispiel', aktiv: true },
]

const FRAKTIONEN = [
  { id: 1, name: 'Musterpartei-Fraktion (MPW)', eigene: true, aktiv: true },
  { id: 2, name: 'Fraktion-Beispiel', eigene: false, aktiv: true },
]

describe('Antragsteller', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockImplementation((url) => {
      if (String(url).includes('budget/ansicht')) { return Promise.resolve({ data: ANSICHT }) }
      if (String(url).includes('mitglieder')) { return Promise.resolve({ data: MITGLIEDER }) }
      if (String(url).includes('fraktionen')) {
        return Promise.resolve({ data: [{ id: 1, name: 'Musterpartei-Fraktion (MPW)', eigene: true, aktiv: true }] })
      }
      return Promise.resolve({ data: [] })
    })
  })

  it('bietet Vorname und Nachname zur Auswahl, nie den Nachnamen allein', async () => {
    const w = mount(Budgetliste, { props: { mitglieder: MITGLIEDER, fraktionen: FRAKTIONEN }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    const optionen = w.vm.antragstellerOptionen('eigene')
    const namen = optionen.map(o => o.label)
    expect(namen, 'der Nachname steht allein in der Auswahl').not.toContain('Wäckerlin')
    expect(namen).toContain('Marc Wäckerlin')
    const werte = optionen.map(o => o.value)
    expect(werte, 'gespeichert würde der Nachname allein').toContain('Marc Wäckerlin')
  })

  it('bietet auch bei einem fremden Antrag den ganzen Namen', async () => {
    const w = mount(Budgetliste, { props: { mitglieder: MITGLIEDER, fraktionen: FRAKTIONEN }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    const namen = w.vm.antragstellerOptionen('fremde').map(o => o.label)
    expect(namen).toContain('Anna Müller')
    expect(namen).not.toContain('Müller')
  })
})
