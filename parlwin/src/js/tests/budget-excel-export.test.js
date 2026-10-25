import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Excel-Export der Budgetanträge (F138).
 *
 * Marc am 24.10.2026: «Noch ein Excel export zusätzlich zum PDF Export. Direkt
 * in den files in Nextcloud Speichen. … Und optional download statt in der
 * Cloud speichern.» Die Datei geht in denselben Ordner wie die Dokumente der
 * Anträge: «Fraktion/50_Budget/<Budgetjahr>».
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
    code: '240', name: 'Immobilien', departement: 'Finanzen', buchseite: 243,
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

async function liste() {
  const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
  await flushPromises()
  w.vm.aktiverTab = 'antraege'
  await w.vm.$nextTick()
  return w
}

/**
 * Der Knopf an seiner Klasse. Die Knöpfe von Nextcloud sind im
 * Komponententest durch eine Attrappe ersetzt, die kein `button`-Element und
 * keine Beschriftung rendert — darum die Klasse und nicht der Text.
 */
function knopfKlasse(w, klasse) {
  const treffer = w.find('.' + klasse)
  expect(treffer.exists(), 'der Knopf ' + klasse + ' fehlt').toBe(true)
  return treffer
}

describe('Excel-Export der Budgetanträge', () => {
  let geoeffnet

  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2027 }] }) }
      if (u.includes('nc/users') || u.includes('/dokumente')) { return Promise.resolve({ data: [] }) }
      return Promise.resolve({ data: ANSICHT })
    })
    axios.post.mockImplementation(() => Promise.resolve({
      data: { name: 'Budgetantraege-2027.xlsx', pfad: 'Fraktion/50_Budget/2027/Budgetantraege-2027.xlsx', id: 7 },
    }))
    geoeffnet = vi.spyOn(window, 'open').mockImplementation(() => null)
  })

  afterEach(() => { geoeffnet.mockRestore() })

  it('stellt im Tab «Anträge» beide Wege bereit', async () => {
    const w = await liste()

    expect(w.find('.pw-excel-ablegen').exists(), 'der Knopf zum Ablegen fehlt').toBe(true)
    expect(w.find('.pw-excel-download').exists(), 'der Knopf zum Herunterladen fehlt').toBe(true)
    // Beide stehen neben dem PDF-Knopf, in derselben Kopfzeile des Tabs.
    const kopf = QUELLE.slice(QUELLE.indexOf('pw-antraege-kopf'))
    const zeile = kopf.slice(0, kopf.indexOf('</div>'))
    expect(zeile, 'der PDF-Knopf ist verschwunden').toContain('Anträge als PDF')
    expect(zeile, 'die Excel-Knöpfe stehen nicht beim PDF-Knopf').toContain('pw-excel-ablegen')
    expect(zeile).toContain('pw-excel-download')
  })

  it('legt die Tabelle auf Klick in den Dateien ab und nennt den Pfad', async () => {
    const w = await liste()
    await knopfKlasse(w, 'pw-excel-ablegen').trigger('click')
    await flushPromises()

    // Das Ablegen schreibt etwas und geht darum über POST, mit dem Token der Sitzung.
    const aufruf = axios.post.mock.calls.map(c => String(c[0])).find(u => u.includes('/antraege-excel'))
    expect(aufruf, 'der Export wurde nicht angefordert').toBeTruthy()
    expect(aufruf).toContain('/budget/2027/antraege-excel')
    expect(window.open, 'das Ablegen öffnet kein Fenster').not.toHaveBeenCalled()
  })

  it('lädt dieselbe Tabelle herunter, ohne sie abzulegen', async () => {
    const w = await liste()
    await knopfKlasse(w, 'pw-excel-download').trigger('click')
    await flushPromises()

    expect(window.open).toHaveBeenCalledTimes(1)
    const url = String(window.open.mock.calls[0][0])
    expect(url).toContain('/budget/2027/antraege-excel')
    expect(
      axios.post.mock.calls.map(c => String(c[0])).some(u => u.includes('/antraege-excel')),
      'der Download legt die Datei zusätzlich ab',
    ).toBe(false)
  })

  it('übernimmt Kommission und fremde Anträge aus der Auswahl', async () => {
    const w = await liste()
    w.vm.departementOption = { value: 'Finanzen', label: 'Finanzen' }
    w.vm.pdfMitFremden = true
    await w.vm.$nextTick()
    await knopfKlasse(w, 'pw-excel-download').trigger('click')

    const url = String(window.open.mock.calls[0][0])
    expect(url).toContain('kommission=Finanzen')
    expect(url).toContain('mitFremden=1')
  })

  it('kennt beide Wege auf dem Server', () => {
    expect(ROUTEN).toContain("'/budget/{jahr}/antraege-excel'")
    expect(ROUTEN.match(/antraege-excel/g).length, 'es fehlt eine der beiden Routen').toBe(2)
    expect(CONTROLLER).toContain('public function antraegeExcel(int $jahr)')
    expect(CONTROLLER).toContain('public function antraegeExcelAblegen(int $jahr)')
    expect(CONTROLLER, 'die Datei liegt nicht beim Budgetjahr').toContain("'Fraktion/50_Budget/' . \$jahr")
  })
})
