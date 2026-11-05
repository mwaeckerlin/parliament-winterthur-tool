import { describe, it, expect } from 'vitest'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Vertrag über die Images der beiden Compose-Dateien.
 *
 * `docker-compose.yml` ist der Entwicklungs-Stack, `example/docker-compose.yml`
 * die Auslieferung. Beide beschreiben dieselben Dienste, und beide werden von
 * Hand gepflegt: Die Auslieferung lief in keinem Test je hoch, und deshalb fiel
 * dort ein falsches Image jahrelang niemandem auf.
 *
 * Drei Regeln:
 *
 * 1. Ein Image im Namensraum dieses Projekts existiert nur, wenn ein
 *    `Dockerfile.<Marke>` im Wurzelverzeichnis es baut. Docker Hub baut genau
 *    diese Dateien; ein erfundener Name lässt sich nirgends herunterladen.
 * 2. Dieselbe Rolle trägt in beiden Stacks dasselbe Image. Die Auslieferung
 *    nahm für nginx das Basisimage `mwaeckerlin/nextcloud:nginx`, während alle
 *    Tests gegen das eigene `:nginx` liefen (gemeldet am 01.09.2026). Getestet
 *    wurde damit ein anderes Image als ausgeliefert, und der Reverse-Proxy
 *    `/ws/parlwin/` dieses Projekts lag in der Produktion gar nicht vor.
 * 3. Die Auslieferung bindet zusätzlich auf die Version: `nginx-v1.9.10` statt
 *    der rollenden Marke `nginx`. Ein Neustart holt damit dieselbe Fassung, die
 *    beim Aufsetzen geprüft wurde, und nicht die neueste. Der Entwicklungs-Stack
 *    bleibt auf der rollenden Marke, weil er das baut, was gerade entsteht.
 */

const wurzel = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')
const NAMENSRAUM = 'mwaeckerlin/parliament-winterthur-tool'
const DIENSTE = ['nextcloud-nginx', 'nextcloud-php-fpm', 'parlwin-realtime']

/** Alle Image-Angaben einer Compose-Datei, je Dienst. */
function imageer(datei) {
  const zeilen = readFileSync(resolve(wurzel, datei), 'utf8').split('\n')
  const gefunden = {}
  let dienst = null
  for (const zeile of zeilen) {
    const dienstZeile = zeile.match(/^ {2}([a-z0-9-]+):\s*$/)
    if (dienstZeile) { dienst = dienstZeile[1] }
    const bildZeile = zeile.match(/^\s+image:\s*(\S+)\s*$/)
    if (bildZeile && dienst) { gefunden[dienst] = bildZeile[1] }
  }
  return gefunden
}

/** Die Version, die dieses Projekt gerade trägt. */
function version() {
  return JSON.parse(readFileSync(resolve(wurzel, 'package.json'), 'utf8')).version
}

/** Die Marke ohne ihren Versionsteil: «nginx-v1.9.10» → «nginx». */
function ohneVersion(marke) {
  return marke.replace(/-v\d+(?:\.\d+)*$/, '')
}

const DATEIEN = ['docker-compose.yml', 'example/docker-compose.yml']

describe('Images der Compose-Dateien', () => {
  it.each(DATEIEN)('%s: jedes eigene Image wird von einem Dockerfile gebaut', (datei) => {
    const ohneDockerfile = Object.entries(imageer(datei))
      .filter(([, bild]) => bild.startsWith(`${NAMENSRAUM}:`))
      .map(([dienst, bild]) => ({ dienst, bild, marke: ohneVersion(bild.split(':')[1]) }))
      .filter(({ marke }) => !existsSync(resolve(wurzel, `Dockerfile.${marke}`)))
      .map(({ dienst, bild }) => `${dienst} → ${bild}`)

    expect(ohneDockerfile, 'Image im eigenen Namensraum ohne Dockerfile im Wurzelverzeichnis').toEqual([])
  })

  it('dieselbe Rolle trägt in beiden Stacks dasselbe Image', () => {
    const dev = imageer('docker-compose.yml')
    const auslieferung = imageer('example/docker-compose.yml')
    const abweichend = DIENSTE
      .filter(dienst => ohneVersion(auslieferung[dienst] || '') !== dev[dienst])
      .map(dienst => `${dienst}: ${dev[dienst]} gegen ${auslieferung[dienst]}`)

    expect(abweichend, 'Entwicklung und Auslieferung nehmen für denselben Dienst verschiedene Images').toEqual([])
  })

  it('die Auslieferung bindet jeden Dienst auf die Version dieses Projekts', () => {
    const dev = imageer('docker-compose.yml')
    const auslieferung = imageer('example/docker-compose.yml')
    const erwartet = {}
    for (const dienst of DIENSTE) {
      const marke = ohneVersion((dev[dienst] || '').split(':')[1] || '')
      erwartet[dienst] = `${NAMENSRAUM}:${marke}-v${version()}`
    }
    const gefunden = Object.fromEntries(DIENSTE.map(d => [d, auslieferung[d]]))

    expect(gefunden, 'die Auslieferung muss auf «<Marke>-v<Version>» binden, nicht auf die rollende Marke')
      .toEqual(erwartet)
  })

  it('die Auslieferung nimmt für nginx das Image mit dem /ws/parlwin/-Proxy', () => {
    expect(ohneVersion(imageer('example/docker-compose.yml')['nextcloud-nginx'])).toBe(`${NAMENSRAUM}:nginx`)
  })

  it('das Image der PHPUnit-Läufe wird über docker compose gebaut, unter dem Namen, den das Skript startet', () => {
    const skript = readFileSync(resolve(wurzel, 'tests/php-suite.sh'), 'utf8')
    expect(skript).toMatch(/^docker compose --file tests\/docker-compose\.php-test\.yml build php-test /m)
    expect(skript, 'tests/php-suite.sh baut ein Image an docker compose vorbei').not.toMatch(/^\s*docker build\b/m)
    const name = skript.match(/^IMAGE="([^"]+)"$/m)
    expect(name, 'tests/php-suite.sh nennt kein IMAGE').not.toBeNull()
    const compose = readFileSync(resolve(wurzel, 'tests/docker-compose.php-test.yml'), 'utf8')
    expect(compose).toMatch(/^\s+dockerfile: Dockerfile\.php-test$/m)
    expect(imageer('tests/docker-compose.php-test.yml')['php-test']).toBe(name[1])
  })
})
