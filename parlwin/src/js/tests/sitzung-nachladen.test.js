import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import { BUENDEL_MS, FENSTER, ereignisseBuendeln, listeAbgleichen, fensterKennungAnhaengen, FENSTER_HEADER } from '../nachladen'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

/**
 * Nachladen während der Sitzung, wenn ein anderes Fenster etwas eingibt.
 *
 * Bei langsamer Leitung verschwand die Traktandenliste bei jedem Ereignis
 * hinter «Traktanden laden...», und jedes Traktandum holte danach seine
 * Notizen neu: 21 Anfragen bei 20 Traktanden. Jetzt lädt die Ansicht im
 * Hintergrund nur, was sich geändert hat, und gleicht die Tabelle an Ort und
 * Stelle an; das Fenster, das gespeichert hat, lädt nichts.
 */
const SITZUNG = { id: 5, datum: '2099-01-01', titel: 'Fraktionssitzung', notizen: '[]' }
const TRAKTANDEN = [
  { id: 11, nummer: 1, titel: 'Ohne Geschäft', geschaeftId: 0, notizen: '[]' },
  { id: 12, nummer: 2, titel: 'Mit Geschäft', geschaeftId: 77, geschaeft: { id: 77, titel: 'Budget' }, notizen: '[]' },
]

function antworten(url) {
  const u = String(url)
  if (u.endsWith('/sitzungen/5/traktanden')) { return Promise.resolve({ data: TRAKTANDEN.map((t) => ({ ...t })) }) }
  if (u.endsWith('/sitzungen')) { return Promise.resolve({ data: [{ ...SITZUNG }] }) }
  return Promise.resolve({ data: [] })
}

async function offeneSitzung(global = {}) {
  axios.get.mockReset().mockImplementation(antworten)
  const w = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] }, global })
  await flushPromises()
  w.vm.offeneSitzungen.push(5)
  await w.vm.ladeTraktandenFuerSitzung(5)
  await flushPromises()
  axios.get.mockClear()
  return w
}

function angefragt() {
  return axios.get.mock.calls.map(([url]) => String(url))
}

async function ereignis(w, typ, payload) {
  w.vm.handleRealtimeEvent({ type: typ, payload })
  await vi.advanceTimersByTimeAsync(BUENDEL_MS)
  await flushPromises()
}

