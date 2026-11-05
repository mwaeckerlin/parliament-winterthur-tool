// Die E2E-Stacks des Laufs: je Browser ein Compose-Projekt, einmal gebaut,
// einmal aufgebaut und über alle Phasen gehalten.
//
//   Images      je Dienst eine Marke aus dem Hash seiner Eingaben; gebaut wird
//               nur, wenn die Marke fehlt (bildHash in hash.mjs).
//   Aufbau      Nextcloud, App, Benutzer, Abgleich, Budget 2026 → Stand s0;
//               Rollen, Fraktionsraum, feste Testdaten → Stand s1.
//   Zustand     vor jeder Phase: Datenbank und Datenvolume auf s0 oder s1
//               zurück, Container nextcloud-php-fpm neu aus dem Image, die
//               geänderten Dateien der App in seinen Seed, Start. Der
//               Bootstrap spiegelt den Seed beim Start ins Volume
//               (pwbSyncParlwin in docker/parlwin-bootstrap.php), APCu und
//               opcache beginnen leer.
//   Playwright  ein Container der Tests je Stack für den ganzen Lauf, der
//               Dienst `playwright` des Compose-Projekts; jede Phase ist ein
//               `npx playwright test` darin über `docker exec`.
//
// Kommandozeile: node tests/regression/stack.mjs <auf|ab|status|bild> [--browser <name>]
import { createHash } from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { ABLAGE, LaufFehler, WURZEL, atomarSchreiben, ausfuehren, dauerText, lesen, melden, warte } from './hilfen.mjs'
import { bildHash, bildId } from './hash.mjs'
import { Waechter } from './speicher.mjs'

const REPO = 'mwaeckerlin/parliament-winterthur-tool'
const SEED = '/usr/local/share/nextcloud/seed/custom_apps/parlwin'
const E2E_SKRIPT = path.join(WURZEL, 'tests', 'e2e', 'run-compose-e2e.sh')
const OVERRIDE = path.join(ABLAGE, 'compose.regression.yml')

// Jeder Prozess, den dieses Modul startet, geht über diese beiden Funktionen;
// die Komponententests setzen hier ihre eigenen ein.
export const werkzeug = { ausfuehren, lesen }

// Die Eingaben jedes Image, aus den COPY-Zeilen seines Dockerfile.
export const BILDER = {
  'nextcloud-nginx': { marke: 'nginx', dateien: ['Dockerfile.nginx', 'docker/nginx'], basen: ['mwaeckerlin/nextcloud:nginx'] },
  'nextcloud-php-fpm': {
    marke: 'php-fpm',
    dateien: ['Dockerfile.php-fpm', 'package.json', 'package-lock.json', 'CHANGELOG.md', 'README.md', 'docker/iconv-shim.c', 'docker/parlwin-bootstrap.php', 'docker/parlwin-watcher.php', 'parlwin'],
    // .dockerignore: **/tests, node_modules, .phpunit.result.cache
    ohne: /(^|\/)(tests|node_modules)(\/|$)|\.phpunit\.result\.cache$/,
    basen: ['mwaeckerlin/nextcloud:php-fpm', 'mwaeckerlin/very-base', 'mwaeckerlin/nodejs-build'],
  },
  'parlwin-realtime': { marke: 'realtime', dateien: ['Dockerfile.realtime', 'realtime-server'], basen: ['mwaeckerlin/nodejs-build', 'mwaeckerlin/nodejs'] },
  'parlwin-quelle': { marke: 'quelle', dateien: ['Dockerfile.quelle', 'Dockerfile.quelle.dockerignore', 'docker/quelle', 'parlwin/tests/Fixtures'], basen: ['mwaeckerlin/very-base', 'mwaeckerlin/nginx'] },
  playwright: {
    marke: 'playwright',
    dateien: ['tests/e2e'],
    // tests/e2e/.dockerignore
    ohne: /(^|\/)(node_modules|\.junit|test-results|playwright-report)(\/|$)|docker-compose\.e2e\.yml$/,
    basen: ['mcr.microsoft.com/playwright:v1.61.0-noble'],
  },
}

export function projektName(browser) {
  return `parlwin_e2e_${browser}`
}

// Das Verzeichnis eines Stacks hält seinen Stand mit den App-Passwörtern und
// die Sicherungen der Datenbank; nur das eigene Konto kann es lesen.
function ablage(browser) {
  const dir = path.join(ABLAGE, projektName(browser))
  fs.mkdirSync(dir, { recursive: true, mode: 0o700 })
  fs.chmodSync(dir, 0o700)
  return dir
}

function standLesen(browser) {
  const datei = path.join(ablage(browser), 'stack.json')
  return fs.existsSync(datei) ? JSON.parse(fs.readFileSync(datei, 'utf8')) : {}
}

function standSchreiben(browser, stand) {
  atomarSchreiben(path.join(ablage(browser), 'stack.json'), JSON.stringify(stand, null, 2))
}

