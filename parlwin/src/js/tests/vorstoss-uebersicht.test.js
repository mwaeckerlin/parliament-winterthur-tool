import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import axios from '@nextcloud/axios'

// Die Vorstoss-Übersicht folgt dem Muster der Geschäfte: Klick auf die Karte
// öffnet die Bearbeitung. Das Löschen bleibt als eigener Knopf auf der Karte
// erreichbar — wie bei den Sitzungstypen.
describe('Vorstoesseliste — Übersicht wie die Geschäfte', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const mount = () => shallowMount(Vorstoesseliste, { props: { mitglieder: [], fraktionen: [] } })

  it('rendert Vorstösse als klickbare Datenkarten (pw-data-card)', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu', art: 'Motion' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    const karte = wrapper.find('.pw-data-card')
    expect(karte.exists()).toBe(true)
    expect(karte.attributes('role')).toBe('button')
    expect(karte.text()).toContain('Mehr Velowege')
  })

  it('Klick auf die Karte öffnet die Bearbeitung', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    await wrapper.find('.pw-data-card').trigger('click')
    expect(wrapper.vm.bearbeitung).not.toBeNull()
    expect(wrapper.vm.bearbeitung.id).toBe(5)
  })

  it('bietet auf der Karte einen Löschen-Weg an', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    expect(typeof wrapper.vm.loeschen, 'Der Löschen-Weg fehlt').toBe('function')
    expect(
      wrapper.find('.pw-data-card .pw-data-card-aktionen').exists(),
      'Kein Löschen-Knopf auf der Vorstoss-Karte',
    ).toBe(true)
  })

  it('hebt hohe Priorität in der Karte hervor, tiefe schwächt sie ab', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [
      { id: 1, titel: 'Hoch', herkunft: 'eigene', status: 'neu', prioritaet: 'hoch' },
      { id: 2, titel: 'Tief', herkunft: 'eigene', status: 'neu', prioritaet: 'tief' },
      { id: 3, titel: 'Ohne', herkunft: 'eigene', status: 'neu', prioritaet: '' },
    ] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()
    const karten = wrapper.findAll('.pw-data-card')
    expect(karten[0].classes()).toContain('pw-prio-hoch')
    expect(karten[1].classes()).toContain('pw-prio-tief')
    expect(karten[2].classes()).not.toContain('pw-prio-hoch')
    expect(karten[2].classes()).not.toContain('pw-prio-tief')
  })

  it('lädt die Priorität beim Bearbeiten (Default undefiniert)', async () => {
    const wrapper = mount()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', prioritaet: 'tief' })
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.prioritaet).toBe('tief')
    wrapper.vm.bearbeiten({ id: 6, titel: 'Y' })
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.prioritaet).toBe('')
  })
})
