// @vitest-environment node
/**
 * Die Prüfung `int:einrichtungsuebersicht` (tests/e2e/run-compose-e2e.sh) lässt
 * die Punkte der Einrichtungsübersicht von Nextcloud zu, die der Container
 * nicht beheben kann. Die Prüfung der Internetverbindung gehört dazu: Sie ruft
 * vier Webseiten ausserhalb auf, und ihr Ergebnis hängt am Netz des Rechners,
 * nicht am Stack. Gemessen wird mit dem Muster SETUP_ERLAUBT aus dem Skript und
 * dem Filter, den das Skript mit jq auf die Ausgabe von
 * `occ setupchecks --output=json` anwendet: `select(.value.severity !=
 * "success") | select((.key | test($erlaubt)) | not)`.
 */
import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const skript = fs.readFileSync(path.join(WURZEL, 'tests', 'e2e', 'run-compose-e2e.sh'), 'utf8')
const erlaubt = new RegExp(skript.match(/^SETUP_ERLAUBT='([^']*)'/m)[1])

/** Die Punkte, die das Skript als offen meldet. */
function offen(ausgabe) {
  return Object.entries(ausgabe).flatMap(([, pruefungen]) => Object.entries(pruefungen)
    .filter(([, p]) => p.severity !== 'success')
    .filter(([schluessel]) => !erlaubt.test(schluessel))
    .map(([schluessel]) => schluessel))
}

describe('Einrichtungsübersicht im E2E-Stack', () => {
  it('lässt die Warnung der Internetverbindung zu', () => {
    const ausgabe = {
      network: {
        'OCA\\Settings\\SetupChecks\\InternetConnectivity': { name: 'Internet connectivity', severity: 'warning', description: 'This server has no working internet connection: Multiple endpoints could not be reached.' },
      },
    }
    expect(offen(ausgabe)).toEqual([])
  })

  it('meldet weiter jede Warnung, die nicht zugelassen ist', () => {
    const ausgabe = {
      system: {
        'OCA\\Settings\\SetupChecks\\PhpMemoryLimit': { name: 'PHP memory limit', severity: 'error', description: 'The PHP memory limit is below the recommended value.' },
        'OCA\\Settings\\SetupChecks\\CronInfo': { name: 'Cron last run', severity: 'success', description: '' },
      },
    }
    expect(offen(ausgabe)).toEqual(['OCA\\Settings\\SetupChecks\\PhpMemoryLimit'])
  })
})
