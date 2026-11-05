import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Jeder Benutzer der Nextcloud steht als Antragsteller zur Wahl.
 *
 * Ein Mitglied der Fraktion, das nicht im Parlament sitzt, hat ein Konto in
 * der Nextcloud und wählt sich darüber selbst als Antragsteller.
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
  { id: 1, externId: 'e1', vorname: 'Marc', name: 'Wäckerlin', fraktion: 'Musterpartei-Fraktion (MPW)', aktiv: true, nextcloudUid: 'mw' },
]

const FRAKTIONEN = [
  { id: 1, name: 'Musterpartei-Fraktion (MPW)', eigene: true, aktiv: true },
]

const BENUTZER = [
  { uid: 'mw', displayName: 'Marc Wäckerlin', email: '' },
  { uid: 'rmuster', displayName: 'Rita Muster', email: '' },
]

function mounten() {
  return mount(Budgetliste, {
    props: { mitglieder: MITGLIEDER, fraktionen: FRAKTIONEN },
    global: { stubs: { Teleport: true } },
  })
}

describe('Antragsteller aus dem Benutzerbestand', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockImplementation((url) => {
      if (String(url).includes('budget/ansicht')) { return Promise.resolve({ data: ANSICHT }) }
      if (String(url).includes('nc/users')) { return Promise.resolve({ data: BENUTZER }) }
      if (String(url).includes('mitglieder')) { return Promise.resolve({ data: MITGLIEDER }) }
      if (String(url).includes('fraktionen')) { return Promise.resolve({ data: FRAKTIONEN }) }
      return Promise.resolve({ data: [] })
    })
  })

  it('bietet einen Benutzer an, der nicht im Parlament sitzt', async () => {
    const w = mounten()
    await flushPromises()
    const namen = w.vm.antragstellerOptionen('eigene').map(o => o.label)
    expect(namen, 'ein Benutzer ohne Parlamentssitz fehlt in der Auswahl').toContain('Rita Muster')
  })

  it('führt einen Benutzer, der zugleich Mitglied ist, nur einmal', async () => {
    const w = mounten()
    await flushPromises()
    const namen = w.vm.antragstellerOptionen('eigene').map(o => o.label)
    expect(namen.filter(n => n === 'Marc Wäckerlin')).toHaveLength(1)
  })

  it('bietet die Benutzer auch bei einem fremden Antrag an', async () => {
    const w = mounten()
    await flushPromises()
    const namen = w.vm.antragstellerOptionen('fremde').map(o => o.label)
    expect(namen).toContain('Rita Muster')
  })
})
