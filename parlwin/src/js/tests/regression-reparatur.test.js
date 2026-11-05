// @vitest-environment node
/**
 * Die Reparaturrunde des Testlaufs (tests/regression.mjs) führt nur die roten
 * Tests aus, mit genau den Vorgängern, die sie brauchen.
 *
 * - Ein Playwright-Test, der auf dem Ergebnis eines anderen Tests seiner Datei
 *   aufbaut, nennt diesen als Annotation `braucht`. Das Inventar führt die
 *   Abhängigkeit, und die Reparaturrunde nimmt den Vorgänger mit.
 * - Ein roter vitest-Fall läuft in der Reparaturrunde allein, mit seiner Datei
 *   und seinem Namen aus der Kennung `js:<datei>::<voller Name>`.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const WAECHTER = { stackErlaubt: () => ({ erlaubt: false, grund: 'Komponententest' }), lokalErlaubt: () => true, projekte: new Set() }
const pw = (titel, braucht = []) => ({ id: `pw:chromium:a.spec.js::Ablauf › ${titel}`, projekt: 'chromium', pfad: ['a.spec.js', 'Ablauf', titel], requires: braucht.map((b) => `pw:chromium:a.spec.js::Ablauf › ${b}`) })
const ANLEGEN = pw('4: legt an')
const LESEN = pw('5: liest', ['4: legt an'])
const ANDERS = pw('0: unabhängig')

let ablage
let umgebung

beforeEach(() => {
  ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-reparatur-'))
  umgebung = { ablage: process.env.PARLWIN_REGRESSION_ABLAGE, ohne: process.env.PARLWIN_TESTS_OHNE_E2E }
  process.env.PARLWIN_REGRESSION_ABLAGE = ablage
  process.env.PARLWIN_TESTS_OHNE_E2E = '0'
  vi.spyOn(console, 'log').mockImplementation(() => {})
})

afterEach(() => {
  for (const [name, wert] of [['PARLWIN_REGRESSION_ABLAGE', umgebung.ablage], ['PARLWIN_TESTS_OHNE_E2E', umgebung.ohne]]) {
    if (wert === undefined) delete process.env[name]
    else process.env[name] = wert
  }
  vi.restoreAllMocks()
  fs.rmSync(ablage, { recursive: true, force: true })
})

describe('Playwright: Abhängigkeiten zwischen Tests', () => {
  it('das Inventar liest die Annotation «braucht» als Voraussetzung', async () => {
    vi.resetModules()
    const stack = await import('../../../../tests/regression/stack.mjs')
    const bericht = {
      suites: [{ title: 'a.spec.js', suites: [{ title: 'Ablauf', specs: [
        { title: '4: legt an', tests: [{ projectName: 'chromium', annotations: [] }] },
        { title: '5: liest', tests: [{ projectName: 'chromium', annotations: [{ type: 'braucht', description: '4: legt an' }] }] },
      ] }] }],
    }
    stack.werkzeug.lesen = (befehl) => ({ code: 0, stdout: befehl === 'bash' ? 'NEXTCLOUD_DB_PASSWORD=x\n' : 'abc\n', stderr: '' })
    stack.werkzeug.ausfuehren = async (befehl, argumente, optionen = {}) => {
      if (argumente.includes('--list')) optionen.zeile?.(JSON.stringify(bericht), 'stdout')
      return { code: 0 }
    }
    const tests = await stack.playwrightInventar('chromium', ['chromium'])
    expect(tests.find((t) => t.id === LESEN.id).requires).toEqual([ANLEGEN.id])
    expect(tests.find((t) => t.id === ANLEGEN.id).requires).toEqual([])
  })

  it('das Inventar lehnt eine Voraussetzung ab, die es nicht gibt', async () => {
    vi.resetModules()
    const stack = await import('../../../../tests/regression/stack.mjs')
    const bericht = { suites: [{ title: 'a.spec.js', specs: [{ title: '5: liest', tests: [{ projectName: 'chromium', annotations: [{ type: 'braucht', description: '4: gibt es nicht' }] }] }] }] }
    stack.werkzeug.lesen = (befehl) => ({ code: 0, stdout: befehl === 'bash' ? 'NEXTCLOUD_DB_PASSWORD=x\n' : 'abc\n', stderr: '' })
    stack.werkzeug.ausfuehren = async (befehl, argumente, optionen = {}) => {
      if (argumente.includes('--list')) optionen.zeile?.(JSON.stringify(bericht), 'stdout')
      return { code: 0 }
    }
    await expect(stack.playwrightInventar('chromium', ['chromium'])).rejects.toThrow('braucht «4: gibt es nicht»')
  })

  it('die Reparaturrunde nimmt den Vorgänger eines roten Tests mit', async () => {
    vi.resetModules()
    const m = await import('../../../../tests/regression.mjs')
    const auswahlen = []
    let runde = 0
    m.abh.phpTestBildBauen = async () => ({ code: 0 })
    m.abh.gruppenHash = (g) => `h-${g}`
    m.abh.quellIdentitaet = () => 'q1'
    m.abh.gruppeAusfuehren = async (lauf, g) => { lauf.beginn(`${g}:gruppe`); lauf.ende(`${g}:gruppe`, 'bestanden', {}); return 0 }
    m.abh.stack = {
      abschnittListe: () => [],
      aufbauen: async () => ({}),
      playwrightInventar: async () => [ANDERS, ANLEGEN, LESEN],
      zustandHerstellen: async () => 0,
      abschnitte: async () => ({ code: 0, ergebnisse: {} }),
      markenBestimmen: () => ({}),
      projektName: (b) => `test_${b}`,
      bild: async () => ({ code: 0, bilder: 1 }),
      playwright: async (browser, { auswahl, ereignis }) => {
        runde += 1
        auswahlen.push(auswahl)
        const gewaehlt = auswahl ? [ANDERS, ANLEGEN, LESEN].filter((t) => auswahl.some((p) => JSON.stringify(p) === JSON.stringify(t.pfad))) : [ANDERS, ANLEGEN, LESEN]
        for (const t of gewaehlt) {
          ereignis({ ereignis: 'beginn', id: t.id })
          ereignis({ ereignis: 'ende', id: t.id, ergebnis: runde === 1 && t === LESEN ? 'rot' : 'bestanden', dauer_ms: 1 })
        }
        return { code: runde === 1 ? 1 : 0 }
      },
    }
    const bildZiel = path.join(ablage, 'bilder')
    const erster = await m.schlussregression(WAECHTER, 'q1', { neu: true, bildZiel })
    expect(erster.freigegeben).toBe(false)
    await m.schlussregression(WAECHTER, 'q1', { neu: false, bildZiel })
    expect(auswahlen[1], 'die Reparaturrunde führt den roten Test ohne seinen Vorgänger aus').toEqual([ANLEGEN.pfad, LESEN.pfad])
    const inventar = JSON.parse(fs.readFileSync(path.join(ablage, 'inventar.json'), 'utf8'))
    expect(inventar.find((t) => t.id === LESEN.id).requires).toEqual([ANLEGEN.id])
  })

  it('multi-user-sharing.spec.js nennt die Vorgänger jedes Tests, der auf einem anderen aufbaut', () => {
    const quelle = fs.readFileSync(path.join(WURZEL, 'tests', 'e2e', 'multi-user-sharing.spec.js'), 'utf8')
    const braucht = (titel) => {
      const at = quelle.indexOf(`test('${titel}'`)
      expect(at, `kein Test «${titel}»`).toBeGreaterThan(-1)
      const kopf = quelle.slice(at, quelle.indexOf('async', at))
      return [...kopf.matchAll(/braucht\('([^']+)'\)/g)].map((x) => x[1])
    }
    expect(braucht('5: User2 sieht den Termin und bearbeitet ihn')).toEqual(['4: User1 legt einen Termin im geteilten Kalender an (CalDAV)'])
    expect(braucht('6+7: User3 und User1 sehen den bearbeiteten Termin')).toEqual(['5: User2 sieht den Termin und bearbeitet ihn'])
    expect(braucht('9: User2 sieht den geteilten Ordner und bearbeitet das Dokument')).toEqual(['8: User1 legt ein Dokument im geteilten Fraktionsordner an'])
    expect(braucht('10+11: User3 und User1 sehen den bearbeiteten Dokumentinhalt')).toEqual(['9: User2 sieht den geteilten Ordner und bearbeitet das Dokument'])
  })
})

describe('vitest: Reparaturrunde über die Kennung', () => {
  it('ruft vitest mit der Datei und dem Namen jedes roten Falls auf', async () => {
    vi.resetModules()
    const m = await import('../../../../tests/regression.mjs')
    let argumente = null
    m.abh.ausfuehren = async (befehl, args) => { argumente = args; return { code: 0 } }
    const lauf = { kopf: { lauf: 'x', runde: 2, phase: 'reparatur' }, beginn() {}, ende() {} }
    await m.gruppeAusfuehren(lauf, 'js', { filter: ['js:parlwin/src/js/tests/a.test.js::Gruppe > macht x (y)', 'js:parlwin/src/js/tests/b.test.js::zweiter Fall'] })
    const nach = argumente.slice(argumente.indexOf('--') + 1)
    expect(nach).toContain('parlwin/src/js/tests/a.test.js')
    expect(nach).toContain('parlwin/src/js/tests/b.test.js')
    const muster = nach[nach.indexOf('-t') + 1]
    expect(muster).toBe('macht x \\(y\\)|zweiter Fall')
    expect(new RegExp(muster).test('Gruppe macht x (y)')).toBe(true)
  })
})