describe('Nachladen während der Sitzung', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('lässt die Tabelle stehen, solange im Hintergrund nachgeladen wird', async () => {
    const w = await offeneSitzung()
    const vorher = w.vm.traktanden[5]
    let freigeben
    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? new Promise((weiter) => { freigeben = () => weiter({ data: [{ ...TRAKTANDEN[0], titel: 'Neu benannt' }, TRAKTANDEN[1]] }) })
      : antworten(url)))

    w.vm.handleRealtimeEvent({ type: 'traktanden.updated', payload: { id: 11, sitzungId: 5 } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    expect(w.vm.ladenTraktanden[5], 'die Tabelle weicht «Traktanden laden...»').toBeFalsy()
    expect(w.vm.traktanden[5], 'die Tabelle ist während des Nachladens weg').toBe(vorher)

    freigeben()
    await flushPromises()
    expect(w.vm.traktanden[5], 'die Tabelle wurde ersetzt statt angeglichen').toBe(vorher)
    expect(vorher[0].titel).toBe('Neu benannt')
    expect(w.vm.ladenTraktanden[5]).toBeFalsy()
  })

  it('lädt für eine Notiz an einem Traktandum genau dessen Notizen', async () => {
    const w = await offeneSitzung()
    await ereignis(w, 'traktanden.updated', { id: 11, sitzungId: 5, aktionTyp: 'notiz' })
    expect(angefragt()).toEqual([expect.stringContaining('/sitzungen/5/traktanden/11/notizen')])
  })

  it('lädt für geänderte Felder eines Traktandums genau die Liste seiner Sitzung, ohne die Notizen', async () => {
    const w = await offeneSitzung()
    await ereignis(w, 'traktanden.updated', { id: 11, sitzungId: 5 })
    expect(angefragt()).toEqual([expect.stringMatching(/\/sitzungen\/5\/traktanden$/)])
  })

  it('lädt für eine Sitzungsnotiz an einem Geschäft genau dessen Notizen, und nur, wenn es auf einer offenen Sitzung steht', async () => {
    const w = await offeneSitzung()
    await ereignis(w, 'geschaefte.action', { id: 77, aktionTyp: 'sitzungsnotiz' })
    expect(angefragt()).toEqual([expect.stringContaining('/geschaefte/77/notizen')])

    axios.get.mockClear()
    await ereignis(w, 'geschaefte.action', { id: 999, aktionTyp: 'sitzungsnotiz' })
    expect(angefragt(), 'ein Geschäft, das auf keiner offenen Sitzung steht, lädt nach').toEqual([])
  })

  it('bündelt mehrere Ereignisse zu einer Anfrage je Ziel', async () => {
    const w = await offeneSitzung()
    w.vm.handleRealtimeEvent({ type: 'traktanden.updated', payload: { id: 11, sitzungId: 5, aktionTyp: 'notiz' } })
    w.vm.handleRealtimeEvent({ type: 'traktanden.updated', payload: { id: 11, sitzungId: 5, aktionTyp: 'notiz' } })
    w.vm.handleRealtimeEvent({ type: 'traktanden.updated', payload: { id: 11, sitzungId: 5, aktionTyp: 'notiz' } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    await flushPromises()
    expect(angefragt()).toHaveLength(1)
  })

  it('lädt nichts nach, wenn das Ereignis aus diesem Fenster stammt', async () => {
    const w = await offeneSitzung()
    await ereignis(w, 'traktanden.updated', { id: 11, sitzungId: 5, aktionTyp: 'notiz', _fenster: FENSTER, _uid: 'u' })
    await ereignis(w, 'geschaefte.action', { id: 77, aktionTyp: 'sitzungsnotiz', _fenster: FENSTER, _uid: 'u' })
    expect(angefragt()).toEqual([])
  })

  it('holt im Hintergrund die Notizen eines Traktandums, das in dieser Runde dazukommt', async () => {
    const w = await offeneSitzung()
    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({
        data: [
          ...TRAKTANDEN.map((t) => ({ ...t })),
          { id: 13, nummer: 3, titel: 'Neu ohne Geschäft', geschaeftId: 0, notizen: '[]' },
          { id: 14, nummer: 4, titel: 'Neu mit Geschäft', geschaeftId: 88, geschaeft: { id: 88, titel: 'Velo' }, notizen: '[]' },
        ],
      })
      : antworten(url)))
    await ereignis(w, 'traktanden.updated', { id: 11, sitzungId: 5 })
    const urls = angefragt()
    expect(urls.some((u) => u.endsWith('/sitzungen/5/traktanden/13/notizen')), 'das neue Traktandum ohne Geschäft holt seine Notizen nicht').toBe(true)
    expect(urls.some((u) => u.endsWith('/geschaefte/88/notizen')), 'das neue Traktandum mit Geschäft holt seine Sitzungsnotizen nicht').toBe(true)
    expect(urls.filter((u) => /\/traktanden\/1[12]\/notizen$|\/geschaefte\/77\/notizen$/.test(u)), 'die bekannten Traktanden holen ihre Notizen neu').toEqual([])
    expect(urls).toHaveLength(3)
  })

  it('zeigt nach dem Aufklappen den neuen Stand eines Geschäfts, das sich in der zugeklappten Sitzung geändert hat', async () => {
    const w = await offeneSitzung()
    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    expect(w.vm.offeneSitzungen, 'die Sitzung klappt nicht zu').not.toContain(5)

    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({ data: [TRAKTANDEN[0], { ...TRAKTANDEN[1], geschaeft: { id: 77, titel: 'Budget neu', prioritaet: 'hoch' } }] })
      : antworten(url)))
    await ereignis(w, 'geschaefte.updated', { id: 77 })

    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    const zeile = w.find('tr[aria-label="Traktandum 2 öffnen"]')
    expect(zeile.classes(), 'die aufgeklappte Sitzung zeigt die alte Priorität').toContain('pw-prio-hoch')
    expect(zeile.text()).toContain('Budget neu')
  })

  it('lädt nach einem Abgleich, der ein Geschäft der offenen Sitzung nennt, deren Traktanden im Hintergrund', async () => {
    const w = await offeneSitzung()
    await ereignis(w, 'sync.completed', {})
    expect(angefragt().filter((u) => /\/sitzungen\/5\/traktanden$/.test(u)), 'ein Abgleich ohne Geschäfte lädt die Traktanden').toEqual([])

    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({ data: [TRAKTANDEN[0], { ...TRAKTANDEN[1], geschaeft: { id: 77, titel: 'Budget neu', prioritaet: 'hoch' } }] })
      : antworten(url)))
    await ereignis(w, 'sync.completed', { geschaeftIds: [77] })
    expect(angefragt().filter((u) => /\/sitzungen\/5\/traktanden$/.test(u)), 'die Traktanden kommen nicht neu').toHaveLength(1)
    const zeile = w.find('tr[aria-label="Traktandum 2 öffnen"]')
    expect(zeile.classes(), 'die offene Sitzung zeigt die alte Priorität').toContain('pw-prio-hoch')
    expect(zeile.text()).toContain('Budget neu')
  })

  it.each(['sync.cancelled', 'sync.failed'])('lädt nach %s, das ein Geschäft der offenen Sitzung nennt, deren Traktanden im Hintergrund', async (typ) => {
    const w = await offeneSitzung()
    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({ data: [TRAKTANDEN[0], { ...TRAKTANDEN[1], geschaeft: { id: 77, titel: 'Budget neu', prioritaet: 'hoch' } }] })
      : antworten(url)))
    await ereignis(w, typ, { geschaeftIds: [77] })
    expect(angefragt().filter((u) => /\/sitzungen\/5\/traktanden$/.test(u)), 'die Traktanden kommen nicht neu').toHaveLength(1)
    const zeile = w.find('tr[aria-label="Traktandum 2 öffnen"]')
    expect(zeile.classes(), 'die offene Sitzung zeigt die alte Priorität').toContain('pw-prio-hoch')
    expect(zeile.text()).toContain('Budget neu')
  })

  it('zeigt nach einem Abgleich, der ein Geschäft der zugeklappten Sitzung nennt, beim Aufklappen den neuen Stand', async () => {
    const w = await offeneSitzung()
    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({ data: [TRAKTANDEN[0], { ...TRAKTANDEN[1], geschaeft: { id: 77, titel: 'Budget neu', prioritaet: 'hoch' } }] })
      : antworten(url)))
    await ereignis(w, 'sync.completed', { geschaeftIds: [77] })
    expect(angefragt().filter((u) => /\/sitzungen\/5\/traktanden$/.test(u)), 'die zugeklappte Sitzung lädt im Hintergrund').toEqual([])

    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    const zeile = w.find('tr[aria-label="Traktandum 2 öffnen"]')
    expect(zeile.classes(), 'die aufgeklappte Sitzung zeigt die alte Priorität').toContain('pw-prio-hoch')
    expect(zeile.text()).toContain('Budget neu')
  })

  it('lädt beim Aufklappen nicht neu, solange kein Ereignis die zugeklappte Sitzung betraf', async () => {
    const w = await offeneSitzung()
    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    // Ein Geschäft, das auf keinem Traktandum dieser Sitzung steht.
    await ereignis(w, 'geschaefte.updated', { id: 999 })
    axios.get.mockClear()

    await w.find('#pw-sitzung-5 .pw-sitzung-kopf').trigger('click')
    await flushPromises()
    expect(w.vm.offeneSitzungen).toContain(5)
    expect(angefragt().filter((u) => /\/sitzungen\/5\/traktanden$/.test(u)), 'das Aufklappen lädt die Traktanden neu').toEqual([])
    expect(w.find('tr[aria-label="Traktandum 2 öffnen"]').exists()).toBe(true)
  })
})