// Die Umgebung, mit der run-compose-e2e.sh den Stack beschreibt (Passwörter
// des Testlaufs, Adressen, Grenzen des Abgleichs). Das Skript gibt sie mit
// `--umgebung` selbst aus; so steht jeder Wert nur dort.
let umgebungCache = null
export function e2eUmgebung() {
  if (umgebungCache) return umgebungCache
  const r = werkzeug.lesen('bash', [E2E_SKRIPT, '--umgebung'], { env: { PARLWIN_COMPOSE_ZUSATZ: OVERRIDE } })
  if (r.code !== 0) throw new LaufFehler(`run-compose-e2e.sh --umgebung endete mit ${r.code}: ${r.stderr.trim()}`)
  umgebungCache = Object.fromEntries(r.stdout.split('\n').filter((z) => z.includes('=')).map((z) => [z.slice(0, z.indexOf('=')), z.slice(z.indexOf('=') + 1)]))
  return umgebungCache
}

function env(browser, zusatz = {}) {
  const projekt = projektName(browser)
  return {
    ...e2eUmgebung(),
    COMPOSE_PROJECT_NAME: projekt,
    PARLWIN_COMPOSE_ZUSATZ: OVERRIDE,
    PARLWIN_ZUSTAND_DIR: path.join(ablage(browser), 'zustand'),
    PARLWIN_STACK_BEHALTEN: '1',
    // Die Images baut imagesBauen() nach ihren Marken; aufbau:start baut nichts.
    PARLWIN_OHNE_BAU: '1',
    MYSQL_PWD: e2eUmgebung().NEXTCLOUD_DB_PASSWORD,
    ...zusatz,
  }
}

function compose(browser, argumente, optionen = {}) {
  return werkzeug.ausfuehren('docker', ['compose', ...argumente], { ...optionen, env: { ...env(browser), ...(optionen.env || {}) } })
}

function composeLesen(browser, argumente) {
  return werkzeug.lesen('docker', ['compose', ...argumente], { env: env(browser) })
}

// ---------------------------------------------------------------- Images ---

export function markenBestimmen() {
  const marken = {}
  for (const [dienst, b] of Object.entries(BILDER)) marken[dienst] = `${REPO}:${b.marke}-${bildHash(b)}`
  return marken
}

/** Schreibt die Zusatzdatei für Compose: Marken je Dienst, Werkzeug-Dienste. */
export function overrideSchreiben(marken, nutzer = '') {
  const zeilen = ['# Erzeugt von tests/regression/stack.mjs. Nicht von Hand ändern.', 'services:']
  for (const [dienst, marke] of Object.entries(marken)) {
    zeilen.push(`  ${dienst}:`, `    image: ${marke}`)
    // Der Container der Tests wartet den ganzen Lauf über, bis eine Phase
    // `npx playwright test` darin startet. Die Tests stecken im Image.
    if (dienst === 'playwright') zeilen.push('    command: ["node", "-e", "setInterval(() => {}, 1 << 30)"]')
  }
  zeilen.push(
    // Sichert und spielt das Volume `data` zurück: tar schreibt nach stdout
    // und liest von stdin, die Datei liegt in tests/.regression. Der Dienst
    // läuft als der Nutzer von php-fpm, dem die Dateien im Volume gehören;
    // ein zweites Volume für die Sicherung gehörte root und war für ihn
    // nicht beschreibbar.
    '  sicherung:',
    '    image: mwaeckerlin/very-base',
    '    profiles: ["werkzeug"]',
    '    network_mode: none',
    ...(nutzer ? [`    user: "${nutzer}"`] : []),
    '    volumes:',
    '      - data:/daten',
    // Baut nur die Oberfläche (Stufe app-build), für einen Schalter in parlwin/src.
    '  app-bau:',
    '    build:',
    '      context: .',
    '      dockerfile: Dockerfile.php-fpm',
    '      target: app-build',
    '    image: parlwin-app-bau:lokal',
    '    profiles: ["werkzeug"]',
    '',
  )
  atomarSchreiben(OVERRIDE, zeilen.join('\n'))
}

/** Baut jedes Image, dessen Marke fehlt; gibt die Marken zurück. */
export async function imagesBauen(browser) {
  const marken = markenBestimmen()
  overrideSchreiben(marken, standLesen(browser).nutzer || '')
  for (const [dienst, marke] of Object.entries(marken)) {
    if (bildId(marke)) {
      melden(`[bau] ${dienst}: ${marke} vorhanden, kein Bau`)
      continue
    }
    const beginn = Date.now()
    melden(`[bau] ${dienst}: ${marke} fehlt, baue`)
    const r = await compose(browser, ['build', dienst])
    if (r.code !== 0) throw new LaufFehler(`Bau von ${dienst} endete mit ${r.code}`)
    melden(`[bau] ${dienst}: gebaut in ${dauerText(Date.now() - beginn)}`)
  }
  return marken
}

// -------------------------------------------------------- Abschnitte ---

/**
 * Führt Abschnitte von run-compose-e2e.sh aus. Jede Zeile «##INT {json}» geht an
 * `ereignis`; zurück kommen die Ergebnisse je Abschnitt.
 */
