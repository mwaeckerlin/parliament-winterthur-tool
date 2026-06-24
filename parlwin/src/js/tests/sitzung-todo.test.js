import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

function mount() {
  return shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
}

function todoCalls() {
  return axios.post.mock.calls.filter(c => String(c[0]).includes('/todo'))
}

describe('Sitzungsliste — To-do zu Deck', () => {
  beforeEach(() => {
    axios.post.mockReset().mockResolvedValue({ data: { kartenId: 1 } })
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('sendet das To-do an den Deck-Endpunkt der Sitzung', async () => {
    const wrapper = mount()
    wrapper.vm.neuesTodoText = { 7: 'Protokoll versenden' }
    await wrapper.vm.todoZuDeck({ id: 7, titel: 'Sitzung', datum: '2099-01-01' })

    const calls = todoCalls()
    expect(calls).toHaveLength(1)
    expect(calls[0][0]).toContain('/sitzungen/7/todo')
    expect(calls[0][1].titel).toBe('Protokoll versenden')
  })

  it('sendet nichts bei leerem To-do-Text', async () => {
    const wrapper = mount()
    wrapper.vm.neuesTodoText = { 7: '   ' }
    await wrapper.vm.todoZuDeck({ id: 7, titel: 'Sitzung', datum: '2099-01-01' })

    expect(todoCalls()).toHaveLength(0)
  })

  it('leert das Eingabefeld nach erfolgreichem Senden', async () => {
    const wrapper = mount()
    wrapper.vm.neuesTodoText = { 7: 'Aufgabe' }
    await wrapper.vm.todoZuDeck({ id: 7, titel: 'Sitzung', datum: '2099-01-01' })

    expect(wrapper.vm.neuesTodoText[7]).toBe('')
  })
})
