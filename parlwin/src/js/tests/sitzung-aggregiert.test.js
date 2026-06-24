import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import NotizenListe from '../components/NotizenListe.vue'
import PwWysiwyg from '../components/PwWysiwyg.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

describe('NotizenListe — readonly', () => {
  it('zeigt im readonly-Modus kein Eingabefeld', () => {
    const wrapper = shallowMount(NotizenListe, {
      props: { modelValue: [{ text: 'x', uid: 'a' }], readonly: true },
    })
    expect(wrapper.findComponent(PwWysiwyg).exists()).toBe(false)
  })

  it('zeigt ohne readonly ein Eingabefeld', () => {
    const wrapper = shallowMount(NotizenListe, {
      props: { modelValue: [], readonly: false },
    })
    expect(wrapper.findComponent(PwWysiwyg).exists()).toBe(true)
  })
})

describe('Sitzungsliste — aggregierte Sicht verknüpfter Sitzungen', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('lädt die verknüpften Sitzungen und filtert die Sitzung selbst heraus', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    wrapper.vm.sitzungen = [{ id: 1, verknuepfungId: 7, datum: '2099-01-01', titel: 'A' }]
    axios.get.mockResolvedValueOnce({ data: [{ id: 1, titel: 'A' }, { id: 2, titel: 'B' }] })

    await wrapper.vm.ladeVerknuepfteSitzungen(1)

    expect(wrapper.vm.verknuepfteSitzungen[1].map(s => s.id)).toEqual([2])
  })

  it('lädt nichts, wenn die Sitzung nicht verknüpft ist', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    wrapper.vm.sitzungen = [{ id: 1, verknuepfungId: null }]

    await wrapper.vm.ladeVerknuepfteSitzungen(1)

    expect(wrapper.vm.verknuepfteSitzungen[1]).toEqual([])
  })
})
