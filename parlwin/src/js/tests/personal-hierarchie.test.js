import { describe, it, expect } from 'vitest'
import { personalHierarchie } from '../budget-hierarchie'

/**
 * F123: Der Stellenplan als Kreise — dieselbe Darstellung wie beim Geld, nur
 * zählt hier die Stelle statt des Frankens. Der Baum geht Stellen → Departement
 * → Produktegruppe und hört dort auf: Das Budgetbuch weist die Stellen je
 * Produktegruppe aus, nicht je Produkt.
 *
 * Die Farbe sagt die Richtung gegenüber dem Vorjahr: Aufbau rot, Abbau grün.
 *
 * vitest läuft im Projekt-Root.
 */
const gruppen = [
  {
    code: '121', name: 'Personalamt', departement: 'Präsidiales',
    stellen: { ist: 19.5, sollVorjahr: 19.5, soll: 19.5 },
    aufwand: { soll: 6000000 }, ertrag: { soll: 1000000 },
    produkte: [{ nummer: '1', name: 'Lohnadministration', kostentabelle: [{ label: 'Kosten', werte: [1, 2, 3] }] }],
  },
  {
    code: '160', name: 'Bibliotheken', departement: 'Präsidiales',
    stellen: { ist: 48.0, sollVorjahr: 48.55, soll: 49.55 },
    aufwand: { soll: 8000000 }, ertrag: { soll: 0 },
    produkte: [],
  },
  {
    code: '420', name: 'Stadtpolizei', departement: 'Sicherheit',
    stellen: { ist: 266.0, sollVorjahr: 266.69, soll: 260.19 },
    aufwand: { soll: 37840000 }, ertrag: { soll: 0 },
    produkte: [],
  },
  {
    code: '280', name: 'Steuern und Finanzausgleich', departement: 'Finanzen',
    stellen: { ist: 0, sollVorjahr: 0, soll: 0 },
    aufwand: { soll: 500000 }, ertrag: { soll: 0 },
    produkte: [],
  },
]

const finde = (knoten, name) => (knoten.children || []).find(k => k.name === name)

describe('personalHierarchie (F123)', () => {
  it('hat einen einzigen obersten Kreis «Stellen»', () => {
    const baum = personalHierarchie(gruppen)
    expect((baum.children || []).map(k => k.name)).toEqual(['Stellen'])
  })

  it('gliedert nach Departement und Produktegruppe und hört dort auf', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    expect((stellen.children || []).map(k => k.name)).toEqual(['Präsidiales', 'Sicherheit'])
    const praesidiales = finde(stellen, 'Präsidiales')
    expect((praesidiales.children || []).map(k => k.name)).toEqual(['Personalamt', 'Bibliotheken'])
    const personalamt = finde(praesidiales, 'Personalamt')
    expect(personalamt.children, 'eine Produktegruppe hat bei den Stellen keine Produkte').toBeUndefined()
  })

  it('nimmt als Fläche die Stellen des Jahres', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    const praesidiales = finde(stellen, 'Präsidiales')
    expect(finde(praesidiales, 'Bibliotheken').betrag).toBeCloseTo(49.55, 5)
    expect(praesidiales.betrag, 'das Departement summiert seine Gruppen').toBeCloseTo(19.5 + 49.55, 5)
    expect(stellen.betrag, 'der oberste Kreis summiert alle Departemente').toBeCloseTo(19.5 + 49.55 + 260.19, 5)
  })

  it('lässt eine Produktegruppe ohne Stellen weg', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    expect((stellen.children || []).map(k => k.name)).not.toContain('Finanzen')
  })

  it('nennt je Produktegruppe die Richtung gegenüber dem Vorjahr', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    const praesidiales = finde(stellen, 'Präsidiales')
    expect(finde(praesidiales, 'Bibliotheken').richtung, 'mehr Stellen als im Vorjahr').toBe('aufbau')
    expect(finde(praesidiales, 'Personalamt').richtung, 'gleich viele Stellen').toBe('gleich')
    const sicherheit = finde(stellen, 'Sicherheit')
    expect(finde(sicherheit, 'Stadtpolizei').richtung, 'weniger Stellen als im Vorjahr').toBe('abbau')
  })

  it('trägt je Produktegruppe den Vorjahreswert und die Veränderung', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    const stapo = finde(finde(stellen, 'Sicherheit'), 'Stadtpolizei')
    expect(stapo.vorjahr).toBeCloseTo(266.69, 5)
    expect(stapo.veraenderung).toBeCloseTo(-6.5, 5)
  })

  it('nennt die Richtung auch für Departement und Gesamtsumme', () => {
    const stellen = finde(personalHierarchie(gruppen), 'Stellen')
    expect(finde(stellen, 'Präsidiales').richtung, '+1 Stelle im Departement').toBe('aufbau')
    // 19.5 + 49.55 + 260.19 = 329.24 gegen 19.5 + 48.55 + 266.69 = 334.74
    expect(stellen.veraenderung).toBeCloseTo(-5.5, 5)
    expect(stellen.richtung).toBe('abbau')
  })

  it('verträgt eine leere Liste', () => {
    const baum = personalHierarchie([])
    expect(baum.name).toBe('Stellenplan')
    expect(baum.children).toBeUndefined()
  })
})
