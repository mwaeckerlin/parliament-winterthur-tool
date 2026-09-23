import { describe, it, expect } from 'vitest'
import { budgetHierarchie } from '../budget-hierarchie'

// F111: Die grafische Übersicht baut aus den Produktegruppen einen Baum
// Einnahmen/Ausgaben → Departement → Produktegruppe → Produkt. Die Beträge sind
// die Grundlage der Kreisflächen, darum wird hier genau geprüft, welcher Betrag
// auf welcher Ebene landet.
const gruppen = [
  {
    code: '121', name: 'Personalamt', departement: 'Präsidiales', kuenstlich: false,
    aufwand: { soll: 6000000 }, ertrag: { soll: 1000000 },
    produkte: [
      {
        nummer: '1', name: 'Personaldienstleistungen',
        kostentabelle: [
          { label: 'Kosten', werte: [3800000, 3900000, 4000000] },
          { label: 'Erlös', werte: [500000, 550000, 600000] },
          { label: 'Nettokosten', werte: [3300000, 3350000, 3400000] },
        ],
      },
      {
        nummer: '2', name: 'Lohnadministration',
        kostentabelle: [
          { label: 'Kosten', werte: [1900000, 1950000, 2000000] },
          { label: 'Erlös', werte: [350000, 380000, 400000] },
        ],
      },
    ],
  },
  {
    code: '160', name: 'Bibliotheken', departement: 'Präsidiales', kuenstlich: false,
    aufwand: { soll: 8000000 }, ertrag: { soll: 0 },
    produkte: [],
  },
  {
    code: '221', name: 'Finanzamt', departement: 'Finanzen', kuenstlich: false,
    aufwand: { soll: 2000000 }, ertrag: { soll: 500000 },
    produkte: [],
  },
]

const finde = (knoten, name) => (knoten.children || []).find(k => k.name === name)

