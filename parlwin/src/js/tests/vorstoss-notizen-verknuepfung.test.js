import { describe, it, expect, beforeEach } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import NotizenListe from '../components/NotizenListe.vue'
import axios from '@nextcloud/axios'

// Notizen bei Vorstössen + Verknüpfung mit einem Geschäft (Vorstufe → Geschäft),
// inkl. Titel-Ähnlichkeitssuche zum Finden des passenden Geschäfts.
describe('Vorstoesseliste — Notizen & Geschäfts-Verknüpfung', () => {
  beforeEach(() => {
    // Ziel-Container für die Filter-Teleports bereitstellen.
    document.body.innerHTML = '<div id="pw-search-slot"></div><div id="pw-filter-slot"></div>'
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const simple = () => shallowMount(Vorstoesseliste, { props: { mitglieder: [], fraktionen: [] } })

  // Voller Mount (rendert den Dialog-Teleport echt), damit die eingebettete
  // NotizenListe im Wrapper auffindbar ist. Schwere Fremd-Komponenten + die
  // NotizenListe selbst werden gestubbt.
  const mountFull = () => mount(Vorstoesseliste, {
    props: { mitglieder: [], fraktionen: [] },
    global: {
      stubs: {
        NotizenListe: true,
        PwWysiwyg: true,
        GeschaeftDokumente: true,
        BeschlussWidget: true,
        PwMultiSelect: true,
        NcSelect: true,
        NcTextField: true,
        NcButton: true,
        NcLoadingIcon: true,
      },
    },
  })

  // Notizen bei Vorstössen nutzen 100 % dieselbe Komponente wie bei Geschäften
  // (NotizenListe) — die Vorstoss-Ansicht verdrahtet nur basisUrl/Aktionen.
  it('bindet die geteilte NotizenListe mit der Vorstoss-basisUrl und den Aktionen ein', async () => {
    const wrapper = mountFull()
    const aktionen = [{ id: 1, aktionTyp: 'notiz', text: 'Alt', autorUid: 'u', geloescht: false }]
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', aktionen })
    await wrapper.vm.$nextTick()
    const liste = wrapper.findComponent(NotizenListe)
    expect(liste.exists()).toBe(true)
    expect(liste.props('basisUrl')).toBe('vorstoesse/5')
    expect(liste.props('notizen')).toEqual(aktionen)
  })

  it('rendert die reale NotizenListe mit «+ Neue Notiz» im geöffneten Vorstoss-Dialog', async () => {
    // Wie e2e: echte NotizenListe (nur der Tiptap-Editor gestubbt), Dialog offen.
    const wrapper = mount(Vorstoesseliste, {
      props: { mitglieder: [], fraktionen: [] },
      global: {
        stubs: {
          PwWysiwyg: true, GeschaeftDokumente: true, BeschlussWidget: true,
          PwMultiSelect: true, NcSelect: true, NcTextField: true, NcButton: true, NcLoadingIcon: true,
        },
      },
    })
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', herkunft: 'eigene', aktionen: [] })
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(NotizenListe).exists()).toBe(true)
    // Der Dialog ist ein Teleport nach body — dort liegt die gerenderte NotizenListe.
    expect(document.querySelector('.pw-notizen-liste')).not.toBeNull()
    expect(document.body.innerHTML).toContain('+ Neue Notiz')
  })

  it('übernimmt die von NotizenListe gemeldete Liste in bearbeitung.aktionen', async () => {
    const wrapper = mountFull()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', aktionen: [] })
    await wrapper.vm.$nextTick()
    const neu = [{ id: 2, aktionTyp: 'notiz', text: 'Neu', autorUid: 'u', geloescht: false }]
    wrapper.findComponent(NotizenListe).vm.$emit('geaendert', neu)
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.aktionen).toEqual(neu)
  })

  it('verknüpft den Vorstoss mit einem gewählten Geschäft', async () => {
    const wrapper = simple()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X' })
    let gerufen = null
    axios.post = (url, body) => { gerufen = { url, body }; return Promise.resolve({ data: { geschaeftId: 42, status: 'erledigt' } }) }

    await wrapper.vm.verknuepfen({ id: 42, titel: 'Geschäft' })
    expect(gerufen.url).toContain('/apps/parlwin/vorstoesse/5/verknuepfen')
    expect(gerufen.body).toEqual({ geschaeftId: 42 })
    expect(wrapper.vm.bearbeitung.geschaeftId).toBe(42)
    expect(wrapper.vm.bearbeitung.status).toBe('erledigt')
  })

  // Die Ähnlichkeitssuche steckt jetzt im GETEILTEN Verknüpfen-Dialog (kein
  // Copy-Paste; genutzt von Vorstoss→Geschäft UND eigenes→offizielles Geschäft).
  it('der geteilte Verknüpfen-Dialog sortiert Geschäfte nach Titel-Ähnlichkeit, bei Gleichstand neueste zuerst', async () => {
    axios.get.mockResolvedValue({ data: [
      { id: 1, titel: 'Ganz anderes Thema', datum: '2026-05-01' },
      { id: 2, titel: 'Mehr Velowege bauen', datum: '2026-01-02' },
      { id: 3, titel: 'Noch etwas Anderes', datum: '2026-06-01' },
    ] })
    const { default: GeschaeftVerknuepfenDialog } = await import('../components/GeschaeftVerknuepfenDialog.vue')
    const dlg = shallowMount(GeschaeftVerknuepfenDialog, { props: { titel: 'Mehr Velowege in der Stadt' } })
    await Promise.resolve()
    await Promise.resolve()
    await dlg.vm.$nextTick()
    const ids = dlg.vm.aehnliche.map(g => g.id)
    expect(ids[0]).toBe(2) // höchste Titel-Ähnlichkeit («Velowege»/«Mehr»/«Stadt»)
  })

  it('der geteilte Dialog bietet mit nurOffizielle keine eigenen Geschäfte an und schliesst sich selbst nicht ein', async () => {
    axios.get.mockResolvedValue({ data: [
      { id: 10, titel: 'Offizielles A', externId: '2026.1', datum: '2026-05-01' },
      { id: 11, titel: 'Eigenes B', externId: 'eigen:xy', datum: '2026-05-02' },
      { id: 12, titel: 'Offizielles C (selbst)', externId: '2026.2', datum: '2026-05-03' },
    ] })
    const { default: GeschaeftVerknuepfenDialog } = await import('../components/GeschaeftVerknuepfenDialog.vue')
    const dlg = shallowMount(GeschaeftVerknuepfenDialog, { props: { titel: 'x', nurOffizielle: true, ausschlussId: 12 } })
    await Promise.resolve()
    await Promise.resolve()
    await dlg.vm.$nextTick()
    const ids = dlg.vm.aehnliche.map(g => g.id)
    expect(ids).toContain(10)
    expect(ids).not.toContain(11) // eigenes ausgeblendet
    expect(ids).not.toContain(12) // Selbst-Verknüpfung ausgeschlossen
  })
})
