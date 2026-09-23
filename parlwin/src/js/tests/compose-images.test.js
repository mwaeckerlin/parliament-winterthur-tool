import { describe, it, expect } from 'vitest'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Vertrag über die Abbilder der beiden Compose-Dateien.
 *
 * `docker-compose.yml` ist der Entwicklungs-Stack, `example/docker-compose.yml`
 * die Auslieferung. Beide beschreiben dieselben Dienste, und beide werden von
 * Hand gepflegt: Die Auslieferung lief in keinem Test je hoch, und deshalb fiel
 * dort ein falsches Abbild jahrelang niemandem auf.
 *
 * Zwei Regeln, beide am 01.09.2026 aus der Produktion gemeldet:
 *
 * 1. Ein Abbild im Namensraum dieses Projekts existiert nur, wenn ein
 *    `Dockerfile.<Marke>` im Wurzelverzeichnis es baut. Docker Hub baut genau
 *    diese Dateien; ein erfundener Name lässt sich nirgends herunterladen.
 * 2. Dieselbe Rolle trägt in beiden Stacks dasselbe Abbild. Die Auslieferung
 *    nahm für nginx das Basisabbild `mwaeckerlin/nextcloud:nginx`, während alle
 *    Tests gegen das eigene `:nginx` liefen. Getestet wurde damit ein anderes
 *    Abbild als ausgeliefert, und der Reverse-Proxy `/ws/parlwin/` dieses
 *    Projekts lag in der Produktion gar nicht vor (die Echtzeit-Verbindung kam
 *    über die generische Regel `/ws/<appid>/` der Basis zustande, deshalb fiel
 *    es nie auf).
 */

const wurzel = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')
const NAMENSRAUM = 'mwaeckerlin/parliament-winterthur-tool'

/** Alle Abbild-Angaben einer Compose-Datei, je Dienst. */
function abbilder(datei) {
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

const DATEIEN = ['docker-compose.yml', 'example/docker-compose.yml']

describe('Abbilder der Compose-Dateien', () => {
  it.each(DATEIEN)('%s: jedes eigene Abbild wird von einem Dockerfile gebaut', (datei) => {
    const ohneDockerfile = Object.entries(abbilder(datei))
      .filter(([, bild]) => bild.startsWith(`${NAMENSRAUM}:`))
      .map(([dienst, bild]) => ({ dienst, bild, marke: bild.split(':')[1] }))
      .filter(({ marke }) => !existsSync(resolve(wurzel, `Dockerfile.${marke}`)))
      .map(({ dienst, bild }) => `${dienst} → ${bild}`)

    expect(ohneDockerfile, 'Abbild im eigenen Namensraum ohne Dockerfile im Wurzelverzeichnis').toEqual([])
  })

  it('dieselbe Rolle trägt in beiden Stacks dasselbe Abbild', () => {
    const dev = abbilder('docker-compose.yml')
    const auslieferung = abbilder('example/docker-compose.yml')
    const abweichend = ['nextcloud-nginx', 'nextcloud-php-fpm', 'parlwin-realtime']
      .filter(dienst => dev[dienst] !== auslieferung[dienst])
      .map(dienst => `${dienst}: ${dev[dienst]} gegen ${auslieferung[dienst]}`)

    expect(abweichend, 'Entwicklung und Auslieferung nehmen für denselben Dienst verschiedene Abbilder').toEqual([])
  })

  it('die Auslieferung nimmt für nginx das Abbild mit dem /ws/parlwin/-Proxy', () => {
    expect(abbilder('example/docker-compose.yml')['nextcloud-nginx']).toBe(`${NAMENSRAUM}:nginx`)
  })
})
