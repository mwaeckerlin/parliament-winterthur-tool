import { describe, it, expect } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import BudgetAntragForm from '../components/BudgetAntragForm.vue'

function leer() {
  return {
    betrag: '', prozent: '', mehrausgabe: false,
    herkunft: 'eigene', antragsteller: 'U', haltung: 'einreichen',
    unterstuetzer: [], begruendung: '', zielAenderungen: [], aufteilung: [],
  }
}

// F109: das Antragsformular schreibt Zielvorgaben-Änderungen und die Einsparungs-
// verteilung in das Formularobjekt; leere Werte entfernen den Eintrag, das Vorzeichen
// der Aufteilung folgt der Richtung (Reduktion −).
describe('BudgetAntragForm', () => {
  function form(props = {}) {
    const f = leer()
    return shallowMount(BudgetAntragForm, { props: { form: f, ...props } })
  }

  it('setzt und entfernt eine Zielvorgaben-Änderung', () => {
    const w = form()
    const zv = { zielNummer: 2, messgroesse: 'Prozentsatz zufrieden', soll: '85' }
    w.vm.zielWertGesetzt(zv, '100')
    expect(w.vm.form.zielAenderungen).toEqual([{ zielNummer: 2, messgroesse: 'Prozentsatz zufrieden', neuerWert: '100' }])
    expect(w.vm.zielWert(zv)).toBe('100')
    w.vm.zielWertGesetzt(zv, '')
    expect(w.vm.form.zielAenderungen).toEqual([])
  })

  it('setzt einen Aufteilungs-Betrag mit dem Vorzeichen der Richtung', () => {
    const w = form()
    w.vm.aufteilungGesetzt('produkt', '1', '20000')
    expect(w.vm.form.aufteilung).toEqual([{ ebene: 'produkt', ref: '1', betrag: -20000 }])
    expect(w.vm.aufteilungWert('produkt', '1')).toBe('20000')
    // Bei Mehrausgabe kehrt sich das Vorzeichen um.
    w.vm.form.mehrausgabe = true
    w.vm.aufteilungGesetzt('pg-kosten', 'Personalkosten', '5000')
    const kosten = w.vm.form.aufteilung.find(x => x.ref === 'Personalkosten')
    expect(kosten.betrag).toBe(5000)
    // Leerer Wert entfernt den Eintrag.
    w.vm.aufteilungGesetzt('produkt', '1', '')
    expect(w.vm.form.aufteilung.find(x => x.ref === '1')).toBeUndefined()
  })
})
