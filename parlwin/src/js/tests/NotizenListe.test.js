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

// Die geteilte Notizen-Liste kapselt den kompletten Notiz-Editor-Zustand. Seit
// dem Konzeptwechsel gilt für Notizen NUR Häkchen/X: KEIN Autosave (5 s), KEIN
// Speichern bei Fokus-Verlust. Diese Tests nageln genau dieses Konzept fest —
// sie sind die Absicherung gegen die zuvor untauglichen Autosave-Tests.
function mountComponent(extraData = {}) {
  return shallowMount(NotizenListe, {
    props: { basisUrl: 'geschaefte/1', notizen: [], aktuelleUid: 'testuser' },
    data() {
      return { ...extraData }
    },
  })
}

describe('Kein Autosave, kein Speichern bei Fokus-Verlust', () => {
  beforeEach(() => { vi.clearAllMocks(); vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('eine Eingabe löst KEIN Speichern aus — auch nach 5 Sekunden nicht', () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: '' })
    wrapper.vm.notizEingabe('Guten Tag')
    vi.advanceTimersByTime(5000)
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('die Komponente hat keinen Autosave-Timer und keine notizAbschliessen-Methode', () => {
    const wrapper = mountComponent()
    expect(wrapper.vm.notizAutosave).toBeUndefined()
    expect(wrapper.vm.notizAbschliessen).toBeUndefined()
  })

  it('der Editor bietet keinen @blur-Handler, der speichert (nur Häkchen/X)', () => {
    // Der Editor im Template bindet kein @blur mehr — Fokus-Verlust lässt den
    // Editor offen. Belegt über die Abwesenheit von notizAbschliessen (der
    // frühere Blur-Handler) und dass eine Eingabe nichts speichert.
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 5, aktiveNotizText: 'x' })
    wrapper.vm.notizEingabe('geändert')
    vi.advanceTimersByTime(10000)
    expect(axios.put).not.toHaveBeenCalled()
  })
})

describe('Häkchen speichert, X verwirft', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('Häkchen legt eine neue Notiz an (POST) und schliesst den Editor', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'Neue Notiz' })
    await wrapper.vm.notizBestaetigen()
    expect(axios.post).toHaveBeenCalledOnce()
    expect(wrapper.vm.editorOffen).toBe(false)
  })

  it('Häkchen an einer bestehenden Notiz aktualisiert sie (PUT) OHNE das Kennzeichen zwischenspeichern', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'Endstand' })
    await wrapper.vm.notizBestaetigen()
    expect(axios.put).toHaveBeenCalledOnce()
    const [, body] = axios.put.mock.calls[0]
    // Kein Zwischenspeichern mehr: der Abschluss archiviert immer eine Revision.
    expect(body).not.toHaveProperty('zwischenspeichern')
  })

  it('X verwirft ohne zu speichern und schliesst den Editor', () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'edit', aktiveNotizId: 42, aktiveNotizText: 'X' })
    wrapper.vm.notizVerwerfen()
    expect(axios.post).not.toHaveBeenCalled()
    expect(axios.put).not.toHaveBeenCalled()
    expect(wrapper.vm.editorOffen).toBe(false)
    expect(wrapper.vm.aktiveNotizText).toBe('')
  })

  it('ein leerer neuer Editor legt beim Häkchen nichts an', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: '' })
    await wrapper.vm.notizBestaetigen()
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.vm.editorOffen).toBe(false)
  })
})

describe('History: der mehrzeilige Text bleibt vollständig erhalten', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('speichert den gesamten mehrzeiligen Text (kein Verlust der Folgezeilen)', async () => {
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null })
    // «Guten Tag» + Zeilenumbruch + «Hallo Welt» — alles muss ankommen.
    wrapper.vm.notizEingabe('Guten Tag\n\nHallo Welt')
    await wrapper.vm.notizBestaetigen()
    expect(axios.post).toHaveBeenCalledOnce()
    expect(axios.post.mock.calls[0][1].text).toBe('Guten Tag\n\nHallo Welt')
  })
})

describe('hatUngespeicherteAenderungen', () => {
  it('ist false bei geschlossenem Editor', () => {
    const wrapper = mountComponent()
    expect(wrapper.vm.hatUngespeicherteAenderungen()).toBe(false)
  })

  it('ist false, wenn der Text dem Öffnungsstand entspricht', () => {
    const wrapper = mountComponent({ editorOffen: true, aktiveNotizText: 'A', aktiveNotizOriginal: 'A' })
    expect(wrapper.vm.hatUngespeicherteAenderungen()).toBe(false)
  })

  it('ist true, wenn der Text vom Öffnungsstand abweicht', () => {
    const wrapper = mountComponent({ editorOffen: true, aktiveNotizText: 'A geändert', aktiveNotizOriginal: 'A' })
    expect(wrapper.vm.hatUngespeicherteAenderungen()).toBe(true)
  })
})

describe('notizBearbeitenStarten – Datenzustand', () => {
  it('lädt die Notiz in den Editor und merkt sich den Original-Text', async () => {
    const wrapper = mountComponent()
    await wrapper.vm.notizBearbeitenStarten({ id: 42, text: 'Testnotiz' })
    expect(wrapper.vm.aktiveNotizId).toBe(42)
    expect(wrapper.vm.aktiveNotizText).toBe('Testnotiz')
    expect(wrapper.vm.aktiveNotizOriginal).toBe('Testnotiz')
    expect(wrapper.vm.editorModus).toBe('edit')
    expect(wrapper.vm.editorOffen).toBe(true)
  })
})

describe('Gelöschte Notizen erscheinen NICHT in der Notizen-Liste', () => {
  it('zeigt nur aktive Notizen; gelöschte bleiben der Aktionszeitleiste vorbehalten', () => {
    const notizen = [
      { id: 1, aktionTyp: 'notiz', text: 'aktiv', autorUid: 'testuser', geloescht: false },
      { id: 2, aktionTyp: 'notiz', text: 'weg', autorUid: 'testuser', geloescht: true },
    ]
    const wrapper = shallowMount(NotizenListe, {
      props: { basisUrl: 'geschaefte/1', notizen, aktuelleUid: 'testuser' },
    })
    expect(wrapper.vm.aktiveNotizen).toHaveLength(1)
    expect(wrapper.vm.aktiveNotizen[0].id).toBe(1)
    // Kein Lösch-Vermerk mehr in dieser Komponente.
    expect(wrapper.text()).not.toContain('hat seine Notiz gelöscht')
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

describe('notizSpeichern – keine Duplikate bei schnellen Aufrufen', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('zwei schnelle Speicherungen erzeugen keine zweite Notiz', async () => {
    let ersteAntwortAusloesen
    axios.post.mockImplementationOnce(() => new Promise(resolve => {
      ersteAntwortAusloesen = () => resolve({ data: { id: 50, aktionTyp: 'notiz', text: 'X', titel: '', autorUid: 'testuser', geloescht: false } })
    }))
    const wrapper = mountComponent({ editorOffen: true, editorModus: 'neu', aktiveNotizId: null, aktiveNotizText: 'X' })
    const erste = wrapper.vm.notizSpeichern()
    const zweite = wrapper.vm.notizSpeichern()
    await vi.waitFor(() => { if (typeof ersteAntwortAusloesen !== 'function') throw new Error('POST noch nicht gestartet') })
    ersteAntwortAusloesen()
    await erste
    await zweite
    expect(axios.post).toHaveBeenCalledOnce()
  })
})