describe('Geschäft aus einem Traktandum bearbeiten', () => {
  it('zeigt nach dem Speichern in der Maske die neue Zeile des Traktandums', async () => {
    // Die Maske steht in einem Teleport; der Stub rendert ihren Inhalt an Ort und Stelle.
    const w = await offeneSitzung({ stubs: { teleport: true, GeschaeftMaske: false } })
    const zeile = () => w.find('tr[aria-label="Traktandum 2 öffnen"]')
    expect(zeile().classes()).not.toContain('pw-prio-hoch')
    await zeile().trigger('click')
    expect(w.vm.ausgewaehlteGeschaeftId, 'der Klick in die Zeile öffnet das Geschäft nicht').toBe(77)
    const maske = w.findComponent({ name: 'GeschaeftDetail' })
    expect(maske.exists(), 'die Maske öffnet nicht').toBe(true)
    expect(maske.props('geschaeftId')).toBe(77)

    axios.get.mockImplementation((url) => (String(url).endsWith('/sitzungen/5/traktanden')
      ? Promise.resolve({ data: [TRAKTANDEN[0], { ...TRAKTANDEN[1], geschaeft: { id: 77, titel: 'Budget neu', prioritaet: 'hoch' } }] })
      : antworten(url)))
    // So meldet GeschaeftDetail ein Speichern an die Maske (GeschaeftMaske.vue).
    w.findComponent({ name: 'GeschaeftMaske' }).vm.melden('gespeichert', 77)
    await flushPromises()

    expect(angefragt(), 'die Traktanden der Sitzung kommen nicht neu').toContainEqual(expect.stringMatching(/\/sitzungen\/5\/traktanden$/))
    expect(zeile().classes(), 'die Zeile zeigt die alte Priorität').toContain('pw-prio-hoch')
    expect(zeile().text()).toContain('Budget neu')
  })
})

