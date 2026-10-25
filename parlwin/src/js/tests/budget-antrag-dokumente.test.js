import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Dokumente am Budgetantrag (F125).
 *
 * Marc am 24.10.2026: «im Weiteren ist beschwert worden, dass man bei den
 * Anträgen im Budget kein Dokument aufladen kann». Jedes andere Objekt der
 * Anwendung trägt Dokumente; der Antrag trug nur Notizen.
 *
 * vitest läuft im Projekt-Root.
 */
const QUELLE = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')
const ROUTEN = readFileSync('parlwin/appinfo/routes.php', 'utf8')
const CONTROLLER = readFileSync('parlwin/lib/Controller/BudgetController.php', 'utf8')

const SUMMEN = { ausgaben: 1000, einnahmen: 900, ergebnis: -100, stellen: 1 }

const ANSICHT = {
  jahr: { jahr: 2027, steuerfuss: 125, steuerfussEffektiv: 125 },
  summen: SUMMEN,
  summenStadtrat: SUMMEN,
  departemente: ['Finanzen'],
  eigeneFraktion: 'SVP-Fraktion',
  produktegruppen: [{
    code: '240', name: 'Immobilien', departement: 'Finanzen',
    globalkredit: { soll: 1000, sollFraktion: 900, vorjahr: 1000 },
    stellen: { soll: 1, sollFraktion: 1, vorjahr: 1 },
    zielvorgaben: [], kostenzeilen: [], produkte: [],
  }],
  investitionen: [],
  antraege: [{
    id: 5, bereich: 'globalbudget', zielTyp: 'produktegruppe', zielRef: '240',
    betragDelta: -100, begruendung: 'Weniger Unterhalt', antragsteller: 'Marc Wäckerlin',
    entscheid: 'offen', aktionen: [],
  }],
  pauschalantraege: [],
}

describe('Dokumente am Budgetantrag', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
      if (u.includes('nc/users') || u.includes('/dokumente')) { return Promise.resolve({ data: [] }) }
      return Promise.resolve({ data: ANSICHT })
    })
  })

  it('spricht die Dokumente eines Antrags unter seiner eigenen Adresse an', () => {
    expect(QUELLE).toContain("'/apps/parlwin/budget/antraege/' + a.id")
    expect(QUELLE, 'die Dokumente hängen an keinem Antrag').toMatch(/api-basis="'\/apps\/parlwin\/budget\/antraege\/' \+ a\.id"/)
  })

  it('lädt die Dokumente erst beim Aufklappen', async () => {
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    expect(w.vm.offeneDokumente[5]).toBeUndefined()
    w.vm.dokumenteToggle(5, { target: { open: true } })
    expect(w.vm.offeneDokumente[5]).toBe(true)
  })

  it('kennt die drei Wege: ansehen, anlegen, hochladen', () => {
    expect(ROUTEN).toContain("'/budget/antraege/{id}/dokumente'")
    expect(ROUTEN).toContain("'/budget/antraege/{id}/dokumente/upload'")
    expect(CONTROLLER).toContain('public function dokumente(int $id)')
    expect(CONTROLLER).toContain('public function dokumentErstellen(int $id)')
    expect(CONTROLLER).toContain('public function dokumentHochladen(int $id)')
  })

  it('legt die Dateien im Ordner des Budgetjahres ab, mit der Antragsnummer am Namen', () => {
    expect(CONTROLLER).toContain("'Fraktion/50_Budget/' . \$jahr")
    expect(CONTROLLER).toContain("'Antrag-' . \$id . '-'")
  })
})
