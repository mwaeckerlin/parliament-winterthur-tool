// @vitest-environment node
/**
 * Die Gruppe composer-audit (tests/run-all.sh) prüft parlwin/composer.lock mit
 * `composer audit --no-dev --locked`. Der Rechner und das PHP-Testimage haben
 * kein composer; der Dienst der Gruppe läuft über Compose in der Stufe php-deps
 * von Dockerfile.php-fpm. Die Gruppe läuft hier ganz, mit einem Programm
 * `docker` vorne im PATH, das jeden Aufruf in eine Datei schreibt und `run`
 * mit FAKE_RC enden lässt.
 */
import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const COMPOSE = 'tests/regression/docker-compose.composer-audit.yml'

const DOCKER = `#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$FAKE_AUFRUFE"
for a in "$@"; do [[ "$a" == "run" ]] && exit "\${FAKE_RC:-0}"; done
exit 0
`

let basis

function gruppe(rc) {
  const aufrufe = path.join(basis, `aufrufe-${rc}`)
  const junit = path.join(basis, `junit-${rc}`)
  const r = spawnSync('bash', [path.join(WURZEL, 'tests', 'run-all.sh'), 'composer-audit'], {
    cwd: WURZEL,
    encoding: 'utf8',
    timeout: 60_000,
    env: { ...process.env, PATH: `${path.join(basis, 'bin')}:${process.env.PATH}`, FAKE_RC: String(rc), FAKE_AUFRUFE: aufrufe, PARLWIN_JUNIT_DIR: junit },
  })
  return { r, aufrufe: fs.existsSync(aufrufe) ? fs.readFileSync(aufrufe, 'utf8').split('\n').filter(Boolean) : [], bericht: fs.readFileSync(path.join(junit, 'composer-audit.xml'), 'utf8') }
}

describe('Gruppe composer-audit', () => {
  beforeAll(() => {
    basis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-composer-audit-'))
    fs.mkdirSync(path.join(basis, 'bin'))
    fs.writeFileSync(path.join(basis, 'bin', 'docker'), DOCKER, { mode: 0o755 })
  })

  afterAll(() => {
    fs.rmSync(basis, { recursive: true, force: true })
  })

  it('baut den Dienst über Compose und führt ihn einmal aus', () => {
    const { r, aufrufe, bericht } = gruppe(0)
    expect(r.status, r.stdout + r.stderr).toBe(0)
    expect(aufrufe).toEqual([`compose --file ${COMPOSE} build composer-audit`, `compose --file ${COMPOSE} run --rm composer-audit`])
    expect(bericht).toContain('failures="0"')
  })

  it('ist rot, wenn composer audit eine Lücke meldet', () => {
    const { r, bericht } = gruppe(1)
    expect(r.status).toBe(1)
    expect(bericht).toContain('failures="1"')
    expect(bericht).toContain('name="composer-audit-ausgeliefert"')
  })

  it('der Dienst prüft die composer.lock ohne Entwicklungswerkzeuge in der Stufe mit composer', () => {
    const datei = fs.readFileSync(path.join(WURZEL, COMPOSE), 'utf8')
    expect(datei).toMatch(/^\s+dockerfile: Dockerfile\.php-fpm$/m)
    expect(datei).toMatch(/^\s+target: php-deps$/m)
    expect(datei).toMatch(/^\s+working_dir: \/deps$/m)
    expect(datei).toMatch(/^\s+command: \["composer", "audit", "--no-dev", "--locked", "--no-interaction"\]$/m)
    // Die Stufe php-deps samt den Stufen, auf denen sie aufbaut.
    const stufen = new Map()
    for (const teil of fs.readFileSync(path.join(WURZEL, 'Dockerfile.php-fpm'), 'utf8').split(/^FROM /m).slice(1)) {
      const [kopf, ...rumpf] = teil.split('\n')
      const m = kopf.match(/^(\S+)(?: AS (\S+))?/)
      if (m[2]) stufen.set(m[2], { basis: m[1], rumpf: rumpf.join('\n') })
    }
    expect(stufen.has('php-deps'), 'Dockerfile.php-fpm hat keine Stufe php-deps').toBe(true)
    let kette = ''
    for (let name = 'php-deps'; stufen.has(name); name = stufen.get(name).basis) kette += `${stufen.get(name).rumpf}\n`
    expect(kette).toMatch(/^RUN \$\{PKG_INSTALL\} composer$/m)
    expect(kette).toMatch(/^WORKDIR \/deps$/m)
    expect(kette).toMatch(/^COPY parlwin\/composer\.lock \.\/composer\.lock$/m)
  })
})
