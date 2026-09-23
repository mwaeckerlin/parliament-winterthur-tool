import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import axios from '@nextcloud/axios'

// Konsistenz (erste Designregel): Wie überall in der App speichert JEDE Eingabe
// sofort — es gibt KEINE Abbrechen/Speichern-Buttons. Das Neu-Anlegen öffnet
// dieselbe vollständige Maske wie das Bearbeiten; ein Entwurf ohne Titel wird
// beim Schliessen wieder verworfen.
describe('Vorstoesseliste — jede Eingabe speichert sofort', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div id="pw-search-slot"></div><div id="pw-filter-slot"></div>'
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset().mockResolvedValue({ data: { id: 42, titel: 'Neu', herkunft: 'eigene', status: 'neu' } })
    axios.put = vi.fn().mockResolvedValue({ data: {} })
  })

  const mountFn = () => mount(Vorstoesseliste, {
    props: {
      mitglieder: [
        { id: 1, vorname: 'Anna', name: 'Müller', fraktion: 'Grüne', nextcloudUid: 'amueller', aktiv: true, externId: 'e1' },
      ],
      fraktionen: [{ id: 1, name: 'Grüne', aktiv: true }],
    },
    global: {
      stubs: {
        PwWysiwyg: true,
        GeschaeftDokumente: true,
        NcSelect: true,
        NcTextField: true,
        NcButton: true,
        NcLoadingIcon: true,
      },
    },
  })

  it('Bearbeitungs-Dialog hat KEINE Abbrechen/Speichern-Buttons (kein Modal-Footer)', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 7, titel: 'Test' })
    await wrapper.vm.$nextTick()
    expect(document.body.querySelector('.pw-modal')).not.toBeNull()
    expect(document.body.querySelector('.pw-modal-footer')).toBeNull()
    expect(document.body.textContent).not.toContain('Abbrechen')
  })

  it('eine Feldänderung (Art, Priorität, Zuständigkeit) speichert sofort per PUT', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 7, titel: 'Test' })
    await wrapper.vm.$nextTick()

    wrapper.vm.artGewaehlt({ label: 'Motion', value: 'Motion' })
    await Promise.resolve()
    expect(axios.put).toHaveBeenCalledTimes(1)
    expect(axios.put.mock.calls[0][0]).toContain('/apps/parlwin/vorstoesse/7')
    expect(axios.put.mock.calls[0][1].art).toBe('Motion')

    // PwPrioritaetSelect emittiert den Rohwert (String).
    wrapper.vm.prioritaetGewaehlt('hoch')
    await Promise.resolve()
    expect(axios.put).toHaveBeenCalledTimes(2)
    expect(axios.put.mock.calls[1][1].prioritaet).toBe('hoch')

    wrapper.vm.zustaendigkeitGewaehlt([{ label: 'Anna Müller', value: 'mitglied:e1' }])
    await Promise.resolve()
    expect(axios.put).toHaveBeenCalledTimes(3)
    expect(axios.put.mock.calls[2][1].zustaendigkeit).toEqual([{ key: 'mitglied:e1', name: 'Anna Müller' }])
  })

  it('der Inhalt speichert beim Verlassen des Editors (Blur), nicht erst über einen Knopf', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 7, titel: 'Test' })
    await wrapper.vm.$nextTick()
    wrapper.vm.bearbeitung.inhalt = 'Neuer Inhalt'
    await wrapper.vm.inhaltAbschliessen()
    expect(axios.put).toHaveBeenCalledTimes(1)
    expect(axios.put.mock.calls[0][1].inhalt).toBe('Neuer Inhalt')
  })

  // Bug 2026-08-31: Wer den Inhalt tippt und gleich schliesst, verlor ihn. Der
  // Editor speichert beim Verlassen; die Maske schloss über die laufende Anfrage
  // hinweg, und beim nächsten Öffnen stand der Stand von davor da.
  it('das Schliessen wartet auf das laufende Speichern', async () => {
    const wrapper = mountFn()
    let fertig
    axios.put = vi.fn(() => new Promise(aufloesen => { fertig = () => aufloesen({ data: { id: 7, titel: 'Test', inhalt: 'Neuer Inhalt' } }) }))
    wrapper.vm.bearbeiten({ id: 7, titel: 'Test' })
    await wrapper.vm.$nextTick()

    wrapper.vm.bearbeitung.inhalt = 'Neuer Inhalt'
    wrapper.vm.inhaltAbschliessen()
    const geschlossen = wrapper.vm.schliessen()
    // Solange die Anfrage läuft, bleibt die Maske offen.
    await Promise.resolve()
    expect(wrapper.vm.bearbeitung, 'Die Maske schliesst über das Speichern hinweg').not.toBeNull()

    fertig()
    await geschlossen
    expect(wrapper.vm.bearbeitung).toBeNull()
    expect(axios.put.mock.calls[0][1].inhalt).toBe('Neuer Inhalt')
  })

  it('ein leerer Titel wird nie weggespeichert', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 7, titel: '' })
    await wrapper.vm.$nextTick()
    wrapper.vm.artGewaehlt({ label: 'Motion', value: 'Motion' })
    await Promise.resolve()
    expect(axios.put).not.toHaveBeenCalled()
  })

  it('Neu-Anlegen öffnet dieselbe Maske und legt noch nichts an', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerVorstoss()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.istEntwurf).toBe(true)
    expect(axios.post, 'Es wurde angelegt, bevor gespeichert wurde').not.toHaveBeenCalled()

    // Dieselben Eingabefelder wie beim Bearbeiten. Notizen, Dokumente und
    // Zeitleiste brauchen eine bestehende ID und fehlen deshalb noch.
    const labels = [...document.body.querySelectorAll('.pw-field-label')].map(e => e.textContent.trim())
    for (const f of ['Titel *', 'Art', 'Herkunft', 'Status', 'Priorität', 'Zuständigkeit', 'Inhalt']) {
      expect(labels, `Feld «${f}» fehlt in der Neu-Maske`).toContain(f)
    }
    for (const f of ['Dokument', 'Notizen', 'Aktionszeitleiste', 'Geschäft']) {
      expect(labels, `«${f}» kann es ohne gespeicherten Vorstoss noch nicht geben`).not.toContain(f)
    }
    // Speichern und Abbrechen stehen zur Verfügung.
    expect(document.body.querySelector('.pw-modal-footer'), 'Speichern/Abbrechen fehlen').not.toBeNull()
  })

  it('erst «Speichern» legt den Vorstoss an und öffnet die Bearbeitung', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerVorstoss()
    wrapper.vm.bearbeitung.titel = 'Mein Vorstoss'
    await wrapper.vm.speichern()

    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(axios.post.mock.calls[0][1].titel).toBe('Mein Vorstoss')
    expect(wrapper.vm.bearbeitung?.id).toBe(42)
    expect(wrapper.vm.istEntwurf, 'Nach dem Speichern ist es kein Entwurf mehr').toBe(false)
  })

  it('«Abbrechen» verwirft, ein Klick daneben nicht', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerVorstoss()
    wrapper.vm.bearbeitung.titel = 'Angefangen'

    // Klick neben die Maske: die Eingaben bleiben erhalten.
    wrapper.vm.overlayKlick()
    expect(wrapper.vm.bearbeitung, 'Ein Klick daneben hat die Eingaben verworfen').not.toBeNull()
    expect(wrapper.vm.bearbeitung.titel).toBe('Angefangen')

    // Nur der ausdrückliche Abbruch verwirft.
    wrapper.vm.abbrechen()
    expect(wrapper.vm.bearbeitung).toBeNull()
    expect(axios.post).not.toHaveBeenCalled()
  })
})
