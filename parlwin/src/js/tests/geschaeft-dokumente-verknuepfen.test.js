import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: [] })),
    delete: vi.fn(() => Promise.resolve({ data: [] })),
  },
}))
vi.mock('@nextcloud/dialogs', () => ({ getFilePickerBuilder: vi.fn() }))

import axios from '@nextcloud/axios'
import GeschaeftDokumente from '../components/GeschaeftDokumente.vue'

// F72: Die geteilte Dokument-Komponente kann bestehende Dateien explizit
// verknüpfen (überall gleich, konfiguriert über Objekttyp + Objekt-ID).
describe('GeschaeftDokumente — bestehende Dokumente verknüpfen', () => {
  beforeEach(() => {
    axios.get.mockReset()
    axios.post.mockReset().mockResolvedValue({ data: [] })
    axios.delete.mockReset().mockResolvedValue({ data: [] })
  })

  const mount = async (props, links = [], legacy = []) => {
    axios.get.mockImplementation((url) => String(url).includes('/dokument-links/')
      ? Promise.resolve({ data: links })
      : Promise.resolve({ data: legacy }))
    const wrapper = shallowMount(GeschaeftDokumente, {
      props,
      global: { stubs: { NcActions: true, NcActionButton: true } },
    })
    await new Promise(r => setTimeout(r, 0))
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('zeigt «Verknüpfen» nur, wenn Objekttyp und -ID gesetzt sind', async () => {
    const ohne = await mount({})
    expect(ohne.vm.hatLinks).toBe(false)
    expect(ohne.text()).not.toContain('Verknüpfen')

    const mit = await mount({ objektTyp: 'vorstoss', objektId: 6 })
    expect(mit.vm.hatLinks).toBe(true)
    expect(mit.text()).toContain('Verknüpfen')
  })

  it('lädt die verknüpften Dateien und markiert sie als verknüpft', async () => {
    const wrapper = await mount(
      { objektTyp: 'vorstoss', objektId: 6 },
      [{ fileId: 42, name: 'Rede.pdf', pfad: 'Fraktion/40_Vorstösse/10_Eigene/2026/Rede.pdf' }],
    )
    const eintrag = wrapper.vm.dokumente.find(d => d.fileId === 42)
    expect(eintrag).toBeTruthy()
    expect(eintrag.verknuepft).toBe(true)
  })

  it('linkLoesen ruft den DELETE-Endpunkt mit Objekttyp/-ID/File-ID', async () => {
    const wrapper = await mount({ objektTyp: 'vorstoss', objektId: 6 }, [{ fileId: 42, name: 'Rede.pdf' }])
    await wrapper.vm.linkLoesen({ fileId: 42 })
    expect(axios.delete).toHaveBeenCalledTimes(1)
    expect(String(axios.delete.mock.calls[0][0])).toContain('/apps/parlwin/dokument-links/vorstoss/6/42')
  })
})
