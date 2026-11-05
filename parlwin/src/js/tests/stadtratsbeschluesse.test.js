import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { BUENDEL_MS, FENSTER } from '../nachladen'
import Stadtratsbeschluesseliste from '../components/Stadtratsbeschluesseliste.vue'
import App from '../App.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))
// Die Ansicht meldet sich am Echtzeit-Kanal an; der Test hält ihren Empfänger fest.
const echtzeit = vi.hoisted(() => ({ empfaenger: null }))
vi.mock('../realtime', () => ({ subscribeRealtime: (empfaenger) => { echtzeit.empfaenger = empfaenger; return () => {} } }))

/**
 * Die Ansicht «Stadtratsbeschlüsse» (F144, F145).
 *
 * Die Ansicht steht in der Navigation zwischen «Kommissionen» und
 * «Vorstösse» und zeigt die Beschlüsse, die der Abgleich von der Webseite der
 * Stadt geholt hat.
 *
 * vitest läuft im Projekt-Root.
 */
const BESCHLUESSE = [
  {
    id: 1,
    nummer: '2026/1279',
    externId: '20261279',
    titel: 'Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)',
    datum: '2026-10-21',
    departement: 'Stadtkanzlei',
    sitzung: 'Stadtratssitzung vom 21. Oktober 2026',
    publiziertAm: '2026-10-28',
    url: 'https://stadt.winterthur.ch/stadtratsbeschluesse/2026-10-21/20261279',
    dokumente: [
      { titel: 'BEI 1 (gelayoutete Legislaturschwerpunkte)', url: 'https://stadt.winterthur.ch/download/resolution-document/3252/x.pdf' },
      { titel: 'SRB_SRA (Festlegung Legislaturschwerpunkte)', url: 'https://stadt.winterthur.ch/download/resolution-document/3253/y.pdf' },
    ],
    aktionen: [{ id: 7, text: 'Für die Fraktionssitzung', geloescht: false }],
  },
  {
    id: 2,
    nummer: '2025/0815',
    externId: '20250815',
    titel: 'Beitrag an den Verein',
    datum: '2025-06-04',
    departement: 'Soziales',
    sitzung: 'Stadtratssitzung vom 4. Juni 2025',
    publiziertAm: '2025-06-11',
    url: 'https://stadt.winterthur.ch/stadtratsbeschluesse/2025-06-04/20250815',
    dokumente: [],
    aktionen: [],
  },
]

async function liste(daten = BESCHLUESSE) {
  axios.get.mockImplementation((url) => {
    if (String(url).includes('/stadtratsbeschluesse')) { return Promise.resolve({ data: daten }) }
    return Promise.resolve({ data: [] })
  })
  const w = mount(Stadtratsbeschluesseliste, {
    global: { stubs: { Teleport: true, NotizenListe: true } },
  })
  await flushPromises()
  return w
}

/** Die Beschriftungen und Werte der Angaben einer Karte als Paare. */
function angaben(karte) {
  return karte.findAll('.pw-data-pair').map(paar => [
    paar.find('span').text(),
    paar.find('strong').text(),
  ])
}

