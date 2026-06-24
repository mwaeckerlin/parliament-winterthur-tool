import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

describe('Sitzungsliste — In-place-Update beim Sync (kein DOM-Rebuild)', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('behält die Objekt-Referenz bestehender Sitzungen beim Neuladen', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    axios.get.mockResolvedValue({ data: [{ id: 1, datum: '2099-01-01', titel: 'A', notizen: '[]' }] })
    await wrapper.vm.ladeSitzungen()
    const ref1 = wrapper.vm.sitzungen.find(s => s.id === 1)

    axios.get.mockResolvedValue({ data: [{ id: 1, datum: '2099-01-01', titel: 'A geändert', notizen: '[]' }] })
    await wrapper.vm.ladeSitzungen()
    const ref2 = wrapper.vm.sitzungen.find(s => s.id === 1)

    expect(ref2).toBe(ref1) // gleiche Referenz → Vue baut das DOM nicht neu auf
    expect(ref2.titel).toBe('A geändert') // Inhalt trotzdem aktualisiert
  })

  it('ergänzt neue und entfernt verschwundene Sitzungen', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    axios.get.mockResolvedValue({ data: [{ id: 1, titel: 'A', notizen: '[]' }, { id: 2, titel: 'B', notizen: '[]' }] })
    await wrapper.vm.ladeSitzungen()

    axios.get.mockResolvedValue({ data: [{ id: 2, titel: 'B', notizen: '[]' }, { id: 3, titel: 'C', notizen: '[]' }] })
    await wrapper.vm.ladeSitzungen()

    expect(wrapper.vm.sitzungen.map(s => s.id)).toEqual([2, 3])
  })
})
