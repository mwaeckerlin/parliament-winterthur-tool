// @vitest-environment node
/**
 * `.claude/design-scan.sh` endet nur dann mit 0, wenn die Aufnahme mit 0 endet
 * und Bilder liefert. Das Skript läuft hier ganz, mit einem Programm `docker`
 * vorne im PATH, das jeden Aufruf annimmt und nur beim Herausholen der Bilder
 * etwas tut: Es legt so viele Bilder ab, wie FAKE_BILDER sagt, und lässt die
 * Aufnahme mit FAKE_AUFNAHME_RC enden.
 */
import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const SKRIPT = path.join(WURZEL, '.claude', 'design-scan.sh')

const DOCKER = `#!/usr/bin/env bash
if [[ "$1" == "run" && "$2" == "--name" ]]; then exit "\${FAKE_AUFNAHME_RC:-0}"; fi
if [[ "$1" == "cp" && "$2" == *":/work/bilder" ]]; then
  [[ "\${FAKE_BILDER:-0}" -gt 0 ]] || exit 1
  mkdir -p "$3"
  for i in $(seq 1 "\${FAKE_BILDER}"); do printf 'png' > "$3/bild-$i.png"; done
fi
exit 0
`

let werkzeuge
let ziel

function lauf(env) {
  fs.rmSync(ziel, { recursive: true, force: true })
  return spawnSync('bash', [SKRIPT, ziel], {
    cwd: WURZEL,
    encoding: 'utf8',
    timeout: 60_000,
    env: { ...process.env, PATH: `${werkzeuge}:${process.env.PATH}`, PARLWIN_DESIGN_PLAYWRIGHT: '1', ...env },
  })
}

describe('design-scan.sh: Exit-Code nach Aufnahme und Bildern', () => {
  beforeAll(() => {
    const basis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-design-scan-'))
    werkzeuge = path.join(basis, 'bin')
    ziel = path.join(basis, 'ziel')
    fs.mkdirSync(werkzeuge)
    fs.writeFileSync(path.join(werkzeuge, 'docker'), DOCKER, { mode: 0o755 })
  })

  afterAll(() => {
    fs.rmSync(path.dirname(werkzeuge), { recursive: true, force: true })
  })

  it('endet mit 0, wenn die Aufnahme mit 0 endet und Bilder liefert', () => {
    const r = lauf({ FAKE_AUFNAHME_RC: '0', FAKE_BILDER: '3' })
    expect(r.stdout).toContain('[design] Bilder: 3')
    expect(r.status, r.stdout + r.stderr).toBe(0)
  })

  it('endet nicht mit 0, wenn die Aufnahme kein Bild liefert', () => {
    const r = lauf({ FAKE_AUFNAHME_RC: '0', FAKE_BILDER: '0' })
    expect(r.stdout).toContain('[design] Bilder: 0')
    expect(r.status, r.stdout + r.stderr).not.toBe(0)
  })

  it('endet nicht mit 0, wenn die Aufnahme scheitert, auch mit Bildern', () => {
    const r = lauf({ FAKE_AUFNAHME_RC: '1', FAKE_BILDER: '2' })
    expect(r.stdout).toContain('[design] Bilder: 2')
    expect(r.status, r.stdout + r.stderr).not.toBe(0)
  })
})
