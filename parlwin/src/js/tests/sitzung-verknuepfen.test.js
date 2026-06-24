import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

function mount() {
  return shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
}

function verknuepfenCalls() {
  return axios.post.mock.calls.filter(c => String(c[0]).includes('/verknuepfen'))
}

describe('Sitzungsliste — Verknüpfen mit', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('listet zukünftige Sitzungen zuerst (aufsteigend), dann vergangene (absteigend)', () => {
    const wrapper = mount()
    wrapper.vm.sitzungen = [
      { id: 1, datum: '2020-01-01', titel: 'Alt' },
      { id: 2, datum: '2099-01-01', titel: 'Zukunft' },
      { id: 3, datum: '2098-01-01', titel: 'Bald' },
    ]
    expect(wrapper.vm.verknuepfungsOptionen.map(o => o.id)).toEqual([3, 2, 1])
  })

  it('verknüpft nach dem Erstellen, wenn eine Zielsitzung gewählt ist', async () => {
    axios.post
      .mockResolvedValueOnce({ data: { id: 99, datum: '2099-01-01', titel: 'Neu' } })
      .mockResolvedValueOnce({ data: { id: 99, datum: '2099-01-01', titel: 'Neu', verknuepfungId: 5 } })
    const wrapper = mount()
    wrapper.vm.gewaehlterTyp = { id: 1, name: 'T' }
    wrapper.vm.neueSitzungDatum = '2099-01-01'
    wrapper.vm.neueSitzungVerknuepfungId = 5

    await wrapper.vm.erstelleNeueSession()

    const calls = verknuepfenCalls()
    expect(calls).toHaveLength(1)
    expect(calls[0][0]).toContain('/sitzungen/99/verknuepfen')
    expect(calls[0][1]).toEqual({ zielId: 5 })
  })

  it('verknüpft NICHT, wenn keine Zielsitzung gewählt ist', async () => {
    axios.post.mockResolvedValue({ data: { id: 99, datum: '2099-01-01', titel: 'Neu' } })
    const wrapper = mount()
    wrapper.vm.gewaehlterTyp = { id: 1, name: 'T' }
    wrapper.vm.neueSitzungDatum = '2099-01-01'
    wrapper.vm.neueSitzungVerknuepfungId = null

    await wrapper.vm.erstelleNeueSession()

    expect(verknuepfenCalls()).toHaveLength(0)
  })
})
