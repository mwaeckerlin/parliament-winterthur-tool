import { describe, it, expect, vi } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import Kommissionsliste from '../components/Kommissionsliste.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

function mountComponent(extraData = {}) {
  return shallowMount(Kommissionsliste, {
    data() {
      return {
        geschaefte: [],
        kommissionen: [],
        mitglieder: [],
        ausgewaehlteGeschaeftId: 99,
        laden: false,
        ...extraData,
      }
    },
    global: {
      stubs: {
        GeschaeftDetail: true,
        NcTextField: true,
        NcCheckboxRadioSwitch: true,
        NcLoadingIcon: true,
        NcEmptyContent: true,
        NcActions: true,
        NcActionButton: true,
        NcActionCaption: true,
        NcButton: true,
      },
    },
  })
}

describe('ladeGeschaefte', () => {
  it('lädt alle Geschäfte seitenweise, nicht nur die erste Seite', async () => {
    // Die Ansicht filtert die pendenten Geschäfte einer Kommission aus der
    // geladenen Liste. Bis zum Fix holte sie höchstens 1000 Stück — bei einem
    // grösseren Bestand fehlten pendente Geschäfte, ohne dass es jemand sah
    // (e2e-Befund 2026-08-29: 1000 geladen, das gesuchte war nicht dabei).
    const seite1 = Array.from({ length: 1000 }, (_, i) => ({ id: i + 1, status: 'Pendent: Stadtrat' }))
    const seite2 = [{ id: 1001, status: 'Pendent: Gemischte Kommission' }]
    axios.get.mockReset().mockImplementation((url, cfg) => {
      if (!String(url).includes('/geschaefte')) { return Promise.resolve({ data: [] }) }
      return Promise.resolve({ data: (cfg?.params?.offset || 0) === 0 ? seite1 : seite2 })
    })

    const wrapper = mountComponent()
    await wrapper.vm.ladeGeschaefte()

    expect(wrapper.vm.geschaefte).toHaveLength(1001)
    expect(wrapper.vm.geschaefte.some(g => String(g.status).includes('Gemischte'))).toBe(true)
  })

  it('hört auf zu blättern, sobald eine Seite nicht mehr voll ist', async () => {
    axios.get.mockReset().mockImplementation((url) => Promise.resolve({
      data: String(url).includes('/geschaefte') ? [{ id: 1, status: 'Pendent: Aufsichtskommission' }] : [],
    }))

    const wrapper = mountComponent()
    axios.get.mockClear() // die Aufrufe beim Aufbau der Ansicht zählen nicht mit
    await wrapper.vm.ladeGeschaefte()

    const seiten = axios.get.mock.calls.filter(c => String(c[0]).includes('/geschaefte'))
    expect(seiten).toHaveLength(1)
    expect(wrapper.vm.geschaefte).toHaveLength(1)
  })
})

describe('geschaefteFuer', () => {
  const ak = { id: 1, name: 'Aufsichtskommission', aktiv: true, mitglieder: '[]' }

  it('zeigt ein eigenes Geschäft, das der Kommission zugewiesen wurde', () => {
    // Der Weg aus der Fraktion: ein eigenes Geschäft anlegen und es im Feld
    // «Kommission» der AK zuweisen. Sein Status ist «Pendent» und nennt keine
    // Kommission — gesucht wurde bisher aber nur im Status, und deshalb erschien
    // das Geschäft in der Lasche «Kommissionen» nie bei seiner Kommission.
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 7, titel: 'Antrag der Fraktion', status: 'Pendent', kommission: 'Aufsichtskommission' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([7])
  })

  it('zeigt weiterhin die Geschäfte, deren Status die Kommission nennt', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 8, titel: 'Weisung', status: 'Bei Aufsichtskommission pendent' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([8])
  })

  it('zeigt ein Geschäft, das beiden Regeln entspricht, genau einmal', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{
        id: 9,
        titel: 'Beides',
        status: 'Bei Aufsichtskommission pendent',
        kommission: 'Aufsichtskommission',
      }],
    })

    expect(wrapper.vm.geschaefteFuer(ak).map(g => g.id)).toEqual([9])
  })

  it('zeigt kein Geschäft, das einer anderen Kommission zugewiesen ist', () => {
    const wrapper = mountComponent({
      kommissionen: [ak],
      geschaefte: [{ id: 10, titel: 'Fremd', status: 'Pendent', kommission: 'Sachkommission Bau und Betriebe' }],
    })

    expect(wrapper.vm.geschaefteFuer(ak)).toEqual([])
  })
})

