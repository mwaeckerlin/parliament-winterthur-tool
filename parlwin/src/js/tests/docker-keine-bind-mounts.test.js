/**
 * Container dürfen kein Verzeichnis des Arbeitsplatzrechners eingeblendet bekommen.
 *
 * Ein Bind-Mount koppelt den Container an den lokalen Arbeitsstand: parallele
 * Änderungen verfälschen einen laufenden Test, und der Container schreibt mit
 * seinen eigenen Rechten in die Arbeitskopie. Genau so hat ein Testlauf einmal die
 * lokalen Abhängigkeiten (node_modules) gelöscht. Alles, was ein Container braucht,
 * wird beim Bauen ins Abbild kopiert; Ergebnisse werden danach herauskopiert.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')

const COMPOSE_DATEIEN = [
  'docker-compose.yml',
  'tests/e2e/docker-compose.e2e.yml',
  'example/docker-compose.yml',
]

// Ein Volume-Eintrag ist ein Bind-Mount, wenn seine Quelle ein Pfad ist: `- .:/work`,
// `- ./x:/y`, `- /srv/x:/y`, `- ~/x:/y`. Benannte Volumes (`- daten:/var/lib`)
// beginnen mit einem Buchstaben und sind erlaubt.
const BIND_MOUNT = /^\s*-\s*["']?[.~/][^\s:"']*:\s*\//

describe('Docker: keine Host-Verzeichnisse in Containern', () => {
  it.each(COMPOSE_DATEIEN)('%s bindet kein Verzeichnis des Rechners ein', (datei) => {
    const zeilen = readFileSync(resolve(WURZEL, datei), 'utf8').split('\n')
    const gefunden = zeilen
      .map((zeile, nr) => ({ zeile, nr: nr + 1 }))
      .filter(({ zeile }) => BIND_MOUNT.test(zeile) || /^\s*type:\s*bind\b/.test(zeile))
      .map(({ zeile, nr }) => `${datei}:${nr}: ${zeile.trim()}`)
    expect(gefunden).toEqual([])
  })

  it('die Routing-Konfiguration des Edge-Proxy steckt im Abbild statt im Mount', () => {
    const dockerfile = readFileSync(resolve(WURZEL, 'example/traefik/Dockerfile'), 'utf8')
    expect(dockerfile).toMatch(/^COPY\s+dynamic\.yml\s+\/etc\/traefik\/dynamic\.yml$/m)
    // ADD kann Archive entpacken und URLs laden — für eine Datei ist COPY der Standard.
    expect(dockerfile).not.toMatch(/^ADD\s/m)
  })

  it('der Browser-Testlauf bringt seine Tests im Abbild mit und läuft ohne besondere Rechte', () => {
    const dockerfile = readFileSync(
      resolve(WURZEL, 'tests/e2e/Dockerfile.playwright'), 'utf8')
    expect(dockerfile).toMatch(/^COPY\s+--chown=pwuser:pwuser\s+\.\s+\/work\/$/m)
    expect(dockerfile).toMatch(/^USER\s+pwuser$/m)
  })
})
