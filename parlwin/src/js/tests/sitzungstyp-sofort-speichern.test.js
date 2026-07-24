import { describe, it, expect, vi, beforeEach } from 'vitest'
import { readFileSync } from 'fs'
import { mount } from '@vue/test-utils'
import Sitzungstypenliste from '../components/Sitzungstypenliste.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import axios from '@nextcloud/axios'

// Konsistenz: Auch Sitzungstypen speichern jede Eingabe sofort — keine
// Abbrechen/Speichern-Buttons; Neu-Anlegen über einen minimalen Namens-Dialog
// («Erstellen»), danach öffnet die Bearbeitung; Bearbeiten per Klick auf die Karte.
describe('Sitzungstypenliste — jede Eingabe speichert sofort', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div id="pw-search-slot"></div><div id="pw-filter-slot"></div>'
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset().mockResolvedValue({ data: { id: 11, name: 'Fraktionssitzung', traktanden: [], teilnehmer: [] } })
    axios.put = vi.fn().mockResolvedValue({ data: {} })
  })

  const mountFn = () => mount(Sitzungstypenliste, {
    props: { mitglieder: [], fraktionen: [], kommissionen: [] },
    global: {
      stubs: {
        NcTextField: true,
        NcButton: true,
        NcLoadingIcon: true,
        NcCheckboxRadioSwitch: true,
        PwMultiSelect: true,
      },
    },
  })

  it('Bearbeitungs-Dialog hat KEINE Abbrechen/Speichern-Buttons (kein Modal-Footer)', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 3, name: 'Fraktionssitzung' })
    await wrapper.vm.$nextTick()
    expect(document.body.querySelector('.pw-modal')).not.toBeNull()
    expect(document.body.querySelector('.pw-modal-footer')).toBeNull()
  })

  it('eine Feldänderung speichert sofort per PUT', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 3, name: 'Fraktionssitzung' })
    await wrapper.vm.$nextTick()
    wrapper.vm.bearbeitung.zweck = 'Vorbereitung'
    await wrapper.vm.feldSpeichern()
    expect(axios.put).toHaveBeenCalledTimes(1)
    expect(axios.put.mock.calls[0][0]).toContain('/apps/parlwin/sitzungstypen/3')
    expect(axios.put.mock.calls[0][1].zweck).toBe('Vorbereitung')
  })

  it('ein leerer Name wird nie weggespeichert', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 3, name: '' })
    await wrapper.vm.$nextTick()
    await wrapper.vm.feldSpeichern()
    expect(axios.put).not.toHaveBeenCalled()
  })

  // Regression: Die Checkboxen «Eigene Fraktion» und «Verknüpfung» müssen
  // wählbar sein. Sie brauchen — wie überall sonst im Projekt — die
  // model-value-API von NcCheckboxRadioSwitch; die alte :checked/@update:checked-
  // API wird von der aktuellen @nextcloud/vue-Version ignoriert (nicht wählbar).
  it('die Optionen-Checkbox «Verknüpfung» ist über update:model-value wählbar', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 3, name: 'Fraktionssitzung', verknuepfen: false })
    await wrapper.vm.$nextTick()

    const checkboxen = wrapper.findAllComponents(NcCheckboxRadioSwitch)
    const verknuepfen = checkboxen[checkboxen.length - 1]
    verknuepfen.vm.$emit('update:model-value', true)
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.bearbeitung.verknuepfen).toBe(true)
  })

  it('beide Checkboxen binden über model-value (nicht die tote :checked-API)', () => {
    // Regression-Guard: die alte :checked/@update:checked-API wird von der
    // aktuellen @nextcloud/vue-Version ignoriert (Checkbox nicht wählbar) — wie
    // überall sonst im Projekt muss model-value verwendet werden.
    const src = readFileSync('parlwin/src/js/components/Sitzungstypenliste.vue', 'utf8')
    expect(src).not.toMatch(/@update:checked/)
    expect(src).not.toMatch(/:checked=/)
    expect((src.match(/@update:model-value/g) || []).length).toBeGreaterThanOrEqual(2)
  })

  it('toggleEigeneFraktion ergänzt bzw. entfernt die Eigene-Fraktion-Regel', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 3, name: 'Fraktionssitzung', teilnehmer: [] })
    await wrapper.vm.$nextTick()
    vi.spyOn(wrapper.vm, 'feldSpeichern').mockResolvedValue()

    wrapper.vm.toggleEigeneFraktion(true)
    expect(wrapper.vm.bearbeitung.teilnehmer.some(p => p.art === 'eigeneFraktion')).toBe(true)
    wrapper.vm.toggleEigeneFraktion(false)
    expect(wrapper.vm.bearbeitung.teilnehmer.some(p => p.art === 'eigeneFraktion')).toBe(false)
  })

  it('Neu-Anlegen öffnet dieselbe Maske und legt noch nichts an', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerTyp()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.istEntwurf).toBe(true)
    expect(axios.post, 'Es wurde angelegt, bevor gespeichert wurde').not.toHaveBeenCalled()

    // Dieselben Felder wie beim Bearbeiten.
    const labels = [...document.body.querySelectorAll('.pw-field-label')].map(e => e.textContent.trim())
    for (const f of ['Name *', 'Zweck', 'Standard-Ort', 'Von', 'Bis']) {
      expect(labels, `Feld «${f}» fehlt in der Neu-Maske`).toContain(f)
    }
    expect(document.body.querySelector('.pw-modal-footer'), 'Speichern/Abbrechen fehlen').not.toBeNull()
  })

  it('erst «Speichern» legt den Sitzungstyp an und öffnet die Bearbeitung', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerTyp()
    wrapper.vm.bearbeitung.name = 'Fraktionssitzung'
    await wrapper.vm.speichern()

    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(axios.post.mock.calls[0][1].name).toBe('Fraktionssitzung')
    expect(wrapper.vm.bearbeitung?.id).toBe(11)
    expect(wrapper.vm.istEntwurf, 'Nach dem Speichern ist es kein Entwurf mehr').toBe(false)
  })

  it('«Abbrechen» verwirft, ein Klick daneben nicht', async () => {
    const wrapper = mountFn()
    wrapper.vm.neuerTyp()
    wrapper.vm.bearbeitung.name = 'Angefangen'

    wrapper.vm.overlayKlick()
    expect(wrapper.vm.bearbeitung, 'Ein Klick daneben hat die Eingaben verworfen').not.toBeNull()

    wrapper.vm.abbrechen()
    expect(wrapper.vm.bearbeitung).toBeNull()
    expect(axios.post).not.toHaveBeenCalled()
  })
})