describe('nachSpeichern', () => {
  it('schliesst das Popup NICHT nach dem Speichern', async () => {
    const wrapper = mountComponent()
    wrapper.vm.ladeGeschaefte = vi.fn(() => Promise.resolve())

    await wrapper.vm.nachSpeichern()

    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBe(99)
  })

  it('ruft ladeGeschaefte auf', async () => {
    const wrapper = mountComponent()
    const ladeGeschaefte = vi.fn(() => Promise.resolve())
    wrapper.vm.ladeGeschaefte = ladeGeschaefte

    await wrapper.vm.nachSpeichern()

    expect(ladeGeschaefte).toHaveBeenCalledOnce()
  })

  it('emittiert aktualisiert', async () => {
    const wrapper = mountComponent()
    wrapper.vm.ladeGeschaefte = vi.fn(() => Promise.resolve())

    await wrapper.vm.nachSpeichern()

    expect(wrapper.emitted('aktualisiert')).toBeTruthy()
  })
})

describe('schliesseDetail', () => {
  it('setzt ausgewaehlteGeschaeftId auf null', () => {
    const wrapper = mountComponent()
    wrapper.vm.schliesseDetail()
    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBeNull()
  })
})

// F118: Die pendenten Geschäfte sind das, wofür die Lasche geöffnet wird — sie
// stehen ohne Aufklappen da. Die Mitglieder füllen die Karte und stehen
// eingeklappt, mit einem Schalter, der sie zeigt.
describe('Kommissionskarte: Geschäfte offen, Mitglieder eingeklappt (F118)', () => {
  const ak = {
    id: 1,
    name: 'Aufsichtskommission',
    aktiv: true,
    mitglieder: '[]',
    mitgliederArray: [
      { label: 'Anna Beispiel', funktion: 'Präsidentin', partei: 'SVP', fraktion: 'SVP', email: 'anna@example.ch', aktiv: true },
      { label: 'Beat Muster', funktion: 'Mitglied', partei: 'SP', fraktion: 'SP', email: 'beat@example.ch', aktiv: true },
    ],
  }
  const geschaefte = [
    { id: 7, nummer: '2026.88', titel: 'Open-Source für die Stadt', status: 'Bei Aufsichtskommission pendent' },
  ]

  // Beim Einhängen lädt die Komponente Kommissionen und Geschäfte nach; die
  // leere Antwort des Mocks würde die feste Liste überschreiben. Darum wird sie
  // gesetzt, nachdem dieses Laden durch ist.
  async function karte() {
    const w = mountComponent()
    await flushPromises()
    await w.setData({ kommissionen: [ak], geschaefte, laden: false })
    return w
  }

  it('zeigt die pendenten Geschäfte ohne jedes Zutun', async () => {
    const w = await karte()
    expect(w.text()).toContain('Pendente Geschäfte (1)')
    expect(w.text()).toContain('2026.88')
    expect(w.text()).toContain('Open-Source für die Stadt')
  })

  it('zeigt die Mitglieder anfangs nicht', async () => {
    const w = await karte()
    expect(w.findAll('.pw-kommission-mitglied-karte')).toHaveLength(0)
    expect(w.text()).not.toContain('Anna Beispiel')
  })

  it('zeigt die Mitglieder, sobald man sie aufklappt', async () => {
    const w = await karte()
    await w.find('.pw-kommission-mitglieder-kopf').trigger('click')
    expect(w.findAll('.pw-kommission-mitglied-karte')).toHaveLength(2)
    expect(w.text()).toContain('Anna Beispiel')
    expect(w.text()).toContain('Beat Muster')
  })

  it('klappt die Mitglieder mit einem zweiten Klick wieder ein', async () => {
    const w = await karte()
    await w.find('.pw-kommission-mitglieder-kopf').trigger('click')
    await w.find('.pw-kommission-mitglieder-kopf').trigger('click')
    expect(w.findAll('.pw-kommission-mitglied-karte')).toHaveLength(0)
  })

  it('nennt die Zahl der Mitglieder auch im eingeklappten Zustand', async () => {
    const w = await karte()
    expect(w.text()).toContain('Mitglieder (2)')
  })

  it('klappt die Mitglieder auf, wenn die Suche einen von ihnen trifft', async () => {
    const w = await karte()
    await w.setData({ suche: 'Beat' })
    expect(w.text()).toContain('Beat Muster')
  })
})
