import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import BudgetSitzungsantraege from '../components/BudgetSitzungsantraege.vue'
import axios from '@nextcloud/axios'

vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

function fixture() {
  return {
    produktegruppen: [{ id: 1, code: '121', name: 'Personalamt', departement: 'Präsidiales' }],
    antraege: [
      { id: 20, bereich: 'globalbudget', zielRef: '121', betragDelta: -30000, antragsteller: 'SVP', begruendung: 'Offizielle Kürzung', entscheid: 'offen', phase: 'sitzung' },
      { id: 21, bereich: 'globalbudget', zielRef: '121', betragDelta: -1, antragsteller: 'X', begruendung: 'Interner Antrag', entscheid: 'offen', phase: 'fraktion' },
    ],
  }
}

// F93: die Budget-Sitzungsanträge werden in die Sitzungsansicht gespiegelt.
describe('BudgetSitzungsantraege', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: fixture() })
    axios.put.mockReset().mockResolvedValue({ data: {} })
  })

  it('lädt die Sitzungsanträge (phase=sitzung) und zeigt nur diese', async () => {
    const w = shallowMount(BudgetSitzungsantraege, { props: { jahr: 2026 } })
    await flushPromises()
    const call = axios.get.mock.calls.find(c => String(c[0]).includes('/budget/2026'))
    expect(call[1].params.phase).toBe('sitzung')
    // Nur der offizielle Sitzungsantrag (20), nicht der Fraktionsantrag (21).
    expect(w.vm.antraegeFuer('121').map(a => a.id)).toEqual([20])
    expect(w.html()).toContain('Offizielle Kürzung')
    expect(w.html()).not.toContain('Interner Antrag')
  })

  it('setzt einen Entscheid live', async () => {
    const w = shallowMount(BudgetSitzungsantraege, { props: { jahr: 2026 } })
    await flushPromises()
    await w.vm.entscheidSetzen(20, 'angenommen')
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/20/entscheid'))
    expect(call[1].status).toBe('angenommen')
  })
})
