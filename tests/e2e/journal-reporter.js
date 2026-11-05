/**
 * Meldet jeden Test vor seinem Beginn und nach seinem Ende als eine Zeile
 * «##PWJ <json>» auf der Standardausgabe. Der Lauf (tests/regression.mjs) liest
 * die Ausgabe von `docker exec` mit und schreibt daraus sein Journal auf dem
 * Rechner; im Container der Tests bleibt nichts, was ein Absturz mitnehmen
 * könnte.
 *
 * Gemeldet wird auch jeder neue Worker: Jeder Worker startet seinen Browser
 * einmal, und Playwright beendet einen Worker nach jedem roten Test. Die Zahl
 * der Worker ist damit die Zahl der Browserstarts des Laufs.
 */

function kennung(test) {
  const [, projekt, datei, ...titel] = test.titlePath()
  return `pw:${projekt}:${datei}::${titel.join(' › ')}`
}

// Das Zeilenende davor schliesst die Zeile, die der Reporter «list» ohne
// Zeilenende stehen lässt; die Marke steht so am Anfang ihrer Zeile.
function schreiben(eintrag) {
  process.stdout.write(`\n##PWJ ${JSON.stringify(eintrag)}\n`)
}

export default class JournalReporter {
  constructor() {
    this.worker = new Set()
  }

  printsToStdio() {
    return false
  }

  onBegin(config, suite) {
    schreiben({
      ereignis: 'inventar',
      worker_hoechstens: config.workers,
      tests: suite.allTests().map((t) => ({ id: kennung(t), projekt: t.parent.project()?.name || '', datei: t.location.file.split('/').pop(), titel: t.title })),
    })
  }

  onTestBegin(test, result) {
    const projekt = test.parent.project()?.name || ''
    const worker = `${projekt}#${result.workerIndex}`
    if (!this.worker.has(worker)) {
      this.worker.add(worker)
      schreiben({ ereignis: 'worker', projekt, worker: result.workerIndex })
    }
    schreiben({ ereignis: 'beginn', id: kennung(test), worker: result.workerIndex })
  }

  onTestEnd(test, result) {
    const fehler = (result.errors || []).map((e) => String(e.message || '').split('\n')[0]).filter(Boolean)
    schreiben({
      ereignis: 'ende',
      id: kennung(test),
      ergebnis: result.status === 'passed' ? 'bestanden' : result.status === 'interrupted' ? 'unterbrochen' : 'rot',
      status: result.status,
      dauer_ms: result.duration,
      fehler: fehler.slice(0, 3),
    })
  }

  onStdOut(chunk) {
    const text = String(chunk)
    for (const zeile of text.split('\n')) {
      if (zeile.startsWith('##SPERRE ') || zeile.startsWith('##ANMELDUNG ')) process.stdout.write(`${zeile}\n`)
    }
  }

  onEnd(result) {
    schreiben({ ereignis: 'schluss', status: result.status, dauer_ms: result.duration, worker_gestartet: this.worker.size })
  }
}
