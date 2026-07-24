import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

// Bei mehreren Sitzungstypen bietet das Neu-Menü jeden Typ als eigenen Eintrag
// an (Nextcloud-Standard, wie «+ Neu» in der Dateien-App). Ein Klick auf einen
// Eintrag startet unmittelbar das vollständige Formular für genau diesen Typ —
// ohne Zwischenschritt.
describe('Neue Sitzung: ein Menüeintrag je Sitzungstyp', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset()
  })

  it('jeder Sitzungstyp erscheint als eigener Menüeintrag', async () => {
    const wrapper = shallowMount(Sitzungsliste, {
      props: { mitglieder: [], fraktionen: [], kommissionen: [] },
      // Der Menüinhalt liegt im Standard-Slot von NcActions; ein durchreichender
      // Stub macht die Einträge im gerenderten Markup sichtbar.
      global: { stubs: { NcActions: { template: '<div class="pw-neu-menue"><slot /></div>' } } },
    })
    await flushPromises()
    wrapper.vm.sitzungstypen = [{ id: 1, name: 'Fraktion' }, { id: 2, name: 'Kommission' }]
    await wrapper.vm.$nextTick()

    const menue = wrapper.find('.pw-neu-menue')
    expect(menue.exists(), 'Neu-Menü nicht gefunden').toBe(true)
    expect(menue.element.children.length, 'Nicht jeder Sitzungstyp hat einen Menüeintrag').toBe(2)
  })

  it('ein Menüeintrag startet direkt das Formular für seinen Typ', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    await flushPromises()
    const typB = { id: 2, name: 'Kommission' }
    wrapper.vm.sitzungstypen = [{ id: 1, name: 'Fraktion' }, typB]

    wrapper.vm.waehleTypFuerNeueSitzung(typB)
    expect(wrapper.vm.gewaehlterTyp, 'Formular öffnet nicht für den gewählten Typ').toStrictEqual(typB)
  })
})
