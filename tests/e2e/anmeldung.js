import fs from 'node:fs'
import path from 'node:path'

/**
 * Anmeldung der Tests an Nextcloud.
 *
 * globale-anmeldung.js meldet jeden Testnutzer einmal je Playwright-Lauf über
 * das Formular an und legt seine Cookies als `storageState` in ANMELDUNG_DIR
 * ab. `login()` übernimmt sie in den Kontext des Tests und fragt Nextcloud, ob
 * die Sitzung gilt. Gilt sie nicht (nach dem Zurückspielen der Datenbank) oder
 * fehlt die Datei, meldet `login()` über das Formular an.
 * `PW_ANMELDUNG=formular` erzwingt das Formular für jeden Test.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
export const ANMELDUNG_DIR = process.env.PW_ANMELDUNG_DIR || '/work/.anmeldung'

export function anmeldungDatei(name) {
  return path.join(ANMELDUNG_DIR, `${name}.json`)
}

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an. */
export async function formularAnmeldung(page, user) {
  await page.goto(`${BASE_URL}/index.php/login`)
  // Auf das interaktive Login-Formular warten: in Firefox wird der Submit-Handler
  // (Vue) teils verzögert gebunden, ein zu früher Klick löst dann keine Navigation
  // aus und der Login bleibt hängen.
  await page.waitForSelector('input[name="user"]', { state: 'visible', timeout: 30_000 })
  await page.fill('input[name="user"]', user.name)
  await page.fill('input[name="password"]', user.pass)
  // Bis zu drei Versuche: Klick auslösen (Fehler bewusst verwerfen, da der Klick
  // selbst die Navigation startet) und auf den Seitenwechsel warten. waitUntil
  // 'commit' statt des Standards 'load' – sonst wartet Playwright in Firefox auf das
  // vollständige Laden des Dashboards (viele Requests) und läuft in einen Timeout,
  // obwohl die URL längst nicht mehr auf /login zeigt.
  let zuletzt
  for (let versuch = 0; versuch < 3; versuch++) {
    void page.click('button[type="submit"], input[type="submit"]', { noWaitAfter: true }).catch(() => {})
    try {
      await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 12_000, waitUntil: 'commit' })
      await page.waitForLoadState('domcontentloaded').catch(() => {})
      return
    } catch (e) {
      zuletzt = e
    }
  }
  throw zuletzt
}

/** Fragt Nextcloud, wer mit den Cookies des Kontexts angemeldet ist. */
export async function angemeldetAls(anfrage) {
  const antwort = await anfrage.get(`${BASE_URL}/ocs/v2.php/cloud/user?format=json`, {
    headers: { 'OCS-APIRequest': 'true' },
    failOnStatusCode: false,
  })
  if (!antwort.ok()) return ''
  const daten = await antwort.json().catch(() => null)
  return String(daten?.ocs?.data?.id || '')
}

/**
 * Meldet `user` in `page` an. Am Ende steht die Seite wie nach dem Formular auf
 * der Startseite von Nextcloud, mit geladenem Dokument.
 */
export async function login(page, user) {
  const datei = anmeldungDatei(user.name)
  if (process.env.PW_ANMELDUNG !== 'formular' && fs.existsSync(datei)) {
    const stand = JSON.parse(fs.readFileSync(datei, 'utf8'))
    await page.context().addCookies(stand.cookies || [])
    if (await angemeldetAls(page.request) === user.name) {
      await page.goto(`${BASE_URL}/index.php/`, { waitUntil: 'commit' })
      await page.waitForLoadState('domcontentloaded').catch(() => {})
      if (!new URL(page.url()).pathname.includes('/login')) return
    }
    await page.context().clearCookies()
  }
  await formularAnmeldung(page, user)
}
