// @vitest-environment node
/**
 * Der Stand eines E2E-Stacks enthält Zugangsdaten des Test-Stacks: die
 * App-Passwörter in `zustand/variablen.sh` (tests/e2e/run-compose-e2e.sh) und
 * die Datenbank mit ihren Hashes in den Sicherungen s0 und s1
 * (tests/regression/stack.mjs). Andere Konten des Rechners lesen davon nichts:
 * Verzeichnisse stehen auf 0700, Dateien auf 0600. Was sich aus einem Token
 * ableiten lässt (ADMIN_AUTH_B64), steht nicht in der Datei, und ebenso keine
 * Variable, die das Skript erbt, etwa ein Token des Rechners. Die Sitzung des
 * Design-Laufs liegt ausserhalb des Verzeichnisses, aus dem die Bilder kommen.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const modus = (pfad) => (fs.statSync(pfad).mode & 0o777).toString(8)

/**
 * Führt den Abschnitt int:projekt-statisch mit dem Stand in `zustand` aus. Das
 * Skript erbt nur PATH, HOME und `geerbt`, damit kein Token des Rechners in
 * den Stand und in die Meldung eines roten Tests gelangt.
 */
const zustandSchreiben = (zustand, geerbt) => spawnSync('bash', [path.join(WURZEL, 'tests', 'e2e', 'run-compose-e2e.sh'), '--nur', 'int:projekt-statisch'], {
  cwd: WURZEL,
  encoding: 'utf8',
  timeout: 60_000,
  env: { PATH: process.env.PATH, HOME: process.env.HOME, PARLWIN_ZUSTAND_DIR: zustand, COMPOSE_PROJECT_NAME: 'parlwin_zustand_test', ...geerbt },
})

/** Die Namen der Variablen, die der Stand setzt; ein roter Test nennt nur sie und keinen Wert. */
const namen = (inhalt) => inhalt.split('\n').map((z) => z.match(/^declare -\S* ([A-Za-z_][A-Za-z0-9_]*)=/)?.[1]).filter(Boolean)

let basis
let vorher

beforeEach(() => {
  basis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-zustand-'))
  vorher = process.env.PARLWIN_REGRESSION_ABLAGE
})

afterEach(() => {
  if (vorher === undefined) delete process.env.PARLWIN_REGRESSION_ABLAGE
  else process.env.PARLWIN_REGRESSION_ABLAGE = vorher
  vi.restoreAllMocks()
  fs.rmSync(basis, { recursive: true, force: true })
})

