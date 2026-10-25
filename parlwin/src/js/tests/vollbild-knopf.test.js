import { readFileSync } from 'node:fs'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import App from '../App.vue'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mw', displayName: 'Marc Wäckerlin' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

/**
 * Vollbild auf flachem Schirm (F131).
 *
 * Marc am 24.10.2026: «Dann ein Knopf neben dem zum einklappen der Seitenleiste,
 * nur anzeigen bei wenig Höhe und wenn es das Feature im Browser gibt.»
 *
 * Das Vollbild nimmt die Adressleiste des Browsers und seine Schaltflächen weg.
 * Eine Webseite darf es nur auf eine Geste des Benutzers hin betreten — darum
 * der Knopf und kein Scroll-Beobachter.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')
const QUELLE = readFileSync('parlwin/src/js/App.vue', 'utf8')

function schirm(flach) {
  window.matchMedia = vi.fn().mockImplementation(() => ({
    matches: flach,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

function browserKann(vollbild) {
  Object.defineProperty(document, 'fullscreenEnabled', { value: vollbild, configurable: true })
  document.documentElement.requestFullscreen = vollbild ? vi.fn().mockResolvedValue(undefined) : undefined
  document.exitFullscreen = vi.fn().mockResolvedValue(undefined)
  Object.defineProperty(document, 'fullscreenElement', { value: null, writable: true, configurable: true })
}

async function app() {
  const w = mount(App, { global: { stubs: { Teleport: true } } })
  await flushPromises()
  return w
}

describe('Vollbild', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    axios.get.mockResolvedValue({ data: [] })
    window.PARLWIN_CONFIG = { version: '1.9.8' }
  })

  afterEach(() => {
    delete document.documentElement.requestFullscreen
  })

  it('zeigt den Knopf auf flachem Schirm, wenn der Browser es anbietet', async () => {
    schirm(true)
    browserKann(true)
    const w = await app()
    expect(w.find('.pw-vollbild-knopf').exists(), 'der Knopf fehlt').toBe(true)
  })

  it('zeigt ihn nicht auf hohem Schirm', async () => {
    schirm(false)
    browserKann(true)
    const w = await app()
    expect(w.find('.pw-vollbild-knopf').exists(), 'der Knopf steht da, wo er nichts bringt').toBe(false)
  })

  it('zeigt ihn nicht, wo der Browser kein Vollbild kann', async () => {
    schirm(true)
    browserKann(false)
    const w = await app()
    expect(w.find('.pw-vollbild-knopf').exists(), 'der Knopf verspricht etwas, das nicht geht').toBe(false)
  })

  it('geht auf Klick ins Vollbild', async () => {
    schirm(true)
    browserKann(true)
    const w = await app()
    await w.find('.pw-vollbild-knopf').trigger('click')
    expect(document.documentElement.requestFullscreen).toHaveBeenCalled()
  })

  it('verlässt das Vollbild auf den zweiten Klick', async () => {
    schirm(true)
    browserKann(true)
    Object.defineProperty(document, 'fullscreenElement', { value: document.documentElement, writable: true, configurable: true })
    const w = await app()
    await w.find('.pw-vollbild-knopf').trigger('click')
    expect(document.exitFullscreen).toHaveBeenCalled()
  })

  it('folgt dem Zustand, den der Browser meldet', async () => {
    schirm(true)
    browserKann(true)
    const w = await app()
    expect(w.vm.imVollbild).toBe(false)
    Object.defineProperty(document, 'fullscreenElement', { value: document.documentElement, writable: true, configurable: true })
    document.dispatchEvent(new Event('fullscreenchange'))
    await flushPromises()
    expect(w.vm.imVollbild, 'der Knopf merkt nicht, dass der Browser ins Vollbild ging').toBe(true)
  })

  /**
   * Marc am 24.10.2026, am Bild: «Im Stil, Grösse, Position, Art, ist der neue
   * button gleich wie der links daneben, direkt Seite an Seite daneben»,
   * «Gleiche Definition, keine Kopie!», «Sie teilen dasselbe CSS», «Und sind
   * Geschwister im selben Element in derselben Ebene», «Es wird nie etwas
   * nachgebaut!»
   */
  it('ist das Geschwister des Griffs im selben Behälter', () => {
    expect(QUELLE, 'der Knopf steht nicht im Behälter des Griffs')
      .toMatch(/<Teleport[^>]*to="\.app-navigation-toggle-wrapper"/)
  })

  it('ist derselbe Knopf wie der Griff: NcButton, «tertiary»', () => {
    const stelle = QUELLE.indexOf('pw-vollbild-knopf')
    expect(stelle, 'der Knopf fehlt in der Vorlage').toBeGreaterThan(-1)
    const block = QUELLE.slice(stelle - 200, stelle + 400)
    expect(block, 'der Knopf ist kein NcButton').toMatch(/<NcButton/)
    expect(block, 'der Knopf trägt eine andere Art als der Griff').toMatch(/variant="tertiary"/)
  })

  it('trägt kein eigenes CSS — Gestalt und Lage kommen vom Behälter', () => {
    expect(CSS, 'der Knopf hat eine eigene Regel, statt die des Griffs zu teilen')
      .not.toMatch(/^\s*\.pw-vollbild-knopf[^{]*\{/m)
  })

  it('steht neben dem Griff, nicht unter ihm', () => {
    // Der Behälter ist ein Block; zwei Knöpfe darin stapeln sich. Die eine
    // Regel am gemeinsamen Behälter stellt beide in eine Reihe.
    expect(CSS, 'die beiden Griffe stehen untereinander')
      .toMatch(/\.app-navigation-toggle-wrapper\s*\{[^}]*display:\s*flex/)
  })
})
