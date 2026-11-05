// Meldet jeden vitest-Fall vor seinem Beginn und nach seinem Ende als Zeile
// «##VTJ <json>»; tests/regression.mjs schreibt daraus sein Journal. Die
// Schnittstelle ist die der Reporter von vitest 4 (onTestCaseReady,
// onTestCaseResult). Kommen keine Zeilen, gilt die Gruppe als rot.
import path from 'node:path'

const WURZEL = process.cwd()

function kennung(testCase) {
  return `js:${path.relative(WURZEL, testCase.module.moduleId)}::${testCase.fullName}`
}

function schreiben(eintrag) {
  process.stdout.write(`##VTJ ${JSON.stringify(eintrag)}\n`)
}

export default class JournalReporter {
  onTestCaseReady(testCase) {
    schreiben({ ereignis: 'beginn', id: kennung(testCase), datei: path.relative(WURZEL, testCase.module.moduleId), name: testCase.name })
  }

  onTestCaseResult(testCase) {
    const r = testCase.result()
    const ergebnis = r.state === 'passed' ? 'bestanden' : r.state === 'skipped' ? 'uebersprungen' : 'rot'
    schreiben({
      ereignis: 'ende',
      id: kennung(testCase),
      ergebnis,
      dauer_ms: testCase.diagnostic()?.duration ?? null,
      fehler: (r.errors || []).map((e) => String(e.message || '').split('\n')[0]).slice(0, 3),
    })
  }
}
