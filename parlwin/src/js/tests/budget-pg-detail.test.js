import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetPgDetail from '../components/BudgetPgDetail.vue'

function gruppe() {
  return {
    code: '121',
    name: 'Personalamt',
    globalkredit: { soll: 4866124, sollVorjahr: 4395510 },
    zielvorgaben: [
      { zielNummer: 2, zielTitel: '2 Kundenorientierung zentrales Personalmanagement', messgroesse: 'Prozentsatz der zufrieden Antwortenden', werte: ['90', '85', '85', '85', '85', '85'], soll: '85' },
      { zielNummer: 3, zielTitel: '3 Kundenorientierung Personalentwicklung', messgroesse: 'Anzahl Kurstage', werte: ['1339', '1000', '1000', '1000', '1000', '1000'], soll: '1000' },
      { zielNummer: 3, zielTitel: '3 Kundenorientierung Personalentwicklung', messgroesse: 'Durchführungsquote', werte: ['87', '80', '80', '80', '80', '80'], soll: '80' },
    ],
    produkte: [
      { nummer: 1, name: 'Personalpolitik / Personalrecht', nettokosten: { soll: 1135916 }, kostentabelle: [
        { label: 'Kosten', werte: [1442744, 1511500, 1540766] },
        { label: 'Erlös', werte: [385014, 366049, 404850] },
        { label: 'Nettokosten', werte: [1057730, 1145451, 1135916] },
        { label: 'Kostendeckungsgrad in %', werte: [27, 24, 26] },
      ], leistungen: ['Erarbeitung der Grundsätze', 'Bearbeitung von Geschäften'] },
      { nummer: 2, name: 'Zentrales Personalmanagement', nettokosten: { soll: 1318580 }, kostentabelle: [], leistungen: [] },
    ],
    auftrag: 'Das Personalamt bearbeitet die personalrechtlichen Fragen.',
    begruendungFap: 'Schwerpunkt WIN HR.\n- Personalbefragung\n- Digitalisierung',
    erlaeuterungStellen: '',
    begruendungAbweichung: '',
    massnahmen: '',
  }
}

// F109/F110: das Vollbild-Detail zeigt die Zielvorgaben (Soll aktuell hervorgehoben),
// die Produkte als Karten und die Erläuterungen mit erhaltener Formatierung.
describe('BudgetPgDetail', () => {
  it('zeigt die Zielvorgaben je Ziel mit Messgrössen und hebt «Soll aktuell» hervor', () => {
    const w = mount(BudgetPgDetail, { props: { gruppe: gruppe(), jahr: 2026 } })
    const html = w.html()
    expect(html).toContain('Parlamentarische Zielvorgaben')
    expect(html).toContain('2 Kundenorientierung zentrales Personalmanagement')
    expect(html).toContain('Anzahl Kurstage')
    // Zwei Ziele (Ziel 3 fasst seine zwei Messgrössen in einer Tabelle).
    expect(w.findAll('.pw-zielvorgabe').length).toBe(2)
    // Die dritte Wertspalte (Soll aktuell) trägt die Hervorhebung.
    const sollZellen = w.findAll('td.pw-zv-soll')
    expect(sollZellen.some(z => z.text() === '85')).toBe(true)
    // Spaltentitel relativ zum Budgetjahr.
    expect(html).toContain('Soll 2026')
    expect(html).toContain('Ist 2024')
  })

  it('zeigt die Produkte als Karten mit Nettokosten und Leistungen', () => {
    const w = mount(BudgetPgDetail, { props: { gruppe: gruppe(), jahr: 2026 } })
    const karten = w.findAll('.pw-produkt-karte')
    expect(karten.length).toBe(2)
    expect(karten[0].text()).toContain('Personalpolitik / Personalrecht')
    // Leistungen als Aufzählung im Produkt-Card (F110).
    const leistungen = karten[0].findAll('.pw-produkt-leistungen li')
    expect(leistungen.length).toBe(2)
    expect(leistungen[0].text()).toContain('Erarbeitung der Grundsätze')
    // Ein Produkt ohne Leistungen zeigt keine leere Liste.
    expect(karten[1].find('.pw-produkt-leistungen').exists()).toBe(false)
    // Kostentabelle (Budgetzahlen) je Produkt: Kosten, Erlös, Nettokosten,
    // Kostendeckungsgrad mit «Soll aktuell» hervorgehoben.
    const tabelle = karten[0].find('.pw-produkt-kosten')
    expect(tabelle.exists()).toBe(true)
    expect(tabelle.text()).toContain('Kosten')
    expect(tabelle.text()).toContain('Kostendeckungsgrad in %')
    const sollZellen = karten[0].findAll('td.pw-kosten-soll')
    expect(sollZellen.some(z => z.text() === '1’135’916')).toBe(true)
    expect(tabelle.findAll('tbody tr').length).toBe(4)
    // Ein Produkt ohne Kostentabelle zeigt keine leere Tabelle.
    expect(karten[1].find('.pw-produkt-kosten').exists()).toBe(false)
  })

  it('rendert Erläuterungen mit Absätzen und Aufzählungen', () => {
    const w = mount(BudgetPgDetail, { props: { gruppe: gruppe(), jahr: 2026 } })
    expect(w.html()).toContain('Das Personalamt bearbeitet')
    // Die Aufzählung im FAP-Text wird als Liste gerendert.
    const listen = w.findAll('.pw-erlaeuterung-liste')
    expect(listen.length).toBeGreaterThanOrEqual(1)
    expect(listen[0].text()).toContain('Personalbefragung')
    expect(listen[0].findAll('li').length).toBe(2)
  })
})