describe('Stand eines E2E-Stacks: nur für das eigene Konto lesbar', () => {
  it('run-compose-e2e.sh schreibt den Stand mit 0600 in ein Verzeichnis mit 0700, ohne ADMIN_AUTH_B64', () => {
    const zustand = path.join(basis, 'zustand')
    // Ein Stand aus einem früheren Lauf, mit den Rechten der umask. Das
    // App-Passwort hat dort ab_benutzer gesetzt.
    fs.mkdirSync(path.join(zustand, 'ergebnis'), { recursive: true, mode: 0o775 })
    fs.chmodSync(zustand, 0o775)
    fs.writeFileSync(path.join(zustand, 'variablen.sh'), 'declare -g ADMIN_TOKEN="app-passwort-aus-abschnitt"\ndeclare -g ADMIN_AUTH_B64="YWRtaW46Z2VoZWlt"\n', { mode: 0o664 })
    const r = zustandSchreiben(zustand, { ADMIN_AUTH_B64: 'YWRtaW46Z2VoZWlt' })
    expect(r.status, r.stdout + r.stderr).toBe(0)
    const datei = path.join(zustand, 'variablen.sh')
    expect(modus(zustand)).toBe('700')
    expect(modus(path.join(zustand, 'ergebnis'))).toBe('700')
    expect(modus(datei)).toBe('600')
    const inhalt = fs.readFileSync(datei, 'utf8')
    expect(namen(inhalt), 'die späteren Abschnitte brauchen das App-Passwort').toContain('ADMIN_TOKEN')
    expect(inhalt.includes('ADMIN_TOKEN="app-passwort-aus-abschnitt"'), 'ADMIN_TOKEN trägt den Wert aus dem Stand').toBe(true)
    expect(namen(inhalt)).not.toContain('ADMIN_AUTH_B64')
    expect(modus(path.join(zustand, 'ergebnis', 'int_projekt-statisch'))).toBe('600')
  })

  it('run-compose-e2e.sh schreibt keine geerbte Variable in den Stand, nur die, die das Skript setzt', () => {
    const zustand = path.join(basis, 'zustand')
    fs.mkdirSync(zustand, { recursive: true })
    fs.writeFileSync(path.join(zustand, 'variablen.sh'), 'declare -g MITGLIED_TOKEN="app-passwort-aus-abschnitt"\n')
    const r = zustandSchreiben(zustand, { GITHUB_TOKEN: 'geerbter-token-des-rechners', ADMIN_TOKEN: 'geerbtes-app-passwort' })
    expect(r.status, r.stdout + r.stderr).toBe(0)
    const inhalt = fs.readFileSync(path.join(zustand, 'variablen.sh'), 'utf8')
    expect(namen(inhalt).filter((n) => ['GITHUB_TOKEN', 'ADMIN_TOKEN'].includes(n)), 'geerbte Variablen im Stand').toEqual([])
    expect(inhalt.includes('geerbter-token-des-rechners'), 'der geerbte Token steht im Stand').toBe(false)
    expect(inhalt.includes('geerbtes-app-passwort'), 'das geerbte App-Passwort steht im Stand').toBe(false)
    expect(namen(inhalt), 'was ein früherer Abschnitt gesetzt hat, bleibt im Stand').toContain('MITGLIED_TOKEN')
  })

  it('ausfuehren legt eine Ausgabedatei, etwa die Sicherung der Datenbank, mit 0600 an', async () => {
    process.env.PARLWIN_REGRESSION_ABLAGE = path.join(basis, 'ablage')
    vi.resetModules()
    const { ausfuehren } = await import('../../../../tests/regression/hilfen.mjs')
    const datei = path.join(basis, 's0.sql')
    const r = await ausfuehren('node', ['-e', 'process.stdout.write("-- Sicherung")'], { ausgabe: datei, still: true })
    expect(r.code).toBe(0)
    expect(fs.readFileSync(datei, 'utf8')).toBe('-- Sicherung')
    expect(modus(datei)).toBe('600')
  })

  it('das Verzeichnis eines Stacks unter tests/.regression steht auf 0700', async () => {
    process.env.PARLWIN_REGRESSION_ABLAGE = path.join(basis, 'ablage')
    // Das Verzeichnis eines früheren Laufs, mit den Rechten der umask.
    fs.mkdirSync(path.join(basis, 'ablage', 'parlwin_e2e_chromium'), { recursive: true })
    fs.chmodSync(path.join(basis, 'ablage', 'parlwin_e2e_chromium'), 0o775)
    vi.resetModules()
    const stack = await import('../../../../tests/regression/stack.mjs')
    vi.spyOn(console, 'log').mockImplementation(() => {})
    stack.werkzeug.lesen = (befehl) => ({ code: 0, stdout: befehl === 'bash' ? 'NEXTCLOUD_DB_PASSWORD=x\n' : 'abc\n', stderr: '' })
    stack.werkzeug.ausfuehren = async () => ({ code: 0 })
    await stack.pwContainerStarten('chromium')
    expect(modus(path.join(basis, 'ablage', 'parlwin_e2e_chromium'))).toBe('700')
  })

  it('die Sitzung des Design-Laufs liegt nicht im Verzeichnis der Bilder', () => {
    const quelle = fs.readFileSync(path.join(WURZEL, '.claude', 'design-capture.spec.js'), 'utf8')
    const zeile = quelle.match(/^const SITZUNG = (.+)$/m)
    expect(zeile, 'design-capture.spec.js setzt SITZUNG nicht').not.toBeNull()
    expect(zeile[1], 'die Sitzung liegt unter PW_DESIGN_OUT, und bild() kopiert sie mit den Bildern heraus').not.toMatch(/\bAUS\b|PW_DESIGN_OUT/)
  })
})