export async function abschnitte(browser, ids, ereignis = () => {}, zusatzEnv = {}) {
  const ergebnisse = {}
  const r = await werkzeug.ausfuehren('bash', [E2E_SKRIPT, '--nur', ids.join(',')], {
    env: { ...env(browser), PARLWIN_STACK_VORHANDEN: ids.includes('aufbau:start') ? '0' : '1', ...zusatzEnv },
    protokoll: path.join(ablage(browser), 'abschnitte.log'),
    zeile: (z) => {
      if (!z.startsWith('##INT ')) return
      const e = JSON.parse(z.slice(6))
      if (e.ereignis === 'ende') ergebnisse[e.id] = e
      ereignis(e)
    },
  })
  return { code: r.code, ergebnisse }
}

export function abschnittListe() {
  const r = werkzeug.lesen('bash', [E2E_SKRIPT, '--liste'])
  if (r.code !== 0) throw new LaufFehler(`run-compose-e2e.sh --liste endete mit ${r.code}: ${r.stderr.trim()}`)
  return r.stdout.split('\n').filter(Boolean).map((z) => {
    const [id, art, abhaengig, gesamtlauf] = z.split('\t')
    return { id, art, requires: abhaengig ? abhaengig.split(',') : [], gesamtlauf: gesamtlauf === '1' }
  })
}

// ------------------------------------------------------------- Zustand ---

function containerId(browser, dienst) {
  return composeLesen(browser, ['ps', '-aq', dienst]).stdout.trim().split('\n')[0] || ''
}

async function phpAusfuehren(browser, code, zusatz = []) {
  let ausgabe = ''
  await compose(browser, ['exec', '-T', ...zusatz, 'nextcloud-php-fpm', 'php', '-r', code], { still: true, zeile: (z, kanal) => { if (kanal === 'stdout') ausgabe += `${z}\n` } })
  return ausgabe.trim()
}

/** Wartet, bis Nextcloud über nginx «installed» meldet (wie ab_nextcloud in run-compose-e2e.sh). */
async function bereit(browser) {
  const beginn = Date.now()
  for (let versuch = 1; versuch <= 180; versuch++) {
    const status = await phpAusfuehren(browser, 'echo @file_get_contents("http://nextcloud-nginx:8080/status.php");').catch(() => '')
    if (/"installed":\s*true/.test(status)) {
      melden(`[stack] ${projektName(browser)} bereit nach ${dauerText(Date.now() - beginn)}`)
      return
    }
    // nginx löst den Namen von php-fpm beim Start auf. Erhält der neue
    // Container eine andere Adresse, antwortet nginx mit 502, bis er neu startet.
    if (versuch === 15) {
      melden(`[stack] ${projektName(browser)}: nach 30s keine Antwort über nginx, starte nextcloud-nginx neu (wird gemessen)`)
      await compose(browser, ['restart', 'nextcloud-nginx'])
    }
    await warte(2000)
  }
  throw new LaufFehler(`${projektName(browser)}: Nextcloud meldet nach 360s nicht «installed»`)
}

async function sichern(browser, name) {
  const beginn = Date.now()
  const dump = path.join(ablage(browser), `${name}.sql`)
  const r = await compose(browser, ['exec', '-T', '-e', 'MYSQL_PWD', 'nextcloud-db', 'mariadb-dump', '-unextcloud', '--single-transaction', '--skip-lock-tables', 'nextcloud'], { ausgabe: dump })
  if (r.code !== 0) throw new LaufFehler(`Sicherung der Datenbank (${name}) endete mit ${r.code}`)
  const archiv = path.join(ablage(browser), `${name}.tar`)
  const d = await compose(browser, ['--profile', 'werkzeug', 'run', '--rm', '-T', '--no-deps', 'sicherung', 'tar', '-C', '/daten', '-cf', '-', '.'], { ausgabe: archiv })
  if (d.code !== 0) throw new LaufFehler(`Sicherung des Volume data (${name}) endete mit ${d.code}`)
  melden(`[stack] ${projektName(browser)}: Stand ${name} gesichert in ${dauerText(Date.now() - beginn)} (Datenbank ${(fs.statSync(dump).size / 1024 ** 2).toFixed(1)}MiB, Volume data ${(fs.statSync(archiv).size / 1024 ** 2).toFixed(1)}MiB)`)
}

/**
 * Stellt Stand `name` (s0 oder s1) her und legt `tausch` in den Seed:
 * `tausch` ist eine Liste von { ziel: <pfad unter parlwin/>, quelle: <datei
 * oder verzeichnis auf dem Rechner> }. Der Container nextcloud-php-fpm wird
 * dafür aus dem Image neu angelegt; ein Tausch der vorigen Phase ist damit weg.
 */
