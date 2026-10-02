import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, shallowMount, flushPromises } from '@vue/test-utils'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import PwAmtlicheDokumente from '../components/PwAmtlicheDokumente.vue'
import axios from '@nextcloud/axios'

// F121: In der Übersicht klappt zuerst die Dokumentliste auf (ein Dreieck am
// Geschäft), und erst danach das einzelne Dokument. Der Kopf eines Dokuments
// trägt Titel und Datum links, den Titel fett.
describe('Amtliche Dokumente: zweistufiges Aufklappen', () => {
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

  it('zeigt am Geschäft nur ein Dreieck, die Dokumentliste erst nach dem Klick', async () => {
    const wrapper = await liste([geschaeft])
    const schalter = wrapper.find('.pw-dokumente-schalter')
    expect(schalter.exists(), 'Das Dreieck fehlt').toBe(true)
    expect(wrapper.findAllComponents({ name: 'PwAmtlicheDokumente' })).toHaveLength(0)

    await schalter.trigger('click')
    expect(wrapper.findAllComponents({ name: 'PwAmtlicheDokumente' }).length).toBeGreaterThan(0)

    await wrapper.find('.pw-dokumente-schalter').trigger('click')
    expect(wrapper.findAllComponents({ name: 'PwAmtlicheDokumente' })).toHaveLength(0)
  })

  it('öffnet mit dem Dreieck nicht das Geschäft', async () => {
    const wrapper = await liste([geschaeft])
    await wrapper.find('.pw-dokumente-schalter').trigger('click')
    expect(wrapper.vm.detailOffen, 'Der Klick auf das Dreieck hat das Geschäft geöffnet').toBeFalsy()
  })

  it('trägt im Kopf des Dokuments den Titel fett und das Datum daneben', () => {
    const wrapper = mount(PwAmtlicheDokumente, {
      props: { geschaeftId: 1, dokumente: geschaeft.amtlicheDokumente },
    })
    const titel = wrapper.find('.pw-dokument-titel')
    expect(titel.text()).toBe('2026.15V')
    expect(titel.element.tagName).toBe('STRONG')
    const datum = wrapper.find('.pw-dokument-datum')
    expect(datum.text()).toBe('2.3.2026')
    // Titel vor Datum, beide im selben Kopf: die Reihenfolge im DOM ist die
    // Reihenfolge auf dem Bildschirm.
    const kopf = wrapper.find('.pw-dokument-kopf').element
    const kinder = [...kopf.children].map(k => k.className)
    expect(kinder.indexOf('pw-dokument-titel')).toBeLessThan(kinder.indexOf('pw-dokument-datum'))
  })
})
