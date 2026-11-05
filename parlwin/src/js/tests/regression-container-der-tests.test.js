// @vitest-environment node
/**
 * Der Container der Tests eines E2E-Stacks (tests/regression/stack.mjs) ist der
 * Dienst `playwright` des Compose-Projekts. Ein Einmal-Container aus
 * `docker compose run` überstand das Aufräumen der Warteschlange, die jedes
 * Compose-Projekt mit `docker compose down` abbaut. Gemessen wird an den
 * Aufrufen, die stack.mjs an docker gibt (`werkzeug` in stack.mjs), und an der
 * Zusatzdatei für Compose, die es schreibt.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

let ablage
let vorher

async function stackMitGegenstelle({ laeuft = () => '', uebrig = '' } = {}) {
  vi.resetModules()
  const stack = await import('../../../../tests/regression/stack.mjs')
  const aufrufe = []
  stack.werkzeug.lesen = (befehl, argumente) => {
    if (befehl === 'bash') return { code: 0, stdout: 'NEXTCLOUD_DB_PASSWORD=x\n', stderr: '' }
    if (argumente.includes('ps') && argumente.includes('playwright')) return { code: 0, stdout: laeuft(aufrufe), stderr: '' }
    if (argumente[0] === 'ps') return { code: 0, stdout: uebrig, stderr: '' }
    return { code: 0, stdout: '', stderr: '' }
  }
  stack.werkzeug.ausfuehren = async (befehl, argumente) => {
    aufrufe.push([befehl, ...argumente])
    return { code: 0 }
  }
  return { stack, aufrufe }
}

describe('E2E-Stack: der Container der Tests gehört zum Compose-Projekt', () => {
  beforeEach(() => {
    ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-stack-'))
    vorher = process.env.PARLWIN_REGRESSION_ABLAGE
    process.env.PARLWIN_REGRESSION_ABLAGE = ablage
    vi.spyOn(console, 'log').mockImplementation(() => {})
  })

  afterEach(() => {
    if (vorher === undefined) delete process.env.PARLWIN_REGRESSION_ABLAGE
    else process.env.PARLWIN_REGRESSION_ABLAGE = vorher
    vi.restoreAllMocks()
    fs.rmSync(ablage, { recursive: true, force: true })
  })

  it('startet ihn als Dienst mit `docker compose up`, nie als Einmal-Container mit `run`', async () => {
    const { stack, aufrufe } = await stackMitGegenstelle({ laeuft: (a) => (a.length ? 'abc123\n' : '') })
    const id = await stack.pwContainerStarten('chromium')
    expect(id).toBe('abc123')
    const compose = aufrufe.filter((a) => a[0] === 'docker' && a[1] === 'compose')
    expect(compose, 'kein Aufruf von docker compose').toHaveLength(1)
    expect(compose[0]).toEqual(expect.arrayContaining(['up', '-d', '--no-deps', 'playwright']))
    expect(compose[0]).not.toContain('run')
    expect(compose[0]).not.toContain('--name')
  })

  it('nimmt einen laufenden Container der Tests, ohne einen zweiten zu starten', async () => {
    const { stack, aufrufe } = await stackMitGegenstelle({ laeuft: () => 'def456\n' })
    expect(await stack.pwContainerStarten('chromium')).toBe('def456')
    expect(aufrufe).toEqual([])
  })

  it('gibt dem Dienst playwright in der Zusatzdatei einen Befehl, der den Lauf über wartet', async () => {
    const { stack } = await stackMitGegenstelle()
    stack.overrideSchreiben({ playwright: 'bild:marke', 'nextcloud-nginx': 'nginx:marke' })
    const zeilen = fs.readFileSync(path.join(ablage, 'compose.regression.yml'), 'utf8').split('\n')
    const ab = zeilen.indexOf('  playwright:')
    expect(ab).toBeGreaterThan(-1)
    expect(zeilen.slice(ab + 1, ab + 3)).toEqual(['    image: bild:marke', '    command: ["node", "-e", "setInterval(() => {}, 1 << 30)"]'])
    expect(zeilen[zeilen.indexOf('  nextcloud-nginx:') + 2]).not.toMatch(/command/)
  })

  it('baut den Stack mit dem Profil des Containers der Tests ab und meldet, was danach noch steht', async () => {
    const { stack, aufrufe } = await stackMitGegenstelle({ uebrig: 'aaa\nbbb\n' })
    await expect(stack.abbauen('chromium')).rejects.toThrow('nach docker compose down stehen noch Container des Projekts: aaa, bbb')
    const down = aufrufe.find((a) => a.includes('down'))
    expect(down).toEqual(expect.arrayContaining(['--profile', 'tools', '--remove-orphans']))
  })

  it('baut einen Stack ohne übrige Container ohne Fehler ab', async () => {
    const { stack } = await stackMitGegenstelle({ uebrig: '' })
    await expect(stack.abbauen('chromium')).resolves.toBeUndefined()
  })
})

/**
 * Die Aufnahme der Bilder (`bild()`) legt die Datei des Design-Laufs in den
 * Container der Tests. Bleibt sie dort, nimmt das nächste `--list` sie ins
 * Inventar auf, und der nächste Playwright-Lauf führt sie ohne Kennwort aus.
 */