export async function zustandHerstellen(browser, name, tausch = []) {
  const beginn = Date.now()
  const projekt = projektName(browser)
  melden(`[stack] ${projekt}: stelle Stand ${name} her${tausch.length ? `, tausche ${tausch.map((t) => t.ziel).join(', ')}` : ''}`)
  await compose(browser, ['rm', '-s', '-f', 'nextcloud-php-fpm'])
  const db = await compose(browser, ['exec', '-T', '-e', 'MYSQL_PWD', 'nextcloud-db', 'mariadb', '-unextcloud', 'nextcloud'], { eingabe: path.join(ablage(browser), `${name}.sql`) })
  if (db.code !== 0) throw new LaufFehler(`${projekt}: Zurückspielen der Datenbank (${name}) endete mit ${db.code}`)
  const daten = await compose(browser, ['--profile', 'werkzeug', 'run', '--rm', '-T', '--no-deps', 'sicherung', 'sh', '-c', 'find /daten -mindepth 1 -delete && tar -C /daten -xf -'], { eingabe: path.join(ablage(browser), `${name}.tar`) })
  if (daten.code !== 0) throw new LaufFehler(`${projekt}: Zurückspielen des Volume data (${name}) endete mit ${daten.code}`)
  const erstellt = await compose(browser, ['create', 'nextcloud-php-fpm'])
  if (erstellt.code !== 0) throw new LaufFehler(`${projekt}: nextcloud-php-fpm liess sich nicht neu anlegen`)
  const id = containerId(browser, 'nextcloud-php-fpm')
  for (const t of tausch) {
    // Ein Verzeichnis geht mit «/.» hinein, sonst legt docker cp es als
    // Unterverzeichnis in das bestehende (js/js).
    const quelle = fs.statSync(t.quelle).isDirectory() ? `${t.quelle}/.` : t.quelle
    const r = await werkzeug.ausfuehren('docker', ['cp', quelle, `${id}:${SEED}/${t.ziel}`], { still: true })
    if (r.code !== 0) throw new LaufFehler(`${projekt}: docker cp von ${t.quelle} nach ${SEED}/${t.ziel} endete mit ${r.code}`)
  }
  await compose(browser, ['start', 'nextcloud-php-fpm'])
  await bereit(browser)
  if (tausch.length) await tauschMessen(browser, tausch)
  const stand = standLesen(browser)
  stand.zuletzt = { name, tausch: tausch.map((t) => t.ziel), zeit: new Date().toISOString(), dauer_ms: Date.now() - beginn }
  standSchreiben(browser, stand)
  melden(`[stack] ${projekt}: Stand ${name} steht nach ${dauerText(Date.now() - beginn)}`)
  return Date.now() - beginn
}

/** Misst, was der Tausch im laufenden Container bewirkt hat: Besitz, Rechte, Inhalt. */
async function tauschMessen(browser, eintraege) {
  // Ein getauschtes Verzeichnis (js/, css/) wird an seiner Hauptdatei gemessen.
  const tausch = eintraege.flatMap((t) => {
    if (fs.statSync(t.quelle).isFile()) return [t]
    const haupt = ['parlwin-main.js', 'parlwin-style.css'].find((n) => fs.existsSync(path.join(t.quelle, n)))
    return haupt ? [{ ziel: `${t.ziel}/${haupt}`, quelle: path.join(t.quelle, haupt) }] : []
  })
  const pfade = tausch.map((t) => t.ziel)
  const code = `foreach (json_decode(getenv("PFADE"), true) as $p) {
    $s = @stat("${SEED}/" . $p); $v = @stat("/app/custom_apps/parlwin/" . $p);
    echo json_encode(["pfad" => $p, "seed_uid" => $s ? $s["uid"] : null, "seed_modus" => $s ? decoct($s["mode"] & 0777) : null,
      "volume_uid" => $v ? $v["uid"] : null, "volume_modus" => $v ? decoct($v["mode"] & 0777) : null,
      "volume_sha" => $v ? hash_file("sha256", "/app/custom_apps/parlwin/" . $p) : null, "php_uid" => posix_getuid()]), "\\n"; }`
  const ausgabe = await phpAusfuehren(browser, code, ['-e', `PFADE=${JSON.stringify(pfade)}`])
  for (const zeile of ausgabe.split('\n').filter(Boolean)) {
    const m = JSON.parse(zeile)
    const host = tausch.find((t) => t.ziel === m.pfad)
    const hostSha = createHash('sha256').update(fs.readFileSync(host.quelle)).digest('hex')
    melden(`[messung] Tausch ${m.pfad}: Seed uid ${m.seed_uid} Modus ${m.seed_modus}, Volume uid ${m.volume_uid} Modus ${m.volume_modus}, php uid ${m.php_uid}, Inhalt ${m.volume_sha === hostSha ? 'gleich der Datei auf dem Rechner' : 'ANDERS als die Datei auf dem Rechner'}`)
    if (m.volume_sha !== hostSha) throw new LaufFehler(`Der Tausch von ${m.pfad} kam im Volume nicht an`)
  }
}

// ---------------------------------------------------------- Messungen ---

