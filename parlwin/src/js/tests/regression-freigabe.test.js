// @vitest-environment node
/**
 * Die Freigabe des Testlaufs (tests/regression.mjs, `schlussregression`).
 *
 * Der Lauf läuft hier ganz, mit seinem Journal, seinem Inventar und seiner
 * Entscheidung über die Freigabe, in einem eigenen Verzeichnis
 * (PARLWIN_REGRESSION_ABLAGE). Ersetzt sind nur die Teile, die Docker
 * brauchen: die Gruppen, die Stacks, Playwright und die Aufnahme der Bilder
 * (`abh` in tests/regression.mjs). Jede Gruppe meldet ihren Gesamtfall grün,
 * Playwright einen grünen Test.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

const PW_TEST = { id: 'pw:chromium:a.spec.js::ein Test', projekt: 'chromium', pfad: ['a.spec.js', 'ein Test'] }
const WAECHTER = { stackErlaubt: () => ({ erlaubt: false, grund: 'Komponententest' }), lokalErlaubt: () => true, projekte: new Set() }

let ablage
let umgebung

async function laden() {
  vi.resetModules()
  return import('../../../../tests/regression.mjs')
}

function journal() {
  const datei = path.join(ablage, 'journal.jsonl')
  if (!fs.existsSync(datei)) return []
  return fs.readFileSync(datei, 'utf8').split('\n').filter(Boolean).map((z) => JSON.parse(z))
}

const ende = (id) => journal().filter((e) => e.ereignis === 'ende' && e.id === id).at(-1)

/** Setzt die Gegenstellen ein; `bild` und `pwCode` bestimmen Aufnahme und Rückgabewert von Playwright. */
function gegenstellen(m, { pwCode = 0, bild = async () => ({ code: 0, bilder: 3 }), quelleEnde = 'q1', hash = (g) => `h-${g}` } = {}) {
  const protokoll = { gruppen: [], aufnahmen: 0, journalBeiAufnahme: null }
  m.abh.phpTestBildBauen = async () => ({ code: 0 })
  m.abh.gruppenHash = hash
  m.abh.quellIdentitaet = () => quelleEnde
  m.abh.gruppeAusfuehren = async (lauf, g) => {
    protokoll.gruppen.push(g)
    lauf.beginn(`${g}:gruppe`)
    lauf.ende(`${g}:gruppe`, 'bestanden', {})
    return 0
  }
  m.abh.stack = {
    abschnittListe: () => [],
    aufbauen: async () => ({}),
    playwrightInventar: async () => [PW_TEST],
    zustandHerstellen: async () => 0,
    abschnitte: async () => ({ code: 0, ergebnisse: {} }),
    markenBestimmen: () => ({}),
    projektName: (b) => `test_${b}`,
    playwright: async (browser, { ereignis }) => {
      ereignis({ ereignis: 'beginn', id: PW_TEST.id })
      ereignis({ ereignis: 'ende', id: PW_TEST.id, ergebnis: 'bestanden', dauer_ms: 1 })
      return { code: pwCode }
    },
    bild: async (ziel) => {
      protokoll.aufnahmen += 1
      protokoll.journalBeiAufnahme = journal()
      return bild(ziel)
    },
  }
  return protokoll
}

const lauf = (m, neu = true) => m.schlussregression(WAECHTER, 'q1', { neu, bildZiel: path.join(ablage, 'bilder') })