describe('E2E-Stack: die Aufnahme der Bilder räumt den Container der Tests auf', () => {
  const ID = 'c0ffee'
  let ziel

  async function stackFuerBild({ aufnahme }) {
    vi.resetModules()
    const stack = await import('../../../../tests/regression/stack.mjs')
    const aufrufe = []
    stack.werkzeug.lesen = (befehl, argumente) => {
      if (befehl === 'bash') return { code: 0, stdout: 'NEXTCLOUD_DB_PASSWORD=x\n', stderr: '' }
      if (argumente.includes('ps')) return { code: 0, stdout: `${ID}\n`, stderr: '' }
      return { code: 0, stdout: '', stderr: '' }
    }
    stack.werkzeug.ausfuehren = async (befehl, argumente, optionen = {}) => {
      aufrufe.push([befehl, ...argumente])
      if (argumente.some((a) => String(a).includes('status.php'))) optionen.zeile?.('{"installed":true}', 'stdout')
      if (argumente.includes('design-capture.spec.js')) return aufnahme(argumente)
      if (argumente[0] === 'cp' && String(argumente[1]).endsWith(':/work/bilder')) {
        fs.mkdirSync(argumente[2], { recursive: true })
        fs.writeFileSync(path.join(argumente[2], 'bild.png'), 'png')
      }
      return { code: 0 }
    }
    return { stack, aufrufe }
  }

  const imContainer = (aufrufe) => aufrufe.filter((a) => a[0] === 'docker' && a[1] === 'exec' && a[2] === ID)
  const entfernt = (aufruf, pfad) => aufruf.includes('rm') && aufruf.includes(pfad)

  beforeEach(() => {
    ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-stack-'))
    ziel = path.join(ablage, 'design')
    vorher = process.env.PARLWIN_REGRESSION_ABLAGE
    process.env.PARLWIN_REGRESSION_ABLAGE = ablage
    vi.spyOn(console, 'log').mockImplementation(() => {})
  })

  afterEach(() => {
    if (vorher === undefined) delete process.env.PARLWIN_REGRESSION_ABLAGE
    else process.env.PARLWIN_REGRESSION_ABLAGE = vorher
    vi.restoreAllMocks()
    fs.rmSync(ablage, { recursive: true, force: true })
  })

  it('entfernt die Datei des Design-Laufs nach der Aufnahme aus dem Container', async () => {
    const { stack, aufrufe } = await stackFuerBild({ aufnahme: async () => ({ code: 0 }) })
    const r = await stack.bild(ziel)
    expect(r).toEqual({ code: 0, bilder: 1 })
    const danach = imContainer(aufrufe).at(-1)
    expect(danach, 'nach der Aufnahme bleibt /work/design-capture.spec.js im Container').toBeDefined()
    expect(entfernt(danach, '/work/design-capture.spec.js')).toBe(true)
  })

  it('entfernt die Datei auch, wenn die Aufnahme abbricht', async () => {
    const { stack, aufrufe } = await stackFuerBild({ aufnahme: async () => { throw new Error('Playwright brach ab') } })
    await expect(stack.bild(ziel)).rejects.toThrow('Playwright brach ab')
    expect(entfernt(imContainer(aufrufe).at(-1), '/work/design-capture.spec.js')).toBe(true)
  })

  it('leert die Bilder im Container vor der Aufnahme, damit keine alten mitgezählt werden', async () => {
    const { stack, aufrufe } = await stackFuerBild({ aufnahme: async () => ({ code: 0 }) })
    await stack.bild(ziel)
    const aufnahme = aufrufe.findIndex((a) => a.includes('design-capture.spec.js') && a.includes('npx'))
    const leeren = aufrufe.findIndex((a) => a[1] === 'exec' && entfernt(a, '/work/bilder'))
    expect(leeren, 'die Bilder eines früheren Laufs bleiben im Container').toBeGreaterThan(-1)
    expect(leeren).toBeLessThan(aufnahme)
  })
})
