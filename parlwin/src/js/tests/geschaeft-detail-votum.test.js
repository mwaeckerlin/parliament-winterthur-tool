import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { aktionen: [], zustaendigkeiten: [] } })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
    put: vi.fn(() => Promise.resolve({ data: { id: 55 } })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'

// Das Votum im Rat speichert automatisch (kurz nach dem Tippen bzw. sofort vor
// dem Archivieren) und lässt sich archivieren.
describe('GeschaeftDetail — Votum', () => {
  beforeEach(() => {
    axios.put.mockClear()
    axios.post.mockClear()
    vi.useFakeTimers()
  })

  const mountFn = () => shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
    data() {
      return { geschaeft: { id: 1, titel: 'T', aktionen: [], zustaendig: [] }, laden: false }
    },
    global: {
      stubs: { NcSelect: true, PwMultiSelect: true, PwWysiwyg: true, GeschaeftDokumente: true },
    },
  })

  it('für Nicht-Zuständige ist das leere Votum-Feld NICHT sichtbar (kein grauer Kasten)', async () => {
    const wrapper = mountFn()
    wrapper.vm.laden = false
    wrapper.vm.geschaeft = { id: 1, titel: 'T', aktionen: [], zustaendigkeiten: [] }
    await wrapper.vm.$nextTick()
    // Niemand zuständig, Votum leer → das Feld erscheint gar nicht.
    expect(wrapper.vm.votumSchreibbar).toBe(false)
    expect(wrapper.vm.votumHatInhalt).toBe(false)
    expect(wrapper.find('.pw-votum').exists(), 'Ein leeres Votum darf für Nicht-Zuständige kein Feld zeigen').toBe(false)
  })

  it('für Nicht-Zuständige mit vorhandenem Votum ist der Wortlaut sichtbar, aber schreibgeschützt', async () => {
    const wrapper = mountFn()
    // Erst das anfängliche Laden abwarten (es setzt votumHtml sonst danach zurück).
    await flushPromises()
    wrapper.vm.laden = false
    wrapper.vm.geschaeft = { id: 1, titel: 'T', aktionen: [], zustaendigkeiten: [] }
    wrapper.vm.votumHtml = '<p>Ein erfasstes Votum</p>'
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.votumSchreibbar).toBe(false)
    expect(wrapper.vm.votumHatInhalt).toBe(true)
    // Feld sichtbar (Wortlaut lesbar), Hinweis auf die Zuständigkeit, kein Archivieren.
    expect(wrapper.find('.pw-votum').exists(), 'Ein vorhandenes Votum muss auch für Nicht-Zuständige sichtbar sein').toBe(true)
    expect(wrapper.find('.pw-votum .pw-hinweis').exists(), 'Hinweis zur Zuständigkeit fehlt').toBe(true)
    expect(wrapper.find('.pw-votum-archivieren').exists()).toBe(false)
  })

  it('die zuständige Person kann das Votum erfassen und archivieren', async () => {
    const wrapper = shallowMount(GeschaeftDetail, {
      props: {
        geschaeftId: 1,
        // «testuser» ist der angemeldete Benutzer in der Testumgebung.
        mitglieder: [{ externId: 'e1', vorname: 'Anna', name: 'Muster', nextcloudUid: 'testuser', aktiv: true }],
        traktandumKontext: null,
      },
      global: { stubs: { NcSelect: true, PwMultiSelect: true, PwWysiwyg: true, GeschaeftDokumente: true } },
    })
    // Erst das anfängliche Laden abwarten — es setzt die Zuständigkeiten sonst
    // nachträglich wieder zurück.
    await flushPromises()
    wrapper.vm.laden = false
    wrapper.vm.geschaeft = { id: 1, titel: 'T', aktionen: [], zustaendigkeiten: [] }
    wrapper.vm.ausgewaehltePersonKeys = ['mitglied:e1']
    wrapper.vm.votumHtml = '<p>Mein Votum</p>'
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.votumSchreibbar, 'Die zuständige Person darf das Votum nicht erfassen').toBe(true)
    expect(wrapper.find('.pw-votum .pw-hinweis').exists(), 'Für die zuständige Person darf kein Sperr-Hinweis erscheinen').toBe(false)
    expect(wrapper.find('.pw-votum-archivieren').exists(), 'Der Knopf zum Archivieren des Votums fehlt').toBe(true)
    expect(wrapper.vm.votumPdfUrl, 'Der PDF-Verweis fehlt').toContain('/geschaefte/1/votum/pdf')
  })

  it('speichert das Votum kurz nach der Eingabe automatisch (PUT auf den Votum-Endpunkt)', async () => {
    const wrapper = mountFn()
    wrapper.vm.votumGeaendert('<p>Mein Votum</p>')
    expect(axios.put).not.toHaveBeenCalled()
    vi.advanceTimersByTime(900)
    await Promise.resolve()
    await Promise.resolve()
    expect(axios.put).toHaveBeenCalledTimes(1)
    expect(axios.put.mock.calls[0][0]).toContain('/geschaefte/1/votum')
    expect(axios.put.mock.calls[0][1]).toEqual({ text: '<p>Mein Votum</p>' })
    expect(wrapper.vm.votumStatus).toBe('Gespeichert')
  })

  it('ein unverändertes Votum wird nicht erneut gespeichert', async () => {
    const wrapper = mountFn()
    wrapper.vm.votumGeaendert('<p>V</p>')
    vi.advanceTimersByTime(900)
    await Promise.resolve()
    await Promise.resolve()
    await wrapper.vm.votumSofortSpeichern() // nichts dirty → kein zweiter PUT
    expect(axios.put).toHaveBeenCalledTimes(1)
  })

  it('Archivieren sichert ausstehende Eingaben, ruft den Archiv-Endpunkt und leert das Votum', async () => {
    const wrapper = mountFn()
    wrapper.vm.votumGeaendert('<p>V</p>')
    await wrapper.vm.votumArchivieren()
    expect(axios.put).toHaveBeenCalledTimes(1) // ausstehende Eingabe zuerst gespeichert
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/votum/archivieren'))).toBe(true)
    expect(wrapper.vm.votumHtml).toBe('')
    expect(wrapper.vm.votumStatus).toBe('Votum archiviert')
  })
})
