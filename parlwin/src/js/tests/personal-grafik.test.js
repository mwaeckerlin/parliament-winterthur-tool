import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetGrafik from '../components/BudgetGrafik.vue'

/**
 * F123: Dieselbe Grafik zeigt auf Wunsch den Stellenplan statt der Franken.
 * Ein Kreis je Produktegruppe, seine Fläche sind die Stellen, und seine Farbe
 * sagt die Richtung: Aufbau rot, Abbau grün, unverändert grau.
 *
 * vitest läuft im Projekt-Root.
 */
const gruppen = [
  {
    code: '121', name: 'Personalamt', departement: 'Präsidiales',
    stellen: { ist: 19.5, sollVorjahr: 19.5, soll: 19.5 },
    aufwand: { soll: 6000000 }, ertrag: { soll: 1000000 },
    // Mit Produkten, damit der Fall «keine Produkte im Stellen-Modus» etwas
    // beweist: Im Franken-Modus zeichnet dieselbe Gruppe zwei innere Kreise.
    produkte: [
      { nummer: '1', name: 'Personaldienstleistungen', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 4000000] }] },
      { nummer: '2', name: 'Lohnadministration', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 2000000] }] },
    ],
  },
  {
    code: '160', name: 'Bibliotheken', departement: 'Präsidiales',
    stellen: { ist: 48, sollVorjahr: 48.55, soll: 49.55 },
    aufwand: { soll: 8000000 }, ertrag: { soll: 0 }, produkte: [],
  },
  {
    code: '420', name: 'Stadtpolizei', departement: 'Sicherheit',
    stellen: { ist: 266, sollVorjahr: 266.69, soll: 260.19 },
    aufwand: { soll: 37840000 }, ertrag: { soll: 0 }, produkte: [],
  },
]

const montiert = (masseinheit = 'stellen') =>
  mount(BudgetGrafik, { props: { produktegruppen: gruppen, jahr: 2027, masseinheit } })

const kreis = (w, name) => w.find(`circle[data-name="${name}"]`)

describe('BudgetGrafik im Stellen-Modus (F123)', () => {
  it('zeigt eine einzige Fläche «Stellen» statt Einnahmen und Ausgaben', () => {
    const w = montiert()
    const flaechen = w.findAll('.pw-grafik-seite')
    expect(flaechen).toHaveLength(1)
    expect(flaechen[0].attributes('data-seite')).toBe('Stellen')
  })

  it('nennt im Kopf die Summe der Stellen, nicht Franken', () => {
    const kopf = montiert().find('.pw-grafik-seite-kopf').text()
    expect(kopf).toContain('Stellen')
    // 19.5 + 49.55 + 260.19 = 329.24
    expect(kopf).toMatch(/329[.,]24/)
    expect(kopf, 'kein Frankenzeichen im Stellen-Modus').not.toContain('CHF')
  })

  it('zeichnet einen Kreis je Produktegruppe und keinen je Produkt', async () => {
    const w = montiert()
    await w.find('circle[data-name="Präsidiales"]').trigger('click')
    expect(kreis(w, 'Personalamt').exists()).toBe(true)
    expect(kreis(w, 'Bibliotheken').exists()).toBe(true)
    expect(w.find('circle[data-ebene="2"]').exists(), 'unter der Produktegruppe liegt nichts mehr').toBe(false)
  })

  it('färbt Aufbau rot, Abbau grün und Unverändertes grau', async () => {
    const w = montiert()
    await w.find('circle[data-name="Präsidiales"]').trigger('click')
    const gruppeVon = (name) => kreis(w, name).element.closest('.pw-bubble')
    expect(gruppeVon('Bibliotheken').getAttribute('data-richtung')).toBe('aufbau')
    expect(gruppeVon('Personalamt').getAttribute('data-richtung')).toBe('gleich')
    expect(gruppeVon('Bibliotheken').className.baseVal).toContain('pw-bubble-aufbau')
    expect(gruppeVon('Personalamt').className.baseVal).toContain('pw-bubble-gleich')
  })

  it('sagt im Hinweistext die Stellen, das Vorjahr und die Veränderung', async () => {
    const w = montiert()
    await w.find('circle[data-name="Sicherheit"]').trigger('click')
    const titel = kreis(w, 'Stadtpolizei').find('title').text()
    expect(titel).toContain('Stadtpolizei')
    expect(titel).toMatch(/260[.,]19/)
    expect(titel).toMatch(/266[.,]69/)
    expect(titel).toMatch(/−6[.,]5|-6[.,]5/)
  })

  it('nimmt einem unveränderten Kreis die Farbe', () => {
    // Rot und Grün tragen die Aussage; wo sich nichts bewegt hat, soll keine
    // Farbe etwas behaupten. Der Farbton ist dort gleichgültig, die Sättigung
    // nimmt ihn zurück. vitest läuft im Projekt-Root.
    const css = readFileSync('parlwin/src/js/components/BudgetGrafik.vue', 'utf8')
    const regel = css.slice(css.indexOf('.pw-bubble-gleich'))
    expect(css, 'keine Regel für einen unveränderten Kreis').toMatch(/\.pw-bubble-gleich/)
    expect(regel.slice(0, 300), 'der unveränderte Kreis behält seine Farbe')
      .toMatch(/--pw-bubble-saettigung:\s*0%/)
  })

  it('bleibt im Franken-Modus bei den beiden Seiten und zeigt dort die Produkte', async () => {
    const w = mount(BudgetGrafik, { props: { produktegruppen: gruppen, jahr: 2027, masseinheit: 'franken' } })
    expect(w.findAll('.pw-grafik-seite').map(f => f.attributes('data-seite')))
      .toEqual(['Einnahmen', 'Ausgaben'])
    const ausgaben = w.find('[data-seite="Ausgaben"]')
    await ausgaben.find('circle[data-name="Präsidiales"]').trigger('click')
    expect(ausgaben.find('circle[data-name="Lohnadministration"]').exists()).toBe(true)
  })
})
