import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { BESTAND_SCHLUESSEL, erzeugeBestand } from '../geschaefte-bestand.js'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import Kommissionsliste from '../components/Kommissionsliste.vue'
import Sitzungsliste from '../components/Sitzungsliste.vue'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'Anna Beispiel' }) }))

/**
 * F8, F150: Die Maske eines Geschäfts ist in «Geschäfte», «Kommissionen» und
 * «Sitzungen» dieselbe. Was sie speichert, steht danach in der Ansicht, auch
 * wenn die Maske schon zu ist, bevor die Antwort des Servers kommt: Ein
 * geänderter Titel speichert beim Verlassen des Feldes, und das Verlassen ist
 * oft der Klick auf ✕.
 */
let server
let putFreigeben

function antworten(url) {
  const u = String(url)
  if (u.endsWith('/apps/parlwin/geschaefte')) { return Promise.resolve({ data: [{ ...server }] }) }
  if (u.endsWith('/apps/parlwin/geschaefte/7')) { return Promise.resolve({ data: { ...server, aktionen: [], zustaendigkeiten: [] } }) }
  if (u.endsWith('/apps/parlwin/kommissionen')) { return Promise.resolve({ data: [{ id: 1, name: 'Aufsichtskommission', aktiv: true, mitglieder: '[]' }] }) }
  if (u.endsWith('/apps/parlwin/sitzungen')) { return Promise.resolve({ data: [{ id: 5, datum: '2099-01-01', titel: 'Parlamentssitzung', notizen: '[]' }] }) }
  if (u.endsWith('/sitzungen/5/traktanden')) {
    return Promise.resolve({ data: [{ id: 12, sitzungId: 5, nummer: 1, titel: 'Traktandum', geschaeftId: 7, geschaeft: { ...server }, notizen: '[]' }] })
  }
  return Promise.resolve({ data: [] })
}

async function anzeigen(Ansicht) {
  const bestand = erzeugeBestand()
  const w = shallowMount(Ansicht, {
    props: { mitglieder: [], fraktionen: [], kommissionen: [] },
    global: {
      provide: { [BESTAND_SCHLUESSEL]: bestand },
      stubs: { teleport: true, GeschaeftDetail: false, GeschaeftMaske: false },
    },
  })
  await bestand.laden()
  await flushPromises()
  return w
}

/** Ändert den Titel in der offenen Maske und schliesst sie mit ✕, bevor der Server antwortet. */
async function titelAendernUndSchliessen(w) {
  await flushPromises()
  const detail = w.findComponent({ name: 'GeschaeftDetail' })
  expect(detail.exists(), 'die Maske öffnet nicht').toBe(true)
  axios.put.mockImplementation(() => new Promise((weiter) => { putFreigeben = () => weiter({ data: {} }) }))
  server = { ...server, titel: 'Neuer Titel' }
  detail.vm.geschaeft.titel = 'Neuer Titel'
  detail.vm.stammdatenSpeichern()
  await w.find('.pw-btn-schliessen').trigger('click')
  expect(w.findComponent({ name: 'GeschaeftDetail' }).exists(), 'das ✕ schliesst die Maske nicht').toBe(false)
  putFreigeben()
  await flushPromises()
}

describe('Gespeichert kurz vor dem Schliessen (F150)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    server = { id: 7, externId: 'eigen:7', nummer: '', titel: 'Alter Titel', status: 'Pendent', datum: '2026-11-01', kommission: 'Aufsichtskommission', prioritaet: '' }
    axios.get.mockImplementation(antworten)
  })

  it('«Geschäfte» zeigt den neuen Titel in der Tabelle', async () => {
    const w = await anzeigen(Geschaeftsliste)
    w.vm.oeffneDetail(7)
    await titelAendernUndSchliessen(w)
    expect(w.find('.pw-tabelle-geschaefte tbody').text(), 'die Tabelle zeigt den alten Titel').toContain('Neuer Titel')
  })

  it('«Kommissionen» zeigt den neuen Titel bei der Kommission', async () => {
    const w = await anzeigen(Kommissionsliste)
    await w.find('.pw-kommission-geschaeft-eintrag').trigger('click')
    await titelAendernUndSchliessen(w)
    expect(w.find('.pw-kommission-geschaeft-eintrag').text(), 'die Karte zeigt den alten Titel').toContain('Neuer Titel')
  })

  it('«Sitzungen» zeigt den neuen Titel in der Traktandenzeile', async () => {
    const w = await anzeigen(Sitzungsliste)
    w.vm.offeneSitzungen.push(5)
    await w.vm.ladeTraktandenFuerSitzung(5)
    await flushPromises()
    await w.find('tr[aria-label="Traktandum 1 öffnen"]').trigger('click')
    await titelAendernUndSchliessen(w)
    expect(w.find('tr[aria-label="Traktandum 1 öffnen"]').text(), 'die Traktandenzeile zeigt den alten Titel').toContain('Neuer Titel')
  })
})

