import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import NotizenListe from '../components/NotizenListe.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

// Nach der Vereinheitlichung gibt es NUR die geteilte NotizenListe — auch für
// Sitzungs- und verknüpfte (readonly angezeigte) Notizen. Der Nur-Lese-Modus
// blendet «+ Neue Notiz», Löschen und Bearbeiten aus.
describe('NotizenListe — readonly (verknüpfte Sitzungen)', () => {
  it('zeigt im readonly-Modus keinen «+ Neue Notiz»-Knopf und kein Löschen', () => {
    const wrapper = shallowMount(NotizenListe, {
      props: {
        basisUrl: 'sitzungen/2',
        notizen: [{ id: 1, text: 'x', autorUid: 'me' }],
        aktuelleUid: 'me',
        readonly: true,
      },
    })
    expect(wrapper.find('.pw-btn-neue-notiz').exists()).toBe(false)
    expect(wrapper.find('.pw-btn-loeschen').exists()).toBe(false)
    // Auch die eigene Notiz ist im readonly-Modus nicht bearbeitbar.
    expect(wrapper.vm.darfBearbeiten({ autorUid: 'me' })).toBe(false)
  })

  it('zeigt ohne readonly den «+ Neue Notiz»-Knopf', () => {
    const wrapper = shallowMount(NotizenListe, {
      props: { basisUrl: 'sitzungen/2', notizen: [], aktuelleUid: 'me', readonly: false },
    })
    expect(wrapper.find('.pw-btn-neue-notiz').exists()).toBe(true)
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

  it('lädt die Notizen jeder verknüpften Sitzung über den geteilten NotizService', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    wrapper.vm.sitzungen = [{ id: 1, verknuepfungId: 7, datum: '2099-01-01', titel: 'A' }]
    // 1. Aufruf: verknüpfte Sitzungen; danach je verknüpfter Sitzung deren Notizen.
    axios.get.mockResolvedValueOnce({ data: [{ id: 2, titel: 'B' }] })
    axios.get.mockResolvedValueOnce({ data: [{ id: 9, text: 'Notiz von B', autorUid: 'x' }] })

    await wrapper.vm.ladeVerknuepfteSitzungen(1)
    await Promise.resolve()

    expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('/apps/parlwin/sitzungen/2/notizen'))
    expect((wrapper.vm.sitzungNotizenNs[2] || []).map(n => n.id)).toEqual([9])
  })

  it('lädt nichts, wenn die Sitzung nicht verknüpft ist', async () => {
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    wrapper.vm.sitzungen = [{ id: 1, verknuepfungId: null }]

    await wrapper.vm.ladeVerknuepfteSitzungen(1)

    expect(wrapper.vm.verknuepfteSitzungen[1]).toEqual([])
  })
})