async function messen(browser, waechter) {
  const projekt = projektName(browser)
  let opcache = ''
  await compose(browser, ['exec', '-T', 'nextcloud-php-fpm', '/usr/sbin/php-fpm', '-i'], {
    still: true,
    zeile: (z) => { if (/^opcache\.(enable|validate_timestamps|revalidate_freq) =>/.test(z)) opcache += `${z.trim()}; ` },
  })
  melden(`[messung] ${projekt} opcache im php-fpm: ${opcache || 'keine Zeile opcache.* in php-fpm -i'}`)
  const ids = await phpAusfuehren(browser, 'echo posix_getuid(), ":", posix_getgid();')
  melden(`[messung] ${projekt} Nutzer von php-fpm: ${ids}`)
  waechter.bedarfMessen(projekt)
  return { opcache, nutzer: ids }
}

// ---------------------------------------------------------- Playwright ---

/** Die ID des laufenden Containers der Tests, leer wenn keiner läuft. */
function pwContainerId(browser) {
  return composeLesen(browser, ['--profile', 'tools', 'ps', '-q', 'playwright']).stdout.trim().split('\n')[0] || ''
}

/**
 * Startet den Container der Tests als Dienst `playwright` des Compose-Projekts
 * und gibt seine ID zurück. Als Dienst trägt er die Labels des Projekts, und
 * jedes `docker compose down` des Projekts entfernt ihn mit, auch das der
 * Warteschlange der Maschine.
 */
export async function pwContainerStarten(browser) {
  const laeuft = pwContainerId(browser)
  if (laeuft) return laeuft
  const r = await compose(browser, ['--profile', 'tools', 'up', '-d', '--no-deps', 'playwright'])
  if (r.code !== 0) throw new LaufFehler(`Container der Tests von ${projektName(browser)} startete nicht (${r.code})`)
  const id = pwContainerId(browser)
  if (!id) throw new LaufFehler(`Container der Tests von ${projektName(browser)} läuft nach dem Start nicht`)
  melden(`[stack] Container der Tests von ${projektName(browser)} gestartet (${id.slice(0, 12)})`)
  return id
}

// Die Container der Tests, in denen gerade ein Playwright-Lauf arbeitet.
const LAUFENDE = new Set()

/** Führt `arbeit` aus und hält während dieser Zeit fest, dass im Container `id` Playwright läuft. */
async function imContainer(id, arbeit) {
  LAUFENDE.add(id)
  try {
    return await arbeit()
  } finally {
    LAUFENDE.delete(id)
  }
}

/**
 * Bei einem Abbruch des Laufs: `docker exec` gibt das Signal nicht an den
 * Playwright-Lauf im Container weiter. Jeder Container der Tests, in dem
 * gerade einer arbeitet, wird darum entfernt; `pwContainerStarten` legt ihn
 * beim nächsten Aufruf neu an.
 */
export function abbrechen() {
  for (const id of LAUFENDE) werkzeug.lesen('docker', ['rm', '-f', id])
  LAUFENDE.clear()
}

/**
 * Ein Playwright-Lauf im Container der Tests mit einem Worker
 * (tests/e2e/playwright.config.js). `auswahl` ist eine Liste von Titelpfaden
 * ([datei, describe…, titel]) oder null für alle Tests der Projekte.
 */
export async function playwright(browser, { projekte, auswahl = null, phase, workers = 1, ereignis = () => {} }) {
  const name = await pwContainerStarten(browser)
  const zusatz = ['-e', `PW_WORKERS=${workers}`]
  if (auswahl) {
    const datei = path.join(ablage(browser), `auswahl-${phase}.json`)
    fs.writeFileSync(datei, JSON.stringify(auswahl))
    await werkzeug.ausfuehren('docker', ['cp', datei, `${name}:/work/auswahl.json`], { still: true })
    zusatz.push('-e', 'PW_AUSWAHL=/work/auswahl.json')
  } else {
    zusatz.push('-e', 'PW_AUSWAHL=')
  }
  const zaehler = { worker: 0, anmeldungen: [], sperren: [] }
  const r = await imContainer(name, () => werkzeug.ausfuehren('docker', ['exec', ...zusatz, name, 'npx', 'playwright', 'test', '--config', 'playwright.config.js', ...projekte.flatMap((p) => ['--project', p])], {
    protokoll: path.join(ablage(browser), `playwright-${phase}.log`),
    // Der Reporter «list» schreibt die Ergebniszeile eines Tests ohne
    // Zeilenende, die Marke des Journals steht dann hinter ihr auf derselben
    // Zeile («… (42.6s)##PWJ {…}»). Gesucht wird sie darum in der ganzen Zeile.
    zeile: (z) => {
      const marke = (name) => { const at = z.indexOf(`##${name} `); return at === -1 ? null : JSON.parse(z.slice(at + name.length + 3)) }
      const pwj = marke('PWJ')
      if (pwj) {
        if (pwj.ereignis === 'worker') zaehler.worker += 1
        ereignis(pwj)
        return
      }
      const anmeldung = marke('ANMELDUNG')
      if (anmeldung) {
        zaehler.anmeldungen.push(anmeldung)
        return
      }
      const sperre = marke('SPERRE')
      if (sperre) zaehler.sperren.push(sperre)
    },
  }))
  const ziel = path.join(ablage(browser), `ergebnis-${phase}`)
  fs.mkdirSync(ziel, { recursive: true })
  await werkzeug.ausfuehren('docker', ['cp', `${name}:/work/.junit/e2e.xml`, path.join(ziel, 'e2e.xml')], { still: true })
  await werkzeug.ausfuehren('docker', ['cp', `${name}:/work/test-results`, ziel], { still: true })
  melden(`[messung] ${projektName(browser)} Phase ${phase}: ${zaehler.worker} Worker gestartet (je Worker ein Browserstart), ${zaehler.anmeldungen.filter((a) => a.art === 'neu angemeldet').length} Anmeldungen über das Formular, ${zaehler.anmeldungen.filter((a) => a.art === 'fehlgeschlagen').length} fehlgeschlagen, längste Wartezeit an der Sperre ${dauerText(Math.max(0, ...zaehler.sperren.map((s) => s.gewartet_ms)))}`)
  for (const a of zaehler.anmeldungen.filter((x) => x.art === 'fehlgeschlagen')) melden(`[messung] Anmeldung ${a.nutzer} ohne Browser fehlgeschlagen: ${a.grund}; die Tests melden diesen Nutzer über das Formular an`)
  return { code: r.code, ...zaehler }
}

