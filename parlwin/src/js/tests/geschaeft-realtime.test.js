import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

describe('Geschaeftsliste — In-place-Update beim Sync (kein DOM-Rebuild)', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('behält die Objekt-Referenz bestehender Geschäfte beim Neuladen', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [{ id: 1, titel: 'A' }] })
    await wrapper.vm.ladeGeschaefte()
    const ref1 = wrapper.vm.geschaefte.find(g => g.id === 1)

    axios.get.mockResolvedValue({ data: [{ id: 1, titel: 'A geändert' }] })
    await wrapper.vm.ladeGeschaefte()
    const ref2 = wrapper.vm.geschaefte.find(g => g.id === 1)

    expect(ref2).toBe(ref1)
    expect(ref2.titel).toBe('A geändert')
  })

  it('ergänzt neue und entfernt verschwundene Geschäfte', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [{ id: 1, titel: 'A' }, { id: 2, titel: 'B' }] })
    await wrapper.vm.ladeGeschaefte()

    axios.get.mockResolvedValue({ data: [{ id: 2, titel: 'B' }, { id: 3, titel: 'C' }] })
    await wrapper.vm.ladeGeschaefte()

    expect(wrapper.vm.geschaefte.map(g => g.id)).toEqual([2, 3])
  })
})
