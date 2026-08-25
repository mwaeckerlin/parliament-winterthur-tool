import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import App from '../App.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

describe('Vorstoesseliste', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: {} })
  })

  it('nutzt dieselbe View-Struktur wie alle anderen Seiten', () => {
    const wrapper = shallowMount(Vorstoesseliste)
    expect(wrapper.find('.pw-view-content').exists()).toBe(true)
    expect(wrapper.find('.pw-view-header').exists()).toBe(true)
    expect(wrapper.find('.pw-view-title').text()).toBe('Vorstösse')
  })

  it('lädt die Vorstösse beim Mount', () => {
    shallowMount(Vorstoesseliste)
    expect(axios.get.mock.calls.some(c => String(c[0]).includes('/vorstoesse'))).toBe(true)
  })

  it('filtert nach Herkunft und Status', () => {
    const wrapper = shallowMount(Vorstoesseliste)
    wrapper.vm.vorstoesse = [
      { id: 1, titel: 'A', herkunft: 'eigene', status: 'neu' },
      { id: 2, titel: 'B', herkunft: 'fremde', status: 'erledigt' },
    ]
    wrapper.vm.herkunftOption = { label: 'Fremde', value: 'fremde' }
    expect(wrapper.vm.gefiltert.map(v => v.id)).toEqual([2])
    wrapper.vm.herkunftOption = { label: 'Alle', value: '' }
    wrapper.vm.statusOption = { label: 'Neu', value: 'neu' }
    expect(wrapper.vm.gefiltert.map(v => v.id)).toEqual([1])
  })

  // Grundprinzip: ein Filter bietet nur die Werte an, die in den Daten wirklich
  // vorkommen — keine leeren Kategorien als Ballast.
  it('Herkunft- und Status-Filter bieten nur tatsächlich vorkommende Werte', () => {
    const wrapper = shallowMount(Vorstoesseliste)
    wrapper.vm.vorstoesse = [
      { id: 1, titel: 'A', herkunft: 'eigene', status: 'neu' },
      { id: 2, titel: 'B', herkunft: 'eigene', status: 'eingereicht' },
    ]
    // Nur «eigene» kommt vor → «fremde» ist Ballast (nur «Alle» + «Eigene»).
    expect(wrapper.vm.herkunftOptionen.map(o => o.value)).toEqual(['', 'eigene'])
    // Nur «neu» und «eingereicht» kommen vor (feste Reihenfolge bleibt erhalten).
    expect(wrapper.vm.statusOptionen.map(o => o.value)).toEqual(['', 'neu', 'eingereicht'])
  })

  it('das Neu-Anlegen öffnet die Maske, legt aber noch nichts an', async () => {
    const wrapper = shallowMount(Vorstoesseliste)
    wrapper.vm.neuerVorstoss()
    expect(wrapper.vm.bearbeitung, 'Die vollständige Maske ist nicht offen').not.toBeNull()
    expect(wrapper.vm.istEntwurf).toBe(true)
    expect(
      axios.post.mock.calls.filter(c => String(c[0]).includes('/vorstoesse')),
      'Es wurde etwas angelegt, obwohl noch nicht gespeichert wurde',
    ).toHaveLength(0)

    // Erst «Speichern» legt an.
    wrapper.vm.bearbeitung.titel = 'Mehr Velowege'
    await wrapper.vm.speichern()
    const calls = axios.post.mock.calls.filter(c => String(c[0]).includes('/vorstoesse'))
    expect(calls).toHaveLength(1)
    expect(calls[0][1].titel).toBe('Mehr Velowege')
  })

  it('App bietet ein Vorstösse-Tab zwischen Kommissionen und Budget', () => {
    const keys = App.data().ansichten.map(a => a.key)
    expect(keys.indexOf('vorstoesse')).toBe(keys.indexOf('kommissionen') + 1)
    expect(keys.indexOf('budget')).toBe(keys.indexOf('vorstoesse') + 1)
  })
})
