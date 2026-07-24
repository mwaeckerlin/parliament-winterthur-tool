import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

// axios mock
vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { aktionen: [], zustaendigkeiten: [] } })),
    post: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '' } })),
    put: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '' } })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'

const mockGeschaeft = {
  id: 1,
  nummer: '2024/001',
  titel: 'Testgeschäft',
  typ: 'Motion',
  status: 'pendent',
  datum: '2024-01-01',
  aktionen: [],
  fraktionsstatus: null,
  fraktionssitzung: null,
  zustaendig: [],
}

function mountComponent(extraData = {}) {
  return shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
    data() {
      return { geschaeft: mockGeschaeft, laden: false, ...extraData }
    },
    global: {
      stubs: {
        NcSelect: true,
        PwMultiSelect: true,
        GeschaeftDokumente: true,
        NotizenListe: true,
      },
    },
  })
}

describe('beschlussNachWahl', () => {
  it('speichert sofort bei normaler Auswahl', async () => {
    const wrapper = mountComponent({ beschlussWert: null })
    const spy = vi.spyOn(wrapper.vm, 'beschlussSpeichern').mockResolvedValue()
    await wrapper.vm.beschlussNachWahl({ label: 'Ablehnen', value: 'ablehnen' })
    expect(spy).toHaveBeenCalledOnce()
  })

  it('nimmt Beschluss zurück bei null (wenn vorher ein Wert da war)', async () => {
    const wrapper = mountComponent({ beschlussWert: { label: 'Ablehnen', value: 'ablehnen' } })
    const spy = vi.spyOn(wrapper.vm, 'beschlussZuruecknehmen').mockResolvedValue()
    await wrapper.vm.beschlussNachWahl(null)
    expect(spy).toHaveBeenCalledOnce()
  })

  it('ruft beschlussZuruecknehmen NICHT auf wenn vorher kein Wert', async () => {
    const wrapper = mountComponent({ beschlussWert: null })
    const spy = vi.spyOn(wrapper.vm, 'beschlussZuruecknehmen').mockResolvedValue()
    await wrapper.vm.beschlussNachWahl(null)
    expect(spy).not.toHaveBeenCalled()
  })

  it('speichert sofort bei freitext (BeschlussWidget managed blur/debounce intern)', async () => {
    const wrapper = mountComponent({ beschlussWert: null })
    const spy = vi.spyOn(wrapper.vm, 'beschlussSpeichern').mockResolvedValue()
    await wrapper.vm.beschlussNachWahl({ label: 'Freitext', value: '', freitext: true })
    expect(spy).toHaveBeenCalledOnce()
  })
})

describe('beschlussSpeichern – Eingabe-Session merged in EINE Aktion', () => {
  beforeEach(() => { vi.clearAllMocks() })

  const beschlussAntwort = { id: 77, aktionTyp: 'beschluss', aktionCode: '', titel: 'Abschr', text: 'Abschr', entscheidGueltig: true }

  it('legt beim ersten Speichern eine Aktion an (POST) und merkt sich deren Id', async () => {
    axios.post.mockResolvedValueOnce({ data: beschlussAntwort })
    const wrapper = mountComponent()
    await wrapper.vm.beschlussNachWahl({ label: 'Abschr', value: '', freitext: true })
    expect(axios.post).toHaveBeenCalledOnce()
    expect(wrapper.vm.beschlussAktionId).toBe(77)
  })

  it('aktualisiert bei weiterem Tippen dieselbe Aktion (PUT), kein zweiter POST', async () => {
    axios.post.mockResolvedValueOnce({ data: beschlussAntwort })
    axios.put.mockResolvedValue({ data: { ...beschlussAntwort, text: 'Abschreiben' } })
    const wrapper = mountComponent()
    await wrapper.vm.beschlussNachWahl({ label: 'Abschr', value: '', freitext: true })
    await wrapper.vm.beschlussNachWahl({ label: 'Abschreiben', value: '', freitext: true })
    await wrapper.vm.beschlussNachWahl({ label: 'Abschreiben', value: 'abschreiben' })
    expect(axios.post).toHaveBeenCalledOnce()
    const puts = axios.put.mock.calls.filter(c => String(c[0]).includes('/beschluesse/77'))
    expect(puts).toHaveLength(2)
  })

  it('speichert unveränderten Wert nicht erneut (blur nach Debounce-Save)', async () => {
    axios.post.mockResolvedValueOnce({ data: beschlussAntwort })
    const wrapper = mountComponent()
    await wrapper.vm.beschlussNachWahl({ label: 'Abschreiben', value: 'abschreiben' })
    await wrapper.vm.beschlussNachWahl({ label: 'Abschreiben', value: 'abschreiben' })
    expect(axios.post).toHaveBeenCalledOnce()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('nach Fokus-Verlust (Session-Ende) erzeugt eine erneute Änderung eine neue Aktion', async () => {
    axios.post.mockResolvedValue({ data: beschlussAntwort })
    const wrapper = mountComponent()
    await wrapper.vm.beschlussNachWahl({ label: 'Ablehnen', value: 'ablehnen' })
    await wrapper.vm.beschlussSessionBeenden()
    expect(wrapper.vm.beschlussAktionId).toBeNull()
    await wrapper.vm.beschlussNachWahl({ label: 'Zustimmen', value: 'zustimmen' })
    expect(axios.post).toHaveBeenCalledTimes(2)
  })

  it('parallel angestossene Speicherungen erzeugen keine zweite Aktion (Blur während laufendem Debounce-POST)', async () => {
    let ersteAntwortAusloesen
    axios.post.mockImplementationOnce(() => new Promise(resolve => {
      ersteAntwortAusloesen = () => resolve({ data: beschlussAntwort })
    }))
    const wrapper = mountComponent()
    const erste = wrapper.vm.beschlussNachWahl({ label: 'Abschr', value: '', freitext: true })
    const zweite = wrapper.vm.beschlussNachWahl({ label: 'Abschreiben', value: '', freitext: true })
    await vi.waitFor(() => { if (typeof ersteAntwortAusloesen !== 'function') throw new Error('POST noch nicht gestartet') })
    ersteAntwortAusloesen()
    await erste
    await zweite
    expect(axios.post).toHaveBeenCalledOnce()
  })
})

describe('_aktionHinzufuegen / _aktionAktualisieren / _aktionEntfernen', () => {
  it('fügt Aktion zur lokalen Liste hinzu', () => {
    const wrapper = mountComponent()
    wrapper.vm.geschaeft.aktionen = []
    wrapper.vm._aktionHinzufuegen({ id: 1, aktionTyp: 'notiz', text: 'X' })
    expect(wrapper.vm.geschaeft.aktionen).toHaveLength(1)
    expect(wrapper.vm.geschaeft.aktionen[0].id).toBe(1)
  })

  it('aktualisiert bestehende Aktion in-place', () => {
    const wrapper = mountComponent()
    wrapper.vm.geschaeft.aktionen = [{ id: 1, text: 'alt' }]
    wrapper.vm._aktionAktualisieren({ id: 1, text: 'neu' })
    expect(wrapper.vm.geschaeft.aktionen[0].text).toBe('neu')
  })

  it('entfernt Aktion aus der Liste', () => {
    const wrapper = mountComponent()
    wrapper.vm.geschaeft.aktionen = [{ id: 1, text: 'X' }, { id: 2, text: 'Y' }]
    wrapper.vm._aktionEntfernen(1)
    expect(wrapper.vm.geschaeft.aktionen).toHaveLength(1)
    expect(wrapper.vm.geschaeft.aktionen[0].id).toBe(2)
  })
})
