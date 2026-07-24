import { describe, it, expect, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import axios from '@nextcloud/axios'

// Der angemeldete Benutzer ist «amueller» (= Anna Müller, aktiv, Fraktion Grüne).
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'amueller', displayName: 'Anna Müller' }) }))

describe('Vorstoesseliste — Vorbelegung & aktive-Einträge', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset()
  })

  const mountFn = (props = {}) => shallowMount(Vorstoesseliste, {
    props: {
      mitglieder: [
        { id: 1, vorname: 'Anna', name: 'Müller', fraktion: 'Grüne', nextcloudUid: 'amueller', aktiv: true, externId: 'e1' },
        { id: 2, vorname: 'Bob', name: 'Meier', fraktion: 'SP', nextcloudUid: 'bmeier', aktiv: true, externId: 'e2' },
        { id: 3, vorname: 'Carla', name: 'Alt', fraktion: 'SP', nextcloudUid: 'calt', aktiv: false, externId: 'e3' },
      ],
      fraktionen: [
        { id: 1, name: 'Grüne', aktiv: true },
        { id: 2, name: 'SP', aktiv: true },
        { id: 3, name: 'EVP', aktiv: false },
      ],
      ...props,
    },
  })

  it('neuer Vorstoss: Zuständigkeit ist standardmässig der angemeldete Benutzer', async () => {
    const wrapper = mountFn()
    axios.post.mockResolvedValue({ data: { id: 9, titel: 'X' } })
    wrapper.vm.neuerVorstoss()
    // Schon in der Maske vorbelegt — und beim Speichern mitgesendet.
    expect(wrapper.vm.bearbeitung.zustaendigkeit).toEqual([{ key: 'mitglied:e1', name: 'Anna Müller' }])
    wrapper.vm.bearbeitung.titel = 'X'
    await wrapper.vm.speichern()
    expect(axios.post.mock.calls[0][1].zustaendigkeit).toEqual([{ key: 'mitglied:e1', name: 'Anna Müller' }])
  })

  it('Bearbeiten eines bestehenden Vorstosses überschreibt die Zuständigkeit nicht mit dem User', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', zustaendigkeit: [{ key: 'mitglied:e2', name: 'Bob Meier' }] })
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.zustaendigkeit).toEqual([{ key: 'mitglied:e2', name: 'Bob Meier' }])
  })

  it('Herkunft listet nur aktive Fraktionen (ohne die eigene)', () => {
    const wrapper = mountFn()
    wrapper.vm.eigeneFraktion = 'Grüne'
    const werte = wrapper.vm.fremdeFraktionsOptionen.map(o => o.value)
    expect(werte).toEqual(['SP']) // EVP inaktiv raus, Grüne = eigene raus
  })

  it('Ansprechpartner listet nur aktive Mitglieder der gewählten Fraktion', async () => {
    const wrapper = mountFn()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X' })
    wrapper.vm.bearbeitung.herkunft = 'fremde'
    wrapper.vm.bearbeitung.herkunftFraktion = 'SP'
    await wrapper.vm.$nextTick()
    const namen = wrapper.vm.ansprechpartnerOptionen.map(o => o.label)
    expect(namen).toEqual(['Bob Meier']) // Carla Alt (inaktiv) fehlt
  })
})
