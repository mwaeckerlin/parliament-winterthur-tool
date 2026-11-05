import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Kommissionsliste from '../components/Kommissionsliste.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

/**
 * F141: An jeder Kommission steht ein Knopf «Neues Geschäft». Er öffnet
 * dieselbe Maske wie «+ Eigenes Geschäft» in der Ansicht «Geschäfte», mit
 * derselben Wirkung — nur ist die Kommission schon gewählt.
 *
 * Vorher führte der einzige Weg über die Ansicht «Geschäfte», dort «+ Eigenes
 * Geschäft» und die Auswahl der Kommission von Hand.
 *
 * vitest läuft im Projekt-Root.
 */
const KOMMISSIONEN = [
  { id: 1, name: 'Umwelt- und Betriebskommission', kuerzel: 'UBK', aktiv: true, mitglieder: [] },
  { id: 2, name: 'Sachkommission Bau und Betriebe', kuerzel: 'SBB', aktiv: true, mitglieder: [] },
]

async function liste() {
  axios.get.mockImplementation((url) => {
    const u = String(url)
    if (u.includes('/kommissionen')) { return Promise.resolve({ data: KOMMISSIONEN }) }
    return Promise.resolve({ data: [] })
  })
  const w = mount(Kommissionsliste, { props: { mitglieder: [] }, global: { stubs: { Teleport: true, GeschaeftDetail: true } } })
  await flushPromises()
  return w
}

describe('Neues Geschäft an der Kommission (F141)', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('zeigt an jeder Kommission einen Knopf «Neues Geschäft»', async () => {
    const w = await liste()
    const knoepfe = w.findAll('.pw-kommission-neues-geschaeft')
    expect(knoepfe.length, 'der Knopf fehlt an einer Kommission').toBe(KOMMISSIONEN.length)
    expect(knoepfe[0].text()).toContain('Neues Geschäft')
  })

  it('öffnet dieselbe Maske mit der Kommission als Vorauswahl', async () => {
    const w = await liste()
    await w.findAll('.pw-kommission-neues-geschaeft')[0].trigger('click')
    await flushPromises()

    const maske = w.findComponent({ name: 'GeschaeftDetail' })
    expect(maske.exists(), 'die Maske wurde nicht geöffnet').toBe(true)
    expect(maske.props('geschaeftId'), 'die Maske legt kein neues Geschäft an').toBe(0)
    expect(maske.props('vorauswahlKommission'), 'die Kommission ist nicht vorgewählt')
      .toBe('Umwelt- und Betriebskommission')
  })

  it('nimmt die Kommission, an der der Knopf steht', async () => {
    const w = await liste()
    await w.findAll('.pw-kommission-neues-geschaeft')[1].trigger('click')
    await flushPromises()
    expect(w.findComponent({ name: 'GeschaeftDetail' }).props('vorauswahlKommission'))
      .toBe('Sachkommission Bau und Betriebe')
  })

  it('schliesst die Maske wieder und vergisst die Vorauswahl', async () => {
    const w = await liste()
    await w.findAll('.pw-kommission-neues-geschaeft')[0].trigger('click')
    await flushPromises()
    w.vm.schliesseDetail()
    await flushPromises()
    expect(w.findComponent({ name: 'GeschaeftDetail' }).exists(), 'die Maske blieb offen').toBe(false)
    expect(w.vm.neueGeschaeftKommission).toBe('')
  })

  // Wie «+ Eigenes Geschäft» in «Geschäfte»: Nach dem Anlegen bleibt dieselbe
  // Maske offen und bearbeitet das neue Geschäft, damit sich Dokumente und
  // Notizen gleich anschliessen lassen.
  it('führt nach dem Anlegen in die Bearbeitung des neuen Geschäfts', async () => {
    const w = await liste()
    await w.findAll('.pw-kommission-neues-geschaeft')[0].trigger('click')
    await flushPromises()
    w.findComponent({ name: 'GeschaeftDetail' }).vm.$emit('erstellt', 42)
    await flushPromises()

    const maske = w.findComponent({ name: 'GeschaeftDetail' })
    expect(maske.exists(), 'die Maske schliesst nach dem Anlegen').toBe(true)
    expect(maske.props('geschaeftId'), 'die Maske bearbeitet nicht das neue Geschäft').toBe(42)
    expect(maske.props('vorauswahlKommission')).toBe('')
    expect(w.find('.pw-btn-schliessen').exists(), 'die Bearbeitung hat kein ✕').toBe(true)
  })

  it('zeigt den Knopf nur an aktiven Kommissionen, auch wenn die inaktiven sichtbar sind', async () => {
    axios.get.mockImplementation((url) => Promise.resolve({
      data: String(url).includes('/kommissionen')
        ? [...KOMMISSIONEN, { id: 3, name: 'Aufgelöste Spezialkommission', aktiv: false, mitglieder: [] }]
        : [],
    }))
    const w = mount(Kommissionsliste, { props: { mitglieder: [] }, global: { stubs: { Teleport: true, GeschaeftDetail: true } } })
    await flushPromises()
    await w.setData({ nurAktive: false })

    const karten = w.findAll('.pw-kommission-karte')
    expect(karten, 'die inaktive Kommission ist nicht zu sehen').toHaveLength(3)
    const inaktiv = karten.find((k) => k.text().includes('Aufgelöste Spezialkommission'))
    expect(inaktiv.classes()).toContain('ist-inaktiv')
    expect(inaktiv.find('.pw-kommission-neues-geschaeft').exists(), 'die inaktive Kommission bietet «Neues Geschäft» an').toBe(false)
    expect(karten.filter((k) => k.find('.pw-kommission-neues-geschaeft').exists())).toHaveLength(2)
  })
})