describe('Testlauf: Urteil, Aufnahme und Freigabe', () => {
  beforeEach(() => {
    ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-regression-'))
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

  it('führt die Aufnahme und den Rückgabewert jedes Stacks im Inventar', async () => {
    const m = await laden()
    gegenstellen(m)
    await lauf(m)
    const ids = JSON.parse(fs.readFileSync(path.join(ablage, 'inventar.json'), 'utf8')).map((t) => t.id)
    expect(ids).toContain('bild:aufnahme')
    expect(ids).toContain('pw:chromium:lauf')
    expect(ids).toContain('composer-audit:gruppe')
  })

  it('schreibt das Urteil über die Tests ins Journal, bevor die Aufnahme beginnt', async () => {
    const m = await laden()
    const p = gegenstellen(m)
    const r = await lauf(m)
    const urteil = p.journalBeiAufnahme.filter((e) => e.ereignis === 'urteil')
    expect(urteil, 'kein Urteil im Journal, als die Aufnahme begann').toHaveLength(1)
    expect(urteil[0].tests_gruen).toBe(true)
    expect(urteil[0].zahlen.rot).toBe(0)
    expect(ende('bild:aufnahme').ergebnis).toBe('bestanden')
    expect(ende('pw:chromium:lauf').ergebnis).toBe('bestanden')
    expect(r.freigegeben).toBe(true)
  })

  it('setzt nach einem Abbruch in der Aufnahme nur mit der Aufnahme fort', async () => {
    const erster = await laden()
    const p1 = gegenstellen(erster, { bild: () => new Promise(() => {}) })
    void lauf(erster)
    await vi.waitFor(() => expect(p1.aufnahmen).toBe(1))
    expect(journal().filter((e) => e.ereignis === 'urteil')).toHaveLength(1)

    const zweiter = await laden()
    const p2 = gegenstellen(zweiter)
    const r = await lauf(zweiter, false)
    expect(p2.gruppen, 'die Tests liefen nach dem Abbruch ein zweites Mal').toEqual([])
    expect(p2.aufnahmen).toBe(1)
    expect(r.freigegeben).toBe(true)
  })

  it('gibt nicht frei, wenn die Aufnahme kein Bild liefert', async () => {
    const m = await laden()
    gegenstellen(m, { bild: async () => ({ code: 0, bilder: 0 }) })
    const r = await lauf(m)
    expect(ende('bild:aufnahme').ergebnis).toBe('rot')
    expect(ende('bild:aufnahme').fehler).toBe('Aufnahme lieferte kein Bild')
    expect(r.freigegeben).toBe(false)
  })

  it('gibt nicht frei, wenn die Aufnahme nicht mit 0 endet', async () => {
    const m = await laden()
    gegenstellen(m, { bild: async () => ({ code: 1, bilder: 5 }) })
    const r = await lauf(m)
    expect(ende('bild:aufnahme').ergebnis).toBe('rot')
    expect(r.freigegeben).toBe(false)
  })

  it('gibt nicht frei, wenn sich der Quellstand während des Laufs ändert', async () => {
    const m = await laden()
    gegenstellen(m, { quelleEnde: 'q2' })
    const r = await lauf(m)
    expect(r.freigegeben).toBe(false)
    expect(journal().find((e) => e.ereignis === 'fertig').quelle_gleich).toBe(false)
    expect(fs.existsSync(path.join(ablage, 'gruppen-gruen.json'))).toBe(false)
  })

  it('hält die Hashes fest, die jede Gruppe vor ihrem Start hatte', async () => {
    const m = await laden()
    const aufrufe = new Map()
    gegenstellen(m, {
      hash: (g) => {
        aufrufe.set(g, (aufrufe.get(g) || 0) + 1)
        return aufrufe.get(g) === 1 ? `start-${g}` : `spaet-${g}`
      },
    })
    const r = await lauf(m)
    expect(r.freigegeben).toBe(true)
    const gruen = JSON.parse(fs.readFileSync(path.join(ablage, 'gruppen-gruen.json'), 'utf8'))
    for (const g of ['php-pdf', 'php-unit', 'js', 'compose']) expect(gruen[g].hash).toBe(`start-${g}`)
  })

  it('führt php-live, audit und composer-audit auch mit unverändertem Hash aus', async () => {
    const m = await laden()
    const gruen = Object.fromEntries(m.GRUPPEN.map((g) => [g, { hash: `h-${g}`, zeit: '2000-01-01T00:00:00.000Z' }]))
    fs.writeFileSync(path.join(ablage, 'gruppen-gruen.json'), JSON.stringify(gruen))
    const p = gegenstellen(m)
    await lauf(m)
    expect(p.gruppen).toEqual(expect.arrayContaining(['php-live', 'audit', 'composer-audit']))
    for (const g of ['php-pdf', 'php-unit', 'js', 'compose']) expect(p.gruppen, `${g} lief trotz unverändertem Hash`).not.toContain(g)
  })

  it('wertet den Rückgabewert von Playwright aus: ein Lauf mit grünen Tests und Code 1 ist rot', async () => {
    const m = await laden()
    gegenstellen(m, { pwCode: 1 })
    const r = await lauf(m)
    expect(ende(PW_TEST.id).ergebnis).toBe('bestanden')
    expect(ende('pw:chromium:lauf').ergebnis).toBe('rot')
    expect(ende('pw:chromium:lauf').fehler).toBe('Playwright endete mit 1')
    expect(r.freigegeben).toBe(false)
  })
})

describe('Doppelprüfung: Rückgabewert von Playwright gegen die Tests', () => {
  beforeEach(() => {
    ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-regression-'))
    umgebung = { ablage: process.env.PARLWIN_REGRESSION_ABLAGE }
    process.env.PARLWIN_REGRESSION_ABLAGE = ablage
  })

  afterEach(() => {
    if (umgebung.ablage === undefined) delete process.env.PARLWIN_REGRESSION_ABLAGE
    else process.env.PARLWIN_REGRESSION_ABLAGE = umgebung.ablage
    fs.rmSync(ablage, { recursive: true, force: true })
  })

  const phase = { name: 'aus', rot: [PW_TEST.id] }

  it('nimmt einen roten Lauf mit rotem Test als erwartet hin, ohne Eintrag in der Datei', async () => {
    const { erwartungPruefen } = await laden()
    const ergebnisse = new Map([[PW_TEST.id, { ergebnis: 'rot' }], ['pw:chromium:lauf', { ergebnis: 'rot', fehler: 'Playwright endete mit 1', tests: 1, rot_tests: 1 }]])
    expect(erwartungPruefen(phase, ergebnisse)).toEqual([])
  })

  it('meldet einen roten Lauf ohne roten Test', async () => {
    const { erwartungPruefen } = await laden()
    const ergebnisse = new Map([[PW_TEST.id, { ergebnis: 'bestanden' }], ['pw:chromium:lauf', { ergebnis: 'rot', fehler: 'Playwright endete mit 1', tests: 1, rot_tests: 0 }]])
    expect(erwartungPruefen({ name: 'an' }, ergebnisse)).toEqual(['pw:chromium:lauf ist rot (Playwright endete mit 1) bei 0 roten von 1 Tests'])
  })

  it('meldet einen grünen Lauf mit rotem Test', async () => {
    const { erwartungPruefen } = await laden()
    const ergebnisse = new Map([[PW_TEST.id, { ergebnis: 'rot' }], ['pw:chromium:lauf', { ergebnis: 'bestanden', tests: 1, rot_tests: 1 }]])
    expect(erwartungPruefen(phase, ergebnisse)).toEqual(['pw:chromium:lauf ist bestanden bei 1 roten von 1 Tests'])
  })
})

describe('Doppelprüfung: PHPUnit sieht die umgeschalteten Dateien', () => {
  it('baut das PHP-Testimage in jeder Phase neu, bevor PHPUnit läuft', async () => {
    const { doppelpruefungPhpOptionen } = await laden()
    const optionen = doppelpruefungPhpOptionen('(SyncCommandTest)')
    // tests/php-suite.sh baut das Image, das das Projekt hineinkopiert, nur
    // ohne PARLWIN_PHP_TEST_GEBAUT=1 neu.
    expect(optionen.env.PARLWIN_PHP_TEST_GEBAUT).toBe('0')
    expect(optionen.werkzeug).toEqual(['--filter', '(SyncCommandTest)'])
    const quelle = fs.readFileSync(path.join(__dirname, '../../../../tests/regression.mjs'), 'utf8')
    expect(quelle).toContain("gruppeAusfuehren(lauf, 'php-unit', doppelpruefungPhpOptionen(phase.php.filter))")
    const suite = fs.readFileSync(path.join(__dirname, '../../../../tests/php-suite.sh'), 'utf8')
    expect(suite).toMatch(/if \[\[ "\$\{PARLWIN_PHP_TEST_GEBAUT:-0\}" != "1" \]\]; then/)
  })
})