/**
 * Das Inventar der Playwright-Tests, ohne Browser (`--list`). Ein Test, der auf
 * dem Ergebnis eines anderen Tests derselben Datei und desselben `describe`
 * aufbaut, nennt dessen Titel als Annotation `braucht`; daraus werden seine
 * Voraussetzungen.
 */
export async function playwrightInventar(browser, projekte) {
  const name = await pwContainerStarten(browser)
  let json = ''
  const r = await imContainer(name, () => werkzeug.ausfuehren('docker', ['exec', '-e', 'PW_AUSWAHL=', '-e', 'PW_OHNE_ANMELDUNG=1', name, 'npx', 'playwright', 'test', '--config', 'playwright.config.js', '--list', '--reporter=json', ...projekte.flatMap((p) => ['--project', p])], {
    still: true,
    zeile: (z, kanal) => { if (kanal === 'stdout') json += `${z}\n` },
  }))
  if (r.code !== 0) throw new LaufFehler(`Playwright --list endete mit ${r.code}`)
  const bericht = JSON.parse(json)
  const tests = []
  const kennung = (projekt, titelPfad) => `pw:${projekt}:${titelPfad[0]}::${titelPfad.slice(1).join(' › ')}`
  const gehen = (suite, pfad) => {
    for (const s of suite.suites || []) gehen(s, s.title ? [...pfad, s.title] : pfad)
    for (const spec of suite.specs || []) {
      for (const t of spec.tests || []) {
        const titelPfad = [...pfad, spec.title]
        const braucht = (t.annotations || []).filter((a) => a.type === 'braucht').map((a) => a.description)
        tests.push({ id: kennung(t.projectName, titelPfad), projekt: t.projectName, pfad: titelPfad, braucht, requires: braucht.map((titel) => kennung(t.projectName, [...pfad, titel])) })
      }
    }
  }
  for (const s of bericht.suites || []) gehen(s, [s.title])
  const ids = new Set(tests.map((t) => t.id))
  for (const t of tests) {
    const fehlt = t.braucht.filter((titel, i) => !ids.has(t.requires[i]))
    if (fehlt.length) throw new LaufFehler(`${t.id} braucht «${fehlt.join('», «')}», und diesen Test gibt es in seiner Datei nicht`)
    delete t.braucht
  }
  return tests
}

// ------------------------------------------------------------ Aufbau ---

const AUFBAU_S0 = ['aufbau:start', 'aufbau:nextcloud', 'aufbau:app', 'aufbau:benutzer', 'aufbau:abgleich', 'aufbau:budget']
const AUFBAU_S1 = ['aufbau:rollen', 'int:fraktionsraum', 'aufbau:firstrun', 'aufbau:modus-aus', 'int:testdaten']
// Woraus s1 besteht. Steht im Stand des Stacks; ein Stack mit einem anderen
// s1 wird neu aufgebaut.
const S1_ART = 'alle-abschnitte-vor-playwright'

/**
 * Stand s1 ist der Stand, den die Browser-Tests im vollen Lauf vorfinden:
 * nach jedem Abschnitt von run-compose-e2e.sh, der dort vor Playwright läuft.
 * Die Tests bauen auf dessen Spuren auf; mit nur den Abschnitten von
 * AUFBAU_S1 fehlt jeder Sitzungstyp («Kein Sitzungstyp definiert»), und die
 * Tests des Sitzungsformulars werden rot.
 */
function abschnitteVorPlaywright() {
  const s0 = new Set(AUFBAU_S0)
  const imGesamtlauf = abschnittListe().filter((a) => a.gesamtlauf && !s0.has(a.id))
  const pw = imGesamtlauf.findIndex((a) => a.id === 'int:playwright')
  const vor = imGesamtlauf.slice(0, pw).map((a) => a.id)
  for (const id of AUFBAU_S1) if (!vor.includes(id)) vor.push(id)
  return vor
}

/**
 * Baut den Stack eines Browsers auf, oder nimmt ihn, wenn er mit denselben
 * Marken schon läuft (Fortsetzen nach einem Absturz, Schlussregression nach
 * der Doppelprüfung).
 */
