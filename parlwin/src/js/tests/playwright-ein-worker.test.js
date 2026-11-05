// @vitest-environment node
/**
 * `npm run test:e2e` und jedes `test:e2e:*` lassen Playwright ohne PW_WORKERS
 * laufen. Mit zwei Workern wartet der zweite an der Sperre «allein»
 * (tests/e2e/sperre.js), bis sein `beforeAll` abläuft; der Standard ist darum
 * ein Worker. Gemessen wird an der Konfiguration, die Playwright lädt.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'

let vorher

async function konfiguration() {
  vi.resetModules()
  return (await import('../../../../tests/e2e/playwright.config.js')).default
}

describe('Playwright: Zahl der Worker', () => {
  beforeEach(() => {
    vorher = { workers: process.env.PW_WORKERS, auswahl: process.env.PW_AUSWAHL }
    delete process.env.PW_AUSWAHL
  })

  afterEach(() => {
    for (const [name, wert] of [['PW_WORKERS', vorher.workers], ['PW_AUSWAHL', vorher.auswahl]]) {
      if (wert === undefined) delete process.env[name]
      else process.env[name] = wert
    }
  })

  it('läuft ohne PW_WORKERS mit einem Worker', async () => {
    delete process.env.PW_WORKERS
    expect((await konfiguration()).workers).toBe(1)
  })

  it('nimmt PW_WORKERS, wenn es gesetzt ist', async () => {
    process.env.PW_WORKERS = '2'
    expect((await konfiguration()).workers).toBe(2)
  })
})
