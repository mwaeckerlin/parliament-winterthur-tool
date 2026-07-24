import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

// Feature: Die konfigurierten Kürzel (Suchtext → Kürzel) gelten nicht nur für
// Status, sondern auch für Partei-, Fraktions- und Kommissionsnamen — überall,
// wo diese angezeigt oder in Formularfeldern angeboten werden. Die gespeicherten
// Werte bleiben unverändert (nur die Anzeige kürzt).
describe('Kürzel gelten überall (Status, Parteien, Fraktionen, Kommissionen)', () => {
  const KUERZEL = [
    { suche: 'Sozialdemokratische Partei-Fraktion (SP)', kuerzel: 'SP-Fraktion' },
    { suche: 'Sozialdemokratische Partei', kuerzel: 'SP' },
    { suche: 'Kommission Bildung, Sport und Kultur', kuerzel: 'BSKK' },
    { suche: 'Beim Stadtrat pendent', kuerzel: 'Pendent: Stadtrat' },
  ]

  beforeEach(() => {
    window.PARLWIN_CONFIG = { statusKuerzel: KUERZEL }
    document.body.innerHTML = '<div id="pw-search-slot"></div><div id="pw-filter-slot"></div>'
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })
  afterEach(() => {
    delete window.PARLWIN_CONFIG
  })

  it('utils.kuerze ersetzt alle konfigurierten Suchtexte; ohne Treffer bleibt der Text', async () => {
    const { kuerze } = await import('../utils')
    expect(kuerze('Sozialdemokratische Partei', KUERZEL)).toBe('SP')
    expect(kuerze('Kommission Bildung, Sport und Kultur', KUERZEL)).toBe('BSKK')
    expect(kuerze('Grüne', KUERZEL)).toBe('Grüne')
    // Default-Liste kommt aus der App-Konfiguration.
    expect(kuerze('Sozialdemokratische Partei')).toBe('SP')
  })

  it('Mitgliederliste zeigt Partei, Fraktion und Kommission gekürzt', async () => {
    const { default: Mitgliederliste } = await import('../components/Mitgliederliste.vue')
    const wrapper = mount(Mitgliederliste, {
      props: {
        mitglieder: [{
          id: 1, externId: 'e1', vorname: 'Anna', name: 'Müller', aktiv: true,
          partei: 'Sozialdemokratische Partei',
          fraktion: 'Sozialdemokratische Partei-Fraktion (SP)',
        }],
        fraktionen: [],
        kommissionen: [{ id: 9, name: 'Kommission Bildung, Sport und Kultur', aktiv: true, mitglieder: JSON.stringify([{ externId: 'e1', funktion: 'Mitglied' }]) }],
      },
      global: { stubs: { NcTextField: true, NcSelect: true, NcCheckboxRadioSwitch: true, NcEmptyContent: true } },
    })
    const text = wrapper.text()
    expect(text).toContain('SP')
    expect(text).toContain('SP-Fraktion')
    expect(text).toContain('BSKK')
    expect(text).not.toContain('Sozialdemokratische Partei')
    expect(text).not.toContain('Kommission Bildung, Sport und Kultur')
  })

  it('Vorstoss-Herkunftsfraktion wird in Auswahl und Karte gekürzt angezeigt, gespeichert wird der volle Name', async () => {
    const { default: Vorstoesseliste } = await import('../components/Vorstoesseliste.vue')
    const wrapper = shallowMount(Vorstoesseliste, {
      props: {
        mitglieder: [],
        fraktionen: [{ id: 2, name: 'Sozialdemokratische Partei-Fraktion (SP)', aktiv: true }],
      },
    })
    const opt = wrapper.vm.fremdeFraktionsOptionen[0]
    expect(opt.label).toBe('SP-Fraktion')
    expect(opt.value).toBe('Sozialdemokratische Partei-Fraktion (SP)')
  })

  it('Kürzel-Verwaltung schlägt Status, aktuelle Fraktions- und Parteinamen vor', async () => {
    document.body.innerHTML += '<datalist id="pw-status-kuerzel-liste"></datalist>'
    axios.get.mockImplementation((url) => {
      if (String(url).includes('/fraktionen')) {
        return Promise.resolve({ data: [
          { name: 'Sozialdemokratische Partei-Fraktion (SP)', aktiv: true },
          { name: 'Alte Fraktion', aktiv: false },
        ] })
      }
      if (String(url).includes('/mitglieder')) {
        return Promise.resolve({ data: [{ partei: 'Sozialdemokratische Partei' }, { partei: 'Grüne' }] })
      }
      return Promise.resolve({ data: [{ status: 'Beim Stadtrat pendent' }] })
    })
    const { ladeVorschlagswerte } = await import('../admin')
    await ladeVorschlagswerte()
    const werte = [...document.querySelectorAll('#pw-status-kuerzel-liste option')].map(o => o.value)
    expect(werte).toContain('Beim Stadtrat pendent')
    expect(werte).toContain('Sozialdemokratische Partei-Fraktion (SP)')
    expect(werte).toContain('Sozialdemokratische Partei')
    expect(werte).toContain('Grüne')
    expect(werte).not.toContain('Alte Fraktion')
  })

  it('Geschäfts-Detail zeigt den Status gekürzt', async () => {
    axios.get.mockResolvedValue({ data: { titel: 'T', status: 'Beim Stadtrat pendent', aktionen: [], zustaendigkeiten: [] } })
    const { default: GeschaeftDetail } = await import('../components/GeschaeftDetail.vue')
    const wrapper = shallowMount(GeschaeftDetail, {
      props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
      global: { stubs: { NcSelect: true, PwMultiSelect: true, PwWysiwyg: true, GeschaeftDokumente: true } },
    })
    await Promise.resolve()
    await Promise.resolve()
    await wrapper.vm.$nextTick()
    expect(wrapper.html()).toContain('Pendent: Stadtrat')
    expect(wrapper.html()).not.toContain('Beim Stadtrat pendent')
  })

  it('Kommissionsliste zeigt den Kommissionsnamen gekürzt', async () => {
    const { default: Kommissionsliste } = await import('../components/Kommissionsliste.vue')
    const wrapper = mount(Kommissionsliste, {
      props: {
        mitglieder: [],
        fraktionen: [],
      },
      global: { stubs: { NcTextField: true, NcCheckboxRadioSwitch: true, NcLoadingIcon: true, NcEmptyContent: true, GeschaeftDetail: true } },
    })
    wrapper.vm.kommissionen = [{ id: 9, name: 'Kommission Bildung, Sport und Kultur', aktiv: true, mitglieder: '[]' }]
    wrapper.vm.laden = false
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('BSKK')
    expect(wrapper.text()).not.toContain('Kommission Bildung, Sport und Kultur')
  })
})
