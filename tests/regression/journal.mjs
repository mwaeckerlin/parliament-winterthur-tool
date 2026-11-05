// Das Journal des Laufs: eine Zeile JSON je Ereignis, angehängt an
// tests/.regression/journal.jsonl, ausserhalb jedes Containers. Ein Absturz
// kann höchstens die letzte Zeile halb schreiben; das Lesen lässt eine
// unvollständige letzte Zeile weg, und es gilt der Stand davor.
//
// Ereignisse: lauf (Beginn eines logischen Laufs mit Quellstand, Inventar und
// Auswahl), beginn und ende je Test, hash (die Eingaben einer Gruppe, gemessen
// vor ihrem Start), phase (Beginn und Ende mit Dauer), urteil (das Ergebnis der
// Tests vor der Aufnahme der Bilder), fertig, messung, stack. Daraus ergibt
// sich für jeden Test sein letzter Stand.
import fs from 'node:fs'
import path from 'node:path'
import { ABLAGE, LaufFehler, uhrzeit } from './hilfen.mjs'

const DATEI = path.join(ABLAGE, 'journal.jsonl')
const SPERRE = path.join(ABLAGE, 'lauf.lock')
// Der laufende Lauf frischt seine Sperre alle 10s auf; eine Sperre älter als
// eine Minute gehört einem Lauf, der nicht mehr läuft.
const PULS_MS = 10_000
const VERWAIST_MS = 60_000

export function sperreNehmen() {
  if (fs.existsSync(SPERRE)) {
    const alter = Date.now() - Number(fs.readFileSync(SPERRE, 'utf8'))
    if (alter < VERWAIST_MS) {
      throw new LaufFehler(`Ein anderer Lauf dieses Projekts läuft (${SPERRE}, vor ${Math.round(alter / 1000)}s erneuert)`)
    }
  }
  const puls = () => fs.writeFileSync(SPERRE, String(Date.now()))
  puls()
  const zeitgeber = setInterval(puls, PULS_MS)
  zeitgeber.unref()
  return () => {
    clearInterval(zeitgeber)
    fs.rmSync(SPERRE, { force: true })
  }
}

export function schreiben(eintrag) {
  fs.appendFileSync(DATEI, `${JSON.stringify({ zeit: uhrzeit(), ...eintrag })}\n`)
}

export function alleEreignisse() {
  if (!fs.existsSync(DATEI)) return []
  const zeilen = fs.readFileSync(DATEI, 'utf8').split('\n')
  const ereignisse = []
  for (const z of zeilen) {
    if (!z.trim()) continue
    try {
      ereignisse.push(JSON.parse(z))
    } catch {
      // eine abgebrochene letzte Zeile
    }
  }
  return ereignisse
}

/**
 * Der Stand des letzten logischen Laufs: seine Kopfdaten und je Test der
 * letzte Stand in jeder Runde.
 */
export function letzterLauf() {
  const ereignisse = alleEreignisse()
  let kopf = null
  let tests = new Map()
  for (const e of ereignisse) {
    if (e.ereignis === 'lauf') {
      kopf = e
      tests = new Map()
    } else if (!kopf || e.lauf !== kopf.lauf) {
      continue
    } else if (e.ereignis === 'runde') {
      kopf = { ...kopf, phase: e.phase, runde: e.runde }
    } else if (e.ereignis === 'beginn') {
      const t = tests.get(e.id) || { versuche: [] }
      t.laeuft = { runde: e.runde, phase: e.phase, zeit: e.zeit }
      tests.set(e.id, t)
    } else if (e.ereignis === 'ende') {
      const t = tests.get(e.id) || { versuche: [] }
      t.laeuft = null
      t.versuche.push({ runde: e.runde, phase: e.phase, ergebnis: e.ergebnis, dauer_ms: e.dauer_ms, beleg: e.beleg, hash: e.hash })
      if (e.ergebnis === 'bestanden') t.bestanden = { runde: e.runde, phase: e.phase }
      else t.bestanden = t.bestanden && e.ergebnis === 'gesperrt' ? t.bestanden : null
      tests.set(e.id, t)
    } else if (e.ereignis === 'hash') {
      const versuch = tests.get(e.id)?.versuche.at(-1)
      if (versuch) versuch.hash = e.hash
    } else if (e.ereignis === 'urteil') {
      kopf = { ...kopf, urteil: e }
    } else if (e.ereignis === 'fertig') {
      kopf = { ...kopf, fertig: e }
    }
  }
  return { kopf, tests }
}
