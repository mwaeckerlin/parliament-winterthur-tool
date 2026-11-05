// @vitest-environment node
/**
 * Die Anmeldung ohne Browser (tests/e2e/globale-anmeldung.js) schickt das
 * Formular von Nextcloud mit dem Kopf `Origin`. LoginController::tryLogin von
 * Nextcloud weist eine Anmeldung ab, deren `Origin` fehlt oder keine
 * vertrauenswürdige Adresse nennt, und leitet zurück auf die Anmeldeseite; die
 * Sitzung bleibt dann ohne Nutzer. Die Gegenstelle hier hält sich an diese
 * Regel und an das Requesttoken der Anmeldeseite.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

const NUTZER = { name: 'parlwin_mitglied', pass: 'geheim' }
const TOKEN = 'abc+def/=:ghi'
let verzeichnis
let vorher

/** Eine Nextcloud mit den Regeln von tryLogin: Origin vertrauenswürdig, Requesttoken gültig, Kennwort richtig. */
function nextcloud(vertraut) {
  const gesendet = []
  return {
    gesendet,
    newContext: async () => {
      let angemeldet = ''
      return {
        get: async (url) => {
          if (url === '/index.php/login') return { status: () => 200, text: async () => `<head data-requesttoken="${TOKEN}">` }
          return { ok: () => true, json: async () => ({ ocs: { data: { id: angemeldet } } }) }
        },
        post: async (url, optionen) => {
          gesendet.push({ url, ...optionen })
          const origin = optionen.headers?.Origin || ''
          if (origin === vertraut && optionen.form.requesttoken === TOKEN && optionen.form.password === NUTZER.pass) angemeldet = optionen.form.user
          return { status: () => 303 }
        },
        storageState: async ({ path: datei }) => fs.writeFileSync(datei, '{"cookies":[]}'),
        dispose: async () => {},
      }
    },
  }
}

function meldungen(schreiber) {
  return schreiber.mock.calls.map(([z]) => String(z)).filter((z) => z.startsWith('##ANMELDUNG ')).map((z) => JSON.parse(z.slice(12)))
}

describe('Anmeldung ohne Browser', () => {
  beforeEach(() => {
    verzeichnis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-anmeldung-'))
    vorher = { dir: process.env.PW_ANMELDUNG_DIR, url: process.env.PARLWIN_BASE_URL }
    process.env.PW_ANMELDUNG_DIR = verzeichnis
    delete process.env.PARLWIN_BASE_URL
  })

  afterEach(() => {
    for (const [name, wert] of [['PW_ANMELDUNG_DIR', vorher.dir], ['PARLWIN_BASE_URL', vorher.url]]) {
      if (wert === undefined) delete process.env[name]
      else process.env[name] = wert
    }
    vi.restoreAllMocks()
    fs.rmSync(verzeichnis, { recursive: true, force: true })
  })

  it('schickt das Formular mit dem Origin der Adresse von Nextcloud und ist danach angemeldet', async () => {
    vi.resetModules()
    const { anmelden } = await import('../../../../tests/e2e/globale-anmeldung.js')
    const schreiber = vi.spyOn(process.stdout, 'write').mockImplementation(() => true)
    const nc = nextcloud('http://nextcloud-nginx:8080')
    await anmelden(NUTZER, nc)
    const gemeldet = meldungen(schreiber)
    schreiber.mockRestore()
    expect(nc.gesendet).toHaveLength(1)
    expect(nc.gesendet[0].headers).toEqual({ Origin: 'http://nextcloud-nginx:8080' })
    expect(gemeldet).toEqual([{ nutzer: NUTZER.name, art: 'neu angemeldet' }])
    expect(fs.existsSync(path.join(verzeichnis, `${NUTZER.name}.json`))).toBe(true)
  })

  it('meldet eine abgewiesene Anmeldung als fehlgeschlagen und legt keine Datei ab', async () => {
    vi.resetModules()
    const { anmelden } = await import('../../../../tests/e2e/globale-anmeldung.js')
    const schreiber = vi.spyOn(process.stdout, 'write').mockImplementation(() => true)
    await anmelden(NUTZER, nextcloud('http://anderswo:8080'))
    const gemeldet = meldungen(schreiber)
    schreiber.mockRestore()
    expect(gemeldet).toEqual([{ nutzer: NUTZER.name, art: 'fehlgeschlagen', grund: 'nach dem Absenden angemeldet als «niemand»' }])
    expect(fs.existsSync(path.join(verzeichnis, `${NUTZER.name}.json`))).toBe(false)
  })
})
