// Gemeinsame Hilfen des Laufs: Prozesse starten und ihre Ausgabe zeilenweise
// lesen, Protokoll mit Uhrzeit, Pfade unter tests/.regression.
import { spawn, spawnSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

export const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..')
// PARLWIN_REGRESSION_ABLAGE legt Journal, Protokoll und Zusatzdatei für Compose
// woanders ab; die Komponententests des Laufs schreiben so in ein eigenes
// Verzeichnis und nie in das Journal eines laufenden Laufs.
export const ABLAGE = process.env.PARLWIN_REGRESSION_ABLAGE || path.join(WURZEL, 'tests', '.regression')
fs.mkdirSync(ABLAGE, { recursive: true })

const PROTOKOLL = path.join(ABLAGE, 'lauf.log')

export function uhrzeit() {
  return new Date().toISOString()
}

/** Eine Zeile ins Protokoll und auf die Konsole. */
export function melden(text) {
  const zeile = `${uhrzeit()} ${text}`
  console.log(zeile)
  fs.appendFileSync(PROTOKOLL, `${zeile}\n`)
}

/** Fehler des Laufs selbst (nicht eines Tests): mit Ursache, als Ausnahme. */
export class LaufFehler extends Error {
  constructor(meldung, details = {}) {
    super(meldung)
    this.name = 'LaufFehler'
    this.details = details
  }
}

/**
 * Startet einen Befehl ohne Shell. Jede Zeile von stdout und stderr geht an
 * `zeile(text, kanal)` und in `protokoll` (Datei), wenn angegeben. `eingabe`
 * ist ein Dateipfad, dessen Inhalt nach stdin geht; `ausgabe` ein Dateipfad,
 * in den stdout unverändert geschrieben wird (dann keine Zeilen für stdout).
 * Die Ausgabedatei, etwa die Sicherung der Datenbank eines Stacks, kann nur
 * das eigene Konto lesen.
 */
export function ausfuehren(befehl, argumente, { env = {}, cwd = WURZEL, zeile = null, protokoll = null, eingabe = null, ausgabe = null, still = false } = {}) {
  return new Promise((fertig, fehler) => {
    const kind = spawn(befehl, argumente, {
      cwd,
      env: { ...process.env, ...env },
      stdio: [eingabe ? 'pipe' : 'ignore', 'pipe', 'pipe'],
    })
    const log = protokoll ? fs.createWriteStream(protokoll, { flags: 'a' }) : null
    // `mode` wirkt nur auf eine neue Datei; eine bestehende bekommt die Rechte vorher.
    if (ausgabe && fs.existsSync(ausgabe)) fs.chmodSync(ausgabe, 0o600)
    const ziel = ausgabe ? fs.createWriteStream(ausgabe, { mode: 0o600 }) : null
    const reste = { stdout: '', stderr: '' }
    const verarbeiten = (kanal) => (stueck) => {
      const text = stueck.toString()
      if (log) log.write(text)
      if (kanal === 'stdout' && ziel) {
        ziel.write(stueck)
        return
      }
      reste[kanal] += text
      const zeilen = reste[kanal].split('\n')
      reste[kanal] = zeilen.pop()
      for (const z of zeilen) {
        if (zeile) zeile(z, kanal)
        if (!still && !z.startsWith('##')) process.stdout.write(`${z}\n`)
      }
    }
    kind.stdout.on('data', verarbeiten('stdout'))
    kind.stderr.on('data', verarbeiten('stderr'))
    if (eingabe) fs.createReadStream(eingabe).pipe(kind.stdin)
    kind.on('error', (e) => fehler(new LaufFehler(`${befehl} liess sich nicht starten: ${e.message}`, { befehl, argumente })))
    kind.on('close', (code, signal) => {
      for (const kanal of ['stdout', 'stderr']) {
        if (reste[kanal] && zeile) zeile(reste[kanal], kanal)
      }
      const schliessen = []
      if (log) schliessen.push(new Promise((r) => log.end(r)))
      if (ziel) schliessen.push(new Promise((r) => ziel.end(r)))
      Promise.all(schliessen).then(() => fertig({ code: code ?? (signal ? 128 : 1), signal }))
    })
  })
}

/** Kurzer Befehl, dessen Ausgabe als Text gebraucht wird. */
export function lesen(befehl, argumente, { env = {}, cwd = WURZEL } = {}) {
  const r = spawnSync(befehl, argumente, { cwd, env: { ...process.env, ...env }, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 })
  return { code: r.status ?? 1, stdout: r.stdout || '', stderr: r.stderr || '' }
}

export const warte = (ms) => new Promise((weiter) => setTimeout(weiter, ms))

/** Schreibt eine Datei atomar: erst daneben, dann umbenannt. */
export function atomarSchreiben(datei, inhalt) {
  fs.mkdirSync(path.dirname(datei), { recursive: true })
  const neu = `${datei}.${process.pid}.neu`
  fs.writeFileSync(neu, inhalt)
  fs.renameSync(neu, datei)
}

export function dauerText(ms) {
  const s = Math.round(ms / 1000)
  return s >= 60 ? `${Math.floor(s / 60)}min ${s % 60}s` : `${s}s`
}
