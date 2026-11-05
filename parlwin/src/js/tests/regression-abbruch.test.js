// @vitest-environment node
/**
 * Ein Abbruch des Testlaufs (SIGINT, SIGTERM, SIGHUP an tests/regression.mjs)
 * beendet den Playwright-Lauf im Container der Tests. `docker exec` gibt kein
 * Signal an den Prozess im Container weiter; der Lauf entfernt darum jeden
 * Container der Tests, in dem gerade Playwright läuft, und der nächste Aufruf
 * legt ihn neu an (`pwContainerStarten`).
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

let ablage
let vorher

beforeEach(() => {
  ablage = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-abbruch-'))
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

/** stack.mjs mit einer Gegenstelle für docker; `npx` kehrt nie zurück, bis `beenden()` es freigibt. */
async function stackMitLaufendemPlaywright() {
  vi.resetModules()
  const stack = await import('../../../../tests/regression/stack.mjs')
  const entfernt = []
  stack.werkzeug.lesen = (befehl, argumente) => {
    if (befehl === 'bash') return { code: 0, stdout: 'NEXTCLOUD_DB_PASSWORD=x\n', stderr: '' }
    if (argumente[0] === 'rm') { entfernt.push(argumente); return { code: 0, stdout: '', stderr: '' } }
    if (argumente.includes('ps')) return { code: 0, stdout: 'abc123\n', stderr: '' }
    return { code: 0, stdout: '', stderr: '' }
  }
  const zustand = { gestartet: 0, beenden: [] }
  stack.werkzeug.ausfuehren = (befehl, argumente) => {
    if (argumente.includes('npx')) {
      zustand.gestartet += 1
      return new Promise((fertig) => zustand.beenden.push(() => fertig({ code: 143 })))
    }
    return Promise.resolve({ code: 0 })
  }
  return { stack, entfernt, zustand }
}

describe('Abbruch des Testlaufs', () => {
  it('stack.abbrechen() entfernt jeden Container der Tests, in dem gerade Playwright läuft', async () => {
    const { stack, entfernt, zustand } = await stackMitLaufendemPlaywright()
    void stack.playwright('chromium', { projekte: ['chromium'], phase: 'abbruch' })
    await vi.waitFor(() => expect(zustand.gestartet).toBe(1))
    stack.abbrechen()
    expect(entfernt).toEqual([['rm', '-f', 'abc123']])
  })

  it('stack.abbrechen() lässt den Container stehen, wenn kein Playwright mehr darin läuft', async () => {
    const { stack, entfernt, zustand } = await stackMitLaufendemPlaywright()
    const lauf = stack.playwright('chromium', { projekte: ['chromium'], phase: 'fertig' })
    await vi.waitFor(() => expect(zustand.gestartet).toBe(1))
    zustand.beenden[0]()
    await lauf
    stack.abbrechen()
    expect(entfernt).toEqual([])
  })

  it('abbrechen() in tests/regression.mjs ruft stack.abbrechen() auf', async () => {
    vi.resetModules()
    const m = await import('../../../../tests/regression.mjs')
    const stackAbbruch = vi.fn()
    m.abh.stack = { abbrechen: stackAbbruch }
    m.abbrechen()
    expect(stackAbbruch).toHaveBeenCalledTimes(1)
  })
})