export async function aufbauen(browser, waechter, ereignis = () => {}) {
  const beginn = Date.now()
  const projekt = projektName(browser)
  waechter.projekte.add(projekt)
  const marken = await imagesBauen(browser)
  const stand = standLesen(browser)
  const laeuft = containerId(browser, 'nextcloud-db') && werkzeug.lesen('docker', ['ps', '-q', '--filter', `label=com.docker.compose.project=${projekt}`]).stdout.trim()
  if (laeuft && JSON.stringify(stand.marken) === JSON.stringify(marken) && stand.s1 && stand.s1Art === S1_ART) {
    melden(`[stack] ${projekt} läuft mit denselben Marken, wird weiter benutzt`)
    await pwContainerStarten(browser)
    return { wiederverwendet: true, dauer_ms: Date.now() - beginn }
  }
  fs.rmSync(path.join(ablage(browser), 'zustand'), { recursive: true, force: true })
  standSchreiben(browser, { marken, nutzer: stand.nutzer || '' })
  const s0 = await abschnitte(browser, AUFBAU_S0, ereignis)
  const rot0 = AUFBAU_S0.filter((id) => s0.ergebnisse[id]?.ergebnis !== 'bestanden')
  if (rot0.length) throw new LaufFehler(`${projekt}: Aufbau nicht bestanden: ${rot0.join(', ')}`, { ergebnisse: s0.ergebnisse })
  const messung = await messen(browser, waechter)
  overrideSchreiben(marken, messung.nutzer)
  standSchreiben(browser, { marken, nutzer: messung.nutzer, opcache: messung.opcache })
  await sichern(browser, 's0')
  const s1 = await abschnitte(browser, abschnitteVorPlaywright(), ereignis)
  const rot1 = AUFBAU_S1.filter((id) => s1.ergebnisse[id]?.ergebnis !== 'bestanden')
  if (rot1.length) throw new LaufFehler(`${projekt}: Vorbereitung der Testdaten nicht bestanden: ${rot1.join(', ')}`, { ergebnisse: s1.ergebnisse })
  // Die übrigen Abschnitte sind Prüfungen; ihr Ergebnis zählt dort, wo der
  // Lauf sie als Test ausführt. Hier steht nur, was beim Aufbau nicht bestand.
  for (const [id, e] of Object.entries(s1.ergebnisse)) {
    if (e.ergebnis !== 'bestanden' && !AUFBAU_S1.includes(id)) melden(`[stack] ${projekt}: beim Aufbau von s1 ist ${id} ${e.ergebnis}`)
  }
  await sichern(browser, 's1')
  await pwContainerStarten(browser)
  waechter.bedarfMessen(projekt)
  standSchreiben(browser, { ...standLesen(browser), s0: true, s1: true, s1Art: S1_ART, aufgebaut: new Date().toISOString(), dauer_ms: Date.now() - beginn })
  melden(`[stack] ${projekt} aufgebaut in ${dauerText(Date.now() - beginn)}`)
  return { wiederverwendet: false, dauer_ms: Date.now() - beginn }
}

/**
 * Baut den Stack ab, den Container der Tests eingeschlossen, und misst danach,
 * ob noch ein Container das Label des Projekts trägt.
 */
export async function abbauen(browser) {
  const projekt = projektName(browser)
  await compose(browser, ['--profile', 'werkzeug', '--profile', 'tools', 'down', '-v', '--remove-orphans'])
  const uebrig = werkzeug.lesen('docker', ['ps', '-aq', '--filter', `label=com.docker.compose.project=${projekt}`]).stdout.trim()
  if (uebrig) throw new LaufFehler(`${projekt}: nach docker compose down stehen noch Container des Projekts: ${uebrig.split('\n').join(', ')}`)
  fs.rmSync(ablage(browser), { recursive: true, force: true })
  melden(`[stack] ${projekt} abgebaut`)
}

// ------------------------------------------------------- Oberfläche bauen ---

/** Baut die Oberfläche aus dem Arbeitsbaum (Stufe app-build) und holt js/ und css/ heraus. */
export async function oberflaecheBauen(browser, ziel) {
  const beginn = Date.now()
  const r = await compose(browser, ['--profile', 'werkzeug', 'build', 'app-bau'])
  if (r.code !== 0) throw new LaufFehler(`Bau der Oberfläche endete mit ${r.code}`)
  const name = `parlwin-app-bau-${process.pid}`
  fs.rmSync(ziel, { recursive: true, force: true })
  fs.mkdirSync(ziel, { recursive: true })
  await werkzeug.ausfuehren('docker', ['create', '--name', name, 'parlwin-app-bau:lokal'], { still: true })
  try {
    for (const teil of ['js', 'css']) {
      const c = await werkzeug.ausfuehren('docker', ['cp', `${name}:/build/parlwin/${teil}`, path.join(ziel, teil)], { still: true })
      if (c.code !== 0) throw new LaufFehler(`parlwin/${teil} liess sich aus dem Bau nicht holen`)
    }
  } finally {
    await werkzeug.ausfuehren('docker', ['rm', '-f', name], { still: true })
  }
  melden(`[bau] Oberfläche aus dem Arbeitsbaum gebaut in ${dauerText(Date.now() - beginn)}`)
}

