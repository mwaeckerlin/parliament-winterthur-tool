import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetAntragForm from '../components/BudgetAntragForm.vue'

// F95: CHF und Prozent werden wechselseitig aus dem Budgetwert der Position
// berechnet; die Richtung ist das Vorzeichen (Reduktion/Mehrausgabe).
function form(zusatz = {}) {
  return {
    betrag: '', prozent: '', mehrausgabe: false,
    herkunft: 'eigene', antragsteller: '', haltung: 'einreichen',
    unterstuetzer: [], begruendung: '', ...zusatz,
  }
}

function mounted(f, props = {}) {
  return mount(BudgetAntragForm, {
    props: { form: f, basis: 100000, antragstellerOptionen: [], haltungOptionen: [{ value: 'einreichen', label: 'Reichen wir ein' }], fraktionOptionen: [], ...props },
    global: { stubs: { NcSelect: true, NcTextField: true, NcCheckboxRadioSwitch: true, NcButton: true, PwMultiSelect: true } },
  })
}

describe('BudgetAntragForm', () => {
  it('rechnet aus einer CHF-Eingabe den Prozentwert (F95)', () => {
    const f = form()
    const w = mounted(f)
    w.vm.betragGeaendert('20000') // 20 % von 100 000
    expect(f.betrag).toBe('20000')
    expect(Number(f.prozent)).toBe(20)
  })

  it('rechnet aus einer Prozent-Eingabe den CHF-Betrag (F95)', () => {
    const f = form()
    const w = mounted(f)
    w.vm.prozentGeaendert('20') // 20 % von 100 000 = 20 000
    expect(Number(f.prozent)).toBe(20)
    expect(f.betrag).toBe('20000')
  })

  it('leert das Gegenfeld, wenn die Eingabe geleert wird (F95)', () => {
    const f = form({ betrag: '20000', prozent: '20' })
    const w = mounted(f)
    w.vm.betragGeaendert('')
    expect(f.betrag).toBe('')
    expect(f.prozent).toBe('')
  })

  it('meldet einen Herkunftswechsel an die Elternkomponente (F94)', () => {
    const f = form()
    const w = mounted(f)
    w.vm.herkunftGewaehlt({ value: 'fremde', label: 'Fremd' })
    expect(w.emitted().herkunft[0]).toEqual(['fremde'])
  })
})