describe('Ansicht Stadtratsbeschlüsse (F144)', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('holt die Beschlüsse von der eigenen Schnittstelle', async () => {
    await liste()
    expect(axios.get.mock.calls.some(([url]) => String(url).endsWith('/stadtratsbeschluesse')),
      'die Liste fragt die Schnittstelle nicht ab').toBe(true)
  })

  it('zeigt je Beschluss eine Karte mit Nummer und Titel', async () => {
    const w = await liste()
    const karten = w.findAll('.pw-srb-karte')
    expect(karten.length).toBe(2)
    expect(karten[0].find('.pw-data-card-kicker').text()).toContain('2026/1279')
    expect(karten[0].find('h3').text()).toBe('Festlegung Legislaturschwerpunkte 2026-2030 (2. Lesung)')
  })

  it('benutzt das Kartengerüst des Projekts', async () => {
    const w = await liste()
    const karte = w.findAll('.pw-srb-karte')[0]
    expect(karte.classes(), 'die Karte ist keine pw-data-card').toContain('pw-data-card')
    expect(karte.find('.pw-data-card-header').exists()).toBe(true)
    expect(karte.find('.pw-data-card-grid').exists()).toBe(true)
  })

  it('zeigt Datum, Departement, Sitzung und Veröffentlichung als Angaben', async () => {
    const w = await liste()
    expect(angaben(w.findAll('.pw-srb-karte')[0])).toEqual([
      ['Beschlossen', '21.10.2026'],
      ['Departement', 'Stadtkanzlei'],
      ['Sitzung', 'Stadtratssitzung vom 21. Oktober 2026'],
      ['Veröffentlicht', '28.10.2026'],
    ])
  })

  it('verlinkt den Beschluss auf der Webseite der Stadt in einem neuen Tab', async () => {
    const w = await liste()
    const link = w.findAll('.pw-srb-karte')[0].find('.pw-extern-link')
    expect(link.attributes('href')).toBe(BESCHLUESSE[0].url)
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toContain('noopener')
  })

  it('zeigt jedes Dokument als Verweis mit seiner Beschriftung', async () => {
    const w = await liste()
    const verweise = w.findAll('.pw-srb-karte')[0].findAll('.pw-srb-dokumente a')
    expect(verweise.length).toBe(2)
    expect(verweise[0].text()).toBe('BEI 1 (gelayoutete Legislaturschwerpunkte)')
    expect(verweise[0].attributes('href')).toBe(BESCHLUESSE[0].dokumente[0].url)
    expect(verweise[0].attributes('target')).toBe('_blank')
  })

  // Die Farbe der Verweise trägt `pw-extern-link`, wie beim Verweis auf den
  // Beschluss und bei der E-Mail in «Mitglieder»; die Farbe misst der Browsertest.
  it('gestaltet jedes Dokument als Verweis wie die übrigen Verweise', async () => {
    const w = await liste()
    const verweise = w.findAll('.pw-srb-karte')[0].findAll('.pw-srb-dokumente a')
    expect(verweise.map((a) => a.classes()), 'ein Dokument steht als gewöhnlicher Text da').toEqual([['pw-extern-link'], ['pw-extern-link']])
  })

  it('lässt den Dokumentblock weg, wo kein Dokument hängt', async () => {
    const w = await liste()
    expect(w.findAll('.pw-srb-karte')[1].find('.pw-srb-dokumente').exists()).toBe(false)
  })

  it('zeigt die Notizen eingeklappt mit ihrer Zahl und öffnet sie auf Klick', async () => {
    const w = await liste()
    const karte = w.findAll('.pw-srb-karte')[0]
    const kopf = karte.find('.pw-srb-notizen-kopf')
    expect(kopf.element.tagName, 'der Kopf ist kein Knopf').toBe('BUTTON')
    expect(kopf.attributes('aria-expanded')).toBe('false')
    expect(kopf.text()).toContain('Notizen (1)')
    expect(karte.findComponent({ name: 'NotizenListe' }).exists(),
      'die Notizen stehen offen statt eingeklappt').toBe(false)

    await kopf.trigger('click')
    expect(karte.find('.pw-srb-notizen-kopf').attributes('aria-expanded')).toBe('true')
    const notizen = karte.findComponent({ name: 'NotizenListe' })
    expect(notizen.exists(), 'der Klick öffnet die Notizen nicht').toBe(true)
    // Dieselbe geteilte Komponente wie am Geschäft und am Vorstoss.
    expect(notizen.props('basisUrl')).toBe('stadtratsbeschluesse/1')
  })

  it('zählt eine gelöschte Notiz nicht mit', async () => {
    const w = await liste([{
      ...BESCHLUESSE[0],
      aktionen: [
        { id: 1, text: 'bleibt', geloescht: false },
        { id: 2, text: 'weg', geloescht: true },
      ],
    }])
    expect(w.find('.pw-srb-notizen-kopf').text()).toContain('Notizen (1)')
  })

  it('meldet die leere Liste statt einer leeren Fläche', async () => {
    const w = await liste([])
    expect(w.findAll('.pw-srb-karte').length).toBe(0)
    // Die Meldung geht als Eigenschaft an NcEmptyContent; im Testaufbau ist
    // jede Nextcloud-Komponente dieselbe Attrappe, die nur ihre Slots ausgibt,
    // darum steht die Eigenschaft als Attribut im HTML. Den sichtbaren Text
    // prüft der Browsertest.
    expect(w.html()).toContain('name="Noch keine Stadtratsbeschlüsse"')
  })
})