/**
 * F147: Die Sitzung lädt für ein Ereignis aus diesem Fenster nichts. Was in
 * diesem Fenster geändert wird, zeigt sie deshalb selbst neu.
 */
describe('Eigene Änderungen in der Sitzung', () => {
  const ZWEI_SITZUNGEN = [{ ...SITZUNG }, { id: 6, datum: '2099-02-01', titel: 'Zweite Sitzung', notizen: '[]' }]
  const traktandumMit77 = (sid) => ({
    id: sid * 10, sitzungId: sid, nummer: 1, titel: 'Mit Geschäft', geschaeftId: 77,
    geschaeft: { id: 77, titel: 'Budget', erlaubteBeschluesse: [{ code: 'zustimmung', label: 'Zustimmung' }] }, notizen: '[]',
  })

  async function zweiOffeneSitzungen() {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.endsWith('/sitzungen/5/traktanden')) { return Promise.resolve({ data: [traktandumMit77(5)] }) }
      if (u.endsWith('/sitzungen/6/traktanden')) { return Promise.resolve({ data: [traktandumMit77(6)] }) }
      if (u.endsWith('/sitzungen')) { return Promise.resolve({ data: ZWEI_SITZUNGEN.map((s) => ({ ...s })) }) }
      return Promise.resolve({ data: [] })
    })
    const w = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    await flushPromises()
    for (const sid of [5, 6]) {
      w.vm.offeneSitzungen.push(sid)
      await w.vm.ladeTraktandenFuerSitzung(sid)
    }
    await flushPromises()
    axios.get.mockClear()
    return w
  }

  const traktandenAngefragt = () => angefragt().filter((u) => /\/sitzungen\/\d+\/traktanden$/.test(u)).sort()

  it('lädt nach einem Beschluss in der Zeile jede offene Sitzung mit diesem Geschäft', async () => {
    const w = await zweiOffeneSitzungen()
    const zelle = w.find('#pw-sitzung-5 td.pw-col-beschluss')
    zelle.findComponent('.pw-inline-select').vm.$emit('update:modelValue', { value: 'zustimmung', label: 'Zustimmung' })
    await flushPromises()
    expect(traktandenAngefragt(), 'die zweite Sitzung mit demselben Geschäft bleibt alt').toEqual([
      expect.stringMatching(/\/sitzungen\/5\/traktanden$/),
      expect.stringMatching(/\/sitzungen\/6\/traktanden$/),
    ])
  })

  it('lädt nach einer Zuständigkeit in der Zeile jede offene Sitzung mit diesem Geschäft', async () => {
    const w = await zweiOffeneSitzungen()
    const zelle = w.find('#pw-sitzung-5 td.pw-col-zustaendig')
    zelle.findComponent({ name: 'PwMultiSelect' }).vm.$emit('update:modelValue', [])
    await flushPromises()
    expect(traktandenAngefragt(), 'die zweite Sitzung mit demselben Geschäft bleibt alt').toEqual([
      expect.stringMatching(/\/sitzungen\/5\/traktanden$/),
      expect.stringMatching(/\/sitzungen\/6\/traktanden$/),
    ])
  })

  it('zeigt eine in der Maske erfasste Sitzungsnotiz in der Traktandenzeile', async () => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.endsWith('/sitzungen/5/traktanden')) { return Promise.resolve({ data: [traktandumMit77(5)] }) }
      if (u.endsWith('/sitzungen')) { return Promise.resolve({ data: [{ ...SITZUNG }] }) }
      if (u.endsWith('/geschaefte/77')) { return Promise.resolve({ data: { id: 77, externId: '2026.7', titel: 'Budget', aktionen: [], zustaendigkeiten: [] } }) }
      return Promise.resolve({ data: [] })
    })
    // Die echte Maske, damit ihre Notizenliste meldet, was sie gespeichert hat.
    const maskeOffen = shallowMount(Sitzungsliste, {
      props: { mitglieder: [], fraktionen: [], kommissionen: [] },
      global: { stubs: { teleport: true, GeschaeftDetail: false, GeschaeftMaske: false } },
    })
    await flushPromises()
    maskeOffen.vm.offeneSitzungen.push(5)
    await maskeOffen.vm.ladeTraktandenFuerSitzung(5)
    await flushPromises()
    await maskeOffen.find('tr[aria-label="Traktandum 1 öffnen"]').trigger('click')
    await flushPromises()
    const detail = maskeOffen.findComponent({ name: 'GeschaeftDetail' })
    const notiz = { id: 3, aktionTyp: 'sitzungsnotiz', text: 'Für die Fraktion', geloescht: false }
    detail.findAllComponents({ name: 'NotizenListe' })
      .find((liste) => liste.props('kategorie') === 'sitzungsnotiz')
      .vm.$emit('geaendert', [notiz])
    await flushPromises()
    const zeile = maskeOffen.findAll('tr.pw-traktandum-notizen-zeile')[0]
    expect(zeile.findComponent({ name: 'NotizenListe' }).props('notizen'), 'die Traktandenzeile zeigt die Sitzungsnotiz nicht').toEqual([notiz])
  })

  it('lädt die Sitzungen nach dem Entkoppeln neu', async () => {
    const w = await zweiOffeneSitzungen()
    axios.post.mockResolvedValue({ data: { verknuepfungId: null } })
    await w.vm.entkoppleSitzung(w.vm.sitzungen[0])
    await flushPromises()
    expect(angefragt().filter((u) => /\/sitzungen$/.test(u)), 'die Partnersitzung behält ihre alte Verknüpfung').toHaveLength(1)
  })

  it('lädt die Sitzungen nach dem Verknüpfen einer neuen Sitzung neu', async () => {
    const w = await zweiOffeneSitzungen()
    axios.post.mockImplementation((url) => Promise.resolve({
      data: String(url).endsWith('/verknuepfen') ? { id: 9, datum: '2099-03-01', titel: 'Neu', verknuepfungId: 5 } : { id: 9, datum: '2099-03-01', titel: 'Neu' },
    }))
    w.vm.gewaehlterTyp = { id: 1, name: 'Fraktionssitzung' }
    w.vm.neueSitzungDatum = '2099-03-01'
    w.vm.neueSitzungVerknuepfungId = 5
    await w.vm.erstelleNeueSession()
    await flushPromises()
    expect(angefragt().filter((u) => /\/sitzungen$/.test(u)), 'die Partnersitzung zeigt die neue Verknüpfung nicht').toHaveLength(1)
  })
})

