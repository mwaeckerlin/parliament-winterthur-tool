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
