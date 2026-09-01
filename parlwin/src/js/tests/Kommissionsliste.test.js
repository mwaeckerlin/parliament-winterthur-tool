import { describe, it, expect, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Kommissionsliste from '../components/Kommissionsliste.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

function mountComponent(extraData = {}) {
  return shallowMount(Kommissionsliste, {
    data() {
      return {
        geschaefte: [],
        kommissionen: [],
        mitglieder: [],
        ausgewaehlteGeschaeftId: 99,
        laden: false,
        ...extraData,
      }
    },
    global: {
      stubs: {
        GeschaeftDetail: true,
        NcTextField: true,
        NcCheckboxRadioSwitch: true,
        NcLoadingIcon: true,
        NcEmptyContent: true,
        NcActions: true,
        NcActionButton: true,
        NcActionCaption: true,
        NcButton: true,
      },
    },
  })
}

describe('geschaefteFuer', () => {
  const ak = { id: 1, name: 'Aufsichtskommission', aktiv: true, mitglieder: '[]' }

  it('zeigt ein eigenes Geschäft, das der Kommission zugewiesen wurde', () => {
    // Der Weg aus der Fraktion: ein eigenes Geschäft anlegen und es im Feld
    // «Kommission» der AK zuweisen. Sein Status ist «Pendent» und nennt keine
    // Kommission — gesucht wurde bisher aber nur im Status, und deshalb erschien
    // das Geschäft in der Lasche «Kommissionen» nie bei seiner Kommission.
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 7, titel: 'Antrag der Fraktion', status: 'Pendent', kommission: 'Aufsichtskommission' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([7])
  })

  it('zeigt weiterhin die Geschäfte, deren Status die Kommission nennt', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 8, titel: 'Weisung', status: 'Bei Aufsichtskommission pendent' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([8])
  })

  it('zeigt ein Geschäft, das beiden Regeln entspricht, genau einmal', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{
        id: 9,
        titel: 'Beides',
        status: 'Bei Aufsichtskommission pendent',
        kommission: 'Aufsichtskommission',
      }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([9])
  })

  it('zeigt kein Geschäft, das einer anderen Kommission zugewiesen ist', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 10, titel: 'Fremd', status: 'Pendent', kommission: 'Sachkommission Bau und Betriebe' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak)).toEqual([])
  })
})

describe('nachSpeichern', () => {
  it('schliesst das Popup NICHT nach dem Speichern', async () => {
    const wrapper = mountComponent()
    wrapper.vm.ladeGeschaefte = vi.fn(() => Promise.resolve())

    await wrapper.vm.nachSpeichern()

    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBe(99)
  })

  it('ruft ladeGeschaefte auf', async () => {
    const wrapper = mountComponent()
    const ladeGeschaefte = vi.fn(() => Promise.resolve())
    wrapper.vm.ladeGeschaefte = ladeGeschaefte

    await wrapper.vm.nachSpeichern()

    expect(ladeGeschaefte).toHaveBeenCalledOnce()
  })

  it('emittiert aktualisiert', async () => {
    const wrapper = mountComponent()
    wrapper.vm.ladeGeschaefte = vi.fn(() => Promise.resolve())

    await wrapper.vm.nachSpeichern()

    expect(wrapper.emitted('aktualisiert')).toBeTruthy()
  })
})

describe('schliesseDetail', () => {
  it('setzt ausgewaehlteGeschaeftId auf null', () => {
    const wrapper = mountComponent()
    wrapper.vm.schliesseDetail()
    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBeNull()
  })
})
