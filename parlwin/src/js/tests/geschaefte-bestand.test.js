import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { BUENDEL_MS, FENSTER } from '../nachladen'
import { BESTAND_SCHLUESSEL, SEITE, erzeugeBestand, istErledigt } from '../geschaefte-bestand.js'
import Kommissionsliste from '../components/Kommissionsliste.vue'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

/**
 * F150: «Kommissionen», «Geschäfte» und «Sitzungen» teilen eine Liste der
 * Geschäfte. Sie kommt einmal vom Server, und ein Ereignis lädt nur die
 * Geschäfte nach, die es nennt. Vorher lud jede Ansicht bei jedem Ereignis
 * die ganze Liste, die erste davon in Blöcken zu 1000 mit den erledigten.
 */
const BESTAND = [
  { id: 3, nummer: '2026.3', titel: 'Drei', status: 'In Kommission', datum: '2026-03-03', prioritaet: '' },
  { id: 2, nummer: '2026.2', titel: 'Zwei', status: 'Erledigt', datum: '2026-02-02', prioritaet: '' },
  { id: 1, nummer: '2026.1', titel: 'Eins', status: 'In Kommission', datum: '2026-01-01', prioritaet: '' },
]

let server
function antworten(url, config = {}) {
  const params = config.params || {}
  if (String(url).includes('/apps/parlwin/geschaefte') && params.ids) {
    const ids = String(params.ids).split(',').map(Number)
    return Promise.resolve({ data: server.filter((g) => ids.includes(g.id)).map((g) => ({ ...g })) })
  }
  if (String(url).includes('/apps/parlwin/geschaefte')) {
    const ab = Number(params.offset || 0)
    return Promise.resolve({ data: server.slice(ab, ab + Number(params.limit || server.length)).map((g) => ({ ...g })) })
  }
  return Promise.resolve({ data: [] })
}

function anfragen() {
  return axios.get.mock.calls
    .filter(([url]) => String(url).includes('/apps/parlwin/geschaefte'))
    .map(([, config]) => config?.params || {})
}

async function geladenerBestand() {
  const bestand = erzeugeBestand()
  await bestand.laden()
  axios.get.mockClear()
  return bestand
}

async function ereignis(bestand, typ, payload = {}) {
  bestand.ereignis({ type: typ, payload })
  await vi.advanceTimersByTimeAsync(BUENDEL_MS)
  await flushPromises()
}

