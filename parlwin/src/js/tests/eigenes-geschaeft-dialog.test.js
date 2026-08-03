import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: [] })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

import axios from '@nextcloud/axios'

const KOMMISSIONEN = [
  { id: 1, name: 'Aufsichtskommission', aktiv: true },
  { id: 2, name: 'Sachkommission Stadtbau', aktiv: true },
  { id: 3, name: 'Alte Kommission', aktiv: false },
]

// Antworten je Endpunkt — so prüft der Test den echten Ladeweg der Maske.
const antworten = (url) => {
  const pfad = String(url)
  if (pfad.includes('/settings/eigene-typen')) return { data: ['Motion', 'Postulat'] }
  if (pfad.includes('/geschaefte/statuswerte')) return { data: ['Pendent', 'Erledigt'] }
  if (pfad.includes('/kommissionen')) return { data: KOMMISSIONEN }
  return { data: [] }
}

// Ein eigenes Geschäft wird vollständig in der Maske erfasst: Titel über die
// ganze Breite, direkt darunter der Beschreibungstext, dazu Datum (auf heute
// vorbelegt), optional eine Kommission, der Typ aus der vom Administrator
// gepflegten Liste und der Status aus den bereits vorkommenden Werten —
// überschreibbar.
describe('Eigenes Geschäft: Erfassungsmaske', () => {
  const heute = new Date().toISOString().slice(0, 10)

  const neueMaske = () => shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 0, mitglieder: [], traktandumKontext: null },
    global: {
      stubs: { NcSelect: true, PwMultiSelect: true, PwWysiwyg: true, GeschaeftDokumente: true, BeschlussWidget: true },
    },
  })

  beforeEach(() => {
    axios.post.mockClear()
    axios.get.mockClear()
    axios.get.mockImplementation((url) => Promise.resolve(antworten(url)))
  })

  it('das Datum ist beim Anlegen auf heute vorbelegt', () => {
    expect(neueMaske().vm.geschaeft.datum).toBe(heute)
  })

  it('der Beschreibungstext steht direkt unter dem Titel — vor den öffentlichen Informationen', () => {
    const html = neueMaske().html()
    const titel = html.indexOf('pw-detail-titel-input')
    const inhalt = html.indexOf('pw-detail-inhalt')
    const oeffentlich = html.indexOf('pw-oeffentlich')
    expect(inhalt, 'Beschreibungstext fehlt in der Maske').toBeGreaterThan(-1)
    expect(inhalt).toBeGreaterThan(titel)
    expect(inhalt).toBeLessThan(oeffentlich)
  })

  it('die Kommission ist wählbar und bietet nur aktive Kommissionen an', async () => {
    const wrapper = neueMaske()
    await wrapper.vm.ladeAuswahllisten()
    expect(wrapper.html()).toContain('Kommission')
    expect(wrapper.vm.kommissionsOptionen).toEqual(['Aufsichtskommission', 'Sachkommission Stadtbau'])
  })

  it('keine Kommission ist ein gültiger Zustand', async () => {
    const wrapper = neueMaske()
    wrapper.vm.geschaeft.kommission = 'Aufsichtskommission'
    await wrapper.vm.kommissionGewaehlt(null)
    expect(wrapper.vm.geschaeft.kommission).toBe('')
  })

  it('die Typen kommen aus der vom Administrator gepflegten Liste', async () => {
    const wrapper = neueMaske()
    await wrapper.vm.ladeAuswahllisten()
    expect(wrapper.vm.typOptionen).toEqual(['Motion', 'Postulat'])
  })

  it('die Status-Auswahl kommt aus den vorkommenden Werten und bleibt überschreibbar', async () => {
    const wrapper = neueMaske()
    await wrapper.vm.ladeAuswahllisten()
    expect(wrapper.vm.statusOptionen).toEqual(['Pendent', 'Erledigt'])

    wrapper.vm.statusGeaendert('Ganz eigener Status')
    expect(wrapper.vm.geschaeft.status).toBe('Ganz eigener Status')
  })

  // Das Status-Feld nutzt zwar dasselbe Widget wie der Beschluss, trägt aber
  // eine EIGENE Klasse: sonst fänden Selektoren zwei «.pw-beschluss-input».
  it('Status- und Beschluss-Widget sind unterscheidbar (eigene Klasse)', () => {
    const html = neueMaske().html()
    expect(html, 'Das Status-Feld nutzt fälschlich die Beschluss-Klasse').not.toContain('pw-beschluss-input')
    expect(html, 'Das Status-Feld hat keine eigene Klasse').toContain('pw-status-input')
  })

  // Der Titel stand in einem schmalen Kasten, weil die Kopfzeile ihre Kinder
  // nicht wachsen liess. Er nutzt die ganze Zeilenbreite; der Fraktionsstatus
  // bleibt rechts daneben.
  it('der Titel nutzt die ganze Breite der Kopfzeile', () => {
    const css = readFileSync(
      resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss'), 'utf8')
    // Boolean statt Regex-Vergleich: sonst druckt der Fehlerfall das ganze Stylesheet.
    const wachsend = /\.pw-detail-header\s*>\s*div:first-child\s*\{[^}]*flex:\s*1/.test(css)
    expect(wachsend, 'Die Kopfzeile lässt den Titel nicht wachsen').toBe(true)
  })

  it('Speichern schickt Beschreibungstext, Kommission und Datum mit', async () => {
    const wrapper = neueMaske()
    wrapper.vm.geschaeft.titel = 'Mein Geschäft'
    wrapper.vm.geschaeft.inhalt = '<p>Worum es geht</p>'
    wrapper.vm.geschaeft.kommission = 'Aufsichtskommission'
    await wrapper.vm.neuesGeschaeftSpeichern()

    expect(axios.post).toHaveBeenCalledTimes(1)
    const daten = axios.post.mock.calls[0][1]
    expect(daten.titel).toBe('Mein Geschäft')
    expect(daten.inhalt).toBe('<p>Worum es geht</p>')
    expect(daten.kommission).toBe('Aufsichtskommission')
    expect(daten.datum).toBe(heute)
  })
})