// Eine Attrappe der Maske, deren Notizen-Editor ungespeicherten Text hält.
const DetailAttrappe = {
  name: 'GeschaeftDetail',
  props: { geschaeftId: { type: Number, default: 0 } },
  emits: ['oeffneGeschaeft'],
  methods: { hatUngespeicherteNotizen: () => true },
  template: '<div class="pw-detail-attrappe" />',
}

const OEFFNEN = {
  Geschäfte: { Ansicht: Geschaeftsliste, oeffnen: (w) => w.vm.oeffneDetail(7) },
  Kommissionen: { Ansicht: Kommissionsliste, oeffnen: (w) => w.find('.pw-kommission-geschaeft-eintrag').trigger('click') },
  Sitzungen: {
    Ansicht: Sitzungsliste,
    oeffnen: async (w) => {
      w.vm.offeneSitzungen.push(5)
      await w.vm.ladeTraktandenFuerSitzung(5)
      await flushPromises()
      await w.find('tr[aria-label="Traktandum 1 öffnen"]').trigger('click')
    },
  },
}

describe.each(Object.keys(OEFFNEN))('Maske in «%s» (F8)', (name) => {
  const { Ansicht, oeffnen } = OEFFNEN[name]
  let bestaetigen

  beforeEach(() => {
    vi.clearAllMocks()
    server = { id: 7, externId: 'eigen:7', nummer: '', titel: 'Alter Titel', status: 'Pendent', datum: '2026-11-01', kommission: 'Aufsichtskommission', prioritaet: '' }
    axios.get.mockImplementation(antworten)
    bestaetigen = vi.spyOn(window, 'confirm').mockReturnValue(true)
  })

  async function offen() {
    const bestand = erzeugeBestand()
    const w = shallowMount(Ansicht, {
      props: { mitglieder: [], fraktionen: [], kommissionen: [] },
      global: { provide: { [BESTAND_SCHLUESSEL]: bestand }, stubs: { teleport: true, GeschaeftDetail: DetailAttrappe, GeschaeftMaske: false } },
    })
    await bestand.laden()
    await flushPromises()
    await oeffnen(w)
    await flushPromises()
    expect(w.findComponent(DetailAttrappe).exists(), 'die Maske öffnet nicht').toBe(true)
    return w
  }

  it('fragt vor dem Schliessen mit ✕ nach, wenn eine Notiz nicht gespeichert ist', async () => {
    const w = await offen()
    bestaetigen.mockReturnValue(false)
    await w.find('.pw-btn-schliessen').trigger('click')
    expect(bestaetigen, 'das ✕ verwirft die Notiz ohne Rückfrage').toHaveBeenCalledTimes(1)
    expect(w.findComponent(DetailAttrappe).exists(), 'die Maske schliesst trotz «Abbrechen»').toBe(true)
    bestaetigen.mockReturnValue(true)
    await w.find('.pw-btn-schliessen').trigger('click')
    expect(w.findComponent(DetailAttrappe).exists()).toBe(false)
  })

  it('öffnet das Geschäft, auf das die Maske verweist', async () => {
    const w = await offen()
    w.findComponent(DetailAttrappe).vm.$emit('oeffneGeschaeft', 42)
    await flushPromises()
    expect(w.findComponent(DetailAttrappe).props('geschaeftId'), 'der Verweis auf das andere Geschäft tut nichts').toBe(42)
  })
})
