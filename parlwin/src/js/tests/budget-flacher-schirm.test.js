import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Budgetliste from '../components/Budgetliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))

/**
 * Flacher Schirm: Tablet und Telefon quer.
 *
 * Marcs Bild vom 24.10.2026 zeigt das Budget auf einem Tablet quer: oben die
 * Adressleiste des Browsers, unten die Navigationsleiste des Geräts, dazwischen
 * der Kopf mit drei Zeilen, die Tab-Leiste und die Karte der Pauschalanträge —
 * vom scrollbaren Bereich blieben zwei Zeilen. Marc: «Im Tablet ist der
 * scrollbare Bereich zu klein. Nimm bei kleiner Höhe den grünen Rand unten
 * weg.»
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

const ANSICHT = {
  jahr: { jahr: 2027, steuerfuss: 125, steuerfussEffektiv: 125 },
  eigeneFraktion: 'SVP-Fraktion',
  produktegruppen: [{
    code: '240', name: 'Immobilien', departement: 'Finanzen',
    globalkredit: { soll: 1000, sollFraktion: 1000 },
    zielvorgaben: [], kostenzeilen: [], produkte: [],
  }],
  investitionen: [],
  antraege: [],
  pauschalantraege: [],
}

function medienAbfrage(flach) {
  const hoerer = []
  window.matchMedia = vi.fn().mockImplementation(() => ({
    matches: flach,
    addEventListener: (_, fn) => hoerer.push(fn),
    removeEventListener: () => {},
  }))
  return hoerer
}

describe('Flacher Schirm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockImplementation((url) => {
      if (String(url).includes('budget/ansicht')) { return Promise.resolve({ data: ANSICHT }) }
      return Promise.resolve({ data: [] })
    })
  })

  afterEach(() => {
    document.body.classList.remove('pw-kopf-weg')
  })

  it('nimmt den Rand von Nextcloud weg und lässt den Inhalt bis an den Bildrand laufen', () => {
    const stelle = CSS.indexOf('@media (max-height: 56rem)')
    expect(stelle, 'für eine kleine Bildhöhe gilt keine eigene Regel').toBeGreaterThan(-1)
    const block = CSS.slice(stelle, CSS.indexOf('\n}\n\n/* Rand des Papiers'))
    expect(block, 'der Rand von Nextcloud bleibt stehen').toMatch(/--body-container-margin:\s*0/)
    expect(block, 'unten bleibt eine Auffüllung').toMatch(/padding-block-end:\s*0/)
  })

  it('rückt die Abstände eine Stufe zusammen, aus derselben Quelle', () => {
    const block = CSS.slice(CSS.indexOf('@media (max-height: 56rem)'))
    expect(block.slice(0, 900)).toMatch(/--pw-gap-1:\s*var\(--pw-space-xs\)/)
    expect(block.slice(0, 900)).toMatch(/--pw-gap-2:\s*var\(--pw-space-md\)/)
  })

  it('zeigt im Budgetkopf nur die Differenz und blendet die anderen Zeilen aus', () => {
    const block = CSS.slice(CSS.indexOf('@media (max-height: 56rem)'))
    expect(block.slice(0, 1600)).toMatch(/\.pw-budget-summen:not\(\.pw-budget-summen-offen\)[\s\S]{0,200}display:\s*none/)
  })

  it('klappt Kopf und Pauschalanträge auf flachem Schirm zu', async () => {
    medienAbfrage(true)
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    expect(w.vm.summenOffen, 'der Kopf steht offen').toBe(false)
    expect(w.vm.pauschalOffen, 'die Pauschalanträge stehen offen').toBe(false)
  })

  it('lässt auf hohem Schirm beides offen', async () => {
    medienAbfrage(false)
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    expect(w.vm.summenOffen).toBe(true)
    expect(w.vm.pauschalOffen).toBe(true)
  })

  it('schiebt die Kopfleiste beim Blättern nach unten weg und holt sie zurück', async () => {
    medienAbfrage(true)
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    w.vm.scrollBeobachten({ target: { scrollTop: 300 } })
    expect(document.body.classList.contains('pw-kopf-weg'), 'die Kopfleiste bleibt beim Blättern nach unten stehen').toBe(true)
    w.vm.scrollBeobachten({ target: { scrollTop: 100 } })
    expect(document.body.classList.contains('pw-kopf-weg'), 'die Kopfleiste kommt beim Blättern nach oben nicht zurück').toBe(false)
  })

  it('rührt die Kopfleiste auf hohem Schirm nicht an', async () => {
    medienAbfrage(false)
    const w = mount(Budgetliste, { props: { mitglieder: [], fraktionen: [] }, global: { stubs: { Teleport: true } } })
    await flushPromises()
    w.vm.scrollBeobachten({ target: { scrollTop: 300 } })
    expect(document.body.classList.contains('pw-kopf-weg')).toBe(false)
  })
})
