import { describe, it, expect, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Fragestunde from '../components/Fragestunde.vue'
import axios from '@nextcloud/axios'

// Der angemeldete Benutzer ist «amueller» (= Anna Müller, aktiv).
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'amueller', displayName: 'Anna Müller' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

const MITGLIEDER = [
  { id: 1, vorname: 'Anna', name: 'Müller', nextcloudUid: 'amueller', aktiv: true, externId: 'e1' },
  { id: 2, vorname: 'Bob', name: 'Meier', nextcloudUid: 'bmeier', aktiv: true, externId: 'e2' },
  { id: 3, vorname: 'Carla', name: 'Alt', nextcloudUid: 'calt', aktiv: false, externId: 'e3' },
]

const ZUGETEILTE_FRAGE = {
  id: 11,
  fragestundeId: 7,
  fragestunde: { id: 7, titel: 'Fragestunde vom 2. März 2026', datum: '2026-03-02', frist: '2026-02-26' },
  frage: 'Warum wurde das wohnungsnahe Parkieren der Spitex eingeschränkt?',
  zeichen: 62,
  kommentar: '',
  status: 'neu',
  urheber: { key: 'mitglied:e1', name: 'Anna Müller' },
  einreicher: { key: '', name: '' },
  notizen: [],
}

const FREIE_FRAGE = {
  ...ZUGETEILTE_FRAGE,
  id: 12,
  fragestundeId: 0,
  fragestunde: null,
  frage: 'Wie viele Bäume pflanzt die Stadt im nächsten Jahr?',
}

const FRAGESTUNDE = {
  id: 7,
  datum: '2026-03-02',
  titel: 'Fragestunde vom 2. März 2026',
  frist: '2026-02-26',
  geschaeft: {
    id: 4711,
    nummer: '2026.8',
    titel: 'Fragestunde vom 2. März 2026 (Beginn 20.00 Uhr)',
    url: 'https://parlament.winterthur.ch/_rte/information/2744876',
  },
  mehrfachZugeteilt: [],
  fragen: [ZUGETEILTE_FRAGE],
}

const STAND = { fragestunden: [FRAGESTUNDE], fragen: [ZUGETEILTE_FRAGE, FREIE_FRAGE] }

describe('Fragestunde — die Fraktion sammelt ihre Fragen', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: STAND })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: {} })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  const mountFn = () => shallowMount(Fragestunde, { props: { mitglieder: MITGLIEDER } })

  it('zeigt alle Fragen, die zugeteilten wie die freien', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    const text = wrapper.text()
    expect(text).toContain('Warum wurde das wohnungsnahe Parkieren')
    expect(text, 'auch die Frage ohne Fragestunde').toContain('Wie viele Bäume pflanzt die Stadt')
    expect(text, 'eine Frage ohne Fragestunde sagt es').toContain('noch keiner zugeteilt')
    expect(text, 'die zugeteilte nennt ihre Fragestunde').toContain('2. März 2026')
  })

  it('zeigt die geplanten Fragestunden mit Frist und Zahl der zugeteilten Fragen', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    const text = wrapper.text()
    expect(text).toContain('Fragestunde vom 2. März 2026')
    expect(text, 'die Frist steht auf der Seite').toContain('26. Februar 2026')
    expect(text).toContain('1 zugeteilt')
  })

  it('ohne jede Frage steht der Leer-Hinweis, auch ohne Fragestunde', async () => {
    axios.get.mockResolvedValue({ data: { fragestunden: [], fragen: [] } })
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.html()).toContain('Noch keine Frage eingetragen')
  })

  it('eine neue Frage entsteht ohne Fragestunde, Urheber ist das angemeldete Mitglied', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    wrapper.vm.neueFrage()
    expect(wrapper.vm.bearbeitung.fragestundeId, 'keine Fragestunde nötig').toBe(0)
    expect(wrapper.vm.bearbeitung.urheber).toEqual({ key: 'mitglied:e1', name: 'Anna Müller' })
    expect(wrapper.vm.bearbeitung.einreicher, 'der Einreicher wird erst zugeteilt').toEqual({ key: '', name: '' })
  })

  it('eine Frage über 1000 Zeichen lässt sich nicht speichern', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    wrapper.vm.neueFrage()
    wrapper.vm.bearbeitung.frage = 'a'.repeat(1001)
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.zuLang, 'über der Grenze der Organisationsverordnung').toBe(true)

    wrapper.vm.bearbeitung.frage = 'a'.repeat(1000)
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.zuLang, 'genau 1000 Zeichen sind zulässig').toBe(false)
  })

  it('speichert eine neue Frage ohne Fragestunde', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    wrapper.vm.neueFrage()
    wrapper.vm.bearbeitung.frage = 'Wann kommt der Velostreifen?'
    await wrapper.vm.anlegen()
    expect(axios.post.mock.calls[0][0]).toContain('/fragen')
    expect(axios.post.mock.calls[0][1].fragestundeId).toBe(0)
    expect(axios.post.mock.calls[0][1].frage).toBe('Wann kommt der Velostreifen?')
    expect(axios.post.mock.calls[0][1].urheber).toEqual({ key: 'mitglied:e1', name: 'Anna Müller' })
    expect(wrapper.vm.bearbeitung, 'die Maske schliesst nach dem Anlegen').toBe(null)
  })

  it('teilt eine gesammelte Frage einer Fragestunde zu und speichert sofort', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    wrapper.vm.bearbeiten(FREIE_FRAGE)
    wrapper.vm.fragestundeGewaehlt(7)
    await wrapper.vm.$nextTick()
    expect(axios.put.mock.calls[0][0]).toContain('/fragen/12')
    expect(axios.put.mock.calls[0][1].fragestundeId).toBe(7)
  })

  it('die Fragestunden stehen an der Frage zur Auswahl', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.fragestundenOptionen).toEqual([{ label: 'Fragestunde vom 2. März 2026', value: 7 }])
  })

  it('das Zuteilen des Einreichers speichert sofort', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    wrapper.vm.bearbeiten(ZUGETEILTE_FRAGE)
    wrapper.vm.einreicherGewaehlt('mitglied:e2')
    await wrapper.vm.$nextTick()
    expect(axios.put.mock.calls[0][0]).toContain('/fragen/11')
    expect(axios.put.mock.calls[0][1].einreicher).toEqual({ key: 'mitglied:e2', name: 'Bob Meier' })
  })

  it('meldet, wenn einem Mitglied mehr als eine Frage zugeteilt ist', async () => {
    const doppelt = { ...ZUGETEILTE_FRAGE, einreicher: { key: 'mitglied:e2', name: 'Bob Meier' } }
    axios.get.mockResolvedValue({
      data: {
        fragestunden: [{
          ...FRAGESTUNDE,
          mehrfachZugeteilt: ['mitglied:e2'],
          fragen: [doppelt, { ...doppelt, id: 13 }],
        }],
        fragen: [doppelt, { ...doppelt, id: 13 }],
      },
    })
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    const meldung = wrapper.find('[data-mehrfach]')
    expect(meldung.exists(), 'Hinweis auf die doppelte Zuteilung fehlt').toBe(true)
    expect(meldung.text()).toContain('Bob Meier')
    expect(meldung.text()).toContain('jedes Mitglied reicht nur eine Frage ein')
  })

  it('verlinkt das Geschäft des Parlaments, sobald es abgerufen ist', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    const link = wrapper.find('a.pw-extern-link')
    expect(link.exists(), 'Der Verweis auf das Geschäft fehlt').toBe(true)
    expect(link.text()).toContain('2026.8')
    expect(link.attributes('href')).toBe('https://parlament.winterthur.ch/_rte/information/2744876')
  })

  it('wählbar sind nur aktive Mitglieder mit Nextcloud-Benutzer', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.mitgliederOptionen.map(o => o.label)).toEqual(['Anna Müller', 'Bob Meier'])
  })

  it('löscht eine Frage über ihren eigenen Endpunkt', async () => {
    const wrapper = mountFn()
    await wrapper.vm.$nextTick()
    await wrapper.vm.loeschen(ZUGETEILTE_FRAGE)
    expect(axios.delete.mock.calls[0][0]).toContain('/fragen/11')
  })
})
