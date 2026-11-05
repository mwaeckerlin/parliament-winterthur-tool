// @vitest-environment node
/**
 * Die Prüfung int:gegenstelle (tests/e2e/run-compose-e2e.sh) misst am
 * Zugriffsprotokoll von parlwin-quelle, dass der Abgleich dort war. Dasselbe
 * Protokoll enthält die Anfragen der Gruppe quelle (tests/quelle-contract.sh)
 * nach /politbusiness und sechs Dokumenten. Die Prüfung liest darum für den
 * Abgleich nur das Zeitfenster, das aufbau:abgleich festhält, und für die
 * Dokumente nur Anfragen ab seinem Beginn und ausserhalb der Wege, die
 * quelle-contract.sh selbst anfragt.
 *
 * Das Skript läuft hier mit einem Programm `docker` vorne im PATH, das für
 * `docker compose logs` je nach Zeitfenster ein anderes Protokoll ausgibt.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const BEGINN = '2026-11-04T10:00:00.000000000Z'
const ENDE = '2026-11-04T10:05:00.000000000Z'

const zeile = (weg) => `parlwin-quelle-1  | 172.18.0.5 - - [04/Nov/2026:10:01:00 +0000] "GET ${weg} HTTP/1.1" 200 1234 "-" "-"`
const details = Array.from({ length: 120 }, (_, i) => zeile(`/_rte/information/${1000 + i}`))
const beschluesse = Array.from({ length: 12 }, (_, i) => zeile(`/stadtratsbeschluesse/2026-${100 + i}`))
// Was quelle-contract.sh anfragt.
const vertrag = [zeile('/politbusiness'), zeile('/_doc/7242604'), zeile('/_doc/999999'), zeile('/stadtratsbeschluesse')]

const DOCKER = `#!/usr/bin/env bash
if [[ "$1" == "compose" && " $* " == *" logs "* ]]; then
  if [[ " $* " == *" --until "* ]]; then cat "$FAKE_LOG_FENSTER"
  elif [[ " $* " == *" --since "* ]]; then cat "$FAKE_LOG_AB"
  else cat "$FAKE_LOG_ALLES"; fi
fi
exit 0
`

let basis

function pruefen({ fenster, ab, alles }) {
  const zustand = path.join(basis, 'zustand')
  fs.mkdirSync(path.join(zustand, 'ergebnis'), { recursive: true })
  fs.writeFileSync(path.join(zustand, 'ergebnis', 'aufbau_abgleich'), 'bestanden')
  fs.writeFileSync(path.join(zustand, 'variablen.sh'), `declare -g ABGLEICH_BEGINN="${BEGINN}"\ndeclare -g ABGLEICH_ENDE="${ENDE}"\n`)
  for (const [name, inhalt] of Object.entries({ fenster, ab, alles })) fs.writeFileSync(path.join(basis, `${name}.log`), `${inhalt.join('\n')}\n`)
  return spawnSync('bash', [path.join(WURZEL, 'tests', 'e2e', 'run-compose-e2e.sh'), '--nur', 'int:gegenstelle'], {
    cwd: WURZEL,
    encoding: 'utf8',
    timeout: 60_000,
    env: {
      ...process.env,
      PATH: `${path.join(basis, 'bin')}:${process.env.PATH}`,
      PARLWIN_ZUSTAND_DIR: zustand,
      COMPOSE_PROJECT_NAME: 'parlwin_gegenstelle_test',
      FAKE_LOG_FENSTER: path.join(basis, 'fenster.log'),
      FAKE_LOG_AB: path.join(basis, 'ab.log'),
      FAKE_LOG_ALLES: path.join(basis, 'alles.log'),
    },
  })
}

describe('int:gegenstelle liest das Zeitfenster des Abgleichs', () => {
  beforeEach(() => {
    basis = fs.mkdtempSync(path.join(os.tmpdir(), 'parlwin-gegenstelle-'))
    fs.mkdirSync(path.join(basis, 'bin'))
    fs.writeFileSync(path.join(basis, 'bin', 'docker'), DOCKER, { mode: 0o755 })
  })

  afterEach(() => { fs.rmSync(basis, { recursive: true, force: true }) })

  it('besteht, wenn der Abgleich Liste, Detailseiten und Beschlüsse holte und ein Dokument kam', () => {
    const fenster = [zeile('/politbusiness'), ...details, zeile('/stadtratsbeschluesse'), ...beschluesse]
    const r = pruefen({ fenster, ab: [...fenster, zeile('/_doc/5550001'), ...vertrag], alles: [...fenster, zeile('/_doc/5550001'), ...vertrag] })
    expect(r.status, r.stdout + r.stderr).toBe(0)
  })

  it('ist rot, wenn nur quelle-contract.sh nach /politbusiness fragte', () => {
    const fenster = [...details, zeile('/stadtratsbeschluesse'), ...beschluesse]
    const r = pruefen({ fenster, ab: [...fenster, zeile('/_doc/5550001'), ...vertrag], alles: [...fenster, zeile('/_doc/5550001'), ...vertrag] })
    expect(r.status, 'die Anfrage von quelle-contract.sh zählte als Anfrage des Abgleichs').not.toBe(0)
    expect(r.stderr).toContain('/politbusiness')
  })

  it('ist rot, wenn nur quelle-contract.sh ein Dokument holte', () => {
    const fenster = [zeile('/politbusiness'), ...details, zeile('/stadtratsbeschluesse'), ...beschluesse]
    const r = pruefen({ fenster, ab: [...fenster, ...vertrag], alles: [...fenster, ...vertrag] })
    expect(r.status, 'die Dokumente von quelle-contract.sh zählten als Dokumente des Abgleichs').not.toBe(0)
    expect(r.stderr).toContain('kein Dokument')
  })
})
