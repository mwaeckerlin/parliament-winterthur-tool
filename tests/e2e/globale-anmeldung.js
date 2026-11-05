import fs from 'node:fs'
import { request } from '@playwright/test'
import { ANMELDUNG_DIR, anmeldungDatei, angemeldetAls } from './anmeldung.js'

/**
 * Meldet jeden Testnutzer einmal je Playwright-Lauf an und legt seine Cookies
 * als `storageState` ab. Ohne Browser: Die Anmeldung holt die Anmeldeseite
 * (Sitzung, Cookies, Requesttoken) und schickt danach das Formular von
 * Nextcloud über einen Anfrage-Kontext von Playwright, mit dem Requesttoken
 * und dem Kopf `Origin`. Eine abgelegte Anmeldung, die noch gilt, bleibt stehen.
 *
 * Jede Anmeldung steht als Zeile «##ANMELDUNG» in der Ausgabe; der Lauf zählt
 * sie. Schlägt eine fehl, steht dort der Grund, und `login()` in den Tests
 * nimmt für diesen Nutzer das Formular im Browser.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

function nutzer() {
  return [
    { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' },
    { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
    { name: process.env.PW_U2 || 'parlwin_protokoll', pass: process.env.PW_P2 || '' },
    { name: process.env.PW_U3 || 'parlwin_mitglied', pass: process.env.PW_P3 || '' },
  ].filter((u) => u.pass !== '')
}

function melden(eintrag) {
  process.stdout.write(`##ANMELDUNG ${JSON.stringify(eintrag)}\n`)
}

/** `anfrage` ist `request` von Playwright; die Komponententests geben ihre eigene Gegenstelle. */
export async function anmelden(user, anfrage = request) {
  const datei = anmeldungDatei(user.name)
  if (fs.existsSync(datei)) {
    const pruefung = await anfrage.newContext({ baseURL: BASE_URL, storageState: datei })
    try {
      if (await angemeldetAls(pruefung) === user.name) {
        melden({ nutzer: user.name, art: 'weiter gültig' })
        return
      }
    } finally {
      await pruefung.dispose()
    }
  }
  const kontext = await anfrage.newContext({ baseURL: BASE_URL })
  try {
    // Anmeldeseite zuerst: Sie beginnt die Sitzung, an die das Requesttoken
    // gebunden ist, und setzt deren Cookies.
    const seite = await kontext.get('/index.php/login')
    const html = await seite.text()
    const token = (html.match(/data-requesttoken="([^"]+)"/) || [])[1] || ''
    if (!token) throw new Error(`die Anmeldeseite trägt kein Requesttoken (Status ${seite.status()})`)
    // LoginController::tryLogin weist eine Anmeldung ab, deren Kopf `Origin`
    // fehlt oder keine vertrauenswürdige Adresse nennt; ein Browser schickt ihn
    // beim Absenden des Formulars, der Anfrage-Kontext nicht von sich aus.
    await kontext.post('/index.php/login', {
      headers: { Origin: new URL(BASE_URL).origin },
      form: { user: user.name, password: user.pass, requesttoken: token, timezone: 'Europe/Zurich', timezone_offset: '1' },
      maxRedirects: 5,
    })
    const wer = await angemeldetAls(kontext)
    if (wer !== user.name) throw new Error(`nach dem Absenden angemeldet als «${wer || 'niemand'}»`)
    await kontext.storageState({ path: datei })
    melden({ nutzer: user.name, art: 'neu angemeldet' })
  } catch (fehler) {
    fs.rmSync(datei, { force: true })
    melden({ nutzer: user.name, art: 'fehlgeschlagen', grund: String(fehler && fehler.message || fehler) })
  } finally {
    await kontext.dispose()
  }
}

export default async function globaleAnmeldung() {
  if (process.env.PW_OHNE_ANMELDUNG === '1') return
  fs.mkdirSync(ANMELDUNG_DIR, { recursive: true })
  for (const user of nutzer()) {
    await anmelden(user)
  }
}
