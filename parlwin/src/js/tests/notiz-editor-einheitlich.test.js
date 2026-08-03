import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, mount } from '@vue/test-utils'
import NotizenListe from '../components/NotizenListe.vue'

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))
vi.mock('@nextcloud/dialogs', () => ({
  showSuccess: vi.fn(), showError: vi.fn(), showWarning: vi.fn(),
}))

function notizAntwort(id, text) {
  return {
    id, aktionTyp: 'notiz', titel: '', text, aktionCode: '',
    autorUid: 'testuser', autorName: 'Test User',
    erstelltAm: '2026-07-15T14:59:00+00:00', entscheidGueltig: false, geloescht: false,
  }
}

function mountListe(notizen = [], options = {}) {
  return shallowMount(NotizenListe, {
    props: { basisUrl: 'geschaefte/1', notizen, aktuelleUid: 'testuser' },
    ...options,
  })
}

/** Emittiert eine Texteingabe im (gestubbten) Editor. */
function tippe(wrapper, text) {
  const editor = wrapper.findComponent({ name: 'PwWysiwyg' })
  editor.vm.$emit('update:model-value', text)
  return editor
}

describe('Notiz-Editor — neue Notiz und Bearbeiten teilen einen Ablauf', () => {
  beforeEach(async () => {
    const axios = (await import('@nextcloud/axios')).default
    axios.post.mockReset()
    axios.put.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockResolvedValue({ data: notizAntwort(99, 'Test, Hallo Welt') })
    axios.put.mockImplementation((url, body) => Promise.resolve({ data: notizAntwort(99, body?.text || '') }))
  })

  it('speichert eine neue Notiz erst beim Häkchen (genau EINE, kein Doppel)', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    tippe(wrapper, 'Test, Hallo Welt')
    await wrapper.vm.notizBestaetigen()

    const axios = (await import('@nextcloud/axios')).default
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.aktiveNotizen.filter(n => n.id === 99)).toHaveLength(1)
  })

  it('zeigt eine gespeicherte Notiz nach dem Häkchen genau einmal (keine Doppelung)', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    tippe(wrapper, 'Test, Hallo Welt')
    await wrapper.vm.notizBestaetigen()
    await wrapper.vm.$nextTick()

    // Nach dem Häkchen ist der Editor zu und die Notiz erscheint einmal.
    expect(wrapper.findAll('.pw-notiz-inhalt')).toHaveLength(1)
  })

  it('lässt den «Neue Notiz»-Knopf sichtbar, während der Editor offen ist', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    expect(wrapper.find('button[title="Neue Notiz"]').exists()).toBe(true)
  })

  it('Fokus-Verlust speichert NICHT und lässt den Editor offen', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    tippe(wrapper, 'Angefangen')
    // Ein Blur des Editors darf weder speichern noch den Editor schliessen.
    const editor = wrapper.findComponent({ name: 'PwWysiwyg' })
    editor.vm.$emit('blur')
    await wrapper.vm.$nextTick()

    const axios = (await import('@nextcloud/axios')).default
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.vm.editorOffen).toBe(true)
  })

  it('X räumt einen leeren neuen Editor weg, ohne etwas zu erzeugen', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    wrapper.vm.notizVerwerfen()
    await wrapper.vm.$nextTick()

    const axios = (await import('@nextcloud/axios')).default
    expect(axios.post).not.toHaveBeenCalled()
    expect(wrapper.findComponent({ name: 'PwWysiwyg' }).exists()).toBe(false)
    expect(wrapper.vm.aktiveNotizen).toHaveLength(0)
  })

  it('nutzt für neue Notiz und Bearbeiten denselben Editor-Zustand', async () => {
    const bestehende = notizAntwort(7, 'Bestehende Notiz')
    const wrapper = mountListe([bestehende])
    // Bearbeiten öffnet den Editor mit dem Text der bestehenden Notiz.
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.aktiveNotizId).toBe(7)
    expect(wrapper.vm.aktiveNotizText).toBe('Bestehende Notiz')
    // Der Editor wird über genau eine Instanz dargestellt.
    expect(wrapper.findAllComponents({ name: 'PwWysiwyg' })).toHaveLength(1)
  })

  it('reicht die geladenen Revisionen an den Editor durch (Versions-Pfeile)', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'Aktuell')
    const wrapper = mountListe([bestehende])
    // Der nächste GET (im notizBearbeitenStarten) liefert die Versions-History.
    axios.get.mockResolvedValueOnce({ data: [
      { id: 1, aktionId: 7, text: 'Version 1', autorName: 'T', erstelltAm: '2026-07-16T09:00:00+00:00' },
      { id: 2, aktionId: 7, text: 'Version 2', autorName: 'T', erstelltAm: '2026-07-16T10:00:00+00:00' },
    ] })
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await wrapper.vm.$nextTick()
    await Promise.resolve()
    await wrapper.vm.$nextTick()

    const editor = wrapper.findComponent({ name: 'PwWysiwyg' })
    expect(editor.props('revisionen')).toHaveLength(2)
  })

  it('Ok auf einer alten Version stellt sie wieder her: Arbeitstext final, dann alte Version als Kopie', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'D')
    const wrapper = mountListe([bestehende])
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await wrapper.vm.$nextTick()
    // Der Nutzer hat den Arbeitsstand auf «F» gebracht und blättert auf die alte Fassung «B».
    wrapper.vm.aktiveNotizText = 'F'
    wrapper.vm.angezeigteVersion = 'B'
    axios.put.mockClear()

    await wrapper.vm.notizBestaetigen()

    const puts = axios.put.mock.calls
    expect(puts).toHaveLength(2)
    // Erst der Arbeitsstand F (wird zur Revision), dann die alte Fassung B als Kopie.
    expect(puts[0][1]).toMatchObject({ text: 'F' })
    expect(puts[1][1]).toMatchObject({ text: 'B' })
    expect(wrapper.vm.editorOffen).toBe(false)
  })

  it('Ok ausserhalb des Blätterns schliesst normal ab (kein Restore)', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'D')
    const wrapper = mountListe([bestehende])
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await wrapper.vm.$nextTick()
    wrapper.vm.aktiveNotizText = 'F'
    wrapper.vm.angezeigteVersion = null
    axios.put.mockClear()

    await wrapper.vm.notizBestaetigen()

    expect(axios.put.mock.calls).toHaveLength(1)
    expect(axios.put.mock.calls[0][1]).toMatchObject({ text: 'F' })
    expect(wrapper.vm.editorOffen).toBe(false)
  })

  it('Abbrechen im Blättern verwirft alles – kein Restore, kein Speichern', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'D')
    const wrapper = mountListe([bestehende])
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await wrapper.vm.$nextTick()
    wrapper.vm.aktiveNotizText = 'F'
    wrapper.vm.angezeigteVersion = 'B'
    axios.put.mockClear()

    wrapper.vm.notizVerwerfen()

    expect(axios.put).not.toHaveBeenCalled()
    expect(wrapper.vm.editorOffen).toBe(false)
  })

  // Ende-zu-Ende mit echtem Editor (kein Stub): beim Bearbeiten einer Notiz mit
  // Versions-History müssen die Pfeile in der Formatierungsleiste erscheinen.
  it('rendert die Versions-Pfeile im echten Editor beim Bearbeiten', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'Aktuell')
    const wrapper = mount(NotizenListe, {
      props: { basisUrl: 'geschaefte/1', notizen: [bestehende], aktuelleUid: 'testuser' },
    })
    await wrapper.vm.$nextTick()

    axios.get.mockResolvedValueOnce({ data: [
      { id: 1, aktionId: 7, text: 'Version 1', autorName: 'T', erstelltAm: '2026-07-16T09:00:00+00:00' },
      { id: 2, aktionId: 7, text: 'Version 2', autorName: 'T', erstelltAm: '2026-07-16T10:00:00+00:00' },
    ] })
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await new Promise((r) => setTimeout(r, 0))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('button[title="Eine Version zurück"]').exists()).toBe(true)
    wrapper.unmount()
  })

  // Ende-zu-Ende mit echtem Editor: bearbeiten (D→F), zurückblättern auf eine ältere
  // Fassung, dann Ok. Der Arbeitsstand F MUSS als Zwischenversion gespeichert werden,
  // erst danach die zurückgeholte alte Fassung. (Bug: F ging verloren.)
  it('Restore speichert den bearbeiteten Arbeitsstand als Zwischenversion (echtes Blättern)', async () => {
    const axios = (await import('@nextcloud/axios')).default
    const bestehende = notizAntwort(7, 'D')
    const wrapper = mount(NotizenListe, {
      props: { basisUrl: 'geschaefte/1', notizen: [bestehende], aktuelleUid: 'testuser' },
    })
    await wrapper.vm.$nextTick()

    // Versions-History: C (jünger) und B (älter); B ist das Ziel des Restores.
    axios.get.mockResolvedValueOnce({ data: [
      { id: 1, aktionId: 7, text: 'B', autorName: 'T', erstelltAm: '2026-07-16T09:00:00+00:00' },
      { id: 2, aktionId: 7, text: 'C', autorName: 'T', erstelltAm: '2026-07-16T10:00:00+00:00' },
    ] })
    await wrapper.vm.notizBearbeitenStarten(bestehende)
    await new Promise((r) => setTimeout(r, 0))
    await wrapper.vm.$nextTick()

    // Der Nutzer bearbeitet die aktuelle Fassung D → F (echter Editor; wie Tippen
    // emittiert das ein update:model-value → aktiveNotizText wird zum Arbeitsstand).
    const editor = wrapper.findComponent({ name: 'PwWysiwyg' })
    editor.vm.editor.commands.setContent('F', { emitUpdate: true })
    await wrapper.vm.$nextTick()

    // Zurückblättern: C, dann B.
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    await wrapper.vm.$nextTick()

    axios.put.mockClear()
    axios.put.mockResolvedValue({ data: notizAntwort(7, 'gespeichert') })

    // Explizites Ok = Restore.
    await wrapper.find('button[title="Speichern"]').trigger('click')
    await wrapper.vm.$nextTick()
    await Promise.resolve()

    // Zwei finale Speicherungen: erst der Arbeitsstand F, dann die alte Fassung B.
    const texte = axios.put.mock.calls.map(c => c[1].text)
    expect(texte).toEqual(['F', 'B'])
    wrapper.unmount()
  })
})
