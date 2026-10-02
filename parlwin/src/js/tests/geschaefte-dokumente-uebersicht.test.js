import { describe, it, expect, beforeEach, vi } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'
import axios from '@nextcloud/axios'

// F121: Die amtlichen Dokumente stehen in der Übersicht und im geöffneten
// Geschäft, beide Male aufklappbar; die Suche greift auch in ihren Inhalt.
describe('Amtliche Dokumente am Geschäft', () => {
  const geschaeft = {
    id: 1,
    nummer: '2026.15',
    titel: 'Zweck der Immobilien',
    typ: 'Schriftliche Anfrage',
    status: 'Pendent',
    datum: '2026-03-02',
    amtlicheDokumente: [
      { id: 7, titel: '2026.15V', kategorie: 'Vorstoss', datum: '2026-03-02', seiten: 2, hatInhalt: true },
    ],
  }

  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset()
  })

  const liste = async (daten) => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: daten })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('hängt die Dokumentenliste an jedes Geschäft der Übersicht', async () => {
    const wrapper = await liste([geschaeft])
    // Erste Stufe: das Dreieck am Geschäft bringt die Liste hervor.
    await wrapper.find('.pw-dokumente-schalter').trigger('click')
    const dokumente = wrapper.findAllComponents({ name: 'PwAmtlicheDokumente' })
    expect(dokumente.length).toBeGreaterThan(0)
    expect(dokumente[0].props('dokumente')).toHaveLength(1)
    expect(dokumente[0].props('geschaeftId')).toBe(1)
  })

  it('findet ein Geschäft über ein Wort, das nur im Dokument steht', async () => {
    const wrapper = await liste([geschaeft, { id: 2, nummer: '2026.16', titel: 'Anderes', status: 'Pendent' }])
    axios.get.mockResolvedValue({ data: [2] })
    wrapper.vm.suche = 'Nettorendite'
    await flushPromises()
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([2])
  })

  it('zeigt die Dokumente auch im geöffneten Geschäft', async () => {
    axios.get.mockResolvedValue({ data: { ...geschaeft, aktionen: [], zustaendigkeiten: [] } })
    const wrapper = shallowMount(GeschaeftDetail, { props: { geschaeftId: 1, mitglieder: [] } })
    await flushPromises()
    const dokumente = wrapper.findAllComponents({ name: 'PwAmtlicheDokumente' })
    expect(dokumente.length).toBe(1)
    expect(dokumente[0].props('dokumente')).toHaveLength(1)
  })
})
