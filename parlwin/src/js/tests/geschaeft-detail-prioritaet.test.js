import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { aktionen: [], zustaendigkeiten: [] } })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'

// In der offenen Detailansicht ist IMMER alles sichtbar: auch die Priorität
// gehört ins Geschäfts-Detail (fraktionsinterne Bearbeitung) und speichert wie
// alle Eingaben sofort.
describe('GeschaeftDetail — Priorität', () => {
  beforeEach(() => {
    axios.put.mockClear()
  })

  const mountFn = () => shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
    data() {
      return {
        geschaeft: { id: 1, titel: 'Test', prioritaet: '', aktionen: [], zustaendig: [] },
        laden: false,
      }
    },
    global: {
      stubs: { NcSelect: true, PwMultiSelect: true, PwWysiwyg: true, GeschaeftDokumente: true },
    },
  })

  it('zeigt das Prioritäts-Feld in der Detailansicht', async () => {
    const wrapper = mountFn()
    // mounted() lädt das Detail asynchron — auf das gerenderte Resultat warten.
    await Promise.resolve()
    await Promise.resolve()
    await wrapper.vm.$nextTick()
    expect(wrapper.html()).toContain('Priorität')
  })

  it('eine Prioritätswahl speichert sofort über den Prioritäts-Endpunkt', async () => {
    const wrapper = mountFn()
    // PwPrioritaetSelect emittiert den Rohwert (String), nicht das Option-Objekt.
    await wrapper.vm.prioritaetGewaehlt('hoch')
    expect(axios.put).toHaveBeenCalledTimes(1)
    expect(axios.put.mock.calls[0][0]).toContain('/apps/parlwin/geschaefte/1/prioritaet')
    expect(axios.put.mock.calls[0][1]).toEqual({ prioritaet: 'hoch' })
  })

  it('Abwählen speichert die Priorität als «nicht gesetzt» (leer)', async () => {
    const wrapper = mountFn()
    await wrapper.vm.prioritaetGewaehlt('')
    expect(axios.put.mock.calls[0][1]).toEqual({ prioritaet: '' })
  })
})
