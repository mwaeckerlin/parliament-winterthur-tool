import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'

vi.mock('../realtime', () => ({ subscribeRealtime: () => vi.fn() }))
vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: { verknuepftGeschaeftId: 42, status: 'erledigt' } })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'

// Feature: Ein eigenes Geschäft lässt sich mit einem offiziellen Parlaments-
// geschäft verknüpfen (wie Vorstoss→Geschäft): Knopf «Mit offiziellem Geschäft
// verknüpfen», Auswahl über den geteilten Dialog, danach ist das eigene Geschäft
// erledigt und verweist auf das offizielle.
describe('Eigenes Geschäft → offizielles Geschäft verknüpfen', () => {
  beforeEach(() => { axios.post.mockClear() })

  const maske = async (geschaeft, eigeneListe = []) => {
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/verknuepfte-eigene')) return Promise.resolve({ data: eigeneListe })
      if (u.includes('/geschaefte/7') && !u.includes('vorstoesse')) return Promise.resolve({ data: geschaeft })
      return Promise.resolve({ data: [] })
    })
    const wrapper = shallowMount(GeschaeftDetail, {
      props: { geschaeftId: 7, mitglieder: [], traktandumKontext: null },
      // NcButton-Stub, der seinen Slot rendert, damit die Knopfbeschriftung im Text erscheint.
      global: { stubs: { NcButton: { template: '<button class="nc-button-stub"><slot /></button>' } } },
    })
    await Promise.resolve()
    await Promise.resolve()
    await flushPromises()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  const eigenes = (extra = {}) => ({ id: 7, externId: 'eigen:x', titel: 'Mein Geschäft', status: 'Pendent', prioritaet: 'hoch', verknuepftGeschaeftId: 0, aktionen: [], zustaendigkeiten: [], ...extra })

  it('zeigt den Verknüpfen-Knopf für ein eigenes, noch nicht verknüpftes Geschäft', async () => {
    const wrapper = await maske(eigenes())
    expect(wrapper.text()).toContain('Mit offiziellem Geschäft verknüpfen')
  })

  it('zeigt bei einem offiziellen Geschäft KEINEN Verknüpfen-Knopf', async () => {
    const wrapper = await maske(eigenes({ externId: '2026.1' }))
    expect(wrapper.text()).not.toContain('Mit offiziellem Geschäft verknüpfen')
  })

  it('zeigt nach dem Verknüpfen den Abschluss-Hinweis statt des Knopfes', async () => {
    const wrapper = await maske(eigenes({ status: 'erledigt', verknuepftGeschaeftId: 42 }))
    expect(wrapper.text()).toContain('Mit einem offiziellen Geschäft verknüpft und abgeschlossen')
    expect(wrapper.text()).not.toContain('Mit offiziellem Geschäft verknüpfen')
  })

  it('nennt das verknüpfte offizielle Geschäft anklickbar (hin) und navigiert dorthin', async () => {
    const wrapper = await maske(eigenes({ status: 'erledigt', verknuepftGeschaeftId: 42, verknuepftGeschaeft: { id: 42, nummer: '2026.42', titel: 'Amtliches Geschäft' } }))
    expect(wrapper.text()).toContain('2026.42')
    expect(wrapper.text()).toContain('Amtliches Geschäft')
    await wrapper.find('.pw-verweis-knopf').trigger('click')
    expect(wrapper.emitted('oeffneGeschaeft')?.[0]).toEqual([42])
  })

  it('listet am offiziellen Geschäft die verknüpften eigenen Geschäfte anklickbar (her) und navigiert dorthin', async () => {
    const wrapper = await maske(
      eigenes({ externId: '2026.1', titel: 'Amtliches', verknuepftGeschaeftId: 0 }),
      [{ id: 8, titel: 'Verlinktes Eigenes', prioritaet: 'mittel', aktionen: [] }],
    )
    expect(wrapper.text()).toContain('Verknüpfte eigene Geschäfte')
    expect(wrapper.text()).toContain('Verlinktes Eigenes')
    expect(wrapper.text()).toContain('Mittel')
    await wrapper.find('.pw-verweis-knopf').trigger('click')
    expect(wrapper.emitted('oeffneGeschaeft')?.[0]).toEqual([8])
  })

  it('verknuepfen() POSTet das Zielgeschäft und schliesst das eigene Geschäft ab', async () => {
    const wrapper = await maske(eigenes())
    await wrapper.vm.verknuepfen({ id: 42, titel: 'Amtliches Geschäft' })
    expect(axios.post).toHaveBeenCalledTimes(1)
    expect(axios.post.mock.calls[0][0]).toContain('/apps/parlwin/geschaefte/7/verknuepfen')
    expect(axios.post.mock.calls[0][1]).toEqual({ zielGeschaeftId: 42 })
    expect(wrapper.vm.geschaeft.verknuepftGeschaeftId).toBe(42)
    expect(wrapper.vm.geschaeft.status).toBe('erledigt')
  })
})
