// Speicherwächter: misst, was frei ist, bevor ein Stack oder ein lokaler
// Prozess startet, und schreibt den Verlauf alle 30s ins Protokoll.
//
// Container laufen in der cgroup von dockerd und zählen gegen MemAvailable.
// node, vitest und eslint laufen in der cgroup dieses Prozesses, in der
// Entwicklungsumgebung also in deren Scope mit memory.high und memory.max.
import fs from 'node:fs'
import path from 'node:path'
import { ABLAGE, lesen, melden } from './hilfen.mjs'

const VERLAUF = path.join(ABLAGE, 'speicher.jsonl')
// Was neben einem Stack mindestens frei bleibt.
export const RESERVE = 8 * 1024 ** 3

export function memAvailable() {
  const text = fs.readFileSync('/proc/meminfo', 'utf8')
  const m = text.match(/^MemAvailable:\s+(\d+) kB/m)
  return m ? Number(m[1]) * 1024 : 0
}

function eigeneCgroup() {
  try {
    const zeile = fs.readFileSync('/proc/self/cgroup', 'utf8').split('\n').find((z) => z.startsWith('0::'))
    return zeile ? path.join('/sys/fs/cgroup', zeile.slice(3)) : ''
  } catch {
    return ''
  }
}

function wert(datei) {
  try {
    return fs.readFileSync(datei, 'utf8').trim()
  } catch {
    return ''
  }
}

export function scope() {
  const pfad = eigeneCgroup()
  const zahl = (name) => {
    const w = wert(path.join(pfad, name))
    return w === 'max' ? Infinity : w === '' ? null : Number(w)
  }
  const ereignisse = Object.fromEntries(wert(path.join(pfad, 'memory.events')).split('\n').filter(Boolean).map((z) => z.split(' ')).map(([k, v]) => [k, Number(v)]))
  return { pfad, current: zahl('memory.current'), high: zahl('memory.high'), max: zahl('memory.max'), peak: zahl('memory.peak'), ereignisse }
}

/** Speicher der Container eines Compose-Projekts, in Byte, je Container und gesamt. */
export function stackSpeicher(projekt) {
  const ids = lesen('docker', ['ps', '-q', '--filter', `label=com.docker.compose.project=${projekt}`]).stdout.split('\n').filter(Boolean)
  if (!ids.length) return { gesamt: 0, je: {} }
  const r = lesen('docker', ['stats', '--no-stream', '--format', '{{json .}}', ...ids])
  const je = {}
  let gesamt = 0
  for (const zeile of r.stdout.split('\n').filter(Boolean)) {
    const d = JSON.parse(zeile)
    const m = String(d.MemUsage || '').match(/^([\d.]+)\s*([KMGT]?i?B)/)
    if (!m) continue
    const faktor = { B: 1, KiB: 1024, MiB: 1024 ** 2, GiB: 1024 ** 3, TiB: 1024 ** 4, kB: 1000, MB: 1e6, GB: 1e9 }[m[2]] || 1
    je[d.Name] = Math.round(Number(m[1]) * faktor)
    gesamt += je[d.Name]
  }
  return { gesamt, je }
}

const GiB = (b) => (b === null || b === undefined ? '?' : b === Infinity ? 'max' : `${(b / 1024 ** 3).toFixed(2)}GiB`)

export class Waechter {
  constructor() {
    this.projekte = new Set()
    this.bedarf = 0
    this.zeitgeber = null
  }

  messen(anlass = 'verlauf') {
    const s = scope()
    const eintrag = { zeit: new Date().toISOString(), anlass, memAvailable: memAvailable(), scope: s, stacks: {} }
    for (const p of this.projekte) eintrag.stacks[p] = stackSpeicher(p)
    fs.appendFileSync(VERLAUF, `${JSON.stringify(eintrag, (k, v) => (v === Infinity ? 'max' : v))}\n`)
    const stacks = Object.entries(eintrag.stacks).map(([p, v]) => `${p}=${GiB(v.gesamt)}`).join(' ')
    melden(`[speicher] ${anlass}: MemAvailable=${GiB(eintrag.memAvailable)} memory.current=${GiB(s.current)} memory.high=${GiB(s.high)} memory.max=${GiB(s.max)} events.high=${s.ereignisse.high ?? '?'} events.oom_kill=${s.ereignisse.oom_kill ?? '?'} ${stacks}`)
    return eintrag
  }

  starten() {
    this.messen('beginn')
    this.zeitgeber = setInterval(() => this.messen(), 30_000)
    this.zeitgeber.unref()
  }

  anhalten() {
    if (this.zeitgeber) clearInterval(this.zeitgeber)
    this.messen('ende')
  }

  /** Merkt den gemessenen Bedarf des ersten Stacks (Höchstwert über alle Messungen). */
  bedarfMessen(projekt) {
    const s = stackSpeicher(projekt)
    this.bedarf = Math.max(this.bedarf, s.gesamt)
    melden(`[speicher] Stack ${projekt} belegt ${GiB(s.gesamt)}: ${Object.entries(s.je).map(([n, b]) => `${n}=${GiB(b)}`).join(' ')}`)
    return s.gesamt
  }

  /** Darf ein weiterer Stack starten? Erst wenn ein Bedarf gemessen ist. */
  stackErlaubt() {
    const frei = memAvailable()
    if (!this.bedarf) return { erlaubt: false, grund: 'noch kein Bedarf eines Stacks gemessen' }
    const danach = frei - this.bedarf
    return { erlaubt: danach >= RESERVE, grund: `MemAvailable ${GiB(frei)} minus Bedarf ${GiB(this.bedarf)} = ${GiB(danach)}, verlangt ${GiB(RESERVE)}` }
  }

  /** Darf ein weiterer lokaler Prozess starten? Unter 90% von memory.high. */
  lokalErlaubt() {
    const s = scope()
    if (!s.high || s.high === Infinity || s.current === null) return true
    return s.current < 0.9 * s.high
  }
}