// ------------------------------------------------------------- Design ---

/** Zählt die Dateien unter `verzeichnis`, 0 wenn es fehlt. */
export function dateienZaehlen(verzeichnis) {
  if (!fs.existsSync(verzeichnis)) return 0
  return fs.readdirSync(verzeichnis, { recursive: true }).filter((n) => fs.statSync(path.join(verzeichnis, String(n))).isFile()).length
}

/**
 * Der Design-Lauf auf dem Chromium-Stack, auf Stand s0 (nach dem Abgleich,
 * ohne Testdaten). Gibt den Rückgabewert von Playwright und die Zahl der
 * Bilder unter `<ziel>/out` zurück.
 */
export async function bild(ziel) {
  const browser = 'chromium'
  await zustandHerstellen(browser, 's0')
  const name = await pwContainerStarten(browser)
  const passwort = Array.from({ length: 24 }, () => 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'[Math.floor(Math.random() * 56)]).join('')
  const occ = (...a) => compose(browser, ['exec', '-T', 'nextcloud-php-fpm', 'php', '/app/occ', '--no-ansi', ...a])
  await compose(browser, ['exec', '-T', '-e', 'OC_PASS', 'nextcloud-php-fpm', 'php', '/app/occ', '--no-ansi', 'user:add', '--password-from-env', '--display-name=Design Review', 'design-review'], { env: { OC_PASS: passwort } })
  await occ('app:disable', 'firstrunwizard')
  await occ('user:setting', 'design-review', 'core', 'lang', 'de')
  // Die Datei des Design-Laufs, seine Bilder und seine Sitzung liegen nur
  // während der Aufnahme im Container der Tests. Bliebe die Datei liegen,
  // nähme das nächste `--list` sie ins Inventar auf; blieben Bilder liegen,
  // zählte die nächste Aufnahme sie mit.
  const aufraeumen = () => werkzeug.ausfuehren('docker', ['exec', name, 'rm', '-rf', '/work/design-capture.spec.js', '/work/bilder', '/work/.anmeldung/design-review.json'], { still: true })
  try {
    await aufraeumen()
    await werkzeug.ausfuehren('docker', ['cp', path.join(WURZEL, '.claude', 'design-capture.spec.js'), `${name}:/work/design-capture.spec.js`], { still: true })
    const r = await imContainer(name, () => werkzeug.ausfuehren('docker', ['exec', '-e', 'PW_OHNE_ANMELDUNG=1', '-e', 'PW_AUSWAHL=', '-e', 'PW_DESIGN_USER=design-review', '-e', 'PW_DESIGN_PASS', '-e', 'PW_DESIGN_OUT=/work/bilder', name,
      'npx', 'playwright', 'test', '--config', 'playwright.config.js', 'design-capture.spec.js', '--project', 'chromium', '--reporter', 'list'], { env: { PW_DESIGN_PASS: passwort }, protokoll: path.join(ABLAGE, 'bild.log') }))
    fs.rmSync(path.join(ziel, 'out'), { recursive: true, force: true })
    fs.mkdirSync(ziel, { recursive: true })
    await werkzeug.ausfuehren('docker', ['cp', `${name}:/work/bilder`, path.join(ziel, 'out')], { still: true })
    const bilder = dateienZaehlen(path.join(ziel, 'out'))
    melden(`[bild] Aufnahme endete mit ${r.code}, ${bilder} Bilder in ${path.join(ziel, 'out')}`)
    return { code: r.code, bilder }
  } finally {
    await occ('user:delete', 'design-review')
    await aufraeumen()
  }
}

// ------------------------------------------------------- Kommandozeile ---

async function haupt() {
  const [befehl, ...rest] = process.argv.slice(2)
  const browser = rest.includes('--browser') ? rest[rest.indexOf('--browser') + 1] : null
  const browsers = browser ? [browser] : ['chromium', 'firefox']
  const waechter = new Waechter()
  if (befehl === 'auf') {
    waechter.starten()
    try { for (const b of browsers) await aufbauen(b, waechter) } finally { waechter.anhalten() }
  } else if (befehl === 'ab') {
    for (const b of browsers) await abbauen(b)
  } else if (befehl === 'status') {
    for (const b of browsers) console.log(projektName(b), JSON.stringify(standLesen(b)))
  } else if (befehl === 'bild') {
    const r = await bild(rest.find((a) => !a.startsWith('--')) || '/tmp/parlwin-design')
    process.exit(r.code === 0 && r.bilder > 0 ? 0 : 1)
  } else {
    console.error('Aufruf: node tests/regression/stack.mjs <auf|ab|status|bild [ziel]> [--browser chromium|firefox]')
    process.exit(2)
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(new URL(import.meta.url).pathname)) {
  haupt().catch((e) => { console.error(`stack: ${e.message}`); process.exit(1) })
}
