import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetPauschalForm from '../components/BudgetPauschalForm.vue'

// F100/F101: ein einzelner Pauschalantrag — Einsparung in CHF oder Prozent
// (immer negativ), Einreichen-Entscheid, ausgenommene Produktegruppen.
function mounted(pauschal) {
  return mount(BudgetPauschalForm, {
    props: { pauschal, produktegruppen: [{ code: '121', name: 'Personalamt' }] },
    global: { stubs: { NcButton: true, NcTextField: true, NcCheckboxRadioSwitch: true, PwMultiSelect: true } },
  })
}

describe('BudgetPauschalForm', () => {
  it('meldet eine CHF-Einsparung als negativen Betrag (F100)', () => {
    const w = mounted({ id: 1, betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.betragMag = '500000'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ betrag: -500000, prozent: 0 })
  })

  it('meldet eine Prozent-Einsparung mit Vorrang und negativem Vorzeichen (F100)', () => {
    const w = mounted({ id: 1, betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.prozentMag = '10'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ prozent: -10, betrag: 0 })
  })

  it('übernimmt den Einreichen-Entscheid und die Ausnahmen (F100/F101)', () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: ['121'] })
    w.vm.einreichen = false
    w.vm.speichern()
    const f = w.emitted().save[0][0]
    expect(f.haltung).toBe('nicht_einreichen')
    expect(f.ausnahmen).toContain('121')
  })
})
