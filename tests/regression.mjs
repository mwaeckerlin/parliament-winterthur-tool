// Der Testlauf dieses Projekts (`npm test`).
//
// Er ruft jede Gruppe über tests/run-all.sh auf und die E2E-Gruppe über einen
// Stack für Chromium (tests/regression/stack.mjs). Einen zweiten Stack für
// Firefox baut er nur, wenn MemAvailable nach Abzug des gemessenen Bedarfs
// des ersten noch `RESERVE` übrig lässt (`stackErlaubt` in
// tests/regression/speicher.mjs); sonst laufen die Tests für Firefox auf dem
// ersten. Die Stacks bleiben über alle Phasen stehen. Das Journal
// (tests/.regression/journal.jsonl) hält jeden Test vor seinem Beginn und nach
// seinem Ende fest; ein unterbrochener Lauf setzt mit dem unterbrochenen Test
// fort und behält jeden grünen. Ein Lauf mit roten Tests wird zur Reparaturrunde: Der nächste Aufruf
// führt nur die roten und die nie grünen abhängigen aus. Sind alle grün, folgt
// im selben Aufruf eine vollständige Schlussregression. Ihr Urteil steht im
// Journal, bevor die Bilder des Design-Laufs auf demselben Stack entstehen; die
// Aufnahme ist der Fall `bild:aufnahme` des Inventars. Frei gibt nur ein Lauf,
// in dem jeder Test und die Aufnahme grün sind und dessen Quellstand am Ende
// derselbe ist wie am Anfang.
//
//   npm test [-- --list | --from <id|nr> | --to <id|nr> | --neu | --behalten | --bild <ziel> | --ohne-bild]
//   npm test -- --npnp <datei.json>
//
// --from/--to   grenzen die Ausführung ein (Kennung oder Nummer aus --list),
//               nie das Inventar; ein solcher Lauf gibt nicht frei
// --neu         beginnt einen neuen logischen Lauf, statt fortzusetzen
// --behalten    lässt die Stacks nach einem freigegebenen Lauf stehen
// --bild <ziel> Ziel der Bilder des Design-Laufs (Standard /tmp/parlwin-design)
// --ohne-bild   lässt die Bilder weg; ohne Aufnahme gibt der Lauf nicht frei
// --npnp        die Doppelprüfung eines Features oder einer Fehlerbehebung vor
//               der Regression: schaltet Zeilen der Produktdateien nach der
//               Datei um und prüft je Phase, welche Tests rot und welche grün
//               sind. Nur wenn jede Phase wie erwartet endet, folgen die
//               Schlussregression und die Bilder.
import fs from 'node:fs'
import path from 'node:path'
import { ABLAGE, LaufFehler, WURZEL, atomarSchreiben, ausfuehren, dauerText, melden } from './regression/hilfen.mjs'
import { letzterLauf, schreiben, sperreNehmen } from './regression/journal.mjs'
import { gruppenHash, quellIdentitaet } from './regression/hash.mjs'
import { Waechter } from './regression/speicher.mjs'
import * as stackModul from './regression/stack.mjs'

const RUN_ALL = path.join(WURZEL, 'tests', 'run-all.sh')
const GRUEN = path.join(ABLAGE, 'gruppen-gruen.json')
const PHP_TEST_BILD = 'parlwin-php-test:lokal'
export const BILD_ID = 'bild:aufnahme'

// Ohne E2E (GitHub-Runner): keine Stacks, keine Browser, keine Bilder.
const ohneE2e = () => process.env.PARLWIN_TESTS_OHNE_E2E === '1'

// Die Gruppen in der Reihenfolge des Inventars. php-pdf läuft vom Beginn an neben allem anderen, in
// einem eigenen Container auf einer CPU (tests/run-all.sh, Gruppe php-pdf).
// Der Lint gehört nicht dazu: ESLint hat im Projekt keine Konfiguration
// (TODO.md, Befunde); `tests/run-all.sh lint` ruft ihn einzeln auf.
export const GRUPPEN = ['php-pdf', 'php-unit', 'js', 'php-live', 'compose', 'audit', 'composer-audit', 'image', 'quelle', 'e2e']
const LOKAL = ['php-unit', 'js', 'php-live', 'compose', 'audit', 'composer-audit']

// Diese Gruppen fragen Dienste ausserhalb des Projekts: php-live die Webseite
// des Parlaments, audit und composer-audit die Datenbanken der
// Sicherheitshinweise. Ihr Ergebnis ändert sich, ohne dass sich eine Datei
// ändert, und sie laufen darum jedes Mal, auch mit unverändertem Hash.
export const VON_AUSSEN = new Set(['php-live', 'audit', 'composer-audit'])

/**
 * Was der Lauf von aussen braucht. Die Komponententests des Laufs
 * (parlwin/src/js/tests/regression-*.test.js) setzen hier ihre eigenen
 * Fassungen ein und lassen so Docker, Stacks und Gruppen weg.
 */
export const abh = {
  stack: stackModul,
  ausfuehren,
  gruppeAusfuehren: (...a) => gruppeAusfuehren(...a),
  gruppenHash,
  quellIdentitaet,
  phpTestBildBauen: () => ausfuehren('docker', ['compose', '--file', 'tests/docker-compose.php-test.yml', 'build', 'php-test']),
}

function option(name) {
  const at = process.argv.indexOf(name)
  if (at === -1) return undefined
  const wert = process.argv[at + 1]
  if (wert === undefined || wert.startsWith('--')) throw new LaufFehler(`${name} braucht einen Wert`)
  return wert
}
const schalter = (name) => process.argv.includes(name)

// ------------------------------------------------------------ Ergebnisse ---

class Lauf {
  constructor(kopf) {
    this.kopf = kopf
    this.ergebnisse = new Map()
  }

  beginn(id, beleg = {}) {
    schreiben({ ereignis: 'beginn', lauf: this.kopf.lauf, runde: this.kopf.runde, phase: this.kopf.phase, id, ...beleg })
  }

  ende(id, ergebnis, beleg = {}) {
    this.ergebnisse.set(id, { ergebnis, ...beleg })
    schreiben({ ereignis: 'ende', lauf: this.kopf.lauf, runde: this.kopf.runde, phase: this.kopf.phase, id, ergebnis, ...beleg })
  }

  /** Hält den Hash der Eingaben fest, mit denen der Fall `id` lief. */
  hashFesthalten(id, hash) {
    if (!hash) return
    const e = this.ergebnisse.get(id)
    if (e) e.hash = hash
    schreiben({ ereignis: 'hash', lauf: this.kopf.lauf, runde: this.kopf.runde, id, hash })
  }

  phase(name, art, dauer_ms = undefined) {
    schreiben({ ereignis: 'phase', lauf: this.kopf.lauf, runde: this.kopf.runde, name, art, dauer_ms })
    if (art === 'ende') {
      OFFENE_PHASEN.delete(name)
      melden(`[phase] ${name} beendet nach ${dauerText(dauer_ms)}`)
    } else {
      OFFENE_PHASEN.set(name, Date.now())
      melden(`[phase] ${name} beginnt`)
    }
  }
}

