/**
 * Jeder Port, den eine Compose-Datei dieses Projekts veröffentlicht, nimmt die
 * Adresse des Rechners aus `BIND_ADDRESS`.
 *
 * Der Auslieferungs-Stack in `example/` und der Entwicklungs-Stack schreiben
 * `${BIND_ADDRESS:+${BIND_ADDRESS:-}:}`: Ohne Angabe trägt der Port keine
 * Adresse, und Docker veröffentlicht ihn wie ein `- "80:80"` auf allen
 * Adressen, IPv4 und IPv6, sodass eine bestehende Auslieferung nach einem
 * Neustart erreichbar bleibt. Mit Angabe erscheint er nur auf dieser Adresse.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')
const DATEIEN = [
  ['docker-compose.yml', '${BIND_ADDRESS:+${BIND_ADDRESS:-}:}'],
  ['example/docker-compose.yml', '${BIND_ADDRESS:+${BIND_ADDRESS:-}:}'],
]

/** Die Einträge aller Listen `ports:` eines YAML-Texts, ohne Anführungszeichen. */
function ports(text) {
  const gefunden = []
  let einzug = null
  for (const zeile of text.split('\n')) {
    const kopf = zeile.match(/^(\s*)ports:\s*$/)
    if (kopf) { einzug = kopf[1].length; continue }
    if (einzug === null || /^\s*(#.*)?$/.test(zeile)) { continue }
    const eintrag = zeile.match(/^(\s*)-\s*(.+?)\s*$/)
    if (eintrag && eintrag[1].length > einzug) {
      gefunden.push(eintrag[2].replace(/^["']|["']$/g, ''))
    } else {
      einzug = null
    }
  }
  return gefunden
}

/** Die Einträge, die nicht mit der verlangten Form beginnen. */
function abweichend(liste, form) {
  return liste.filter((eintrag) => !eintrag.startsWith(form))
}

describe('Veröffentlichte Ports', () => {
  it.each(DATEIEN)('%s veröffentlicht jeden Port mit %s', (datei, form) => {
    const liste = ports(readFileSync(resolve(WURZEL, datei), 'utf8'))
    expect(liste.length, `${datei} veröffentlicht keinen Port`).toBeGreaterThan(0)
    expect(abweichend(liste, form), `${datei}: Port ohne ${form}`).toEqual([])
  })

  it('ein Eintrag ohne Adresse und ein Eintrag mit fester Adresse werden erkannt', () => {
    const liste = ports('services:\n  x:\n    ports:\n      # Kommentar\n      - "80:80"\n      - "${BIND_ADDRESS:-127.0.0.1}:443:443"\n      - "${BIND_ADDRESS:+${BIND_ADDRESS:-}:}8443:443"\n    networks:\n      - netz\n')
    expect(liste).toEqual(['80:80', '${BIND_ADDRESS:-127.0.0.1}:443:443', '${BIND_ADDRESS:+${BIND_ADDRESS:-}:}8443:443'])
    expect(abweichend(liste, DATEIEN[1][1])).toEqual(['80:80', '${BIND_ADDRESS:-127.0.0.1}:443:443'])
  })

  it('die Auslieferung bleibt auf allen Adressen: example/.env.sample und die Anleitung im README nennen BIND_ADDRESS nur als Beschränkung', () => {
    const muster = readFileSync(resolve(WURZEL, 'example/.env.sample'), 'utf8')
    expect(muster).toMatch(/^# BIND_ADDRESS=127\.0\.0\.1$/m)
    expect(muster).not.toMatch(/^BIND_ADDRESS=/m)
    const readme = readFileSync(resolve(WURZEL, 'README.md'), 'utf8')
    const anleitung = readme.slice(readme.indexOf('##### Konfigurationsdatei anlegen'))
    const block = anleitung.slice(0, anleitung.indexOf('EOF\n```'))
    expect(block).toContain('HOST=')
    expect(block).not.toMatch(/^BIND_ADDRESS=/m)
    expect(anleitung).toContain('`BIND_ADDRESS=<Adresse>`')
  })
})
