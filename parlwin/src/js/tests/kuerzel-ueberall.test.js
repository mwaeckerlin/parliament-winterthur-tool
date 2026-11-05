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
    { suche: 'Musterpartei-Fraktion (MPW)', kuerzel: 'MPW-Fraktion' },
    { suche: 'Musterpartei', kuerzel: 'MPW' },
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
    expect(kuerze('Musterpartei', KUERZEL)).toBe('MPW')
    expect(kuerze('Kommission Bildung, Sport und Kultur', KUERZEL)).toBe('BSKK')
    expect(kuerze('Beispielpartei', KUERZEL)).toBe('Beispielpartei')
    // Die vorgegebene Liste kommt aus der Konfiguration der App.
    expect(kuerze('Musterpartei')).toBe('MPW')
  })

  it('Mitgliederliste zeigt Partei, Fraktion und Kommission gekürzt', async () => {
    const { default: Mitgliederliste } = await import('../components/Mitgliederliste.vue')
    const wrapper = mount(Mitgliederliste, {
      props: {
        mitglieder: [{
          id: 1, externId: 'e1', vorname: 'Anna', name: 'Müller', aktiv: true,
          partei: 'Musterpartei',
          fraktion: 'Musterpartei-Fraktion (MPW)',
        }],
        fraktionen: [],
        kommissionen: [{ id: 9, name: 'Kommission Bildung, Sport und Kultur', aktiv: true, mitglieder: JSON.stringify([{ externId: 'e1', funktion: 'Mitglied' }]) }],
      },
      global: { stubs: { NcTextField: true, NcSelect: true, NcCheckboxRadioSwitch: true, NcEmptyContent: true } },
    })
    const text = wrapper.text()
    expect(text).toContain('MPW')
    expect(text).toContain('MPW-Fraktion')
    expect(text).toContain('BSKK')
    expect(text).not.toContain('Musterpartei')
    expect(text).not.toContain('Kommission Bildung, Sport und Kultur')
  })

  it('Vorstoss-Herkunftsfraktion wird in Auswahl und Karte gekürzt angezeigt, gespeichert wird der volle Name', async () => {
    const { default: Vorstoesseliste } = await import('../components/Vorstoesseliste.vue')
    const wrapper = shallowMount(Vorstoesseliste, {
      props: {
        mitglieder: [],
        fraktionen: [{ id: 2, name: 'Musterpartei-Fraktion (MPW)', aktiv: true }],
      },
    })
    const opt = wrapper.vm.fremdeFraktionsOptionen[0]
    expect(opt.label).toBe('MPW-Fraktion')
    expect(opt.value).toBe('Musterpartei-Fraktion (MPW)')
  })

  it('Kürzel-Verwaltung schlägt Status, aktuelle Fraktions- und Parteinamen vor', async () => {
    document.body.innerHTML += '<datalist id="pw-status-kuerzel-liste"></datalist>'
    axios.get.mockImplementation((url) => {
      if (String(url).includes('/fraktionen')) {
        return Promise.resolve({ data: [
          { name: 'Musterpartei-Fraktion (MPW)', aktiv: true },
          { name: 'Alte Fraktion', aktiv: false },
        ] })
      }
      if (String(url).includes('/mitglieder')) {
        return Promise.resolve({ data: [{ partei: 'Musterpartei' }, { partei: 'Beispielpartei' }] })
      }
      if (String(url).includes('/geschaefte/statuswerte')) {
        return Promise.resolve({ data: ['Beim Stadtrat pendent'] })
      }
      return Promise.reject(new Error(`unerwartete Anfrage ${url}`))
    })
    const { ladeVorschlagswerte } = await import('../admin')
    await ladeVorschlagswerte()
    const werte = [...document.querySelectorAll('#pw-status-kuerzel-liste option')].map(o => o.value)
    expect(werte).toContain('Beim Stadtrat pendent')
    expect(werte).toContain('Musterpartei-Fraktion (MPW)')
    expect(werte).toContain('Musterpartei')
    expect(werte).toContain('Beispielpartei')
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