describe('Geteilte Liste der Geschäfte', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    server = BESTAND.map((g) => ({ ...g }))
    axios.get.mockReset().mockImplementation(antworten)
  })
  afterEach(() => { vi.useRealTimers() })

  it('lädt die ganze Liste mit den erledigten einmal und danach ohne Anfrage', async () => {
    const bestand = erzeugeBestand()
    await bestand.laden()
    await bestand.laden()
    expect(anfragen()).toEqual([{ show_erledigt: 1, limit: SEITE, offset: 0 }])
    expect(bestand.zustand.geschaefte.map((g) => g.id)).toEqual([3, 2, 1])
  })

  it('lädt nach einem Ereignis nur das genannte Geschäft und ersetzt es an Ort und Stelle', async () => {
    const bestand = await geladenerBestand()
    const vorher = bestand.zustand.geschaefte[0]
    server[0].prioritaet = 'hoch'
    await ereignis(bestand, 'geschaefte.updated', { id: 3, grund: 'prioritaet' })
    expect(anfragen()).toEqual([{ ids: '3' }])
    expect(bestand.zustand.geschaefte[0]).toBe(vorher)
    expect(vorher.prioritaet).toBe('hoch')
  })

  it('fasst die Ereignisse einer Frist zu einer Anfrage zusammen', async () => {
    const bestand = await geladenerBestand()
    bestand.ereignis({ type: 'geschaefte.updated', payload: { id: 3 } })
    bestand.ereignis({ type: 'geschaefte.action', payload: { id: 1, aktionTyp: 'notiz' } })
    bestand.ereignis({ type: 'geschaefte.updated', payload: { id: 3 } })
    await vi.advanceTimersByTimeAsync(BUENDEL_MS)
    await flushPromises()
    expect(anfragen()).toEqual([{ ids: '3,1' }])
  })

  it('lädt nach einem Abgleich nur die Geschäfte, die er nennt', async () => {
    const bestand = await geladenerBestand()
    server.unshift({ id: 4, nummer: '2026.4', titel: 'Vier', status: 'Eingereicht', datum: '2026-04-04' })
    await ereignis(bestand, 'sync.completed', { geschaeftIds: [4, 1] })
    expect(anfragen()).toEqual([{ ids: '4,1' }])
    expect(bestand.zustand.geschaefte.map((g) => g.id)).toEqual([4, 3, 2, 1])
  })

  it('lädt nach einem Abgleich ohne geänderte Geschäfte nichts', async () => {
    const bestand = await geladenerBestand()
    await ereignis(bestand, 'sync.completed', { geschaeftIds: [] })
    expect(anfragen()).toEqual([])
  })

  it('lädt die ganze Liste, wenn der Abgleich keine Geschäfte nennt', async () => {
    const bestand = await geladenerBestand()
    await ereignis(bestand, 'sync.completed', { geschaeftIds: null })
    expect(anfragen()).toEqual([{ show_erledigt: 1, limit: SEITE, offset: 0 }])
  })

  it.each(['sync.cancelled', 'sync.failed'])('lädt nach %s die Geschäfte, die der Abgleich bis dahin geändert hat', async (typ) => {
    const bestand = await geladenerBestand()
    server.unshift({ id: 4, nummer: '2026.4', titel: 'Vier', status: 'Eingereicht', datum: '2026-04-04' })
    server.find((g) => g.id === 1).titel = 'Eins neu'
    await ereignis(bestand, typ, { geschaeftIds: [4, 1] })
    expect(anfragen()).toEqual([{ ids: '4,1' }])
    expect(bestand.zustand.geschaefte.map((g) => g.id)).toEqual([4, 3, 2, 1])
    expect(bestand.zustand.geschaefte.find((g) => g.id === 1).titel).toBe('Eins neu')
  })

  // Ohne das Feld kommt `sync.cancelled` aus dem Aufruf, der den Lauf beendet hat und seine Änderungen nicht kennt.
  it.each([
    ['sync.cancelled', { geschaeftIds: null }],
    ['sync.failed', { geschaeftIds: null }],
    ['sync.cancelled', {}],
  ])('lädt nach %s ohne genannte Geschäfte die ganze Liste (%j)', async (typ, nutzlast) => {
    const bestand = await geladenerBestand()
    await ereignis(bestand, typ, nutzlast)
    expect(anfragen()).toEqual([{ show_erledigt: 1, limit: SEITE, offset: 0 }])
  })

  it('nimmt ein Geschäft heraus, das der Server nicht mehr liefert', async () => {
    const bestand = await geladenerBestand()
    server = server.filter((g) => g.id !== 2)
    await ereignis(bestand, 'geschaefte.updated', { id: 2 })
    expect(bestand.zustand.geschaefte.map((g) => g.id)).toEqual([3, 1])
  })

  it('lädt für ein Ereignis aus diesem Fenster nichts', async () => {
    const bestand = await geladenerBestand()
    await ereignis(bestand, 'geschaefte.updated', { id: 3, _fenster: FENSTER, _uid: 'u' })
    expect(anfragen()).toEqual([])
  })

  it('erkennt erledigte Geschäfte nach der Regel des Servers', () => {
    expect(istErledigt({ status: 'Erledigt' })).toBe(true)
    expect(istErledigt({ status: 'Geschäft abgeschlossen' })).toBe(true)
    expect(istErledigt({ status: 'Aufgehoben' })).toBe(true)
    expect(istErledigt({ status: 'In Kommission' })).toBe(false)
    expect(istErledigt({ status: null })).toBe(false)
  })

  it('«Kommissionen» und «Geschäfte» holen die Liste zusammen einmal', async () => {
    const bestand = erzeugeBestand()
    const optionen = {
      props: { mitglieder: [] },
      global: { provide: { [BESTAND_SCHLUESSEL]: bestand } },
    }
    const kommissionen = shallowMount(Kommissionsliste, optionen)
    const geschaefte = shallowMount(Geschaeftsliste, optionen)
    await flushPromises()
    expect(anfragen()).toEqual([{ show_erledigt: 1, limit: SEITE, offset: 0 }])
    // «Geschäfte» zeigt ohne «Erledigte anzeigen» die hängigen.
    expect(geschaefte.vm.geschaefte.map((g) => g.id).sort()).toEqual([1, 3])
    expect(kommissionen.vm.geschaefte.map((g) => g.id)).toEqual([3, 2, 1])

    axios.get.mockClear()
    server[2].titel = 'Eins neu'
    await ereignis(bestand, 'geschaefte.updated', { id: 1 })
    expect(anfragen()).toEqual([{ ids: '1' }])
    expect(geschaefte.vm.geschaefte.find((g) => g.id === 1).titel).toBe('Eins neu')
    expect(kommissionen.vm.geschaefte.find((g) => g.id === 1).titel).toBe('Eins neu')
  })

  // Fehler vom 2026-11-04: Im Fraktionssitzungsmodus setzt «Geschäfte» beim
  // Öffnen «Nur Entscheid nötig» und lud danach nichts, die Tabelle blieb leer.
  it('«Geschäfte» lädt die Liste auch, wenn der Fraktionssitzungsmodus den Filter vorgibt', async () => {
    server = [
      { id: 5, nummer: '2026.5', titel: 'Offen', status: 'In Kommission', datum: '2026-05-05', entscheidungsbedarf: true },
      { id: 6, nummer: '2026.6', titel: 'Entschieden', status: 'In Kommission', datum: '2026-06-06', entscheidungsbedarf: false },
    ]
    axios.get.mockImplementation((url, config) => (String(url).includes('/settings/fraktionssitzung')
      ? Promise.resolve({ data: { modusAktiv: true } })
      : antworten(url, config)))
    const geschaefte = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(0)
    await flushPromises()
    expect(geschaefte.vm.filterEntscheidungsbedarf).toBe('1')
    expect(geschaefte.vm.geschaefte.map((g) => g.id)).toEqual([5])
  })

  // Fehler vom 2026-11-04: Mit «Erledigte anzeigen» zeichnete die Ansicht alle
  // Geschäfte samt den erledigten, und der Browser stand still. Wie vorher die
  // Antwort des Servers zeigt sie die neuesten 500.
  it('«Geschäfte» zeigt mit «Erledigte anzeigen» die neuesten 500', async () => {
    server = Array.from({ length: 1200 }, (_, i) => ({
      id: i + 1,
      nummer: `2020.${i + 1}`,
      titel: `G${i + 1}`,
      status: 'Erledigt',
      datum: `2020-01-01T00:00:${String(i % 60).padStart(2, '0')}.${String(i).padStart(4, '0')}`,
    }))
    const geschaefte = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    await flushPromises()
    geschaefte.vm.zeigeErledigte = true
    await geschaefte.vm.$nextTick()
    await vi.advanceTimersByTimeAsync(0)
    await flushPromises()
    expect(geschaefte.vm.gefilterteGeschaefte).toHaveLength(500)
    const neueste = [...server].sort((a, b) => b.datum.localeCompare(a.datum)).slice(0, 500).map((g) => g.id).sort((a, b) => a - b)
    expect(geschaefte.vm.gefilterteGeschaefte.map((g) => g.id).sort((a, b) => a - b)).toEqual(neueste)
    expect(geschaefte.findAll('.pw-tabelle-geschaefte tbody tr[role="button"]'), 'die Tabelle zeichnet mehr als 500 Zeilen').toHaveLength(500)
  })

  it('«Geschäfte» findet mit «Erledigte anzeigen» über die Suche auch ein Geschäft ausserhalb der neuesten 500', async () => {
    server = Array.from({ length: 1200 }, (_, i) => ({
      id: i + 1,
      nummer: `2020.${i + 1}`,
      titel: `G${i + 1}`,
      status: 'Erledigt',
      datum: `2020-01-01T00:00:${String(i % 60).padStart(2, '0')}.${String(i).padStart(4, '0')}`,
    }))
    const geschaefte = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    await flushPromises()
    geschaefte.vm.zeigeErledigte = true
    await geschaefte.vm.$nextTick()
    await vi.advanceTimersByTimeAsync(0)
    await flushPromises()
    const aeltestes = [...server].sort((a, b) => a.datum.localeCompare(b.datum))[0]
    geschaefte.vm.suche = aeltestes.titel
    await geschaefte.vm.$nextTick()
    expect(geschaefte.vm.gefilterteGeschaefte.map((g) => g.id), 'die Suche findet nur unter den neuesten 500').toContain(aeltestes.id)
    expect(geschaefte.find('.pw-tabelle-geschaefte tbody').text(), 'das gefundene Geschäft steht nicht in der Tabelle').toContain(aeltestes.nummer)
  })
})

