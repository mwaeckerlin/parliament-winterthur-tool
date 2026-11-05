import fs from 'node:fs'
import path from 'node:path'

/**
 * Sperre zwischen den Workern eines Playwright-Laufs.
 *
 * Mehrere Worker laufen gleichzeitig nur über Dateien, die eigene Daten mit
 * eindeutigem Namen anlegen. Die Dateien unten schreiben globale Einstellungen
 * oder messen Anfragen und Zähler einer ganzen Ansicht; jede von ihnen läuft
 * allein, solange kein anderer Worker eine Datei offen hat. Ein Worker hält
 * die Sperre vom ersten Test einer Datei bis zum ersten Test der nächsten.
 *
 * Gemeinsam ist das Verzeichnis SPERR_DIR im Container der Tests:
 *   allein/       angelegt von dem Worker, der allein läuft (mkdir ist atomar)
 *   geteilt-<n>   je Worker, der eine gewöhnliche Datei offen hat
 * Jeder Halter frischt seine Datei alle 5s auf. Eine Datei, die 30s alt ist,
 * gehört einem Worker, der nicht mehr läuft, und wird entfernt.
 */

// Je Datei steht darüber, welche globale Einstellung sie schreibt oder welche
// Zähler einer ganzen Ansicht sie misst.
export const ALLEIN = new Set([
  // Zeitplan, Fraktion, Nextcloud-Gruppe, Kürzel, Mitglieder-Zuordnung, Abgleich
  'admin-sync-echtzeit.spec.js',
  // liest den Lauf-Zustand des Abgleichs, tippt in das Feld der Gruppe
  'feld-inventar-verwaltung-und-bausteine.spec.js',
  // Fraktionssitzungsmodus, Rollen, Hintergrundauftrag mit vollem Abgleich
  'restliche-features-e2e.spec.js',
  // Kürzel, Fraktionssitzungsmodus, zählt die Anfragen nach einem Ereignis
  'geschaefte-datenfluss.spec.js',
  // Kürzel, liest den Fraktionssitzungsmodus, vergleicht Zähler ganzer Ansichten
  'navigation-kuerzel-layout.spec.js',
  // löscht alle Sitzungstypen, zählt die Anfragen des Lesers
  'sitzungen-sitzungstypen.spec.js',
  // Kürzel, gemeinsamer Fraktionsordner dreier Nutzer
  'multi-user-sharing.spec.js',
  // Kürzel
  'mitglieder-kommissionen.spec.js',
  // setzt die Priorität eines synchronisierten Geschäfts und stellt sie danach zurück
  'geschaefte-nachladen.spec.js',
])

const SPERR_DIR = process.env.PW_SPERR_DIR || '/tmp/parlwin-sperre'
const PULS_MS = 5_000
const VERWAIST_MS = 30_000
// Abfragen des Zustands der Sperre; sie wartet auf ein Ereignis (Datei weg),
// das sich nicht anders beobachten lässt.
const ABFRAGE_MS = 500

const warte = (ms) => new Promise((weiter) => setTimeout(weiter, ms))

function frisch(datei) {
  try {
    return Date.now() - fs.statSync(datei).mtimeMs < VERWAIST_MS
  } catch {
    return false
  }
}

function entfernen(datei) {
  fs.rmSync(datei, { recursive: true, force: true })
}

function geteilteHalter() {
  return fs.readdirSync(SPERR_DIR).filter((n) => n.startsWith('geteilt-')).map((n) => path.join(SPERR_DIR, n))
}

export class Sperrhalter {
  constructor(worker) {
    this.worker = worker
    this.datei = null
    this.pfad = null
    this.puls = null
    fs.mkdirSync(SPERR_DIR, { recursive: true })
  }

  /** Sorgt dafür, dass dieser Worker die Sperre für `specDatei` hält. */
  async betrete(specDatei, frist) {
    const name = path.basename(specDatei)
    if (name === this.datei) return 0
    this.freigeben()
    const beginn = Date.now()
    if (ALLEIN.has(name)) {
      await this.alleinNehmen(frist)
    } else {
      await this.geteiltNehmen(frist)
    }
    this.datei = name
    this.puls = setInterval(() => {
      const jetzt = new Date()
      try { fs.utimesSync(this.pfad, jetzt, jetzt) } catch { /* beim Freigeben entfernt */ }
    }, PULS_MS)
    this.puls.unref()
    return Date.now() - beginn
  }

  async alleinNehmen(frist) {
    const ziel = path.join(SPERR_DIR, 'allein')
    while (true) {
      try {
        fs.mkdirSync(ziel)
        break
      } catch (fehler) {
        if (fehler.code !== 'EEXIST') throw fehler
        if (!frisch(ziel)) entfernen(ziel)
        else if (Date.now() > frist) throw new Error('Sperre «allein» nicht erhalten: ein anderer Worker hält sie über die Frist')
        else await warte(ABFRAGE_MS)
      }
    }
    this.pfad = ziel
    // Wer allein laufen will, wartet, bis alle gewöhnlichen Dateien zu sind.
    while (true) {
      const offen = geteilteHalter().filter((d) => { if (frisch(d)) return true; entfernen(d); return false })
      if (offen.length === 0) return
      if (Date.now() > frist) {
        this.freigeben()
        throw new Error(`Sperre «allein» nicht erhalten: ${offen.length} andere Worker haben Dateien offen`)
      }
      await warte(ABFRAGE_MS)
    }
  }

  async geteiltNehmen(frist) {
    const allein = path.join(SPERR_DIR, 'allein')
    const eigene = path.join(SPERR_DIR, `geteilt-${this.worker}-${process.pid}`)
    while (true) {
      if (fs.existsSync(allein) && frisch(allein)) {
        if (Date.now() > frist) throw new Error('Geteilte Sperre nicht erhalten: eine Datei läuft allein über die Frist')
        await warte(ABFRAGE_MS)
        continue
      }
      fs.writeFileSync(eigene, String(process.pid))
      // Kam «allein» zwischen Prüfen und Anlegen, geht der Vortritt dorthin.
      if (fs.existsSync(allein) && frisch(allein)) {
        entfernen(eigene)
        continue
      }
      this.pfad = eigene
      return
    }
  }

  freigeben() {
    if (this.puls) clearInterval(this.puls)
    this.puls = null
    if (this.pfad) entfernen(this.pfad)
    this.pfad = null
    this.datei = null
  }
}