// Die Phasen, die gerade laufen, mit ihrem Beginn. Alle fünf Minuten nennt das
// Protokoll sie, damit eine lange Phase wie php-pdf darin sichtbar bleibt.
const OFFENE_PHASEN = new Map()
function offenePhasenMelden() {
  if (!OFFENE_PHASEN.size) return
  melden(`[lauf] läuft noch: ${[...OFFENE_PHASEN].map(([name, beginn]) => `${name} (seit ${dauerText(Date.now() - beginn)})`).join(', ')}`)
}

async function zeitnehmen(lauf, name, arbeit) {
  const beginn = Date.now()
  lauf.phase(name, 'beginn')
  try {
    return await arbeit()
  } finally {
    lauf.phase(name, 'ende', Date.now() - beginn)
  }
}

// ---------------------------------------------------- Gruppen über run-all ---

const muster = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

function teamcityWert(zeile, schluessel) {
  const m = zeile.match(new RegExp(`${schluessel}='((?:\\|.|[^'|])*)'`))
  return m ? m[1].replace(/\|n/g, '\n').replace(/\|r/g, '\r').replace(/\|([|'[\]])/g, '$1') : ''
}

/**
 * Datei und Name eines vitest-Falls aus seiner Kennung
 * `js:<datei>::<voller Name>`. vitest-reporter.mjs bildet den vollen Namen mit
 * « > » zwischen den Teilen; `-t` sucht im vollen Namen des Falls, darum
 * genügt der letzte Teil.
 */
export function vitestKennung(id) {
  const rest = id.slice('js:'.length)
  const at = rest.indexOf('::')
  return { datei: rest.slice(0, at), name: rest.slice(at + 2).split(' > ').pop() }
}

/**
 * Eine Gruppe über tests/run-all.sh. `filter` sind die Kennungen, auf die eine
 * Reparaturrunde sie eingrenzt (leer: die ganze Gruppe).
 */
export async function gruppeAusfuehren(lauf, gruppe, { filter = [], werkzeug = [], env = {} } = {}) {
  const args = [RUN_ALL, gruppe]
  const extra = [...werkzeug]
  const php = gruppe.startsWith('php-')
  if (php) {
    extra.push('--teamcity')
    // Ein Fall mit Datensatz heisst im Journal «Klasse::methode with data set
    // #3». PHPUnit findet mit diesem Namen im Filter keinen Test («No tests
    // executed!»); gefiltert wird darum auf die Methode, mit allen ihren
    // Datensätzen.
    if (filter.length) {
      // «php-pdf:Klasse::methode» → «Klasse::methode»
      const methoden = [...new Set(filter.map((id) => id.slice(id.indexOf(':') + 1).replace(/ with data set .*$/, '')))]
      extra.push('--filter', `(${methoden.map(muster).join('|')})( with data set .*)?$`)
    }
  }
  if (gruppe === 'js') {
    extra.push('--reporter=./tests/regression/vitest-reporter.mjs')
    if (filter.length) {
      const bekannt = filter.map(vitestKennung)
      extra.push(...new Set(bekannt.map((b) => b.datei)))
      extra.push('-t', bekannt.map((b) => muster(b.name)).join('|'))
    }
  }
  if (extra.length) args.push('--', ...extra)
  const einzeln = !php && gruppe !== 'js'
  const id1 = `${gruppe}:gruppe`
  lauf.beginn(id1)
  const beginn = Date.now()
  let laufend = null
  let fehlgeschlagen = false
  const gesehen = new Set()
  const r = await abh.ausfuehren('bash', args, {
    env: { PARLWIN_PHP_TEST_GEBAUT: '1', ...env },
    protokoll: path.join(ABLAGE, `gruppe-${gruppe}.log`),
    zeile: (z) => {
      if (php && z.startsWith('##teamcity[')) {
        const art = z.slice(11, z.indexOf(' '))
        if (art === 'testStarted') {
          const ort = teamcityWert(z, 'locationHint').split('::')
          const klasse = (ort[ort.length - 2] || '').split('\\').pop()
          laufend = { id: `${gruppe}:${klasse}::${teamcityWert(z, 'name')}`, beginn: Date.now() }
          fehlgeschlagen = false
          lauf.beginn(laufend.id)
        } else if ((art === 'testFailed' || art === 'testIgnored') && laufend) {
          fehlgeschlagen = true
          laufend.fehler = teamcityWert(z, 'message').split('\n')[0]
        } else if (art === 'testFinished' && laufend) {
          gesehen.add(laufend.id)
          lauf.ende(laufend.id, fehlgeschlagen ? 'rot' : 'bestanden', { dauer_ms: Date.now() - laufend.beginn, fehler: laufend.fehler })
          laufend = null
        }
      } else if (gruppe === 'js' && z.startsWith('##VTJ ')) {
        const e = JSON.parse(z.slice(6))
        if (e.ereignis === 'beginn') {
          lauf.beginn(e.id)
        } else {
          gesehen.add(e.id)
          lauf.ende(e.id, e.ergebnis === 'bestanden' ? 'bestanden' : 'rot', { dauer_ms: e.dauer_ms, fehler: e.fehler?.[0] })
        }
      }
    },
  })
  if (laufend) lauf.ende(laufend.id, 'unterbrochen', { fehler: `Gruppe endete mit ${r.code}` })
  // Der Fall «<gruppe>:gruppe» steht für den Rückgabewert der ganzen Gruppe:
  // Eine Gruppe, die abstürzt oder keinen einzigen Test meldet, geht so nicht
  // als grün durch, auch wenn jeder gemeldete Test grün ist.
  const grund = r.code !== 0 ? `Gruppe endete mit ${r.code}` : !einzeln && gesehen.size === 0 ? 'Gruppe meldete keinen einzigen Test' : undefined
  lauf.ende(id1, grund ? 'rot' : 'bestanden', { dauer_ms: Date.now() - beginn, beleg: `tests/.regression/gruppe-${gruppe}.log`, fehler: grund })
  return r.code
}

// ------------------------------------------------------------ E2E-Teil ---

export function e2eProjekte(zweiStacks) {
  return zweiStacks ? { chromium: ['chromium', 'iphone'], firefox: ['firefox'] } : { chromium: ['chromium', 'firefox', 'iphone'] }
}

/** Die Kennung des Falls, der für den Rückgabewert von Playwright auf einem Stack steht. */
export const pwLaufId = (browser) => `pw:${browser}:lauf`
const PW_LAUF = /^pw:[^:]+:lauf$/

function pwEreignis(lauf) {
  return (e) => {
    if (e.ereignis === 'beginn') lauf.beginn(e.id, { worker: e.worker })
    else if (e.ereignis === 'ende') lauf.ende(e.id, e.ergebnis, { dauer_ms: e.dauer_ms, fehler: e.fehler?.[0] })
  }
}

/**
 * Ein Playwright-Lauf auf dem Stack `browser`. Neben den Tests hält er den
 * Fall `pw:<browser>:lauf` fest, so wie `<gruppe>:gruppe` bei den Gruppen: rot,
 * wenn Playwright nicht mit 0 endet oder keinen einzigen Test meldet. Dazu
 * steht die Zahl der roten Tests, mit der die Doppelprüfung den Rückgabewert
 * gegen die Tests prüft.
 */
async function playwrightLauf(lauf, browser, optionen) {
  const id = pwLaufId(browser)
  const zahl = { tests: 0, rot: 0 }
  const weiter = optionen.ereignis
  lauf.beginn(id)
  const beginn = Date.now()
  const r = await abh.stack.playwright(browser, {
    ...optionen,
    ereignis: (e) => {
      if (e.ereignis === 'ende') {
        zahl.tests += 1
        if (e.ergebnis !== 'bestanden') zahl.rot += 1
      }
      weiter(e)
    },
  })
  const grund = r.code !== 0 ? `Playwright endete mit ${r.code}` : zahl.tests === 0 ? 'Playwright meldete keinen einzigen Test' : undefined
  lauf.ende(id, grund ? 'rot' : 'bestanden', { dauer_ms: Date.now() - beginn, fehler: grund, tests: zahl.tests, rot_tests: zahl.rot, beleg: `tests/.regression/${abh.stack.projektName(browser)}/playwright-${optionen.phase}.log` })
  return r
}

function intEreignis(lauf) {
  return (e) => {
    const id = `int:${e.id.replace(/^int:/, '')}`
    if (e.id.startsWith('aufbau:')) return
    if (e.ereignis === 'beginn') lauf.beginn(id)
    else lauf.ende(id, e.ergebnis, { dauer_ms: (e.sekunden ?? 0) * 1000, fehler: e.wegen ? `gesperrt: ${e.wegen} nicht bestanden` : undefined })
  }
}

/** Abschnitte in der Reihenfolge des vollen Laufs, geteilt vor und nach Playwright. */
function intFolge(liste, auswahl) {
  const S0 = new Set(['aufbau:start', 'aufbau:nextcloud', 'aufbau:app', 'aufbau:benutzer', 'aufbau:abgleich', 'aufbau:budget'])
  const imGesamtlauf = liste.filter((a) => a.gesamtlauf && !S0.has(a.id))
  const pw = imGesamtlauf.findIndex((a) => a.id === 'int:playwright')
  const vor = imGesamtlauf.slice(0, pw).map((a) => a.id)
  const nach = imGesamtlauf.slice(pw + 1).map((a) => a.id)
  if (!auswahl) return { vor, nach, erweitert: [] }
  // Nach dem Zurückspielen von s0 fehlt der Zustand, den ein Abschnitt für
  // seinen Nachfolger hinterlässt (Notizen, Aktionen, Rollen). Jede gewählte
  // Prüfung nimmt darum ihre Vorgänger unter den Prüfungen mit.
  const nach_id = new Map(liste.map((a) => [a.id, a]))
  const mit = new Set()
  const hinzu = (id) => { if (mit.has(id) || S0.has(id)) return; mit.add(id); for (const r of nach_id.get(id)?.requires || []) hinzu(r) }
  for (const id of auswahl) hinzu(id)
  const erweitert = [...mit].filter((id) => !auswahl.includes(id))
  return { vor: vor.filter((id) => mit.has(id)), nach: nach.filter((id) => mit.has(id)), erweitert }
}

/** Die gewählten Playwright-Tests samt ihren Voraussetzungen, in der Reihenfolge des Stacks. */
function vorgaengerMitnehmen(vomStack, gewaehlt) {
  const nachId = new Map(vomStack.map((t) => [t.id, t]))
  const mit = new Set()
  const hinzu = (t) => { if (!t || mit.has(t.id)) return; mit.add(t.id); for (const r of t.requires || []) hinzu(nachId.get(r)) }
  for (const t of gewaehlt) hinzu(t)
  return vomStack.filter((t) => mit.has(t.id))
}

/** Meldet einen Aufbau-Abschnitt, der in der Folge nicht bestand, als Fehler des Laufs. */
function aufbauPruefen(lauf, r) {
  for (const [id, e] of Object.entries(r.ergebnisse || {})) {
    if (id.startsWith('aufbau:') && e.ergebnis !== 'bestanden') {
      melden(`[e2e] FEHLER DES LAUFS: ${id} ist ${e.ergebnis}; die Tests danach laufen ohne diese Vorbereitung`)
      lauf.laufFehler = (lauf.laufFehler || 0) + 1
    }
  }
}

async function e2eStack(lauf, browser, { projekte, intAuswahl, pwAuswahl, mitInt, phase }) {
  const liste = abh.stack.abschnittListe()
  const pwOptionen = { projekte, auswahl: pwAuswahl, phase, ereignis: pwEreignis(lauf) }
  if (mitInt && !intAuswahl) {
    // Der volle Lauf in der Reihenfolge von run-compose-e2e.sh: Prüfungen am
    // System ab s0 (darin Fraktionsraum, Testdaten und aufbau:modus-aus nach
    // int:rechte-sitzungsmodus), Playwright, dann Hintergrundauftrag und Zeitplan.
    await abh.stack.zustandHerstellen(browser, 's0')
    const folge = intFolge(liste, null)
    aufbauPruefen(lauf, await zeitnehmen(lauf, `${phase} ${browser} Prüfungen am System`, () => abh.stack.abschnitte(browser, folge.vor, intEreignis(lauf))))
    if (projekte.length) {
      await zeitnehmen(lauf, `${phase} ${browser} Playwright`, () => playwrightLauf(lauf, browser, pwOptionen))
    }
    if (folge.nach.length) await zeitnehmen(lauf, `${phase} ${browser} Prüfungen nach Playwright`, () => abh.stack.abschnitte(browser, folge.nach, intEreignis(lauf)))
  } else if (mitInt) {
    // Reparaturrunde: die gewählten Prüfungen samt Vorgängern ab s0, danach
    // Playwright auf s1, wo Fraktionsraum und Testdaten stehen.
    await abh.stack.zustandHerstellen(browser, 's0')
    const folge = intFolge(liste, intAuswahl)
    if (folge.erweitert.length) melden(`[e2e] Vorgänger mitgenommen (ihr Zustand fehlt nach s0): ${folge.erweitert.join(', ')}`)
    await zeitnehmen(lauf, `${phase} ${browser} Prüfungen am System`, () => abh.stack.abschnitte(browser, [...folge.vor, ...folge.nach], intEreignis(lauf)))
    if (projekte.length) {
      await abh.stack.zustandHerstellen(browser, 's1')
      await zeitnehmen(lauf, `${phase} ${browser} Playwright`, () => playwrightLauf(lauf, browser, pwOptionen))
    }
  } else if (projekte.length) {
    await abh.stack.zustandHerstellen(browser, 's1')
    await zeitnehmen(lauf, `${phase} ${browser} Playwright`, () => playwrightLauf(lauf, browser, pwOptionen))
  }
}

// ------------------------------------------------------------- Inventar ---

/**
 * Das Inventar: je Gruppe ihr Gesamtfall, die Fälle von PHPUnit und vitest, die
 * dieser logische Lauf schon kennt, die Prüfungen am System, jeder
 * Playwright-Test aller drei Projekte (über `--list`, ohne Browser), je Stack
 * der Fall `pw:<browser>:lauf` und zuletzt die Aufnahme der Bilder. Für die
 * Liste von Playwright steht der Chromium-Stack; der Aufbau ist damit
 * erledigt, und nach seinem gemessenen Bedarf entscheidet der Wächter hier, ob
 * Firefox einen eigenen Stack bekommt.
 */
async function inventarBestimmen(waechter, bekannt) {
  const ids = []
  let zweiStacks = false
  for (const g of GRUPPEN.filter((x) => x !== 'e2e')) {
    ids.push({ id: `${g}:gruppe`, gruppe: g, requires: [] })
    for (const id of [...bekannt.keys()].filter((k) => k.startsWith(`${g}:`) && k !== `${g}:gruppe`).sort()) ids.push({ id, gruppe: g, requires: [] })
  }
  if (!ohneE2e()) {
    for (const a of abh.stack.abschnittListe().filter((x) => x.gesamtlauf && x.id.startsWith('int:') && x.id !== 'int:playwright')) {
      ids.push({ id: a.id, gruppe: 'int', requires: a.requires.filter((r) => r.startsWith('int:')) })
    }
    await abh.stack.aufbauen('chromium', waechter)
    const erlaubt = waechter.stackErlaubt()
    melden(`[speicher] zweiter Stack: ${erlaubt.erlaubt ? 'erlaubt' : 'nicht erlaubt'} (${erlaubt.grund})`)
    zweiStacks = erlaubt.erlaubt
    for (const t of await abh.stack.playwrightInventar('chromium', ['chromium', 'firefox', 'iphone'])) ids.push({ id: t.id, gruppe: 'pw', projekt: t.projekt, pfad: t.pfad, requires: t.requires || [] })
    for (const browser of Object.keys(e2eProjekte(zweiStacks))) ids.push({ id: pwLaufId(browser), gruppe: 'pwlauf', browser, requires: [] })
    ids.push({ id: BILD_ID, gruppe: 'bild', requires: [] })
  }
  return { inventar: ids, zweiStacks }
}

// ----------------------------------------------------------- Ausführung ---

/**
 * Führt die Auswahl aus. `auswahl` ist null (alles) oder eine Menge von
 * Kennungen. Unabhängige Teile laufen gleichzeitig.
 */
async function ausfuehrenAuswahl(lauf, inventar, auswahl, waechter, { zweiStacks = false } = {}) {
  const gewaehlt = (id) => !auswahl || auswahl.has(id)
  const gruppenIds = (g) => inventar.filter((t) => t.gruppe === g).map((t) => t.id)
  const gruppeGewaehlt = (g) => !auswahl || [...auswahl].some((id) => id.startsWith(`${g}:`))
  const filterFuer = (g) => {
    if (!auswahl) return []
    const ids = [...auswahl].filter((id) => id.startsWith(`${g}:`) && id !== `${g}:gruppe`)
    return ids
  }
  const gruen = fs.existsSync(GRUEN) ? JSON.parse(fs.readFileSync(GRUEN, 'utf8')) : {}
  const unveraendert = (g, h) => (!VON_AUSSEN.has(g) && h && gruen[g]?.hash === h ? gruen[g] : null)

  // Das PHP-Testimage einmal für alle drei PHP-Gruppen.
  if (['php-pdf', 'php-unit', 'php-live'].some(gruppeGewaehlt)) {
    await zeitnehmen(lauf, 'Bau PHP-Testimage', () => abh.phpTestBildBauen())
  }

  const arbeiten = []
  // Der Hash einer Gruppe misst ihre Eingaben unmittelbar vor ihrem Start; mit
  // ihm hält die Freigabe fest, welchen Stand die Gruppe geprüft hat.
  const gruppeLauf = (g, optionen = {}) => {
    if (!gruppeGewaehlt(g)) return null
    const hash = abh.gruppenHash(g, { phpTestBild: PHP_TEST_BILD })
    const alt = !auswahl && unveraendert(g, hash)
    if (alt) {
      melden(`[gruppe] ${g}: Eingaben unverändert seit dem grünen Lauf vom ${alt.zeit}, nicht erneut`)
      for (const id of gruppenIds(g)) lauf.ende(id, 'bestanden', { beleg: `unverändert seit ${alt.zeit}`, ...(id === `${g}:gruppe` ? { hash } : {}) })
      return null
    }
    return zeitnehmen(lauf, `Gruppe ${g}`, async () => {
      const code = await abh.gruppeAusfuehren(lauf, g, { filter: filterFuer(g), ...optionen })
      lauf.hashFesthalten(`${g}:gruppe`, hash)
      return code
    })
  }

  const pdf = gruppeLauf('php-pdf')
  if (pdf) arbeiten.push(pdf)

  // Stacks: Chromium zuerst; Firefox, wenn das Inventar einen zweiten erlaubt hat.
  const e2eGewaehlt = !ohneE2e() && (gruppeGewaehlt('int') || gruppeGewaehlt('pw') || gruppeGewaehlt('quelle') || gruppeGewaehlt('image'))
  const lokal = (async () => {
    for (const g of LOKAL) {
      // Die lokalen Gruppen laufen nacheinander in der Speichergrenze der
      // Entwicklungsumgebung. Steht sie über 90% von memory.high, wartet die
      // nächste, bis der Wert sinkt, höchstens zehn Minuten (20 × 30s).
      for (let versuch = 0; versuch < 20 && !waechter.lokalErlaubt(); versuch++) {
        melden(`[speicher] memory.current über 90% von memory.high; ${g} wartet`)
        await new Promise((weiter) => setTimeout(weiter, 30_000))
      }
      await gruppeLauf(g)
    }
  })()
  arbeiten.push(lokal)

  if (e2eGewaehlt) {
    await zeitnehmen(lauf, 'Aufbau chromium', () => abh.stack.aufbauen('chromium', waechter))
    const firefoxAufbau = zweiStacks ? zeitnehmen(lauf, 'Aufbau firefox', () => abh.stack.aufbauen('firefox', waechter)) : Promise.resolve()
    const marken = abh.stack.markenBestimmen()
    if (gruppeGewaehlt('image')) arbeiten.push(gruppeLauf('image', { werkzeug: [marken['nextcloud-nginx'], marken['nextcloud-php-fpm'], marken['parlwin-realtime']] }))
    if (gruppeGewaehlt('quelle')) arbeiten.push(gruppeLauf('quelle', { env: { PARLWIN_QUELLE_PROJEKT: abh.stack.projektName('chromium') } }))

    const projekte = e2eProjekte(zweiStacks)
    const pwAuswahl = (browser) => {
      const vomStack = inventar.filter((t) => t.gruppe === 'pw' && projekte[browser].includes(t.projekt))
      let ids = vomStack.filter((t) => gewaehlt(t.id))
      if (!ids.length && auswahl?.has(pwLaufId(browser)) && vomStack.length) {
        // Nur der Rückgabewert dieses Stacks ist offen: Ein einzelner Test
        // misst ihn, mit Start, globaler Anmeldung, Berichten und Ende.
        ids = [vomStack[0]]
        melden(`[e2e] ${pwLaufId(browser)} offen ohne einen Test des Stacks: mitgenommen ${vomStack[0].id}`)
      }
      // Ein gewählter Test nimmt seine Voraussetzungen mit: Sie legen an, was er liest.
      const mit = vorgaengerMitnehmen(vomStack, ids)
      if (mit.length > ids.length) melden(`[e2e] Vorgänger mitgenommen: ${mit.filter((t) => !ids.includes(t)).map((t) => t.id).join(', ')}`)
      ids = mit
      if (!ids.length) return { liste: [], projekte: [] }
      return { liste: ids.length === vomStack.length ? null : ids.map((t) => t.pfad), projekte: [...new Set(ids.map((t) => t.projekt))] }
    }
    const intGewaehlt = auswahl ? inventar.filter((t) => t.gruppe === 'int' && auswahl.has(t.id)).map((t) => t.id) : null
    const mitInt = !auswahl || intGewaehlt.length > 0
    const c = pwAuswahl('chromium')
    arbeiten.push(e2eStack(lauf, 'chromium', { projekte: c.projekte, pwAuswahl: c.liste, intAuswahl: intGewaehlt, mitInt, phase: `runde${lauf.kopf.runde}` }))
    if (zweiStacks) {
      arbeiten.push(firefoxAufbau.then(() => {
        const f = pwAuswahl('firefox')
        return e2eStack(lauf, 'firefox', { projekte: f.projekte, pwAuswahl: f.liste, intAuswahl: null, mitInt: false, phase: `runde${lauf.kopf.runde}` })
      }))
    }
  } else if (ohneE2e()) {
    if (gruppeGewaehlt('image')) arbeiten.push(gruppeLauf('image'))
    if (gruppeGewaehlt('quelle')) arbeiten.push(gruppeLauf('quelle'))
    melden('[e2e] Nicht gelaufen: PARLWIN_TESTS_OHNE_E2E=1; die e2e-Tests laufen auf dem Rechner vor dem Commit, siehe README.')
  }

  const ergebnisse = await Promise.allSettled(arbeiten)
  for (const e of ergebnisse.filter((x) => x.status === 'rejected')) {
    melden(`[lauf] FEHLER DES LAUFS: ${e.reason?.message || e.reason}`)
    lauf.laufFehler = (lauf.laufFehler || 0) + 1
  }
}

/** Das Urteil über die Aufnahme: rot, wenn Playwright nicht mit 0 endet oder kein Bild entsteht. */
export function bildUrteil(r) {
  if (r.code !== 0) return { ergebnis: 'rot', fehler: `Aufnahme endete mit ${r.code}`, bilder: r.bilder }
  if (!r.bilder) return { ergebnis: 'rot', fehler: 'Aufnahme lieferte kein Bild', bilder: 0 }
  return { ergebnis: 'bestanden', bilder: r.bilder }
}

/** Nimmt die Bilder auf dem Chromium-Stack auf und hält das Ergebnis als Fall BILD_ID fest. */
async function bildAufnehmen(lauf, tests, ziel) {
  await zeitnehmen(lauf, 'Design-Aufnahme', async () => {
    lauf.beginn(BILD_ID)
    const beginn = Date.now()
    let urteil
    try {
      urteil = bildUrteil(await abh.stack.bild(ziel))
    } catch (fehler) {
      urteil = { ergebnis: 'rot', fehler: `Aufnahme abgebrochen: ${fehler.message}` }
    }
    lauf.ende(BILD_ID, urteil.ergebnis, { dauer_ms: Date.now() - beginn, fehler: urteil.fehler, bilder: urteil.bilder, beleg: path.join(ziel, 'out') })
  })
  const t = tests.get(BILD_ID) || { versuche: [] }
  const e = lauf.ergebnisse.get(BILD_ID)
  t.versuche.push({ runde: lauf.kopf.runde, ergebnis: e.ergebnis })
  t.bestanden = e.ergebnis === 'bestanden' ? { runde: lauf.kopf.runde } : null
  t.laeuft = null
  tests.set(BILD_ID, t)
}

// --------------------------------------------------------- Auswertung ---

export function zaehlen(inventar, ergebnisse, auswahl) {
  const zahlen = { ausgewaehlt: 0, bestanden: 0, rot: 0, unterbrochen: 0, gesperrt: 0, offen: 0 }
  for (const t of inventar) {
    if (auswahl && !auswahl.has(t.id)) continue
    zahlen.ausgewaehlt += 1
    const e = ergebnisse.get(t.id)?.ergebnis
    if (e === 'bestanden') zahlen.bestanden += 1
    else if (e === 'rot' || e === 'uebersprungen') zahlen.rot += 1
    else if (e === 'unterbrochen') zahlen.unterbrochen += 1
    else if (e === 'gesperrt') zahlen.gesperrt += 1
    else zahlen.offen += 1
  }
  return zahlen
}

function bericht(zahlen) {
  const p = (n) => `${zahlen.ausgewaehlt ? ((100 * n) / zahlen.ausgewaehlt).toFixed(1) : '0.0'}%`
  return `ausgewählt ${zahlen.ausgewaehlt}, bestanden ${zahlen.bestanden} (${p(zahlen.bestanden)}), rot ${zahlen.rot} (${p(zahlen.rot)}), unterbrochen ${zahlen.unterbrochen} (${p(zahlen.unterbrochen)}), gesperrt ${zahlen.gesperrt} (${p(zahlen.gesperrt)}), offen ${zahlen.offen} (${p(zahlen.offen)})`
}

/** Je Test sein letzter Versuch in der Runde `runde`. */
function ergebnisseDerRunde(tests, runde) {
  const m = new Map()
  for (const [id, t] of tests) {
    const v = (t.versuche || []).filter((x) => x.runde === runde).at(-1)
    if (v) m.set(id, v)
  }
  return m
}

// ------------------------------------------------------ Doppelprüfung ---

const SICHERUNG_SCHALTER = path.join(ABLAGE, 'sicherung-schalter')

function schalterZuruecklegen() {
  if (!fs.existsSync(SICHERUNG_SCHALTER)) return
  for (const name of fs.readdirSync(SICHERUNG_SCHALTER)) {
    const ziel = path.join(WURZEL, name.replace(/__/g, '/'))
    fs.writeFileSync(ziel, fs.readFileSync(path.join(SICHERUNG_SCHALTER, name)))
  }
  fs.rmSync(SICHERUNG_SCHALTER, { recursive: true, force: true })
  melden('[schalter] Produktdateien aus der Sicherung zurückgelegt')
}

function schalterPruefen(schalterListe) {
  for (const [kennung, datei, zeile, alt] of schalterListe) {
    const zeilen = fs.readFileSync(path.join(WURZEL, datei), 'utf8').split('\n')
    if (!zeilen[zeile - 1]?.includes(alt)) throw new LaufFehler(`Schalter ${kennung}: ${datei}:${zeile} trägt «${alt}» nicht; die Datei hat sich bewegt`)
  }
}

function schalten(schalterListe) {
  fs.mkdirSync(SICHERUNG_SCHALTER, { recursive: true })
  for (const [kennung, datei, zeile, alt, neu] of schalterListe) {
    const sicherung = path.join(SICHERUNG_SCHALTER, datei.replace(/\//g, '__'))
    const pfad = path.join(WURZEL, datei)
    if (!fs.existsSync(sicherung)) fs.writeFileSync(sicherung, fs.readFileSync(pfad))
    const zeilen = fs.readFileSync(pfad, 'utf8').split('\n')
    zeilen[zeile - 1] = zeilen[zeile - 1].replace(alt, neu)
    fs.writeFileSync(pfad, zeilen.join('\n'))
    melden(`[schalter] aus: ${kennung} (${datei}:${zeile})`)
  }
}

function passt(id, namen) {
  return namen.some((n) => id.includes(n))
}

/**
 * Der PHP-Schritt einer Phase der Doppelprüfung. PHPUnit läuft im Image
 * parlwin-php-test, das das Projekt beim Bau hineinkopiert; ohne neuen Bau
 * sähen die Tests die Dateien von vor dem Umschalten. Darum baut dieser
 * Schritt das Image in jeder Phase neu, auch in der Phase ohne Schalter.
 */
export function doppelpruefungPhpOptionen(filter) {
  return { werkzeug: ['--filter', filter], env: { PARLWIN_PHP_TEST_GEBAUT: '0' } }
}

/**
 * Prüft die Erwartung einer Phase an den Ergebnissen. Der Fall
 * `pw:<browser>:lauf` braucht keinen Eintrag in der Datei: Er muss genau dann
 * rot sein, wenn in seinem Lauf ein Test rot war.
 */
export function erwartungPruefen(phase, ergebnisse) {
  const befunde = []
  const ids = [...ergebnisse.keys()].filter((id) => !PW_LAUF.test(id))
  for (const name of phase.rot || []) {
    const treffer = ids.filter((id) => id.includes(name))
    if (!treffer.length) befunde.push(`erwartet rot, aber kein Test lief: «${name}»`)
    for (const id of treffer.filter((i) => ergebnisse.get(i).ergebnis !== 'rot')) befunde.push(`erwartet rot, ist ${ergebnisse.get(id).ergebnis}: ${id}`)
  }
  for (const name of phase.gruen || []) {
    const treffer = ids.filter((id) => id.includes(name))
    if (!treffer.length) befunde.push(`erwartet grün, aber kein Test lief: «${name}»`)
    for (const id of treffer.filter((i) => ergebnisse.get(i).ergebnis !== 'bestanden')) befunde.push(`erwartet grün, ist ${ergebnisse.get(id).ergebnis}: ${id}`)
  }
  if ((phase.uebrige || 'gruen') === 'gruen') {
    for (const id of ids.filter((i) => !passt(i, phase.rot || []) && ergebnisse.get(i).ergebnis !== 'bestanden')) befunde.push(`nicht genannt, also grün erwartet, ist ${ergebnisse.get(id).ergebnis}: ${id}`)
  }
  for (const [id, e] of [...ergebnisse].filter(([i]) => PW_LAUF.test(i))) {
    const rotErwartet = (e.rot_tests || 0) > 0
    if ((e.ergebnis === 'rot') !== rotErwartet) befunde.push(`${id} ist ${e.ergebnis}${e.fehler ? ` (${e.fehler})` : ''} bei ${e.rot_tests || 0} roten von ${e.tests || 0} Tests`)
  }
  return befunde
}

async function doppelpruefung(datei, waechter, kopfBasis) {
  const plan = JSON.parse(fs.readFileSync(datei, 'utf8'))
  const nach = new Map(plan.schalter.map((s) => [s[0], s]))
  schalterPruefen(plan.schalter)
  const lauf = new Lauf({ ...kopfBasis, lauf: `doppelpruefung-${Date.now()}`, phase: 'doppelpruefung', runde: 1 })
  schreiben({ ereignis: 'lauf', ...lauf.kopf, doppelpruefung: plan.name })
  await zeitnehmen(lauf, 'Aufbau chromium', () => abh.stack.aufbauen('chromium', waechter))
  const erlaubt = waechter.stackErlaubt()
  melden(`[speicher] zweiter Stack: ${erlaubt.erlaubt ? 'erlaubt' : 'nicht erlaubt'} (${erlaubt.grund})`)
  if (erlaubt.erlaubt) await zeitnehmen(lauf, 'Aufbau firefox', () => abh.stack.aufbauen('firefox', waechter))
  const projekte = e2eProjekte(erlaubt.erlaubt)
  const inventar = await abh.stack.playwrightInventar('chromium', Object.values(projekte).flat())
  const ergebnisseJePhase = []
  let sauber = true
  for (const phase of plan.phasen) {
    const beginnPhase = Date.now()
    lauf.kopf.runde = ergebnisseJePhase.length + 1
    const ergebnisse = new Map()
    const ende = lauf.ende.bind(lauf)
    lauf.ende = (id, ergebnis, beleg = {}) => { ergebnisse.set(id, { ergebnis, ...beleg }); ende(id, ergebnis, beleg) }
    lauf.phase(`Doppelprüfung ${phase.name}`, 'beginn')
    const aktiv = (arten) => (phase.schalter || []).map((k) => nach.get(k)).filter((s) => arten.includes(s[5]))
    try {
      if (phase.vitest) {
        schalten(aktiv(['alle', 'vitest']))
        try { await gruppeAusfuehren(lauf, 'js', { werkzeug: [...(phase.vitest.dateien || []), ...(phase.vitest.t ? ['-t', phase.vitest.t] : [])] }) } finally { schalterZuruecklegen() }
      }
      if (phase.php) {
        schalten(aktiv(['alle', 'php']))
        try { await gruppeAusfuehren(lauf, 'php-unit', doppelpruefungPhpOptionen(phase.php.filter)) } finally { schalterZuruecklegen() }
      }
      if (phase.compose) {
        schalten(aktiv(['alle']))
        try { await gruppeAusfuehren(lauf, 'compose') } finally { schalterZuruecklegen() }
      }
      if (phase.lint) {
        schalten(aktiv(['alle']))
        try { await gruppeAusfuehren(lauf, 'lint') } finally { schalterZuruecklegen() }
      }
      if (phase.e2e) {
        const tausch = []
        const alle = aktiv(['alle'])
        schalten(alle)
        try {
          const bau = path.join(ABLAGE, `bau-${phase.name}`)
          fs.rmSync(bau, { recursive: true, force: true })
          if (alle.some((s) => s[1].startsWith('parlwin/src/'))) {
            await zeitnehmen(lauf, `Bau Oberfläche ${phase.name}`, () => abh.stack.oberflaecheBauen('chromium', path.join(bau, 'oberflaeche')))
            tausch.push({ ziel: 'js', quelle: path.join(bau, 'oberflaeche', 'js') }, { ziel: 'css', quelle: path.join(bau, 'oberflaeche', 'css') })
          }
          for (const [kennung, d] of alle) {
            if (d.startsWith('parlwin/src/')) continue
            if (!d.startsWith('parlwin/')) { melden(`[schalter] ${kennung}: ${d} liegt ausserhalb der App und wirkt im Stack nicht`); continue }
            const kopie = path.join(bau, 'dateien', d)
            fs.mkdirSync(path.dirname(kopie), { recursive: true })
            fs.copyFileSync(path.join(WURZEL, d), kopie)
            tausch.push({ ziel: d.slice('parlwin/'.length), quelle: kopie })
          }
        } finally {
          schalterZuruecklegen()
        }
        const muster_ = phase.e2e.grep ? new RegExp(phase.e2e.grep) : null
        const gewaehlt = inventar.filter((t) => (!phase.e2e.dateien?.length || phase.e2e.dateien.includes(t.pfad[0])) && (!muster_ || muster_.test(t.pfad.join(' '))))
        await Promise.all(Object.entries(projekte).map(async ([browser, projekteDesStacks]) => {
          const hier = gewaehlt.filter((t) => projekteDesStacks.includes(t.projekt))
          if (!hier.length) return
          await abh.stack.zustandHerstellen(browser, 's1', tausch)
          await zeitnehmen(lauf, `Doppelprüfung ${phase.name} ${browser} Playwright`, () => playwrightLauf(lauf, browser, {
            projekte: [...new Set(hier.map((t) => t.projekt))], auswahl: [...new Set(hier.map((t) => JSON.stringify(t.pfad)))].map((p) => JSON.parse(p)), phase: phase.name, ereignis: pwEreignis(lauf),
          }))
          const begonnen = hier.filter((t) => ergebnisse.has(t.id)).length
          if (begonnen !== hier.length) melden(`[messung] Auswahl ${browser}: ${hier.length} gewählt, ${begonnen} gelaufen${begonnen === hier.length ? '' : ' — die Auswahl über grep trifft nicht genau'}`)
        }))
      }
    } finally {
      lauf.ende = ende
      schalterZuruecklegen()
    }
    const befunde = erwartungPruefen(phase, ergebnisse)
    const dauer = Date.now() - beginnPhase
    lauf.phase(`Doppelprüfung ${phase.name}`, 'ende', dauer)
    ergebnisseJePhase.push({ phase: phase.name, dauer_ms: dauer, befunde, rot: [...ergebnisse].filter(([, e]) => e.ergebnis !== 'bestanden').map(([id]) => id) })
    schreiben({ ereignis: 'doppelpruefung', lauf: lauf.kopf.lauf, phase: phase.name, dauer_ms: dauer, befunde })
    if (befunde.length) {
      sauber = false
      melden(`[doppelprüfung] Phase ${phase.name} NICHT wie erwartet:\n  ${befunde.join('\n  ')}`)
    } else {
      melden(`[doppelprüfung] Phase ${phase.name} wie erwartet (${ergebnisse.size} Fälle, ${dauerText(dauer)})`)
    }
  }
  atomarSchreiben(path.join(ABLAGE, 'doppelpruefung-ergebnis.json'), JSON.stringify({ plan: plan.name, sauber, phasen: ergebnisseJePhase }, null, 2))
  return sauber
}

// ------------------------------------------------------------- Haupt ---

export async function schlussregression(waechter, quelle, { von, bis, neu, bildZiel }) {
  let { kopf, tests } = letzterLauf()
  if (!kopf || neu || kopf.fertig?.freigegeben || kopf.phase === 'doppelpruefung') {
    kopf = { lauf: `lauf-${Date.now()}`, quelle, phase: 'final', runde: 1 }
    tests = new Map()
    schreiben({ ereignis: 'lauf', ...kopf })
  } else if (kopf.quelle !== quelle) {
    // Geänderte Dateien: eine Schlussregression gilt nicht mehr; die grünen
    // Tests behalten ihr Ergebnis für die Reparaturrunde.
    melden('[lauf] Der Quellstand hat sich geändert; Reparaturrunde über die nicht grünen Tests')
    kopf = { ...kopf, quelle, phase: kopf.phase === 'final' ? 'reparatur' : kopf.phase, runde: kopf.runde + 1 }
    schreiben({ ereignis: 'runde', lauf: kopf.lauf, phase: kopf.phase, runde: kopf.runde, quelle })
  }
  const { inventar, zweiStacks } = await inventarBestimmen(waechter, tests)
  schreiben({ ereignis: 'inventar', lauf: kopf.lauf, ids: inventar.map((t) => ({ id: t.id, requires: t.requires })) })
  atomarSchreiben(path.join(ABLAGE, 'inventar.json'), JSON.stringify(inventar.map((t, i) => ({ nr: i + 1, id: t.id, requires: t.requires })), null, 2))
  const ids = inventar.map((t) => t.id)
  const position = (wert, standard) => {
    if (wert === undefined) return standard
    if (ids.includes(wert)) return ids.indexOf(wert)
    if (/^\d+$/.test(wert) && Number(wert) >= 1 && Number(wert) <= ids.length) return Number(wert) - 1
    throw new LaufFehler(`unbekannte Kennung oder Nummer «${wert}»; tests/.regression/inventar.json nennt alle`)
  }
  const erste = position(von, 0)
  const letzte = position(bis, ids.length - 1)
  if (erste > letzte) throw new LaufFehler('--from liegt hinter --to')
  const teil = erste !== 0 || letzte !== ids.length - 1
  const bereich = new Set(ids.slice(erste, letzte + 1))
  if (!bereich.size) throw new LaufFehler('Die Auswahl ist leer')
  // Die Aufnahme läuft nicht mit den Tests, sondern nach ihrem Urteil.
  const testBereich = [...bereich].filter((id) => id !== BILD_ID)

  const runde = async () => {
    const lauf = new Lauf(kopf)
    const offen = new Set()
    for (const id of testBereich) {
      const t = tests.get(id)
      const inDieserRunde = t?.versuche?.some((v) => v.runde === kopf.runde)
      if (t?.laeuft) melden(`[lauf] fortgesetzt: «${id}» wurde unterbrochen`)
      if (kopf.phase === 'final' ? !inDieserRunde || t?.laeuft : !t?.bestanden) offen.add(id)
    }
    const alle = offen.size === testBereich.length && !teil
    melden(`[lauf] ${kopf.phase === 'final' ? 'Schlussregression' : 'Reparaturrunde'} ${kopf.runde}: ${offen.size} von ${testBereich.length} Tests`)
    if (offen.size) await ausfuehrenAuswahl(lauf, inventar, alle ? null : offen, waechter, { zweiStacks })
    for (const [id, e] of lauf.ergebnisse) {
      const t = tests.get(id) || { versuche: [] }
      t.versuche.push({ runde: kopf.runde, ergebnis: e.ergebnis, hash: e.hash })
      t.bestanden = e.ergebnis === 'bestanden' ? { runde: kopf.runde } : null
      t.laeuft = null
      tests.set(id, t)
    }
    return lauf
  }

  let lauf = await runde()
  const gruenInRunde = (id) => tests.get(id)?.versuche?.some((v) => v.runde === kopf.runde && v.ergebnis === 'bestanden')
  const testIds = ids.filter((id) => id !== BILD_ID)
  let testsGruen = false
  if (!teil && !lauf.laufFehler) {
    if (kopf.phase === 'final' && testIds.every(gruenInRunde)) {
      testsGruen = true
    } else if (testIds.every((id) => tests.get(id)?.bestanden)) {
      kopf = { ...kopf, phase: 'final', runde: kopf.runde + 1 }
      schreiben({ ereignis: 'runde', lauf: kopf.lauf, phase: 'final', runde: kopf.runde })
      melden('[lauf] Jeder Test ist grün; die vollständige Schlussregression folgt')
      lauf = await runde()
      testsGruen = !lauf.laufFehler && testIds.every(gruenInRunde)
    }
  }
  const laufFehler = lauf.laufFehler || 0
  // Das Urteil über die Tests steht im Journal, bevor die Aufnahme beginnt: Ein
  // Abbruch in der Aufnahme lässt es stehen, und der nächste Aufruf setzt mit
  // der Aufnahme fort.
  schreiben({ ereignis: 'urteil', lauf: kopf.lauf, runde: kopf.runde, phase: kopf.phase, tests_gruen: testsGruen, teil, zahlen: zaehlen(inventar.filter((t) => t.id !== BILD_ID), ergebnisseDerRunde(tests, kopf.runde), teil ? bereich : null), laufFehler })
  const bildNoetig = ids.includes(BILD_ID)
  if (bildNoetig && bereich.has(BILD_ID) && (testsGruen || teil) && !laufFehler) {
    if (bildZiel) await bildAufnehmen(lauf, tests, bildZiel)
    else melden('[lauf] --ohne-bild: keine Aufnahme; ohne sie gibt der Lauf nicht frei')
  }
  const bildGruen = !bildNoetig || gruenInRunde(BILD_ID)
  const quelleGleich = abh.quellIdentitaet() === kopf.quelle
  if (!quelleGleich) melden('[lauf] Der Quellstand hat sich während des Laufs geändert; der Lauf gibt nicht frei')
  const freigegeben = !teil && !laufFehler && testsGruen && bildGruen && quelleGleich
  const rundeDesUrteils = kopf.runde
  const zahlen = zaehlen(inventar, ergebnisseDerRunde(tests, rundeDesUrteils), teil ? bereich : null)
  schreiben({ ereignis: 'fertig', lauf: kopf.lauf, runde: rundeDesUrteils, freigegeben, teil, zahlen, laufFehler, bild: bildGruen, quelle_gleich: quelleGleich })
  if (!teil && !laufFehler && !freigegeben) {
    kopf = { ...kopf, phase: 'reparatur', runde: kopf.runde + 1 }
    schreiben({ ereignis: 'runde', lauf: kopf.lauf, phase: 'reparatur', runde: kopf.runde })
  }
  if (freigegeben) {
    // Die Hashes, mit denen jede Gruppe in dieser Schlussregression lief.
    const gruen = {}
    for (const g of GRUPPEN) {
      const v = tests.get(`${g}:gruppe`)?.versuche?.filter((x) => x.runde === rundeDesUrteils && x.ergebnis === 'bestanden').at(-1)
      if (v?.hash) gruen[g] = { hash: v.hash, zeit: new Date().toISOString() }
    }
    atomarSchreiben(GRUEN, JSON.stringify(gruen, null, 2))
  }
  const rot = [...lauf.ergebnisse].filter(([, e]) => e.ergebnis !== 'bestanden')
  if (rot.length) {
    melden('[lauf] Nicht bestanden:')
    for (const [id, e] of rot) melden(`  ✗ [${e.ergebnis}] ${id}${e.fehler ? `\n      ${e.fehler}` : ''}`)
  }
  melden(`[lauf] ${bericht(zahlen)}`)
  const einzeln = [...lauf.ergebnisse.values()]
  melden(`[lauf] In diesem Aufruf gemeldete Fälle, einschliesslich der Einzelfälle von PHPUnit und vitest: ${einzeln.length}, davon nicht bestanden ${einzeln.filter((e) => e.ergebnis !== 'bestanden').length}`)
  melden(freigegeben ? '[lauf] FREIGEGEBEN: jeder Test und die Aufnahme grün in einer vollständigen Schlussregression' : teil ? '[lauf] Teillauf, keine Freigabe' : `[lauf] NICHT freigegeben; der nächste Aufruf ist Reparaturrunde ${kopf.runde}`)
  return { freigegeben, teil, rot: rot.length, laufFehler }
}

/**
 * Bei einem Abbruch durch ein Signal: Der Playwright-Lauf im Container der
 * Tests endet mit seinem Container. Die übrigen Prozesse des Laufs stehen in
 * seiner Prozessgruppe; ein Signal an die Gruppe, wie es die Warteschlange der
 * Maschine schickt, erreicht sie selbst.
 */
export function abbrechen() {
  abh.stack.abbrechen()
}

async function haupt() {
  if (schalter('--list')) {
    const inventar = path.join(ABLAGE, 'inventar.json')
    if (fs.existsSync(inventar)) {
      for (const t of JSON.parse(fs.readFileSync(inventar, 'utf8'))) console.log(`${t.nr} ${t.id}${t.requires.length ? ` (braucht ${t.requires.join(', ')})` : ''}`)
    } else {
      console.log('Noch kein Inventar: Es entsteht beim ersten Lauf (Playwright liefert seine Tests aus dem laufenden Container).')
      for (const g of GRUPPEN) console.log(`Gruppe ${g}`)
      for (const a of abh.stack.abschnittListe()) console.log(`${a.id} (${a.art}${a.requires.length ? `, braucht ${a.requires.join(', ')}` : ''})`)
      console.log(BILD_ID)
    }
    return 0
  }
  // Ein abgebrochener Lauf kann Produktdateien geschaltet zurückgelassen haben.
  schalterZuruecklegen()
  const freigeben = sperreNehmen()
  const beenden = (signal, code) => () => { abbrechen(); schalterZuruecklegen(); freigeben(); melden(`[lauf] beendet durch ${signal}`); process.exit(code) }
  process.on('SIGINT', beenden('SIGINT', 130))
  process.on('SIGTERM', beenden('SIGTERM', 143))
  process.on('SIGHUP', beenden('SIGHUP', 129))
  const waechter = new Waechter()
  waechter.starten()
  setInterval(offenePhasenMelden, 300_000).unref()
  const beginn = Date.now()
  try {
    const quelle = abh.quellIdentitaet()
    const plan = option('--npnp')
    if (plan) {
      const sauber = await doppelpruefung(path.resolve(plan), waechter, { quelle })
      melden(sauber ? '[doppelprüfung] SAUBER: jede Phase wie erwartet; die Schlussregression folgt' : '[doppelprüfung] NICHT SAUBER; die Schlussregression startet nicht')
      if (!sauber) return 1
    }
    const bildZiel = schalter('--ohne-bild') ? null : option('--bild') || '/tmp/parlwin-design'
    const r = await schlussregression(waechter, quelle, { von: option('--from'), bis: option('--to'), neu: schalter('--neu') || Boolean(plan), bildZiel })
    if (r.freigegeben && !schalter('--behalten') && !ohneE2e()) {
      for (const b of ['chromium', 'firefox']) await abh.stack.abbauen(b)
    } else if (!ohneE2e()) {
      melden('[stack] Die Stacks bleiben für die nächste Runde stehen; abbauen mit: npm run test:stack -- ab')
    }
    return r.freigegeben || (r.teil && r.rot === 0 && r.laufFehler === 0) ? 0 : 1
  } finally {
    melden(`[lauf] Dauer ${dauerText(Date.now() - beginn)}`)
    waechter.anhalten()
    schalterZuruecklegen()
    freigeben()
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(new URL(import.meta.url).pathname)) {
  haupt().then((code) => process.exit(code)).catch((e) => {
    schalterZuruecklegen()
    console.error(`regression: ${e.message}`)
    if (e.details) console.error(JSON.stringify(e.details, null, 2))
    process.exit(2)
  })
}
