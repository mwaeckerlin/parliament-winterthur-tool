import { test as basis } from '@playwright/test'
import { Sperrhalter } from './sperre.js'

export { expect, request, devices } from '@playwright/test'

// Die Wartezeit auf die Sperre läuft neben der Zeitgrenze des Tests und hat
// ihre eigene: Die längste Datei der gewöhnlichen Gruppe ist
// budget-datenfluss.spec.js, mit dem Test «Der älteste Jahrgang lässt sich
// importieren und zeigt seine Produktegruppen» von bis zu 600s und den übrigen
// Tests der Datei. Wie lange ein Test hier wirklich wartet, schreibt jede
// Sperre als Zeile «##SPERRE» in die Ausgabe.
const SPERRE_FRIST_MS = 45 * 60_000

function melden(halter, workerIndex, gewartet) {
  if (gewartet > 0) {
    process.stdout.write(`##SPERRE ${JSON.stringify({ datei: halter.datei, worker: workerIndex, gewartet_ms: gewartet })}\n`)
  }
}

/**
 * Steht als erstes `beforeAll` jeder Spec-Datei (`test.beforeAll(sperreVorAllem)`):
 * Ein `beforeAll` einer Datei läuft vor den Fixtures ihres ersten Tests und
 * schreibt mitunter schon globale Einstellungen (navigation-kuerzel-layout.spec.js
 * setzt dort die Kürzel). Die Sperre muss also davor stehen.
 */
export async function sperreVorAllem({ sperrhalter }, testInfo) {
  melden(sperrhalter, testInfo.workerIndex, await sperrhalter.betrete(testInfo.file, Date.now() + SPERRE_FRIST_MS))
}

export const test = basis.extend({
  sperrhalter: [async ({}, verwenden, workerInfo) => {
    const halter = new Sperrhalter(workerInfo.workerIndex)
    await verwenden(halter)
    halter.freigeben()
  }, { scope: 'worker', auto: true }],

  sperre: [async ({ sperrhalter }, verwenden, testInfo) => {
    // Hält der Worker die Sperre der Datei schon (aus sperreVorAllem), kehrt
    // betrete() sofort zurück; sonst nimmt sie diese Stelle vor dem Test.
    melden(sperrhalter, testInfo.workerIndex, await sperrhalter.betrete(testInfo.file, Date.now() + SPERRE_FRIST_MS))
    await verwenden()
  }, { auto: true, timeout: SPERRE_FRIST_MS }],
})
