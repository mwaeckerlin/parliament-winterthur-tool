import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDokumente from '../components/GeschaeftDokumente.vue'
import axios from '@nextcloud/axios'

describe('GeschaeftDokumente — generisch (Geschäfte + Sitzungen)', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('lädt im generischen Modus (Sitzung) von der apiBasis-URL', async () => {
    shallowMount(GeschaeftDokumente, {
      props: { apiBasis: '/apps/parlwin/sitzungen/3', praefix: '2026-06-24' },
    })
    await new Promise(r => setTimeout(r, 0))
    expect(axios.get).toHaveBeenCalled()
    expect(String(axios.get.mock.calls[0][0])).toContain('/apps/parlwin/sitzungen/3/dokumente')
  })

  it('lädt im Geschäfts-Modus weiterhin von der Geschäfts-URL', async () => {
    shallowMount(GeschaeftDokumente, {
      props: { geschaeftId: 5, geschaeftNummer: '2026.1' },
    })
    await new Promise(r => setTimeout(r, 0))
    expect(String(axios.get.mock.calls[0][0])).toContain('/apps/parlwin/geschaefte/5/dokumente')
  })
})
