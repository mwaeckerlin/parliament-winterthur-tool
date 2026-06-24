import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

function mount() {
  return shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
}

describe('Sitzungsliste — Geschäfte mit Sitzung verknüpfen', () => {
  beforeEach(() => {
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  it('lädt die verknüpften Geschäft-IDs', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: { geschaeftIds: [5, 9] } })
    await wrapper.vm.ladeVerknuepfteGeschaefte(1)
    expect(wrapper.vm.verknuepfteGeschaeftIds[1]).toEqual([5, 9])
  })

  it('verlinkt ein Geschäft und übernimmt die neue Liste', async () => {
    const wrapper = mount()
    axios.post.mockResolvedValue({ data: { geschaeftIds: [7] } })
    await wrapper.vm.verlinkeGeschaeftMitSitzung(1, 7)
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/sitzungen/1/geschaefte'))).toBe(true)
    expect(wrapper.vm.verknuepfteGeschaeftIds[1]).toEqual([7])
  })

  it('blendet bereits verknüpfte Geschäfte aus den Auswahl-Optionen aus', () => {
    const wrapper = mount()
    wrapper.vm.geschaefteAlle = [
      { id: 1, nummer: '2026.1', titel: 'A' },
      { id: 2, nummer: '2026.2', titel: 'B' },
    ]
    wrapper.vm.verknuepfteGeschaeftIds = { 5: [1] }
    expect(wrapper.vm.geschaefteOptionenFuer(5).map(o => o.id)).toEqual([2])
  })
})
