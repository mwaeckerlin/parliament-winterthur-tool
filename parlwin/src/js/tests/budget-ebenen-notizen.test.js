import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import BudgetEbeneNotizen from '../components/BudgetEbeneNotizen.vue'

vi.mock('@nextcloud/axios')

/**
 * Notizen am übergeordneten Zusammenhang (F124).
 *
 * Marc am 24.10.2026: «Notizen sollte man überall anlegen, auch in einem
 * übergeordneten Zusammenhang. Beim Budget zum Beispiel sollte man Notizen
 * machen zum Departement und man sollte Notizen machen insgesamt zum ganzen
 * Budget. Global. Und natürlich auf jedem Tab.»
 *
 * vitest läuft im Projekt-Root.
 */
const NOTIZEN = [
  { id: 7, text: 'Zum ganzen Budget', autorUid: 'mw', autorName: 'Marc Wäckerlin', geloescht: false },
  { id: 8, text: 'gelöscht', autorUid: 'mw', autorName: 'Marc Wäckerlin', geloescht: true },
]

function mounten(eigenschaften) {
  return mount(BudgetEbeneNotizen, {
    props: { ebene: 'budget_jahr', jahr: 2027, aktuelleUid: 'mw', ...eigenschaften },
    global: { stubs: { Teleport: true } },
  })
}

describe('Notizen am übergeordneten Zusammenhang', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockResolvedValue({ data: NOTIZEN })
  })

  it('spricht das ganze Budgetjahr unter seiner eigenen Adresse an', () => {
    expect(mounten().vm.basisUrl).toBe('budget/2027/ebene/budget_jahr')
  })

  it('spricht ein Departement mit seinem Namen als Schlüssel an', async () => {
    const w = mounten({ ebene: 'budget_departement', schluessel: 'Präsidiales' })
    expect(w.vm.basisUrl).toBe('budget/2027/ebene/budget_departement')
    await w.vm.laden()
    expect(axios.get).toHaveBeenCalledWith(
      expect.stringContaining('budget/2027/ebene/budget_departement/notizen'),
      { params: { schluessel: 'Präsidiales' } }
    )
  })

  it('spricht einen Tab mit seinem Schlüssel an', async () => {
    const w = mounten({ ebene: 'budget_tab', schluessel: 'investition' })
    await w.vm.laden()
    expect(axios.get).toHaveBeenCalledWith(
      expect.anything(),
      { params: { schluessel: 'investition' } }
    )
  })

  it('lädt die Notizen erst beim Aufklappen', async () => {
    const w = mounten()
    expect(axios.get).not.toHaveBeenCalled()
    await w.vm.aufgeklappt({ target: { open: true } })
    await flushPromises()
    expect(axios.get).toHaveBeenCalled()
    expect(w.vm.offen).toBe(true)
  })

  it('zählt nur die aktiven Notizen', async () => {
    const w = mounten()
    await w.vm.laden()
    expect(w.vm.anzahl).toBe(1)
  })
})
