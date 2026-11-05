import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetGrafik from '../components/BudgetGrafik.vue'

/**
 * F129: Die Seite im Budgetbuch steht an jeder Produktegruppe, in jeder
 * Ansicht. In der Grafik ist der Hinweistext des Kreises die einzige Stelle,
 * an der eine Produktegruppe ihre Angaben trägt. Er nennt Name, Betrag, Anteil
 * und die Seite, damit sich die Position auch im gedruckten Buch finden lässt.
 *
 * vitest läuft im Projekt-Root.
 */
const gruppen = [
  {
    code: '240', name: 'Immobilien', departement: 'Finanzen', buchseite: 74,
    stellen: { ist: 34.4, sollVorjahr: 34.4, soll: 34.9 },
    aufwand: { soll: 8000000 }, ertrag: { soll: 0 }, produkte: [],
  },
  {
    code: '121', name: 'Personalamt', departement: 'Präsidiales',
    stellen: { ist: 19.5, sollVorjahr: 19.5, soll: 19.5 },
    aufwand: { soll: 6000000 }, ertrag: { soll: 0 }, produkte: [],
  },
]

const montiert = (masseinheit = 'franken') =>
  mount(BudgetGrafik, { props: { produktegruppen: gruppen, jahr: 2027, masseinheit } })

async function hinweis(w, departement, gruppe) {
  await w.find(`circle[data-name="${departement}"]`).trigger('click')
  return w.find(`circle[data-name="${gruppe}"]`).find('title').text()
}

describe('Seite im Budgetbuch in der Grafik', () => {
  it('nennt die Seite im Hinweistext der Produktegruppe', async () => {
    const text = await hinweis(montiert(), 'Finanzen', 'Immobilien')
    expect(text, 'die Seite im Budgetbuch fehlt am Kreis').toContain('Buch S. 74')
  })

  it('nennt sie auch im Stellen-Modus', async () => {
    const text = await hinweis(montiert('stellen'), 'Finanzen', 'Immobilien')
    expect(text, 'im Stellen-Modus fehlt die Seite').toContain('Buch S. 74')
  })

  it('lässt sie weg, wo das Buch keine Seite liefert', async () => {
    const text = await hinweis(montiert(), 'Präsidiales', 'Personalamt')
    expect(text, 'ohne Seitenangabe steht eine leere Angabe da').not.toContain('Buch S.')
  })
})