/**
 * Antworten, die sich überholen: Der Server antwortet mit dem Stand im Moment
 * der Anfrage, und der Test gibt die Antworten in der Reihenfolge frei, die er
 * braucht. `art` sagt, welche Anfragen warten: die der ganzen Liste oder die
 * nach einzelnen IDs; die anderen kommen sofort.
 */
function zurueckhalten(art) {
  const offen = []
  axios.get.mockImplementation((url, config = {}) => {
    const antwort = antworten(url, config)
    if ((art === 'ids') !== !!config.params?.ids) { return antwort }
    return new Promise((weiter) => { offen.push(() => weiter(antwort)) })
  })
  return offen
}

describe('Geteilte Liste der Geschäfte bei überholten Antworten', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    server = BESTAND.map((g) => ({ ...g }))
    axios.get.mockReset().mockImplementation(antworten)
  })
  afterEach(() => { vi.useRealTimers() })

  it('lädt die ganze Liste noch einmal, wenn sie während einer Ladung neu verlangt wird', async () => {
    const offen = zurueckhalten('liste')
    const bestand = erzeugeBestand()
    const erste = bestand.laden()
    await flushPromises()
    server[0].titel = 'Drei neu'
    const neu = bestand.laden({ neu: true })
    offen[0]()
    await flushPromises()
    expect(offen, 'die zweite Ladung wurde nicht angefragt').toHaveLength(2)
    offen[1]()
    await Promise.all([erste, neu])
    expect(bestand.zustand.geschaefte.find((g) => g.id === 3).titel).toBe('Drei neu')
  })

  it('eine Ladung überschreibt ein Geschäft nicht, das während ihr nachgeladen wurde', async () => {
    const bestand = await geladenerBestand()
    const offen = zurueckhalten('liste')
    const ladung = bestand.laden({ neu: true })
    await flushPromises()
    server[0].prioritaet = 'hoch'
    await bestand.nachladen([3])
    expect(bestand.zustand.geschaefte[0].prioritaet).toBe('hoch')
    offen[0]()
    await ladung
    expect(bestand.zustand.geschaefte[0].prioritaet, 'die ältere Ladung hat das Nachladen überschrieben').toBe('hoch')
  })

  it('bei zwei Nachladungen mit vertauschten Antworten gilt die neuere', async () => {
    const bestand = await geladenerBestand()
    const offen = zurueckhalten('ids')
    server[0].prioritaet = 'tief'
    const aeltere = bestand.nachladen([3])
    await flushPromises()
    server[0].prioritaet = 'hoch'
    const neuere = bestand.nachladen([3])
    await flushPromises()
    offen[1]()
    await neuere
    offen[0]()
    await aeltere
    expect(bestand.zustand.geschaefte[0].prioritaet, 'die ältere Antwort hat die neuere überschrieben').toBe('hoch')
  })

  it('ein Ereignis während der ersten Ladung lädt danach das genannte Geschäft nach', async () => {
    const offen = zurueckhalten('liste')
    const bestand = erzeugeBestand()
    const ladung = bestand.laden()
    await flushPromises()
    server[0].titel = 'Drei neu'
    await ereignis(bestand, 'geschaefte.updated', { id: 3 })
    offen[0]()
    await ladung
    await flushPromises()
    expect(anfragen()).toEqual([{ show_erledigt: 1, limit: SEITE, offset: 0 }, { ids: '3' }])
    expect(bestand.zustand.geschaefte.find((g) => g.id === 3).titel).toBe('Drei neu')
  })

  it('ein Abgleich ohne genannte Geschäfte während der ersten Ladung lädt danach die ganze Liste', async () => {
    const offen = zurueckhalten('liste')
    const bestand = erzeugeBestand()
    const ladung = bestand.laden()
    await flushPromises()
    server.unshift({ id: 4, nummer: '2026.4', titel: 'Vier', status: 'Eingereicht', datum: '2026-04-04' })
    await ereignis(bestand, 'sync.completed', { geschaeftIds: null })
    offen[0]()
    await flushPromises()
    expect(offen, 'die zweite Ladung wurde nicht angefragt').toHaveLength(2)
    offen[1]()
    await ladung
    expect(bestand.zustand.geschaefte.map((g) => g.id)).toEqual([4, 3, 2, 1])
  })

  it('ein Ereignis ohne laufende und ohne frühere Ladung lädt nichts', async () => {
    const bestand = erzeugeBestand()
    await ereignis(bestand, 'geschaefte.updated', { id: 3 })
    await ereignis(bestand, 'sync.completed', { geschaeftIds: null })
    expect(anfragen()).toEqual([])
  })
})
