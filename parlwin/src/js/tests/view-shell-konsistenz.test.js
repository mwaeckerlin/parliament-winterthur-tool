import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import Budgetliste from '../components/Budgetliste.vue'
import Mitgliederliste from '../components/Mitgliederliste.vue'
import Kommissionsliste from '../components/Kommissionsliste.vue'
import Sitzungstypenliste from '../components/Sitzungstypenliste.vue'
import Fragestunde from '../components/Fragestunde.vue'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

/**
 * Konsistenz-Guard für das Ansichts-Gerüst: JEDE Hauptansicht nutzt dasselbe
 * Gerüst (pw-view-content + pw-view-header/pw-view-title) und legt ihre Filter
 * NICHT als eigenes Band auf die Seite, sondern in den Navigations-Slot
 * (#pw-filter-slot, via Teleport). Ohne diesen Guard schlich sich eine neue
 * Ansicht mit selbst erfundenem Kopf/Filter ein (Budget, 2026-08-22).
 */
const ANSICHTEN = [
  ['Geschaeftsliste', Geschaeftsliste],
  ['Vorstoesseliste', Vorstoesseliste],
  ['Budgetliste', Budgetliste],
  ['Mitgliederliste', Mitgliederliste],
  ['Kommissionsliste', Kommissionsliste],
  ['Sitzungstypenliste', Sitzungstypenliste],
  ['Fragestunde', Fragestunde],
]

describe('Ansichts-Gerüst ist konsistent', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: {} })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  it.each(ANSICHTEN)('%s nutzt pw-view-content mit pw-view-header/pw-view-title', (_name, Comp) => {
    const wrapper = shallowMount(Comp, {
      props: { mitglieder: [], fraktionen: [], kommissionen: [] },
    })
    expect(wrapper.find('.pw-view-content').exists(), 'pw-view-content fehlt').toBe(true)
    expect(wrapper.find('.pw-view-header').exists(), 'pw-view-header fehlt').toBe(true)
    expect(wrapper.find('.pw-view-title').exists(), 'pw-view-title fehlt').toBe(true)
  })

  it.each(ANSICHTEN)('%s baut kein eigenes Filterband auf die Seite', (_name, Comp) => {
    const wrapper = shallowMount(Comp, {
      props: { mitglieder: [], fraktionen: [], kommissionen: [] },
    })
    // Filter gehören in den Navigations-Slot (#pw-filter-slot), nicht als eigenes
    // Element in den Seiteninhalt. Ein neu erfundenes Filterband ist der Verstoss.
    expect(wrapper.find('.pw-budget-filter').exists(), 'eigenes Budget-Filterband').toBe(false)
    expect(wrapper.find('.pw-budget-kopf').exists(), 'eigener Budget-Kopf statt pw-view-header').toBe(false)
  })
})
