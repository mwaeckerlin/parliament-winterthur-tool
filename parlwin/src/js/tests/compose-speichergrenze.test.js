// @vitest-environment node
/**
 * Die Speichergrenze des Containers nextcloud-php-fpm (`mem_limit`) liegt in
 * der Auslieferung über der Speichergrenze, die PHP einem einzelnen Prozess
 * lässt (`PARLWIN_PHP_MEMORY_LIMIT`), mit einer Reserve für den Hauptprozess
 * von php-fpm und die übrigen Prozesse. Sonst beendet der Kernel einen Import,
 * bevor PHP seine eigene Meldung schreiben kann, und nginx antwortet mit 502.
 * Geprüft wird über tests/compose-contract.sh, an der echten Datei und an
 * einer Kopie mit zu kleinem `mem_limit`.
 */
import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const COMPOSE = path.join(WURZEL, 'example', 'docker-compose.yml')
const MUSTER = path.join(WURZEL, 'example', '.env.sample')
let basis

function vertrag(compose, muster) {
  return spawnSync('bash', [path.join(WURZEL, 'tests', 'compose-contract.sh'), compose, muster], { cwd: WURZEL, encoding: 'utf8', timeout: 60_000 })
}

describe('Auslieferung: Speichergrenze des Containers über der von PHP', () => {
  beforeAll(() => { basis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-compose-')) })
  afterAll(() => { fs.rmSync(basis, { recursive: true, force: true }) })

  it('die ausgelieferte Compose-Datei hält die Reserve ein', () => {
    const r = vertrag(COMPOSE, MUSTER)
    expect(r.stdout).toMatch(/PASS {2}nextcloud-php-fpm_mem_limit_ueber_php/)
    expect(r.status, r.stdout + r.stderr).toBe(0)
  })

  it('ein mem_limit unter PARLWIN_PHP_MEMORY_LIMIT plus Reserve ist ein Fehler', () => {
    const compose = path.join(basis, 'docker-compose.yml')
    const muster = path.join(basis, '.env.sample')
    const text = fs.readFileSync(COMPOSE, 'utf8').replace(/mem_limit: \$\{PHP_FPM_MEM_LIMIT:-[^}]+\}/, 'mem_limit: ${PHP_FPM_MEM_LIMIT:-1000m}')
    fs.writeFileSync(compose, text)
    fs.writeFileSync(muster, fs.readFileSync(MUSTER, 'utf8').replace(/^# PHP_FPM_MEM_LIMIT=.*$/m, '# PHP_FPM_MEM_LIMIT=1000m'))
    const r = vertrag(compose, muster)
    expect(r.stdout).toMatch(/FAIL {2}nextcloud-php-fpm_mem_limit_ueber_php/)
    expect(r.status).toBe(1)
  })

  it('liest Grössen mit und ohne Einheit', () => {
    const compose = path.join(basis, 'einheiten.yml')
    const muster = path.join(basis, 'einheiten.env')
    const text = fs.readFileSync(COMPOSE, 'utf8')
      .replace(/PARLWIN_PHP_MEMORY_LIMIT: \$\{PARLWIN_PHP_MEMORY_LIMIT:-[^}]+\}/, 'PARLWIN_PHP_MEMORY_LIMIT: ${PARLWIN_PHP_MEMORY_LIMIT:-1G}')
      .replace(/mem_limit: \$\{PHP_FPM_MEM_LIMIT:-[^}]+\}/, 'mem_limit: ${PHP_FPM_MEM_LIMIT:-1200m}')
    fs.writeFileSync(compose, text)
    fs.writeFileSync(muster, fs.readFileSync(MUSTER, 'utf8')
      .replace(/^# PARLWIN_PHP_MEMORY_LIMIT=.*$/m, '# PARLWIN_PHP_MEMORY_LIMIT=1G')
      .replace(/^# PHP_FPM_MEM_LIMIT=.*$/m, '# PHP_FPM_MEM_LIMIT=1200m'))
    const r = vertrag(compose, muster)
    expect(r.stdout, '1G und 1200m: 176MiB Reserve sind zu wenig').toMatch(/FAIL {2}nextcloud-php-fpm_mem_limit_ueber_php/)
  })
})
