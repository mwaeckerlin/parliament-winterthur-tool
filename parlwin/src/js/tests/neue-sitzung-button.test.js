import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/dialogs', () => ({
  showError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
  showInfo: vi.fn(),
}))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))

const src = readFileSync(
  resolve(dirname(fileURLToPath(import.meta.url)), '../components/Sitzungsliste.vue'),
  'utf8',
)

const mount = () => shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })

// Nextcloud-Standard für Neu-Aktionen: der Neu-Knopf öffnet ein Aktionsmenü mit
// je einem Eintrag pro Vorlage — genau wie «+ Neu» in der Dateien-App. Ein
// selbstgebauter Auswahl-Dialog davor ist eine Eigenkonstruktion und verboten.
describe('Neue-Sitzung-Auslöser folgt dem Nextcloud-Standard', () => {
  it('Header enthält ein NcActions-Menü «+ Neue Sitzung»', () => {
    const header = src.match(/<header class="pw-view-header">([\s\S]*?)<\/header>/)
    expect(header, 'pw-view-header nicht gefunden').toBeTruthy()
    expect(header[1], 'NcActions-Menü «+ Neue Sitzung» fehlt im Header')
      .toMatch(/<NcActions\b[^>]*menu-name="\+ Neue Sitzung"/)
  })

  it('bietet je einen Menüeintrag pro Sitzungstyp', () => {
    expect(src, 'Menüeintrag pro Sitzungstyp fehlt')
      .toMatch(/<NcActionButton[\s\S]*?v-for="typ in sitzungstypen"[\s\S]*?@click="waehleTypFuerNeueSitzung\(typ\)"/)
  })

  it('weist ohne Sitzungstyp im Menü darauf hin, statt es leer zu lassen', () => {
    expect(src).toMatch(/<NcActionButton v-if="sitzungstypen\.length === 0" :disabled="true">Kein Sitzungstyp definiert<\/NcActionButton>/)
  })

  it('hat keinen eigenen Auswahl-Dialog vor dem Formular', () => {
    expect(src, 'Selbstgebauter Typ-Wahl-Dialog ist wieder da').not.toMatch(/typWahlDialog/)
  })
})

describe('Neue-Sitzung-Einstieg: Typwahl startet das Formular', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
    axios.post.mockReset()
  })

  it('ein Menüeintrag öffnet direkt das Formular für genau diesen Typ', async () => {
    const wrapper = mount()
    await flushPromises()
    const typ = { id: 7, name: 'Fraktion' }
    wrapper.vm.waehleTypFuerNeueSitzung(typ)
    expect(wrapper.vm.gewaehlterTyp).toStrictEqual(typ)
  })

  it('das Formular übernimmt die Vorgaben des gewählten Sitzungstyps', async () => {
    const wrapper = mount()
    await flushPromises()
    wrapper.vm.waehleTypFuerNeueSitzung({
      id: 3,
      name: 'Fraktionssitzung',
      standardOrt: 'Sitzungszimmer',
      standardZeitVon: '18:00',
      standardZeitBis: '20:00',
      zweck: 'Vorbereitung',
      traktanden: [{ titel: 'Begrüssung', beschreibung: '' }],
      teilnehmer: [],
    })
    expect(wrapper.vm.neueSitzungTitel).toBe('Fraktionssitzung')
    expect(wrapper.vm.neueSitzungOrt).toBe('Sitzungszimmer')
    expect(wrapper.vm.neueSitzungZeitVon).toBe('18:00')
    expect(wrapper.vm.neueSitzungZeitBis).toBe('20:00')
    expect(wrapper.vm.neueSitzungBemerkungen).toBe('Vorbereitung')
    expect(wrapper.vm.neueSitzungTraktanden).toHaveLength(1)
  })
})