describe('budgetHierarchie (F111)', () => {
  it('stellt Einnahmen und Ausgaben als die beiden obersten Kreise', () => {
    const baum = budgetHierarchie(gruppen)
    expect((baum.children || []).map(k => k.name)).toEqual(['Einnahmen', 'Ausgaben'])
  })

  it('gliedert jede Seite nach Departement, Produktegruppe und Produkt', () => {
    const ausgaben = finde(budgetHierarchie(gruppen), 'Ausgaben')
    expect((ausgaben.children || []).map(k => k.name)).toEqual(['Präsidiales', 'Finanzen'])
    const praesidiales = finde(ausgaben, 'Präsidiales')
    expect((praesidiales.children || []).map(k => k.name)).toEqual(['Personalamt', 'Bibliotheken'])
    const personalamt = finde(praesidiales, 'Personalamt')
    expect((personalamt.children || []).map(k => k.name))
      .toEqual(['Personaldienstleistungen', 'Lohnadministration'])
  })

  it('nimmt je Produkt die Kosten für die Ausgaben und den Erlös für die Einnahmen', () => {
    const baum = budgetHierarchie(gruppen)
    const ausgabeProdukt = finde(finde(finde(finde(baum, 'Ausgaben'), 'Präsidiales'), 'Personalamt'), 'Personaldienstleistungen')
    const einnahmeProdukt = finde(finde(finde(finde(baum, 'Einnahmen'), 'Präsidiales'), 'Personalamt'), 'Personaldienstleistungen')
    // «Soll aktuell» ist der dritte Wert der Kostentabelle.
    expect(ausgabeProdukt.betrag).toBe(4000000)
    expect(einnahmeProdukt.betrag).toBe(600000)
  })

  it('lässt eine Produktegruppe ohne Produkte als unterste Ebene stehen', () => {
    const bibliotheken = finde(finde(finde(budgetHierarchie(gruppen), 'Ausgaben'), 'Präsidiales'), 'Bibliotheken')
    expect(bibliotheken.children).toBeUndefined()
    expect(bibliotheken.betrag).toBe(8000000)
  })

  it('lässt eine Produktegruppe ohne Betrag auf ihrer Seite weg', () => {
    // Bibliotheken haben keinen Ertrag — auf der Einnahmenseite gibt es sie nicht.
    const einnahmen = finde(budgetHierarchie(gruppen), 'Einnahmen')
    const praesidiales = finde(einnahmen, 'Präsidiales')
    expect((praesidiales.children || []).map(k => k.name)).toEqual(['Personalamt'])
  })

  it('trägt an jedem Kreis den Betrag seiner Ebene', () => {
    const baum = budgetHierarchie(gruppen)
    const ausgaben = finde(baum, 'Ausgaben')
    const einnahmen = finde(baum, 'Einnahmen')
    expect(ausgaben.betrag).toBe(16000000)
    expect(einnahmen.betrag).toBe(1500000)
    expect(finde(ausgaben, 'Präsidiales').betrag).toBe(14000000)
    expect(finde(finde(ausgaben, 'Präsidiales'), 'Personalamt').betrag).toBe(6000000)
  })

  it('kommt mit fehlenden Feldern und negativen Beträgen zurecht', () => {
    const baum = budgetHierarchie([
      { code: '900', name: 'Ohne alles', departement: 'X' },
      { code: '901', name: 'Negativ', departement: 'X', aufwand: { soll: -5000 }, ertrag: { soll: 3000 } },
    ])
    // Ohne positive Ausgaben gibt es keinen Ausgaben-Kreis — ein leerer Kreis
    // behauptete ein Budget, das es nicht gibt.
    expect(finde(baum, 'Ausgaben')).toBeUndefined()
    expect(finde(finde(baum, 'Einnahmen'), 'X').betrag).toBe(3000)
  })

  it('liefert für eine leere Budgetansicht einen leeren Baum', () => {
    const baum = budgetHierarchie([])
    expect(baum.children).toBeUndefined()
    expect(baum.betrag).toBe(0)
  })

  // Die Kreisfläche einer Produktegruppe muss ihrem Budget entsprechen, auch wenn
  // ihre Produkte im Buch nur einen Teil davon ausweisen. Sonst erschiene eine
  // Gruppe mit Produkten kleiner als eine gleich teure ohne Produkte. Darum trägt
  // jeder Kreis zwei Werte: «betrag» ist die Zahl aus dem Buch (Anzeige),
  // «flaeche» der auf die Gruppe normierte Anteil (Kreisgrösse).
  it('normiert die Produktflächen auf das Budget ihrer Produktegruppe', () => {
    const baum = budgetHierarchie([{
      code: '222', name: 'Informatikdienste', departement: 'Finanzen',
      aufwand: { soll: 10000000 }, ertrag: { soll: 0 },
      produkte: [
        { nummer: '1', name: 'Betrieb', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 3000000] }] },
        { nummer: '2', name: 'Projekte', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 1000000] }] },
      ],
    }])
    const pg = finde(finde(finde(baum, 'Ausgaben'), 'Finanzen'), 'Informatikdienste')
    const [betrieb, projekte] = pg.children
    // Angezeigt wird, was im Buch steht …
    expect(betrieb.betrag).toBe(3000000)
    expect(projekte.betrag).toBe(1000000)
    // … die Flächen summieren sich auf das Budget der Gruppe, im Verhältnis 3:1.
    expect(betrieb.flaeche + projekte.flaeche).toBe(10000000)
    expect(betrieb.flaeche / projekte.flaeche).toBe(3)
  })

  // Gemessen am Budget 2026: PG 712 «Öffentliche Beleuchtung» hat im Buch keine
  // Kosten-Zeile, wohl aber «Kostendeckungsgrad in %». Wer die Zeile mit
  // «beginnt mit kosten» sucht, nimmt den Deckungsgrad (30) als Kosten.
  it('verwechselt den Kostendeckungsgrad nicht mit den Kosten', () => {
    const baum = budgetHierarchie([{
      code: '712', name: 'Öffentliche Beleuchtung', departement: 'Technische Betriebe',
      aufwand: { soll: 5753755 }, ertrag: { soll: 1717385 },
      produkte: [{
        nummer: '1',
        name: 'Öffentliche Beleuchtung',
        kostentabelle: [
          { label: 'Erlös', werte: [1543293, 1554712, 1717385] },
          { label: 'Nettokosten', werte: [3069805, 4134949, 4036370] },
          { label: 'Kostendeckungsgrad in %', werte: [33, 27, 30] },
        ],
      }],
    }])
    const pg = finde(finde(finde(baum, 'Ausgaben'), 'Technische Betriebe'), 'Öffentliche Beleuchtung')
    // Ohne Kosten-Zeile hat das Produkt keinen Ausgabenbetrag — die Gruppe bleibt
    // die unterste Ebene, statt einen Kreis über 30 Franken zu zeigen.
    expect(pg.children).toBeUndefined()
    expect(pg.betrag).toBe(5753755)
  })

  // Die Normierung darf die Lücke nicht verstecken: wo die Produkte das Budget
  // ihrer Gruppe nicht erklären, wird der Deckungsanteil ausgewiesen. Gemessen am
  // Budget 2026 betrifft das u.a. PG 425 «Parkieren» (Produkte decken 42%).
  it('weist aus, welchen Anteil des Gruppenbudgets die Produkte erklären', () => {
    const baum = budgetHierarchie([{
      code: '425', name: 'Parkieren', departement: 'Bau',
      aufwand: { soll: 5519084 }, ertrag: { soll: 0 },
      produkte: [{ nummer: '1', name: 'Parkhäuser', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 2322060] }] }],
    }])
    const pg = finde(finde(finde(baum, 'Ausgaben'), 'Bau'), 'Parkieren')
    expect(Math.round(pg.produktDeckung)).toBe(42)
  })

  it('meldet volle Deckung, wo die Produkte das Budget erklären', () => {
    const personalamt = finde(finde(finde(budgetHierarchie(gruppen), 'Ausgaben'), 'Präsidiales'), 'Personalamt')
    expect(personalamt.produktDeckung).toBe(100)
  })

  it('nimmt für Kreise ohne Kinder die Fläche gleich dem Betrag', () => {
    const bibliotheken = finde(finde(finde(budgetHierarchie(gruppen), 'Ausgaben'), 'Präsidiales'), 'Bibliotheken')
    expect(bibliotheken.flaeche).toBe(bibliotheken.betrag)
  })
})
