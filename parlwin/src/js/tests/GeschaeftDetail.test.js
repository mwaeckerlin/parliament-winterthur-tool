import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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
        PwWysiwyg: true,
        GeschaeftDokumente: true,
      },
    },
  })
}


describe('notizBearbeitenStarten – Datenzustand', () => {
  it('setzt bearbeitenNotizId und bearbeitenNotizText', () => {
    const wrapper = mountComponent()
    wrapper.vm.notizBearbeitenStarten({ id: 42, text: 'Testnotiz' })
    expect(wrapper.vm.bearbeitenNotizId).toBe(42)
    expect(wrapper.vm.bearbeitenNotizText).toBe('Testnotiz')
  })

  it('setzt bearbeitenNotizText auf leer wenn aktion.text fehlt', () => {
    const wrapper = mountComponent()
    wrapper.vm.notizBearbeitenStarten({ id: 5, text: '' })
    expect(wrapper.vm.bearbeitenNotizText).toBe('')
  })
})

describe('notizBearbeitenAbbrechen', () => {
  it('setzt bearbeitenNotizId und Text zurück', () => {
    const wrapper = mountComponent({ bearbeitenNotizId: 42, bearbeitenNotizText: 'X' })
    wrapper.vm.notizBearbeitenAbbrechen()
    expect(wrapper.vm.bearbeitenNotizId).toBeNull()
    expect(wrapper.vm.bearbeitenNotizText).toBe('')
  })
})

describe('notizDebounce', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('speichert Notiz nach 5 Sekunden', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Test' })
    const spy = vi.spyOn(wrapper.vm, 'notizSpeichern').mockResolvedValue()
    wrapper.vm.notizDebounce()
    expect(spy).not.toHaveBeenCalled()
    vi.advanceTimersByTime(5000)
    expect(spy).toHaveBeenCalledOnce()
  })

  it('setzt vorherigen Timer zurück', () => {
    const wrapper = mountComponent({ neueNotiz: 'A' })
    const spy = vi.spyOn(wrapper.vm, 'notizSpeichern').mockResolvedValue()
    wrapper.vm.notizDebounce()
    wrapper.vm.notizDebounce()
    vi.advanceTimersByTime(5000)
    expect(spy).toHaveBeenCalledOnce()
  })
})

describe('notizSpeichernBeiBlur', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('bricht Timer ab und speichert sofort', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Sofort' })
    const spy = vi.spyOn(wrapper.vm, 'notizSpeichern').mockResolvedValue()
    wrapper.vm.notizDebounce()
    await wrapper.vm.notizSpeichernBeiBlur()
    expect(spy).toHaveBeenCalledOnce()
    vi.advanceTimersByTime(5000)
    expect(spy).toHaveBeenCalledOnce()
  })
})

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


describe('notizSpeichern – chirurgisches Update', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('legt beim ersten Aufruf eine neue Aktion an (POST)', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Hallo' })
    await wrapper.vm.notizSpeichern()
    expect(axios.post).toHaveBeenCalledOnce()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('aktualisiert beim zweiten Aufruf dieselbe Aktion (PUT)', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Hallo', notizAktionId: 42 })
    await wrapper.vm.notizSpeichern()
    expect(axios.put).toHaveBeenCalledOnce()
    expect(axios.post).not.toHaveBeenCalled()
  })

  it('speichert keine leere Notiz', async () => {
    const wrapper = mountComponent({ neueNotiz: '' })
    await wrapper.vm.notizSpeichern()
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('löscht das Feld NICHT (nur blur darf löschen)', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Inhalt bleibt' })
    await wrapper.vm.notizSpeichern()
    expect(wrapper.vm.neueNotiz).toBe('Inhalt bleibt')
  })
})

describe('notizSpeichernBeiBlur – Feld löschen nach blur', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('löscht das Feld nach blur', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Wird gelöscht' })
    await wrapper.vm.notizSpeichernBeiBlur()
    expect(wrapper.vm.neueNotiz).toBe('')
  })

  it('setzt notizAktionId zurück nach blur', async () => {
    const wrapper = mountComponent({ neueNotiz: 'Test', notizAktionId: 42 })
    await wrapper.vm.notizSpeichernBeiBlur()
    expect(wrapper.vm.notizAktionId).toBeNull()
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
