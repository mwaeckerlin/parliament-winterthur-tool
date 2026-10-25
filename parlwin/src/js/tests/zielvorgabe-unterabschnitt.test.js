import { describe, it, expect } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import BudgetAntragForm from '../components/BudgetAntragForm.vue'
import { zielName, gleicheZielvorgabe } from '../utils'

/**
 * Zwei Zielvorgaben mit derselben Messgrösse sind zwei Zielvorgaben.
 *
 * Befund von Marc am 24.10.2026 am Budget 2027, Produktegruppe Immobilien
 * (240): «Wirtschaftlichkeit — Wohnliegenschaften», «Gewerbeliegenschaften»
 * und «Restaurantbetriebe» standen je zweimal da, einmal mit Soll 1.00 und
 * einmal mit 2.31, 2.98 und 2.38. Das Buch gliedert sie (S. 75) in «1.1
 * Minimale Nettorendite» und «1.2 Durchschnittliche Nettorendite»; dieser Teil
 * des Namens fehlte in der Anzeige, und ein Antrag auf die eine traf die
 * andere.
 *
 * vitest läuft im Projekt-Root.
 */
const MINIMAL = {
  zielNummer: 1,
  zielTitel: '1 Wirtschaftlichkeit',
  unterTitel: 'Minimale Nettorendite',
  messgroesse: 'Wohnliegenschaften (in %)',
  soll: '1.00',
}
const DURCHSCHNITT = {
  zielNummer: 1,
  zielTitel: '1 Wirtschaftlichkeit',
  unterTitel: 'Durchschnittliche Nettorendite',
  messgroesse: 'Wohnliegenschaften (in %)',
  soll: '2.31',
}

function leeresFormular() {
  return {
    betrag: '', prozent: '', mehrausgabe: false,
    herkunft: 'eigene', antragsteller: 'U', haltung: 'einreichen',
    unterstuetzer: [], begruendung: '', zielAenderungen: [], aufteilung: [],
  }
}

describe('Unterabschnitt einer Zielvorgabe', () => {
  it('steht im Namen, damit zwei Vorgaben nicht gleich heissen', () => {
    expect(zielName(MINIMAL)).toBe('1 Wirtschaftlichkeit — Minimale Nettorendite — Wohnliegenschaften (in %)')
    expect(zielName(DURCHSCHNITT)).not.toBe(zielName(MINIMAL))
  })

  it('trennt die beiden Vorgaben im Formular', () => {
    const w = shallowMount(BudgetAntragForm, {
      props: { form: leeresFormular(), zielvorgaben: [MINIMAL, DURCHSCHNITT] },
    })
    w.vm.zielWertGesetzt(DURCHSCHNITT, '4')
    expect(w.vm.zielWert(DURCHSCHNITT), 'der gesetzte Wert fehlt').toBe('4')
    expect(w.vm.zielWert(MINIMAL), 'der Wert landete auf der anderen Vorgabe').toBe('')
    expect(w.vm.form.zielAenderungen).toEqual([{
      zielNummer: 1,
      unterTitel: 'Durchschnittliche Nettorendite',
      messgroesse: 'Wohnliegenschaften (in %)',
      neuerWert: '4',
    }])
  })

  it('zeigt beide Vorgaben mit ihrem eigenen Namen an', () => {
    const w = shallowMount(BudgetAntragForm, {
      props: { form: leeresFormular(), zielvorgaben: [MINIMAL, DURCHSCHNITT] },
    })
    const namen = w.findAll('.pw-antrag-ziel-label').map(e => e.text())
    expect(namen).toHaveLength(2)
    expect(new Set(namen).size, 'beide Zeilen heissen gleich').toBe(2)
  })

  it('lässt einen Antrag ohne Unterabschnitt auf die erste Vorgabe zeigen', () => {
    // Ein Antrag, der eingelesen wurde, bevor das Buch den Unterabschnitt
    // mitführte, trägt ihn leer — er bleibt der Vorgabe seines Namens
    // zugeordnet, statt seinen heutigen Soll zu verlieren.
    const alt = { zielNummer: 1, messgroesse: 'Wohnliegenschaften (in %)', neuerWert: '4' }
    expect(gleicheZielvorgabe(alt, MINIMAL)).toBe(true)
    expect(gleicheZielvorgabe(alt, DURCHSCHNITT)).toBe(true)
    const neu = { ...alt, unterTitel: 'Durchschnittliche Nettorendite' }
    expect(gleicheZielvorgabe(neu, MINIMAL)).toBe(false)
    expect(gleicheZielvorgabe(neu, DURCHSCHNITT)).toBe(true)
  })
})
