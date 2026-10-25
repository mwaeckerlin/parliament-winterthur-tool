import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetInvDetail from '../components/BudgetInvDetail.vue'

/**
 * Zwei Zahlen, zwei Namen — und jeder Name überall derselbe.
 *
 * Befund eines Fraktionsmitglieds vom 19.10.2026 am Projekt «SH X
 * (Aussenwachten) Neubau»: «Gesamtkosten» stand auf der Übersichtskarte für
 * eine andere Zahl als im Detail. Auf der Karte waren es 28'333'420 (die
 * Summe der ausgewiesenen Jahre), im Detail 56'138'420 (die Summe der
 * bewilligten Kredite aus dem Anhang «Kontrolle der Investitionskredite»).
 * Beide Zahlen sind richtig; derselbe Name für beide ist es nicht.
 *
 * Seither heisst die Summe der Jahre «Summe der Jahre» und der bewilligte
 * Kredit «Bewilligter Kredit» — an beiden Orten.
 *
 * vitest läuft im Projekt-Root.
 */
const projekt = {
  id: 7,
  departement: 'Schule und Sport',
  cluster: 'Volksschule',
  projekt: '5011980 SH X (Aussenwachten) Neubau',
  bu: 465000,
  fap1: 1100000,
  fap2: 480000,
  fap3: 25738420,
  bereitsGetaetigt: 550000,
  // Das Feld trägt den bewilligten Kredit aus der Kreditkontrolle.
  gesamtkosten: 56138420,
  planungskosten: 0,
  konten: [
    { konto: '500000', name: 'Grundstücke', betrag: 0, kredit: 24738420, bewilligt: '' },
  ],
}

describe('Benennung der Investitionszahlen', () => {
  it('nennt im Detail die Summe der Jahre und den bewilligten Kredit je bei ihrem Namen', () => {
    const w = mount(BudgetInvDetail, { props: { projekt, jahr: 2027 } })
    const kopfzeilen = w.findAll('th').map(t => t.text())
    expect(kopfzeilen, 'die Summe der Jahre heisst noch «Gesamtkosten»').not.toContain('Gesamtkosten')
    expect(kopfzeilen).toContain('Summe der Jahre')
    expect(w.text(), 'der bewilligte Kredit fehlt im Detail').toContain('Bewilligter Kredit')
    // 550'000 + 465'000 + 1'100'000 + 480'000 + 25'738'420 = 28'333'420
    expect(w.text()).toMatch(/28['’]333['’]420/)
    expect(w.text(), 'der bewilligte Kredit steht nicht da').toMatch(/56['’]138['’]420/)
  })

  it('zeigt unter den Konten deren Summe, damit der bewilligte Kredit nachvollziehbar ist', () => {
    // Das Fraktionsmitglied schrieb am 19.10.2026: «Es war sehr schwer mit dem
    // Tool den Investitionskrediten zu folgen … irgendwie fehlt der Überblick.
    // Ich brauche das Buch, resp. Pdf.» Woher der bewilligte Kredit kommt, war
    // nur im PDF zu sehen: Die Tabelle führte die Konten einzeln auf, ohne die
    // Summe, mit der der Kredit darüber zu vergleichen ist.
    const w = mount(BudgetInvDetail, {
      props: {
        projekt: {
          ...projekt,
          konten: [
            { konto: '500000', name: 'Grundstücke', betrag: 100000, kredit: 24738420, bewilligt: '14.03.2024' },
            { konto: '503000', name: 'Hochbauten', betrag: 365000, kredit: 31400000, bewilligt: '' },
          ],
        },
        jahr: 2027,
      },
    })
    // Gezielt die ZWEITE Tabelle: Die Summe gehört unter die Konten, nicht
    // unter die Jahresreihe. In der Jahresreihe stand sie einmal und zeigte
    // dort «Total 0 0» in den Spalten der Planjahre (Bild vom 20.10.2026).
    const tabellen = w.findAll('table')
    expect(tabellen.length, 'die Kontotabelle fehlt').toBe(2)
    expect(tabellen[0].find('tfoot').exists(), 'die Jahresreihe trägt eine Summenzeile').toBe(false)
    const summenzeile = tabellen[1].find('tfoot tr')
    expect(summenzeile.exists(), 'die Kontotabelle führt keine Summe').toBe(true)
    // 24'738'420 + 31'400'000 = 56'138'420, der bewilligte Kredit des Projekts.
    expect(summenzeile.text()).toMatch(/56['’]138['’]420/)
    // 100'000 + 365'000 = 465'000, der Betrag des Budgetjahres.
    expect(summenzeile.text()).toMatch(/465['’]000/)
  })

  it('rechnet auf der Karte die Summe der Jahre über alle Jahre', () => {
    // Die Karte zeigte «bereits getätigt + Budgetjahr» unter dem Namen
    // «Gesamtkosten» — die Planjahre fehlten darin, obwohl sie gleich daneben
    // als «künftig» standen.
    const quelle = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')
    const block = quelle.slice(quelle.indexOf('summeDerJahre(i)'))
    expect(quelle, 'die Karte kennt keine Summe der Jahre').toContain('summeDerJahre')
    expect(block.slice(0, 300), 'die Planjahre fehlen in der Summe').toMatch(/fap1/)
    expect(block.slice(0, 300)).toMatch(/fap3/)
    expect(quelle, 'auf der Karte steht weiterhin «Gesamtkosten»')
      .not.toMatch(/<span>Gesamtkosten<\/span>/)
  })
})
