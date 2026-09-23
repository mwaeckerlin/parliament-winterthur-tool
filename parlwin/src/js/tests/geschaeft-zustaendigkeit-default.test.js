import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'

vi.mock('../realtime', () => ({ subscribeRealtime: () => vi.fn() }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'annab', displayName: 'Anna B' }) }))
vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: { id: 42 } })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'
import { personKey } from '../utils'

// Der angemeldete Nutzer (uid «annab») als Fraktionsmitglied mit Nextcloud-Konto.
const ICH = { id: 5, externId: 'p-annab', vorname: 'Anna', name: 'B', aktiv: true, nextcloudUid: 'annab' }

// Feature: Beim Anlegen eines eigenen Geschäfts ist der Erzeuger standardmässig
// zuständig — als Vorauswahl sichtbar und beim Speichern übernommen.
describe('Eigenes Geschäft: der Erzeuger ist standardmässig zuständig', () => {
  beforeEach(() => { axios.post.mockClear(); axios.put.mockClear() })

  const neueMaske = (mitglieder) => shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 0, mitglieder, traktandumKontext: null },
  })

  it('wählt beim Neuanlegen den angemeldeten Nutzer als zuständig vor', async () => {
    const wrapper = neueMaske([ICH])
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.ausgewaehltePersonKeys).toEqual([personKey(ICH)])
    expect(wrapper.vm.hauptPersonKey).toBe(personKey(ICH))
  })

  it('ohne passendes Mitglied bleibt die Zuständigkeit leer', async () => {
    const wrapper = neueMaske([])
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.ausgewaehltePersonKeys).toEqual([])
  })

  it('speichert die vorausgewählte Zuständigkeit am neu angelegten Geschäft', async () => {
    const wrapper = neueMaske([ICH])
    await wrapper.vm.$nextTick()
    wrapper.vm.geschaeft.titel = 'Mein Geschäft'
    await wrapper.vm.neuesGeschaeftSpeichern()
    // Nach dem POST (id 42) wird die Zuständigkeit per PUT auf /geschaefte/42 gesichert.
    const putCall = axios.put.mock.calls.find(c => String(c[0]).includes('/geschaefte/42'))
    expect(putCall, 'Zuständigkeit wird am neuen Geschäft gespeichert').toBeTruthy()
    expect(putCall[1].zustaendigkeiten.map(z => z.mitgliedExternId)).toContain('p-annab')
  })
})