describe('Suche und Filter der Stadtratsbeschlüsse (F145)', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('bietet in der Auswahl nur die Departemente an, die vorkommen', async () => {
    const w = await liste()
    expect(w.vm.departementOptionen).toEqual([
      { label: 'Alle Departemente', value: '' },
      { label: 'Soziales', value: 'Soziales' },
      { label: 'Stadtkanzlei', value: 'Stadtkanzlei' },
    ])
  })

  it('bietet nur die Jahre an, aus denen Beschlüsse vorliegen, das jüngste zuerst', async () => {
    const w = await liste()
    expect(w.vm.jahrOptionen).toEqual([
      { label: 'Alle Jahre', value: '' },
      { label: '2026', value: '2026' },
      { label: '2025', value: '2025' },
    ])
  })

  it('bindet genau diese Listen an die beiden Auswahlen, mit Lage am sichtbaren Bereich', () => {
    // Die Auswahlen stehen in einem Teleport, und im Testaufbau ist jede
    // Nextcloud-Komponente dieselbe Attrappe: gemessen wird darum die Vorlage.
    // Die offene Liste am Telefon prüft der Browsertest.
    const quelle = readFileSync('parlwin/src/js/components/Stadtratsbeschluesseliste.vue', 'utf8')
    const auswahlen = quelle.match(/<NcSelect[\s\S]*?\/>/g) || []
    expect(auswahlen.length, 'es stehen nicht zwei Auswahllisten in der Seitenleiste').toBe(2)
    expect(auswahlen[0]).toContain(':options="departementOptionen"')
    expect(auswahlen[1]).toContain(':options="jahrOptionen"')
    for (const auswahl of auswahlen) {
      expect(auswahl, 'die Liste folgt dem sichtbaren Bereich nicht')
        .toContain(':calculate-position="filterDropdownPosition"')
      expect(auswahl, 'ohne input-label fehlt der Auswahl ihre Beschriftung')
        .toMatch(/input-label="(Departement|Jahr)"/)
    }
  })

  it('sucht über Nummer, Titel, Departement und Sitzung', async () => {
    const w = await liste()
    w.vm.suche = '2026/1279'
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([1])
    w.vm.suche = 'soziales'
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([2])
    w.vm.suche = 'Juni 2025'
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([2])
  })

  it('filtert auf Departement und auf Jahr, auch wenn die Auswahl das Objekt liefert', async () => {
    const w = await liste()
    w.vm.departement = 'Soziales'
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([2])
    // NcSelect gibt die ganze Option zurück, sobald der Benutzer eine wählt.
    w.vm.departement = { label: 'Stadtkanzlei', value: 'Stadtkanzlei' }
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([1])
    w.vm.departement = ''
    w.vm.jahr = { label: '2026', value: '2026' }
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([1])
  })

  it('setzt Suche und beide Filter zurück', async () => {
    const w = await liste()
    w.vm.suche = 'x'
    w.vm.departement = 'Soziales'
    w.vm.jahr = '2025'
    w.vm.filterZuruecksetzen()
    expect([w.vm.suche, w.vm.departement, w.vm.jahr]).toEqual(['', '', ''])
    expect(w.vm.gefilterte.length).toBe(2)
  })

  it('zeigt ohne Wahl «Alle Departemente» und «Alle Jahre» in den Auswahlen, auch nach dem Zurücksetzen', async () => {
    const w = await liste()
    expect(w.vm.departementAuswahl, 'die Auswahl Departement steht ohne Wahl leer da')
      .toEqual({ label: 'Alle Departemente', value: '' })
    expect(w.vm.jahrAuswahl, 'die Auswahl Jahr steht ohne Wahl leer da')
      .toEqual({ label: 'Alle Jahre', value: '' })
    w.vm.departementAuswahl = { label: 'Soziales', value: 'Soziales' }
    expect(w.vm.gefilterte.map(b => b.id)).toEqual([2])
    w.vm.filterZuruecksetzen()
    expect(w.vm.departementAuswahl).toEqual({ label: 'Alle Departemente', value: '' })
  })

  it('meldet, wenn kein Beschluss zur Suche passt', async () => {
    const w = await liste()
    w.vm.suche = 'Schneefall im August'
    await flushPromises()
    expect(w.findAll('.pw-srb-karte').length).toBe(0)
    expect(w.html()).toContain('name="Kein Beschluss passt zur Suche"')
  })
})

