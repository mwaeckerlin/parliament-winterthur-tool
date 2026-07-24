import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import BeschlussWidget from '../components/BeschlussWidget.vue'
import PwMultiSelect from '../components/PwMultiSelect.vue'
import PwWysiwyg from '../components/PwWysiwyg.vue'
import axios from '@nextcloud/axios'

// Der «Neuer Vorstoss»-Dialog muss dieselben wiederverwendbaren Elemente nutzen
// wie die anderen Geschäftstypen: Art + Fremd-Beschluss über BeschlussWidget,
// Zuständigkeit + Ansprechpartner über PwMultiSelect (Liste), Inhalt über PwWysiwyg.
describe('Vorstoesseliste — Dialog nutzt die gemeinsamen Widgets', () => {
  beforeEach(() => {
    // Ziel-Container für die Filter-Teleports bereitstellen.
    document.body.innerHTML = '<div id="pw-search-slot"></div><div id="pw-filter-slot"></div>'
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset()
  })

  // mount (nicht shallowMount) rendert den Dialog-Teleport echt; die schweren
  // Fremd-Komponenten werden gestubbt, BeschlussWidget/PwMultiSelect echt gerendert.
  const mountFn = () => mount(Vorstoesseliste, {
    props: {
      mitglieder: [
        { id: 1, vorname: 'Anna', name: 'Müller', fraktion: 'Grüne', nextcloudUid: 'amueller', aktiv: true, externId: 'e1' },
        { id: 2, vorname: 'Bob', name: 'Meier', fraktion: 'SP', nextcloudUid: 'bmeier', aktiv: true, externId: 'e2' },
      ],
      fraktionen: [
        { id: 1, name: 'Grüne', aktiv: true },
        { id: 2, name: 'SP', aktiv: true },
      ],
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

  it('eigener Vorstoss: Art über BeschlussWidget, Zuständigkeit über PwMultiSelect, Inhalt über PwWysiwyg', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 5, titel: 'T' })
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(PwWysiwyg).exists()).toBe(true)
    expect(wrapper.findAllComponents(BeschlussWidget).length).toBe(1) // nur Art
    expect(wrapper.findAllComponents(PwMultiSelect).length).toBe(1) // nur Zuständigkeit
  })

  it('fremder Vorstoss: zusätzlich Fremd-Beschluss (BeschlussWidget) und Ansprechpartner (PwMultiSelect)', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 5, titel: 'T' })
    wrapper.vm.bearbeitung.herkunft = 'fremde'
    await wrapper.vm.$nextTick()
    expect(wrapper.findAllComponents(BeschlussWidget).length).toBe(2) // Art + Fremd-Beschluss
    expect(wrapper.findAllComponents(PwMultiSelect).length).toBe(2) // Zuständigkeit + Ansprechpartner
  })

  it('Herkunftsfraktion schliesst die eigene Fraktion aus', async () => {
    const wrapper = mountFn()
    wrapper.vm.eigeneFraktion = 'Grüne'
    await wrapper.vm.$nextTick()
    const namen = wrapper.vm.fremdeFraktionsOptionen.map(o => o.label || o)
    expect(namen).toContain('SP')
    expect(namen).not.toContain('Grüne')
  })

  it('Ansprechpartner-Optionen sind die Mitglieder der gewählten fremden Fraktion', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 5, titel: 'T' })
    wrapper.vm.bearbeitung.herkunft = 'fremde'
    wrapper.vm.bearbeitung.herkunftFraktion = 'SP'
    await wrapper.vm.$nextTick()
    const namen = wrapper.vm.ansprechpartnerOptionen.map(o => o.label)
    expect(namen).toEqual(['Bob Meier'])
  })
})
