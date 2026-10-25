import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

/**
 * Derselbe Detail-Knopf auf jedem Tab (F133).
 *
 * Marc am 24.10.2026: «Auch beim Tab Personalbestand sollte derselbe Detail
 * button sein.» Eine Karte führt auf jedem Tab dieselbe Aktionszeile: «+ Antrag»
 * als Hauptaktion und «Details» ins Vollbild der Produktegruppe.
 *
 * vitest läuft im Projekt-Root.
 */
const LISTE = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')

/** Die Abschnitte der drei Tabs, an ihrem Panel getrennt. */
function tab(name) {
  const anfang = LISTE.indexOf(`aktiverTab === '${name}'`)
  expect(anfang, `der Tab ${name} fehlt`).toBeGreaterThan(-1)
  const ende = LISTE.indexOf('aktiverTab ===', anfang + 20)
  return LISTE.slice(anfang, ende === -1 ? LISTE.length : ende)
}

describe('Detail-Knopf der Karten', () => {
  it('steht bei den Globalbudgets', () => {
    expect(tab('globalbudget')).toMatch(/pw-pg-detail-knopf[\s\S]{0,120}pgDetailOeffnen\(g\.code\)/)
  })

  it('steht auch beim Personalbestand, mit derselben Wirkung', () => {
    const panel = tab('personal')
    expect(panel, 'dem Personalbestand fehlt der Detail-Knopf').toMatch(/pw-pg-detail-knopf/)
    expect(panel, 'der Knopf öffnet nicht das Detail der Produktegruppe').toMatch(/pgDetailOeffnen\(g\.code\)/)
  })

  it('steht in derselben Aktionszeile wie «+ Antrag»', () => {
    const panel = tab('personal')
    const zeile = panel.slice(panel.indexOf('pw-budget-card-aktionen'))
    expect(zeile.slice(0, 500), 'die beiden Knöpfe stehen nicht beieinander').toMatch(/\+ Antrag[\s\S]{0,300}pw-pg-detail-knopf/)
  })

  it('nennt wie überall die Zahl der Zielvorgaben', () => {
    const panel = tab('personal')
    expect(panel).toMatch(/Details<span v-if="g\.zielvorgaben[\s\S]{0,80}Zielvorgaben/)
  })

  it('fehlt bei der rechnerischen Position, die keine Details hat', () => {
    const panel = tab('personal')
    const zeile = panel.slice(panel.indexOf('pw-budget-card-aktionen') - 60)
    expect(zeile.slice(0, 200)).toMatch(/v-if="!g\.kuenstlich"/)
  })
})
