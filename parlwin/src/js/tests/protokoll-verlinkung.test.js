import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

// F115: Das Protokoll einer Sitzung ist mit einem Klick erreichbar — oben auf der
// Karte der Sitzung, die es protokolliert, und noch einmal in der Folgesitzung
// beim Traktandum, das es abnimmt. Beide Links öffnen ein neues Fenster.
const PROTOKOLL_01_12 = 'https://parlament.winterthur.ch/_doc/6625061'
const PROTOKOLL_10_11 = 'https://parlament.winterthur.ch/_doc/6396524'

const sitzung = {
  id: 1,
  datum: '2025-12-01',
  titel: '13./14. Sitzungen',
  ort: 'Rathaus',
  typId: 0,
  url: 'https://parlament.winterthur.ch/_rte/anlass/6866848',
  protokollUrl: PROTOKOLL_01_12,
  protokollTitel: 'Protokoll Stradtparlament vom 1. Dezember 2025',
}

const abnahmeTraktandum = {
  id: 11,
  sitzungId: 1,
  nummer: 1,
  titel: 'Abnahme Parlaments-Protokolle',
  url: 'https://parlament.winterthur.ch/_doc/6378164',
  geschaeft: null,
  protokoll: {
    url: PROTOKOLL_10_11,
    titel: 'Protokoll Stadtparlament vom 10. November 2025',
    datum: '2025-11-10',
  },
}

async function liste(traktanden) {
  const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
  // Erst das Laden beim Einhängen abwarten, sonst überschreibt es die Testdaten.
  await flushPromises()
  wrapper.vm.laden = false
  wrapper.vm.nurKuenftige = false
  wrapper.vm.sitzungen = [sitzung]
  wrapper.vm.offeneSitzungen = [1]
  wrapper.vm.traktanden = { 1: traktanden }
  await wrapper.vm.$nextTick()
  return wrapper
}

describe('F115 — Protokolle sind verlinkt', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('verlinkt oben auf der Sitzungskarte das Protokoll dieser Sitzung, in einem neuen Fenster', async () => {
    const wrapper = await liste([])
    const link = wrapper.find('.pw-protokoll-link')

    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe(PROTOKOLL_01_12)
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toContain('noopener')
    expect(link.attributes('title')).toBe('Protokoll Stradtparlament vom 1. Dezember 2025')
    expect(link.text()).toBe('Protokoll')
  })

  it('zeigt keinen Protokoll-Link, solange das Protokoll nicht veröffentlicht ist', async () => {
    const wrapper = await liste([])
    wrapper.vm.sitzungen = [{ ...sitzung, protokollUrl: '', protokollTitel: '' }]
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.pw-protokoll-link').exists()).toBe(false)
  })

  it('verlinkt beim Traktandum der Abnahme das Protokoll der protokollierten Sitzung', async () => {
    const wrapper = await liste([abnahmeTraktandum])
    const links = wrapper.findAll('.pw-traktandum-protokoll')

    // Tabellendarstellung und Kartendarstellung tragen denselben Link.
    expect(links.length).toBe(2)
    for (const link of links) {
      expect(link.attributes('href')).toBe(PROTOKOLL_10_11)
      expect(link.attributes('target')).toBe('_blank')
      expect(link.attributes('rel')).toContain('noopener')
      expect(link.text()).toBe('Protokoll 10.11.2025')
      expect(link.attributes('title')).toBe('Protokoll Stadtparlament vom 10. November 2025')
    }
  })

  it('lässt ein Traktandum ohne Protokoll unverändert', async () => {
    const wrapper = await liste([{
      id: 12,
      sitzungId: 1,
      nummer: 5,
      titel: 'Budget 2026 und Festsetzung des Steuerfusses',
      url: 'https://parlament.winterthur.ch/_doc/6122695',
      geschaeft: null,
      protokoll: null,
    }])

    expect(wrapper.find('.pw-traktandum-protokoll').exists()).toBe(false)
  })
})