describe('Kommissionen nach einer Datenaktualisierung', () => {
  it('lassen die Liste stehen, solange im Hintergrund nachgeladen wird', async () => {
    const Kommissionsliste = (await import('../components/Kommissionsliste.vue')).default
    axios.get.mockReset().mockImplementation((url) => Promise.resolve({
      data: String(url).endsWith('/kommissionen') ? [{ id: 1, name: 'Aufsichtskommission', mitglieder: '[]' }] : [],
    }))
    const w = shallowMount(Kommissionsliste, { props: { mitglieder: [] } })
    await flushPromises()
    const vorher = w.vm.kommissionen[0]
    let freigeben
    axios.get.mockImplementation((url) => (String(url).endsWith('/kommissionen')
      ? new Promise((weiter) => { freigeben = () => weiter({ data: [{ id: 1, name: 'Aufsichtskommission neu', mitglieder: '[]' }] }) })
      : Promise.resolve({ data: [] })))

    w.vm.handleRealtimeEvent({ type: 'sync.completed', payload: {} })
    await flushPromises()
    expect(w.vm.laden, 'die Kommissionen weichen dem Lade-Zustand').toBe(false)
    freigeben()
    await flushPromises()
    expect(w.vm.kommissionen[0], 'die Kommission wurde ersetzt statt angeglichen').toBe(vorher)
    expect(vorher.name).toBe('Aufsichtskommission neu')
  })
})

