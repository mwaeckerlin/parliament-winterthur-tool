import { describe, it, expect, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

function mount() {
  return shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
}

describe('Sitzungsliste — Default-Datum neue Sitzung', () => {
  it('setzt beim Öffnen das Datum auf heute + 1 Woche', async () => {
    const wrapper = mount()
    wrapper.vm.waehleTypFuerNeueSitzung({ id: 1, name: 'Test' })
    await wrapper.vm.$nextTick()
    const inEinerWoche = new Date()
    inEinerWoche.setDate(inEinerWoche.getDate() + 7)
    expect(wrapper.vm.neueSitzungDatum).toBe(inEinerWoche.toISOString().slice(0, 10))
  })
})
