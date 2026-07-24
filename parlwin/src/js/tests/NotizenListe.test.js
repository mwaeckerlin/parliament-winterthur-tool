import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import NotizenListe from '../components/NotizenListe.vue'

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '', autorUid: 'testuser', geloescht: false } })),
    put: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '', autorUid: 'testuser', geloescht: false } })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

vi.mock('@nextcloud/dialogs', () => ({
  showSuccess: vi.fn(),
  showError: vi.fn(),
  showWarning: vi.fn(),
}))

import axios from '@nextcloud/axios'

// Die geteilte Notizen-Liste kapselt den kompletten Notiz-Editor-Zustand. Die
// Persistenz läuft über `basisUrl`; hier `geschaefte/1` — die Tests gelten
// identisch für Vorstösse (nur die basisUrl ist eine andere).
function mountComponent(extraData = {}) {
  return shallowMount(NotizenListe, {
    props: { basisUrl: 'geschaefte/1', notizen: [], aktuelleUid: 'testuser' },
    data() {
      return { ...extraData }
    },
  })
}

describe('notizBearbeitenStarten – Datenzustand', () => {
  it('lädt die Notiz in den gemeinsamen Editor-Zustand', async () => {
    const wrapper = mountComponent()
    await wrapper.vm.notizBearbeitenStarten({ id: 42, text: 'Testnotiz' })
    expect(wrapper.vm.aktiveNotizId).toBe(42)
    expect(wrapper.vm.aktiveNotizText).toBe('Testnotiz')
    expect(wrapper.vm.editorModus).toBe('edit')
    expect(wrapper.vm.editorOffen).toBe(true)
  })

  it('setzt aktiveNotizText auf leer wenn aktion.text fehlt', async () => {
    const wrapper = mountComponent()
    await wrapper.vm.notizBearbeitenStarten({ id: 5, text: '' })
    expect(wrapper.vm.aktiveNotizText).toBe('')
  })
})

describe('notizVerwerfen', () => {
  it('räumt den Editor weg, ohne zu speichern', () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'X' })
    wrapper.vm.notizVerwerfen()
    expect(wrapper.vm.editorOffen).toBe(false)
    expect(wrapper.vm.aktiveNotizId).toBeNull()
    expect(wrapper.vm.aktiveNotizText).toBe('')
  })
})

describe('notizAutosave', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('persistiert als Zwischenspeicher (final=false) nach 5 Sekunden', () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizText: 'Test' })
    const spy = vi.spyOn(wrapper.vm, 'notizPersistieren').mockResolvedValue()
    wrapper.vm.notizAutosave()
    expect(spy).not.toHaveBeenCalled()
    vi.advanceTimersByTime(5000)
    expect(spy).toHaveBeenCalledOnce()
    expect(spy).toHaveBeenCalledWith(false)
  })

  it('setzt vorherigen Timer zurück', () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizText: 'A' })
    const spy = vi.spyOn(wrapper.vm, 'notizPersistieren').mockResolvedValue()
    wrapper.vm.notizAutosave()
    wrapper.vm.notizAutosave()
    vi.advanceTimersByTime(5000)
    expect(spy).toHaveBeenCalledOnce()
  })
})

describe('notizAbschliessen – Timer abbrechen und final speichern', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('bricht den Autosave-Timer ab und speichert final (final=true)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizText: 'Sofort' })
    const spy = vi.spyOn(wrapper.vm, 'notizPersistieren').mockResolvedValue()
    wrapper.vm.notizAutosave()
    await wrapper.vm.notizAbschliessen()
    expect(spy).toHaveBeenCalledWith(true)
    vi.advanceTimersByTime(5000)
    // Der abgebrochene Autosave-Timer feuert nicht mehr.
    expect(spy).toHaveBeenCalledOnce()
  })
})

describe('notizPersistieren – POST/PUT je nach Zustand', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('legt für eine neue Notiz (aktiveNotizId=null) eine Aktion an (POST)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'Hallo' })
    await wrapper.vm.notizPersistieren(false)
    expect(axios.post).toHaveBeenCalledOnce()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('aktualisiert eine bestehende Notiz (PUT), kein POST', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'Hallo' })
    await wrapper.vm.notizPersistieren(false)
    expect(axios.put).toHaveBeenCalledOnce()
    expect(axios.post).not.toHaveBeenCalled()
  })

  it('speichert keine leere Notiz', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: '' })
    await wrapper.vm.notizPersistieren(false)
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('legt beim Zwischenspeichern KEINE Revision an (zwischenspeichern=true)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'Zwischenstand' })
    await wrapper.vm.notizPersistieren(false)
    const [, body] = axios.put.mock.calls[0]
    expect(body.zwischenspeichern).toBe(true)
  })

  it('archiviert beim finalen Abschluss eine Revision (zwischenspeichern=false)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'Endstand' })
    await wrapper.vm.notizPersistieren(true)
    const [, body] = axios.put.mock.calls[0]
    expect(body.zwischenspeichern).toBe(false)
  })

  it('richtet die Requests an die basisUrl (Geschäft oder Vorstoss)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'Hallo' })
    await wrapper.vm.notizPersistieren(false)
    expect(axios.post.mock.calls[0][0]).toContain('/apps/parlwin/geschaefte/1/notizen')
  })
})

describe('notizAbschliessen – räumt den Editor weg', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('schliesst den Editor nach dem Speichern', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'Wird gespeichert' })
    await wrapper.vm.notizAbschliessen()
    expect(wrapper.vm.editorOffen).toBe(false)
    expect(wrapper.vm.aktiveNotizText).toBe('')
    expect(wrapper.vm.aktiveNotizId).toBeNull()
  })

  it('räumt einen leeren neuen Editor weg, ohne etwas anzulegen', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: '' })
    await wrapper.vm.notizAbschliessen()
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.vm.editorOffen).toBe(false)
  })
})

describe('notizLoeschen – Soft-Delete meldet die aktualisierte Liste', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('markiert die Notiz als gelöscht und meldet die neue Liste über geaendert', async () => {
    const notiz = { id: 7, aktionTyp: 'notiz', text: 'Weg', autorUid: 'testuser', geloescht: false }
    const wrapper = shallowMount(NotizenListe, {
      props: { basisUrl: 'vorstoesse/3', notizen: [notiz], aktuelleUid: 'testuser' },
    })
    await wrapper.vm.notizLoeschen(notiz)
    expect(axios.delete).toHaveBeenCalledWith(expect.stringContaining('/apps/parlwin/vorstoesse/3/notizen/7'))
    const emit = wrapper.emitted('geaendert')
    expect(emit).toBeTruthy()
    expect(emit.at(-1)[0][0].geloescht).toBe(true)
  })
})

describe('notizPersistieren – keine Duplikate bei parallelen Speicherungen', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('Blur während laufendem Debounce-POST erzeugt keine zweite Notiz', async () => {
    let ersteAntwortAusloesen
    axios.post.mockImplementationOnce(() => new Promise(resolve => {
      ersteAntwortAusloesen = () => resolve({ data: { id: 50, aktionTyp: 'notiz', text: 'X', titel: '', autorUid: 'testuser', geloescht: false } })
    }))
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'X' })
    const erste = wrapper.vm.notizPersistieren(false)
    const zweite = wrapper.vm.notizPersistieren(true)
    await vi.waitFor(() => { if (typeof ersteAntwortAusloesen !== 'function') throw new Error('POST noch nicht gestartet') })
    ersteAntwortAusloesen()
    await erste
    await zweite
    expect(axios.post).toHaveBeenCalledOnce()
  })
})