describe('Gemeinsames Nachladen (nachladen.js)', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('gibt die Ereignisse der Frist als eine Runde weiter und lässt eigene weg', async () => {
    const runden = []
    const annehmen = ereignisseBuendeln((runde) => runden.push(runde))
    annehmen({ type: 'a', payload: {} })
    annehmen({ type: 'b', payload: { _fenster: FENSTER, _uid: 'u' } })
    annehmen({ type: 'c', payload: { _fenster: 'anderes-fenster', _uid: 'u' } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    expect(runden.map((r) => r.map((e) => e.type))).toEqual([['a', 'c']])
  })

  // Die Kennung des Fensters geht an alle Empfänger. Schickt ein anderer
  // Benutzer sie mit, bleibt sein Ereignis ein fremdes, und dieses Fenster
  // lädt nach.
  it('lädt nach, wenn ein anderer Benutzer die Kennung dieses Fensters mitschickt', async () => {
    const runden = []
    const annehmen = ereignisseBuendeln((runde) => runden.push(runde))
    annehmen({ type: 'fremd', payload: { _fenster: FENSTER, _uid: 'jemand-anderes' } })
    annehmen({ type: 'ohne-benutzer', payload: { _fenster: FENSTER } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    expect(runden.map((r) => r.map((e) => e.type))).toEqual([['fremd', 'ohne-benutzer']])
  })

  it('behält eigene Ereignisse, wo die Ansicht sie braucht', async () => {
    const runden = []
    const annehmen = ereignisseBuendeln((runde) => runden.push(runde), { eigeneUeberspringen: false })
    annehmen({ type: 'a', payload: { _fenster: FENSTER, _uid: 'u' } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    expect(runden).toHaveLength(1)
  })

  it('gleicht eine Liste an Ort und Stelle an', () => {
    const erster = { id: 1, titel: 'A' }
    const liste = [erster, { id: 2, titel: 'B' }]
    listeAbgleichen(liste, [{ id: 3, titel: 'C' }, { id: 1, titel: 'A neu' }])
    expect(liste.map((e) => e.id)).toEqual([3, 1])
    expect(liste[1], 'der bestehende Eintrag wurde ersetzt statt angeglichen').toBe(erster)
    expect(erster.titel).toBe('A neu')
  })

  it('hängt die Kennung des Fensters an jede Anfrage', () => {
    let abfangen
    const client = { interceptors: { request: { use: (f) => { abfangen = f } } } }
    fensterKennungAnhaengen(client)
    expect(abfangen({ headers: {} }).headers[FENSTER_HEADER]).toBe(FENSTER)
    expect(FENSTER).toMatch(/^[A-Za-z0-9-]{8,64}$/)
  })
})