describe('Stadtratsbeschlüsse nach einem Ereignis (F144, F147)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
  })
  afterEach(() => { vi.useRealTimers() })

  const anfragen = () => axios.get.mock.calls.filter(([url]) => String(url).endsWith('/stadtratsbeschluesse'))

  it('bündelt die Ereignisse einer Frist zu einer Anfrage und gleicht die Karten an Ort und Stelle an', async () => {
    const w = await liste()
    const vorher = w.vm.beschluesse[0]
    axios.get.mockClear()
    axios.get.mockImplementation(() => Promise.resolve({ data: [{ ...BESCHLUESSE[0], titel: 'Neu gefasst' }, BESCHLUESSE[1]] }))
    echtzeit.empfaenger({ type: 'stadtratsbeschluesse.updated', payload: { id: 1 } })
    echtzeit.empfaenger({ type: 'sync.completed', payload: {} })
    expect(anfragen(), 'die Ansicht lädt vor dem Ende der Frist').toHaveLength(0)
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    await flushPromises()
    expect(anfragen(), 'zwei Ereignisse laden zweimal').toHaveLength(1)
    expect(w.vm.beschluesse[0], 'die Karte wurde ersetzt statt angeglichen').toBe(vorher)
    expect(w.find('.pw-srb-karte h3').text()).toBe('Neu gefasst')
  })

  it('lädt für ein Ereignis aus diesem Fenster nichts', async () => {
    await liste()
    axios.get.mockClear()
    echtzeit.empfaenger({ type: 'stadtratsbeschluesse.updated', payload: { id: 1, _fenster: FENSTER, _uid: 'mw' } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    await flushPromises()
    expect(anfragen()).toHaveLength(0)
  })

  it('lässt eine ältere Antwort einen neueren Stand nicht überschreiben', async () => {
    const w = await liste()
    const offen = []
    axios.get.mockImplementation(() => new Promise((weiter) => { offen.push(weiter) }))
    echtzeit.empfaenger({ type: 'stadtratsbeschluesse.updated', payload: { id: 1 } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    echtzeit.empfaenger({ type: 'stadtratsbeschluesse.updated', payload: { id: 1 } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    expect(offen).toHaveLength(2)
    offen[1]({ data: [{ ...BESCHLUESSE[0], titel: 'Neuer Stand' }, BESCHLUESSE[1]] })
    await flushPromises()
    offen[0]({ data: [{ ...BESCHLUESSE[0], titel: 'Alter Stand' }, BESCHLUESSE[1]] })
    await flushPromises()
    expect(w.find('.pw-srb-karte h3').text(), 'die ältere Antwort hat die neuere überschrieben').toBe('Neuer Stand')
  })
})

describe('Platz in der Navigation (F144)', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('steht nach «Kommissionen» und vor «Vorstösse»', () => {
    axios.get.mockResolvedValue({ data: [] })
    const w = mount(App, { global: { stubs: true } })
    const keys = w.vm.ansichten.map(a => a.key)
    const kommissionen = keys.indexOf('kommissionen')
    const beschluesse = keys.indexOf('stadtratsbeschluesse')
    const vorstoesse = keys.indexOf('vorstoesse')

    expect(beschluesse, 'die Ansicht fehlt in der Navigation').toBeGreaterThan(-1)
    expect(beschluesse).toBe(kommissionen + 1)
    expect(vorstoesse).toBe(beschluesse + 1)
    expect(w.vm.ansichten[beschluesse].bezeichnung).toBe('Stadtratsbeschlüsse')
    w.unmount()
  })

  it('zeigt die Ansicht, sobald der Eintrag gewählt ist', async () => {
    axios.get.mockResolvedValue({ data: [] })
    const w = mount(App, {
      global: { stubs: { Teleport: true, NotizenListe: true } },
    })
    await flushPromises()
    expect(w.findComponent({ name: 'Stadtratsbeschluesseliste' }).exists(),
      'die Ansicht steht da, bevor ihr Eintrag gewählt ist').toBe(false)

    w.vm.aktiveAnsicht = 'stadtratsbeschluesse'
    await flushPromises()
    expect(w.findComponent({ name: 'Stadtratsbeschluesseliste' }).exists(),
      'der gewählte Eintrag zeigt die Ansicht nicht').toBe(true)
    w.unmount()
  })
})
