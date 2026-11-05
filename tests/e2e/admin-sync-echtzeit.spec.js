import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei, weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * E2E der Administration im echten Browser gegen das reale System
 * (Nextcloud + DB + API + Sync-Worker + WebSocket-Echtzeit).
 *
 * Geprüft über den echten Nutzerpfad auf der Admin-Einstellungsseite
 * (/index.php/settings/admin/parlwin, Wurzel #parlwin-admin-settings) und die
 * App-Ansicht «Änderungsverlauf»:
 *  - Standard-Sync-Zeitplan (10:00 / 18:00 an allen Wochentagen) ist vorbelegt,
 *  - Zeitplan hinzufügen/löschen/unvollständig speichert automatisch und bleibt,
 *  - manuelle Synchronisation mit Live-Fortschritt und «läuft bereits», danach
 *    sicher abgebrochen (kein Warten auf den langsamen Voll-Lauf),
 *  - Abbruch einer laufenden Synchronisation (Status-API bestätigt den Stopp),
 *    «Synchronisierung abbrechen» bleibt bis zum Ende des Laufs gesperrt,
 *    ein gescheiterter Abbruch lässt sich wiederholen,
 *  - Fraktion/Gruppe: Optionen, Autosave, Persistenz, Gruppen-Status,
 *  - Kürzel über die UI (hinzufügen/löschen) und Datalist-Vorschläge,
 *  - Mitglieder-Zuordnung (Hinweise, Zeilen, der Benutzername speichert von selbst),
 *  - Änderungsverlauf (Karten, neueste offen, ältere zugeklappt, Toggle),
 *  - Echtzeit: eine in Kontext B gestartete Synchronisation erscheint in Kontext A,
 *    ohne dass die Seite neu geladen wird.
 *
 * Die Sync-Tests verändern globalen Zustand; jeder stellt zu Beginn und am Ende
 * sicher, dass KEIN Lauf hängen bleibt (ensureSyncIdle + afterAll-Sicherung).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP = `${BASE_URL}/index.php/apps/parlwin`
const ADMIN_URL = `${BASE_URL}/index.php/settings/admin/parlwin`

// Der Simulator der Stadtquelle im Stack (Dockerfile.quelle) und sein Steuerweg:
// PUT schaltet die Liste der Stadtratsbeschlüsse auf 500, DELETE zurück.
const QUELLE_URL = process.env.PARLWIN_QUELLE_URL || 'http://parlwin-quelle:8080'
const STADT_FEHLER = `${QUELLE_URL}/_steuerung/stadtratsbeschluesse-fehler`

const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

const stamp = Date.now()

// Live-Fortschritt in #pw-sync-details: «… - 5/10 | Gesamt 20/40 (50%) | …».
const FORTSCHRITT_RE = /\d+\/\d+ \| Gesamt \d+\/\d+ \(\d+%\)/

/** Öffnet die Admin-Einstellungsseite und wartet, bis die Wurzel gerendert ist. */
async function openAdmin(page) {
  await page.goto(ADMIN_URL)
  await page.waitForSelector('#parlwin-admin-settings', { state: 'visible', timeout: 30_000 })
  await page.waitForLoadState('networkidle').catch(() => {})
}

/** CSRF-Requesttoken der aktuell geladenen Nextcloud-Seite. */
const requestToken = (page) =>
  page.evaluate(() => window.OC?.Util?.getRequestToken?.() || window.OC?.requestToken || '')

/** GET auf einen App-Endpunkt (nutzt die Session-Cookies der Seite). */
function apiGet(page, path) {
  return page.request.get(`${APP}${path}`, { headers: { 'OCS-APIRequest': 'true' } })
}

/** POST auf einen App-Endpunkt ohne Body (mit Requesttoken). */
async function apiPost(page, path) {
  const token = await requestToken(page)
  return page.request.post(`${APP}${path}`, {
    headers: { 'OCS-APIRequest': 'true', requesttoken: token },
  })
}

/** POST mit JSON-Body (mit Requesttoken). */
async function apiPostJson(page, path, body) {
  const token = await requestToken(page)
  return page.request.post(`${APP}${path}`, {
    headers: { 'OCS-APIRequest': 'true', requesttoken: token, 'Content-Type': 'application/json' },
    data: JSON.stringify(body),
  })
}

/**
 * Aktueller Sync-Status als Objekt, oder null wenn (transient) nicht abrufbar.
 * Wichtig: ein fehlgeschlagener Request darf NICHT als «idle» missverstanden
 * werden — deshalb null statt eines leeren Objekts.
 */
async function syncStatus(page) {
  const r = await apiGet(page, '/sync/status').catch(() => null)
  if (!r || !r.ok()) {
    return null
  }
  return r.json().catch(() => null)
}

/**
 * Stellt sicher, dass KEIN Sync mehr läuft — Firefox-robust. Fordert den Abbruch
 * wiederholt an (alle ~3s, falls ein einzelner Abbruch-Request zwischen zwei
 * Worker-Phasen verpufft) und wartet, bis die Status-API EXPLIZIT running=false
 * meldet. Ein transient nicht abrufbarer Status (null) gilt NICHT als idle — es
 * wird weiter gepollt und abgebrochen. Ein realer Voll-Lauf gegen die
 * Parlaments-Webseite braucht evtl. eine Weile bis zu einem abbrechbaren Punkt;
 * das 240s-Budget deckt das ab. Bleibt am Ende dennoch ein Lauf aktiv, schlägt
 * die Prüfung hart fehl — so kann kein hängender Sync stillschweigend an
 * Folge-Specs weitergereicht werden.
 */
async function ensureSyncIdle(page) {
  const deadline = Date.now() + 240_000
  let letzterCancel = 0
  while (Date.now() < deadline) {
    const status = await syncStatus(page)
    if (status && status.running === false) {
      return
    }
    if (Date.now() - letzterCancel > 3000) {
      await apiPost(page, '/sync/cancel').catch(() => {})
      letzterCancel = Date.now()
    }
    await page.waitForTimeout(1000)
  }
  const final = await syncStatus(page)
  expect(
    final?.running,
    'Synchronisation läuft nach ensureSyncIdle noch — würde folgende Specs stören',
  ).toBe(false)
}

/**
 * Ein Versprechen, das der Test selbst einlöst: Ein Haken von `page.route`
 * wartet auf `warten`, bis der Test `frei()` ruft.
 */
function freigabe() {
  let frei
  const warten = new Promise((r) => { frei = r })
  return { warten, frei }
}

/**
 * Hält fest, was die Seite bei jeder Änderung zeigt: den Text in
 * #pw-sync-status und ob «Synchronisierung abbrechen» gesperrt ist. Gelesen
 * wird die Liste mit `window.__pwSyncVerlauf`.
 */
function verlaufMitschreiben(page) {
  return page.evaluate(() => {
    window.__pwSyncVerlauf = []
    const feld = document.querySelector('#pw-sync-status')
    const knopf = document.querySelector('#pw-btn-sync-cancel')
    const merken = () => {
      const eintrag = { text: (feld.textContent || '').trim(), gesperrt: knopf.disabled }
      const letzter = window.__pwSyncVerlauf[window.__pwSyncVerlauf.length - 1]
      if (!letzter || letzter.text !== eintrag.text || letzter.gesperrt !== eintrag.gesperrt) {
        window.__pwSyncVerlauf.push(eintrag)
      }
    }
    new MutationObserver(merken).observe(feld, { childList: true, characterData: true, subtree: true })
    new MutationObserver(merken).observe(knopf, { attributes: true, attributeFilter: ['disabled'] })
  })
}

/** Namen der aktiven, synchronisierten Fraktionen. */
async function activeFraktionen(page) {
  const r = await apiGet(page, '/fraktionen')
  if (!r.ok()) return []
  const data = await r.json()
  return Array.isArray(data) ? data.filter((f) => f.aktiv !== false).map((f) => String(f.name)) : []
}

test.describe('Administration, Sync, Echtzeit, Änderungsverlauf (e2e)', () => {
  let jsFehler

  test.beforeAll(() => {
    expect(ADMIN.pass, 'Admin-Passwort (PW_ADMIN_PASS) fehlt').not.toBe('')
    expect(U1.pass, `Passwort für ${U1.name} fehlt`).not.toBe('')
  })

  test.beforeEach(({ page }) => {
    jsFehler = []
    page.on('pageerror', (e) => jsFehler.push(e.message))
  })

  // Sicherheitsnetz nach ALLEN Tests: einen evtl. noch laufenden Sync abbrechen
  // und warten, bis der Worker gestoppt hat — sonst würde ein weiterlaufender
  // Sync (bzw. der davon geflutete Realtime-Broker) die Folge-Specs stören.
  // Kein openAdmin (das öffnete unnötig eine weitere WebSocket-Verbindung):
  // Abbruch/Status laufen rein über die API mit den Session-Cookies der Seite.
  test.afterAll(async ({ browser }) => {
    const ctx = await browser.newContext()
    try {
      const p = await ctx.newPage()
      await login(p, ADMIN)
      await ensureSyncIdle(p)
    } finally {
      await ctx.close()
    }
  })

  test.describe('Sync-Zeitplan', () => {
    test('Standard-Zeitplan ist vorbelegt: zwei Einträge 10:00 und 18:00 an allen Wochentagen', async ({ page }) => {
      await login(page, ADMIN)
      await openAdmin(page)

      // Gespeicherten Zeitplan leeren → der Server liefert dann den Standard.
      const res = await apiPostJson(page, '/settings/sync-zeitplan', { sync_zeitplan: [] })
      expect(res.ok(), 'Zeitplan leeren fehlgeschlagen').toBeTruthy()

      await page.reload()
      await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })

      const rows = page.locator('#pw-zeitplan-liste .pw-zeitplan-row')
      await expect(rows).toHaveCount(2, { timeout: 15_000 })

      // Bewusst auf den Input-Werten prüfen, nicht auf dem (evtl. veralteten)
      // Hinweistext.
      await expect(rows.nth(0).locator('.pw-zeitplan-zeit')).toHaveValue('10:00')
      await expect(rows.nth(1).locator('.pw-zeitplan-zeit')).toHaveValue('18:00')

      for (let i = 0; i < 2; i++) {
        const boxes = rows.nth(i).locator('input[type="checkbox"][data-tag]')
        await expect(boxes).toHaveCount(7)
        for (let d = 0; d < 7; d++) {
          await expect(boxes.nth(d), `Zeile ${i}: Wochentag ${d + 1} nicht angehakt`).toBeChecked()
        }
      }

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    test('Zeitplan hinzufügen speichert und bleibt; löschen entfernt; unvollständige Zeile wird nicht gespeichert', async ({ page }) => {
      test.setTimeout(120_000)
      await login(page, ADMIN)
      await openAdmin(page)

      const rowSel = '#pw-zeitplan-liste .pw-zeitplan-row'
      await page.waitForSelector(rowSel, { timeout: 15_000 })
      const status = page.locator('#pw-zeitplan-status')

      const zeilenInfo = () =>
        page.locator(rowSel).evaluateAll((rows) =>
          rows.map((r) => ({
            zeit: r.querySelector('.pw-zeitplan-zeit')?.value || '',
            tage: [...r.querySelectorAll('input[type="checkbox"][data-tag]')]
              .filter((b) => b.checked)
              .map((b) => Number(b.dataset.tag)),
          })),
        )

      // + Zeit hinzufügen → neue Zeile mit Wochentag Sa (6) und eindeutiger Zeit.
      const vorher = await page.locator(rowSel).count()
      await page.locator('#pw-zeitplan-hinzufuegen').click()
      await expect(page.locator(rowSel)).toHaveCount(vorher + 1)

      const neu = page.locator(rowSel).last()
      await neu.locator('.pw-zeitplan-zeit').fill('07:07')
      await neu.locator('.pw-zeitplan-zeit').press('Tab') // commit → change-Event
      await neu.locator('input[type="checkbox"][data-tag="6"]').check() // change → Autosave
      await expect(status).toHaveText(/Gespeichert/i, { timeout: 15_000 })

      await page.reload()
      await page.waitForSelector(rowSel, { timeout: 15_000 })
      let info = await zeilenInfo()
      const eintrag = info.find((e) => e.zeit === '07:07')
      expect(eintrag, 'hinzugefügte Zeile nicht persistiert').toBeTruthy()
      expect(eintrag.tage, 'falsche Wochentage persistiert').toEqual([6])

      // Diese Zeile wieder löschen → nach dem Neuladen verschwunden.
      const idx = info.findIndex((e) => e.zeit === '07:07')
      await page.locator(rowSel).nth(idx).locator('.pw-zeitplan-delete').click()
      await expect(status).toHaveText(/Gespeichert/i, { timeout: 15_000 })

      await page.reload()
      await page.waitForSelector(rowSel, { timeout: 15_000 })
      await expect
        .poll(async () => (await zeilenInfo()).map((e) => e.zeit), { timeout: 15_000 })
        .not.toContain('07:07')

      // Unvollständige Zeile (nur Zeit, kein Wochentag) wird NICHT persistiert.
      await page.locator('#pw-zeitplan-hinzufuegen').click()
      const unvollstaendig = page.locator(rowSel).last()
      await unvollstaendig.locator('.pw-zeitplan-zeit').fill('08:08')
      await unvollstaendig.locator('.pw-zeitplan-zeit').press('Tab')
      await expect(status).toHaveText(/Gespeichert/i, { timeout: 15_000 })

      await page.reload()
      await page.waitForSelector(rowSel, { timeout: 15_000 })
      await expect
        .poll(async () => (await zeilenInfo()).map((e) => e.zeit), { timeout: 15_000 })
        .not.toContain('08:08')

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Fraktion und Nextcloud-Gruppe', () => {
    test('Optionen sind aktive Fraktionen; Auswahl speichert automatisch und bleibt; Gruppen-Status wechselt', async ({ page }) => {
      test.setTimeout(120_000)
      await login(page, ADMIN)
      await openAdmin(page)

      const aktive = await activeFraktionen(page)
      expect(aktive.length, 'keine aktiven Fraktionen synchronisiert').toBeGreaterThan(0)

      const selectFraktion = page.locator('#pw-fraktion')
      const optionValues = await selectFraktion
        .locator('option')
        .evaluateAll((opts) => opts.map((o) => o.value).filter(Boolean))
      for (const name of aktive) {
        expect(optionValues, `aktive Fraktion «${name}» fehlt in den Optionen`).toContain(name)
      }

      const originalFraktion = await selectFraktion.inputValue()
      const originalGruppe = await page.locator('#pw-nextcloud-gruppe').inputValue()

      // Auswahl ändern → Autosave (#pw-admin-autosave «… gespeichert»).
      const ziel = aktive.find((n) => n !== originalFraktion) || aktive[0]
      await selectFraktion.selectOption(ziel)
      await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 15_000 })

      // Persistenz: nach dem Neuladen ist die gewählte Fraktion gesetzt.
      await page.reload()
      await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
      await expect(page.locator('#pw-fraktion')).toHaveValue(ziel)

      // Gruppen-Status: bestehende vs. neue Gruppe.
      const gruppe = page.locator('#pw-nextcloud-gruppe')
      const state = page.locator('#pw-nextcloud-gruppe-state')
      const bekannte = await page.evaluate(() => window.PARLWIN_ADMIN_BOOTSTRAP?.nextcloudGruppen || [])
      if (bekannte.length > 0) {
        await gruppe.fill(String(bekannte[0]))
        await expect(state).toHaveText(/Bestehende Gruppe/i, { timeout: 10_000 })
      }
      await gruppe.fill(`pw-e2e-neu-${stamp}`)
      await expect(state).toHaveText(/Neue Gruppe wird angelegt/i, { timeout: 10_000 })

      // Aufräumen: ursprüngliche Werte wiederherstellen (Autosave).
      await page.locator('#pw-fraktion').selectOption(originalFraktion).catch(() => {})
      await gruppe.fill(originalGruppe)
      await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 15_000 })

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Kürzel', () => {
    test('Kürzel über die UI hinzufügen/löschen; Datalist schlägt Status-, Fraktions- und Parteinamen vor', async ({ page }) => {
      test.setTimeout(120_000)
      await login(page, ADMIN)
      await openAdmin(page)

      // Datalist-Vorschläge: aktive Fraktionsnamen müssen enthalten sein, dazu
      // mindestens ein Status-Wert und (sofern vorhanden) eine Partei.
      await expect
        .poll(() => page.locator('#pw-status-kuerzel-liste option').count(), { timeout: 15_000 })
        .toBeGreaterThan(0)
      const datalistWerte = await page
        .locator('#pw-status-kuerzel-liste option')
        .evaluateAll((os) => os.map((o) => o.value))
      const aktive = await activeFraktionen(page)
      for (const name of aktive) {
        expect(datalistWerte, `Fraktion «${name}» fehlt in den Datalist-Vorschlägen`).toContain(name)
      }
      const gRes = await apiGet(page, '/geschaefte?show_erledigt=1&limit=2000')
      const statuses = gRes.ok()
        ? [...new Set((await gRes.json()).map((g) => g.status).filter(Boolean))]
        : []
      if (statuses.length) {
        expect(
          datalistWerte.some((v) => statuses.includes(v)),
          'kein Status-Wert in den Datalist-Vorschlägen',
        ).toBeTruthy()
      }
      const mRes = await apiGet(page, '/mitglieder?aktiv=1')
      const parteien = mRes.ok()
        ? [...new Set((await mRes.json()).map((m) => m.partei).filter(Boolean))]
        : []
      if (parteien.length) {
        expect(
          datalistWerte.some((v) => parteien.includes(v)),
          'keine Partei in den Datalist-Vorschlägen',
        ).toBeTruthy()
      }

      const rowSel = '#pw-kuerzel-liste .pw-kuerzel-row'
      const status = page.locator('#pw-kuerzel-status')
      const suche = `E2E-Suchtext ${stamp}`
      const wert = `E2E-Kuerzel ${stamp}`

      const zeilen = () =>
        page.locator(rowSel).evaluateAll((rs) =>
          rs.map((r) => ({
            suche: r.querySelector('.pw-kuerzel-suchtext')?.value || '',
            wert: r.querySelector('.pw-kuerzel-wert')?.value || '',
          })),
        )

      const vorher = await page.locator(rowSel).count()
      await page.locator('#pw-kuerzel-hinzufuegen').click()
      await expect(page.locator(rowSel)).toHaveCount(vorher + 1)

      const neu = page.locator(rowSel).last()
      await neu.locator('.pw-kuerzel-suchtext').fill(suche)
      await neu.locator('.pw-kuerzel-wert').fill(wert)
      await neu.locator('.pw-kuerzel-wert').blur()
      await expect(status).toHaveText(/Gespeichert/i, { timeout: 15_000 })

      await page.reload()
      await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
      await expect
        .poll(async () => (await zeilen()).some((r) => r.suche === suche && r.wert === wert), { timeout: 15_000 })
        .toBe(true)

      // Löschen → nach dem Neuladen verschwunden.
      const idx = (await zeilen()).findIndex((r) => r.suche === suche)
      expect(idx, 'gespeicherte Kürzel-Zeile nicht gefunden').toBeGreaterThanOrEqual(0)
      await page.locator(rowSel).nth(idx).locator('.pw-kuerzel-delete').click()
      await expect(status).toHaveText(/Gespeichert/i, { timeout: 15_000 })

      await page.reload()
      await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
      await page.waitForLoadState('networkidle').catch(() => {})
      await expect
        .poll(async () => (await zeilen()).map((r) => r.suche), { timeout: 15_000 })
        .not.toContain(suche)

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Mitglieder-Zuordnung', () => {
    test('Der Abgleich ohne Fraktion warnt; mit Fraktion erscheinen Zeilen; ein geänderter Benutzername speichert automatisch', async ({ page }) => {
      test.setTimeout(120_000)
      await login(page, ADMIN)
      await openAdmin(page)

      const aktive = await activeFraktionen(page)
      expect(aktive.length, 'keine aktiven Fraktionen synchronisiert').toBeGreaterThan(0)

      const selectFraktion = page.locator('#pw-fraktion')
      const original = await selectFraktion.inputValue()
      const membersStatus = page.locator('#pw-members-status')

      // Abgleich ohne Fraktion → Hinweis.
      await selectFraktion.selectOption('')
      await page.locator('#pw-btn-members-provision').click()
      await expect(membersStatus).toHaveText(/Bitte zuerst eine Fraktion wählen\./i, { timeout: 10_000 })

      // Fraktion wählen → Mitglieder-Zeilen rendern. Bevorzugt die aktuell
      // konfigurierte Fraktion (Fraktionsraum hat sicher Mitglieder).
      const ziel = original && aktive.includes(original) ? original : aktive[0]
      await selectFraktion.selectOption(ziel)
      await expect(page.locator('#pw-members-body tr').first()).toBeVisible({ timeout: 30_000 })

      // Der Benutzername speichert von selbst, an einer nicht gesperrten Zeile (falls vorhanden).
      const editierbar = page.locator('#pw-members-body tr input.pw-member-username:not([disabled])').first()
      if ((await editierbar.count()) > 0) {
        const alt = await editierbar.inputValue()
        await editierbar.fill(alt === '' ? `e2e-user-${stamp}` : alt)
        await expect(membersStatus).toHaveText(/Zuordnung|gespeichert|Speichere/i, { timeout: 10_000 })
        await editierbar.fill(alt) // Ausgangswert wiederherstellen
      }

      // Aufräumen: ursprüngliche Fraktion wiederherstellen.
      await selectFraktion.selectOption(original).catch(() => {})
      await page.waitForTimeout(800)

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Änderungsverlauf', () => {
    test('Karten sichtbar; neueste offen (Einträge, ▲), ältere zugeklappt (▼); Toggle klappt auf und zu', async ({ page }) => {
      await login(page, U1)
      await page.goto(`${APP}/`)
      await page.waitForLoadState('networkidle')

      // Über die App-Navigation zur Ansicht wechseln (kein Admin).
      await page.getByText('Änderungsverlauf', { exact: true }).first().click()

      const view = page.locator('.pw-changelog')
      await expect(view).toBeVisible({ timeout: 30_000 })
      const cards = view.locator('.pw-data-card')
      await expect(cards.first()).toBeVisible({ timeout: 15_000 })
      expect(await cards.count(), 'keine Versions-Karten').toBeGreaterThan(0)

      // Neueste (erste) Karte offen: Einträge sichtbar, Toggle ▲.
      const erste = cards.first()
      await expect(erste.locator('.pw-changelog-eintraege')).toBeVisible()
      await expect(erste.locator('.pw-toggle')).toHaveText(/▲/)

      // Mindestens eine ältere Karte zugeklappt: Toggle ▼, keine Einträge.
      const zu = view
        .locator('.pw-data-card')
        .filter({ has: page.locator('.pw-toggle', { hasText: '▼' }) })
        .first()
      await expect(zu.locator('.pw-toggle')).toHaveText(/▼/)
      await expect(zu.locator('.pw-changelog-eintraege')).toHaveCount(0)

      // Toggle-Verhalten: erste Karte zuklappen und wieder aufklappen.
      await erste.locator('.pw-data-card-header').click()
      await expect(erste.locator('.pw-changelog-eintraege')).toHaveCount(0)
      await expect(erste.locator('.pw-toggle')).toHaveText(/▼/)
      await erste.locator('.pw-data-card-header').click()
      await expect(erste.locator('.pw-changelog-eintraege')).toBeVisible()
      await expect(erste.locator('.pw-toggle')).toHaveText(/▲/)

      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Synchronisation', () => {
    test('Manuell starten: laufender Fortschritt und «läuft bereits»; danach Abbruch und Ruhe', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      // Nachdem der Sync zur Ruhe gebracht ist, neu laden, damit der Button-Zustand garantiert der
      // Realität entspricht (kein veralteter «running»-Snapshot, der den
      // Start-Button deaktiviert hielte).
      await openAdmin(page)

      const btnSync = page.locator('#pw-btn-sync')
      const btnCancel = page.locator('#pw-btn-sync-cancel')
      const status = page.locator('#pw-sync-status')
      const details = page.locator('#pw-sync-details')
      const progressVal = () => page.locator('#pw-sync-progress').evaluate((el) => Number(el.value))

      // Firefox re-aktiviert den Start-Button erst über seinen eigenen
      // Status-Poll — grosszügiges Fenster (60s). Ist er danach noch deaktiviert,
      // läuft real noch ein Sync (was ensureSyncIdle zuvor garantiert verhindert).
      await expect(btnSync).toBeEnabled({ timeout: 60_000 })
      await btnSync.click()
      await expect(btnSync).toBeDisabled()
      await expect(btnCancel).toBeEnabled()
      await expect(status).toHaveText(/gestartet|Synchronisiere/i, { timeout: 20_000 })

      // Live-Fortschritt: Detail-Muster und wachsender Fortschrittsbalken.
      await expect(details).toHaveText(FORTSCHRITT_RE, { timeout: 60_000 })
      await expect.poll(progressVal, { timeout: 60_000, intervals: [500] }).toBeGreaterThan(0)

      // Zweiter Start während des Laufs → Server meldet «bereits laufend».
      const zweit = await apiPost(page, '/sync')
      expect(zweit.ok(), 'zweiter Sync-Aufruf sollte 2xx liefern').toBeTruthy()
      expect((await zweit.json())?.bereits_laufend, 'API meldet nicht «bereits laufend»').toBe(true)

      // Und die UI zeigt «läuft bereits». Der Button ist während des Laufs
      // bewusst deaktiviert; ein MutationObserver hält die kurz eingeblendete
      // Server-Meldung fest, danach reaktivieren wir den Button minimal, damit
      // der echte Klick-Handler → echter POST → echte Serverantwort → echte
      // UI-Meldung durchläuft.
      await page.evaluate(() => {
        window.__pwLaeuftBereits = false
        const el = document.getElementById('pw-sync-status')
        window.__pwObs = new MutationObserver(() => {
          if (/läuft bereits/i.test(el.textContent || '')) window.__pwLaeuftBereits = true
        })
        window.__pwObs.observe(el, { childList: true, characterData: true, subtree: true })
      })
      // disabled aufheben UND synchron im selben evaluate klicken, damit kein
      // Status-Poll den Button dazwischen wieder deaktiviert (Firefox-robust);
      // ein separater Playwright-Klick könnte sonst auf einen erneut
      // deaktivierten Button treffen und hängen.
      await btnSync.evaluate((el) => {
        el.disabled = false
        el.click()
      })
      await expect
        .poll(() => page.evaluate(() => window.__pwLaeuftBereits === true), { timeout: 20_000 })
        .toBe(true)
      await page.evaluate(() => window.__pwObs?.disconnect())

      // NICHT auf den vollständigen Abschluss warten: ein realer Sync gegen die
      // Parlaments-Webseite ist langsam und würde — bliebe er in Arbeit — den
      // Realtime-Broker fluten und die spätere multi-user-Echtzeit brechen. Der
      // Live-Fortschritt ist belegt; jetzt abbrechen und den Stopp AUTORITATIV
      // über die Status-API bestätigen (die UI-Textphase «abgebrochen» ist in
      // Firefox unzuverlässig, der Status ist es nicht).
      await btnCancel.click()
      // Entweder die Zwischenmeldung «Abbruch …» ODER bereits «abgebrochen» —
      // bei schnellem Abbruch wird die Zwischenphase übersprungen.
      await expect(status).toHaveText(/Abbruch|abgebrochen/i, { timeout: 20_000 })
      await expect
        .poll(async () => {
          const s = await syncStatus(page)
          return !!s && (s.running === false || s.phase === 'abgebrochen')
        }, { timeout: 180_000, intervals: [1000] })
        .toBe(true)

      await ensureSyncIdle(page)
      const final = await syncStatus(page)
      expect(final?.running, 'Sync läuft am Testende noch').toBe(false)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    test('Laufende Synchronisation abbrechen: die Oberfläche meldet den Abbruch, die Schnittstelle bestätigt den Stopp', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      await openAdmin(page) // frischer Button-Zustand, nachdem der Sync ruht

      const btnSync = page.locator('#pw-btn-sync')
      const btnCancel = page.locator('#pw-btn-sync-cancel')
      const status = page.locator('#pw-sync-status')

      // Grosszügiges Fenster: Firefox re-aktiviert den Button erst über seinen
      // eigenen Status-Poll.
      await expect(btnSync).toBeEnabled({ timeout: 60_000 })
      await btnSync.click()
      await expect(btnCancel).toBeEnabled({ timeout: 30_000 })
      await expect(status).toHaveText(/gestartet|Synchronisiere/i, { timeout: 30_000 })

      await btnCancel.click()
      // UI zeigt den Abbruch sofort (aus dem Klick-Handler «Abbruch …»).
      // Entweder die Zwischenmeldung «Abbruch …» ODER bereits «abgebrochen» —
      // bei schnellem Abbruch wird die Zwischenphase übersprungen.
      await expect(status).toHaveText(/Abbruch|abgebrochen/i, { timeout: 20_000 })
      // Autoritativ: die Status-API meldet den Stopp (running=false oder Phase
      // «abgebrochen») — unabhängig von der UI-Textphase «abgebrochen», die in
      // Firefox unzuverlässig erscheint. Ein transienter null-Status zählt NICHT.
      //
      // Der Verlauf wird mitgeschrieben: Bleibt der Abbruch aus, nennt die
      // Meldung die Folge der Phasen, an der er hängt.
      const verlauf = []
      // F59: Bis der Lauf wirklich steht, bleibt die Meldung beim Abbruch; sie
      // fällt nicht auf «Synchronisiere...» zurück, solange der Server das
      // Signal noch nicht aufgenommen hat. Mitgeschrieben wird darum auch, was
      // die Oberfläche bei jeder Abfrage zeigt.
      const anzeigen = []
      await expect
        .poll(async () => {
          const s = await syncStatus(page)
          const marke = s
            ? `${s.phase}/${s.current?.scope || '-'} läuft=${s.running} ${s.current?.processed ?? '-'}/${s.current?.total ?? '-'}`
            : 'kein Status'
          if (verlauf[verlauf.length - 1] !== marke) verlauf.push(marke)
          const gezeigt = (await status.innerText()).trim()
          if (anzeigen[anzeigen.length - 1] !== gezeigt) anzeigen.push(gezeigt)
          return !!s && (s.running === false || s.phase === 'abgebrochen')
        }, { timeout: 180_000, intervals: [1000] })
        .toBe(true)
        .catch((e) => {
          throw new Error(`${e.message}\nVerlauf des Abbruchs: ${verlauf.join(' → ')}`)
        })
      const abbruchAb = anzeigen.findIndex((t) => /Abbruch|abgebrochen/i.test(t))
      expect(
        anzeigen.slice(Math.max(abbruchAb, 0)).filter((t) => /Synchronisiere/i.test(t)),
        `nach «Synchronisierung abbrechen» stand wieder «Synchronisiere...» da: ${anzeigen.join(' → ')}; Stand des Servers: ${verlauf.join(' → ')}`,
      ).toEqual([])

      await ensureSyncIdle(page)
      const final = await syncStatus(page)
      expect(final?.running, 'Sync läuft am Testende noch').toBe(false)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    // F59: Eine Abfrage des Status, die vor dem Start hinausging, kann nach dem
    // Klick auf «Synchronisierung abbrechen» ankommen. Sie bringt den Stand des
    // vorigen Laufs und darf weder den Abbruch-Wunsch löschen noch die Meldung
    // ersetzen. Der Test hält die echte Antwort der ersten Abfrage zurück, die
    // der Server vor dem Start beantwortet, dazu die Antwort auf den Start und
    // die Anfrage zum Abbruch.
    test('Abbrechen bleibt stehen, wenn die erste Abfrage den vorigen Lauf zurückbringt', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      await openAdmin(page)

      const start = freigabe()
      const abbruch = freigabe()
      const alterGeholt = freigabe()
      const alterStand = freigabe()
      const zweiLaeufe = freigabe()
      let erste = true
      let alteDaten = null
      let alterGeliefert = false
      let laufendGeliefert = 0
      // Der Start geht erst an den Server, wenn die erste Abfrage des Status
      // ihre Antwort hat; diese Antwort trägt darum den Stand vor dem Start.
      await page.route('**/apps/parlwin/sync', async (route) => {
        if (route.request().method() !== 'POST') { return route.continue() }
        await alterGeholt.warten
        const antwort = await route.fetch()
        await start.warten
        await route.fulfill({ response: antwort })
      })
      await page.route('**/apps/parlwin/sync/cancel', async (route) => {
        await abbruch.warten
        await route.continue()
      })
      await page.route('**/apps/parlwin/sync/status', async (route) => {
        const istErste = erste
        erste = false
        const antwort = await route.fetch()
        const daten = await antwort.json().catch(() => null)
        if (istErste) {
          alteDaten = daten
          alterGeholt.frei()
          await alterStand.warten
          await route.fulfill({ response: antwort })
          alterGeliefert = true
          return
        }
        await route.fulfill({ response: antwort })
        if (alterGeliefert && daten?.running === true && ++laufendGeliefert >= 2) { zweiLaeufe.frei() }
      })

      const status = page.locator('#pw-sync-status')
      await verlaufMitschreiben(page)

      await page.locator('#pw-btn-sync').click()
      await page.locator('#pw-btn-sync-cancel').click()
      await expect(status).toHaveText(/Abbruch/, { timeout: 20_000 })
      await alterGeholt.warten
      expect(alteDaten, 'die erste Abfrage des Status hat keine auswertbare Antwort').not.toBeNull()
      expect(alteDaten.running, 'die zurückgehaltene Antwort trägt schon einen laufenden Abgleich').toBe(false)
      // Der vorige Lauf kommt nach dem Abbruch an, danach zweimal der laufende.
      alterStand.frei()
      await zweiLaeufe.warten
      abbruch.frei()
      start.frei()

      await expect
        .poll(async () => (await syncStatus(page))?.running === false, { timeout: 180_000, intervals: [1000] })
        .toBe(true)
      // Gelesen wird bis zur Meldung «abgebrochen» nach der Antwort auf den
      // Start: Danach kann der Zeitplan des Stacks (PARLWIN_CRON_INTERVAL) einen
      // neuen Lauf starten, der zu Recht «Synchronisiere...» zeigt. Ein
      // «abgebrochen» davor ist der Stand des vorigen Laufs.
      await expect
        .poll(() => page.evaluate(() => {
          const verlauf = window.__pwSyncVerlauf
          const gestartet = verlauf.findIndex((e) => /Synchronisation gestartet/.test(e.text))
          return gestartet >= 0 && verlauf.slice(gestartet + 1).some((e) => /abgebrochen/i.test(e.text))
        }), { timeout: 30_000 })
        .toBe(true)
      const texte = await page.evaluate(() => window.__pwSyncVerlauf.map((e) => e.text))
      const abbruchAb = texte.findIndex((t) => /Abbruch/.test(t))
      expect(abbruchAb, `kein Abbruch angezeigt: ${texte.join(' → ')}`).toBeGreaterThanOrEqual(0)
      const gestartet = texte.findIndex((t) => /Synchronisation gestartet/.test(t))
      const ende = texte.findIndex((t, i) => i > gestartet && gestartet >= 0 && /abgebrochen/i.test(t))
      expect(texte.slice(abbruchAb, ende < 0 ? undefined : ende).filter((t) => /Synchronisiere/.test(t)),
        `nach «Synchronisierung abbrechen» stand wieder «Synchronisiere...» da: ${texte.join(' → ')}`).toEqual([])

      await page.unrouteAll({ behavior: 'ignoreErrors' })
      await ensureSyncIdle(page)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    // F59: Nach dem Klick auf «Synchronisierung abbrechen» bleibt der Knopf
    // gesperrt, bis der Lauf steht; ein zweiter Klick schickte den Abbruch ein
    // zweites Mal an den Server. Der Test hält die Anfrage zum Abbruch zurück,
    // bis zwei echte Stände des laufenden Abgleichs angekommen sind.
    test('Der Knopf zum Abbrechen bleibt nach dem Klick gesperrt, bis der Lauf steht', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      await openAdmin(page)

      const btnSync = page.locator('#pw-btn-sync')
      const btnCancel = page.locator('#pw-btn-sync-cancel')
      const status = page.locator('#pw-sync-status')

      const abbruch = freigabe()
      const zweiLaeufe = freigabe()
      let abbruchAnfragen = 0
      let geklickt = false
      let laufendNachKlick = 0
      await page.route('**/apps/parlwin/sync/cancel', async (route) => {
        abbruchAnfragen++
        await abbruch.warten
        await route.continue()
      })
      await page.route('**/apps/parlwin/sync/status', async (route) => {
        const antwort = await route.fetch()
        const daten = await antwort.json().catch(() => null)
        await route.fulfill({ response: antwort })
        if (geklickt && daten?.running === true && ++laufendNachKlick >= 2) { zweiLaeufe.frei() }
      })
      await verlaufMitschreiben(page)

      await expect(btnSync).toBeEnabled({ timeout: 60_000 })
      await btnSync.click()
      await expect(btnCancel).toBeEnabled({ timeout: 30_000 })
      await expect(status).toHaveText(/Synchronisiere/, { timeout: 60_000 })

      geklickt = true
      await btnCancel.click()
      await expect(status).toHaveText(/Abbruch/, { timeout: 20_000 })
      await zweiLaeufe.warten
      await expect(btnCancel, '«Synchronisierung abbrechen» ist wieder frei, solange der Lauf noch steht').toBeDisabled()
      abbruch.frei()

      await expect
        .poll(async () => (await syncStatus(page))?.running === false, { timeout: 180_000, intervals: [1000] })
        .toBe(true)
      // Gelesen wird bis zur Meldung «abgebrochen»: Danach kann der Zeitplan
      // des Stacks einen neuen Lauf starten, der «Synchronisierung abbrechen»
      // zu Recht freigibt.
      await expect
        .poll(() => page.evaluate(() => {
          const verlauf = window.__pwSyncVerlauf
          const ab = verlauf.findIndex((e) => /Abbruch/.test(e.text))
          return ab >= 0 && verlauf.slice(ab + 1).some((e) => /abgebrochen/i.test(e.text))
        }), { timeout: 30_000 })
        .toBe(true)
      const verlauf = await page.evaluate(() => window.__pwSyncVerlauf.slice())
      const ab = verlauf.findIndex((e) => /Abbruch/.test(e.text))
      const ende = verlauf.findIndex((e, i) => i > ab && /abgebrochen/i.test(e.text))
      const bild = verlauf.map((e) => `${e.text} (${e.gesperrt ? 'gesperrt' : 'frei'})`).join(' → ')
      expect(verlauf.slice(ab, ende + 1).filter((e) => !e.gesperrt),
        `«Synchronisierung abbrechen» war vor dem Ende des Laufs wieder frei: ${bild}`).toEqual([])
      expect(abbruchAnfragen, 'die Seite hat den Abbruch mehr als einmal geschickt').toBe(1)

      await page.unrouteAll({ behavior: 'ignoreErrors' })
      await ensureSyncIdle(page)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    // F59: Kommt die Anfrage zum Abbruch nicht beim Server an, gibt die nächste
    // Meldung des laufenden Abgleichs «Synchronisierung abbrechen» wieder frei,
    // und ein zweiter Klick bricht den Lauf ab. Der Test lässt die Verbindung
    // der ersten Anfrage zum Abbruch scheitern; die Antworten des Servers
    // bleiben echt.
    test('Kommt der Abbruch nicht an, lässt sich der Lauf erneut abbrechen', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      await openAdmin(page)

      const btnSync = page.locator('#pw-btn-sync')
      const btnCancel = page.locator('#pw-btn-sync-cancel')
      const status = page.locator('#pw-sync-status')

      let abbruchAnfragen = 0
      await page.route('**/apps/parlwin/sync/cancel', async (route) => {
        abbruchAnfragen++
        if (abbruchAnfragen === 1) { return route.abort('connectionfailed') }
        return route.continue()
      })
      await verlaufMitschreiben(page)

      await expect(btnSync).toBeEnabled({ timeout: 60_000 })
      await btnSync.click()
      await expect(btnCancel).toBeEnabled({ timeout: 30_000 })
      await expect(status).toHaveText(/Synchronisiere/, { timeout: 60_000 })

      await btnCancel.click()
      // Die Fehlermeldung kann die nächste Meldung des Laufs gleich ersetzen,
      // darum wird sie im Verlauf gesucht.
      await expect
        .poll(() => page.evaluate(() => window.__pwSyncVerlauf.some((e) => /^Fehler/.test(e.text))), { timeout: 20_000 })
        .toBe(true)
      const zwischenstand = await syncStatus(page)
      expect(['queued', 'running'], `der Abbruch kam doch beim Server an: ${JSON.stringify(zwischenstand)}`)
        .toContain(zwischenstand?.phase)
      await expect(btnCancel, 'nach dem gescheiterten Abbruch bleibt «Synchronisierung abbrechen» gesperrt').toBeEnabled({ timeout: 30_000 })
      await expect(status, 'nach dem gescheiterten Abbruch meldet die Seite weiter den Abbruch').toHaveText(/Synchronisiere/)

      await btnCancel.click()
      await expect(status).toHaveText(/Abbruch/, { timeout: 20_000 })
      await expect
        .poll(async () => (await syncStatus(page))?.running === false, { timeout: 180_000, intervals: [1000] })
        .toBe(true)
      expect(abbruchAnfragen, 'die Seite hat den zweiten Abbruch nicht geschickt').toBe(2)

      await page.unrouteAll({ behavior: 'ignoreErrors' })
      await ensureSyncIdle(page)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })

    // F146: Antwortet die Webseite der Stadt nicht, läuft die Synchronisation
    // zu Ende, und die Verwaltung nennt den Bereich der Stadtratsbeschlüsse mit
    // der Meldung des Servers unter der Statuszeile. Die Webseite der Stadt ist
    // im Stack der Simulator `parlwin-quelle`; sein Steuerweg schaltet die
    // Liste der Stadtratsbeschlüsse auf 500 und wieder zurück.
    test('Ist die Webseite der Stadt nicht erreichbar, steht der Bereich mit seiner Meldung unter der Statuszeile', async ({ page }) => {
      test.setTimeout(600_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)

      const gesetzt = await page.request.put(STADT_FEHLER, { data: '500' })
      expect([201, 204], 'der Simulator nimmt den Fehler nicht an').toContain(gesetzt.status())
      try {
        const liste = await page.request.get(`${QUELLE_URL}/stadtratsbeschluesse`)
        expect(liste.status(), 'die Liste der Stadtratsbeschlüsse antwortet trotz gesetztem Fehler').toBe(500)

        await openAdmin(page)
        const btnSync = page.locator('#pw-btn-sync')
        const status = page.locator('#pw-sync-status')
        await expect(btnSync).toBeEnabled({ timeout: 60_000 })
        await btnSync.click()
        await expect(status).toHaveText(/gestartet|Synchronisiere/i, { timeout: 30_000 })

        // Der Lauf endet: Der Server meldet ihn abgeschlossen und nicht gescheitert.
        await expect
          .poll(async () => (await syncStatus(page))?.phase, { timeout: 540_000, intervals: [2000] })
          .toBe('abgeschlossen')
        const stand = await syncStatus(page)
        // Die Meldung nennt Adresse und HTTP-Status, nie den Rumpf der Fehlerseite.
        const erwartet = `Die Liste der Stadtratsbeschlüsse unter ${QUELLE_URL}/stadtratsbeschluesse ist nicht erreichbar (HTTP 500).`
        const meldung = String(stand?.sections?.stadtratsbeschluesse?.fehler || '')
        expect(meldung, 'der Server schreibt keinen oder einen anderen Fehler in den Bereich der Stadtratsbeschlüsse').toBe(erwartet)
        expect(stand?.error ?? null, 'der Lauf meldet einen Fehler des ganzen Laufs').toBeNull()
        expect(stand?.statistik?.stadtratsbeschluesse?.ausgefallen, 'die Statistik nennt den Bereich nicht als ausgefallen').toBe(true)
        for (const bereich of ['geschaefte', 'sitzungen']) {
          expect(typeof stand?.statistik?.[bereich]?.aktualisiert, `der Bereich ${bereich} ist nicht durchgelaufen`).toBe('number')
        }
        expect(stand?.statistik?.mitglieder?.mitglieder, 'die Mitglieder sind nicht durchgelaufen').toBeTruthy()

        await expect(status).toHaveText(/Synchronisation abgeschlossen/, { timeout: 30_000 })
        const zeilen = page.locator('#pw-sync-fehler li')
        await expect(page.locator('#pw-sync-fehler')).toBeVisible()
        await expect(zeilen).toHaveText([`Fehler im Bereich Stadtratsbeschlüsse: ${erwartet}`])
      } finally {
        await page.request.delete(STADT_FEHLER).catch(() => {})
      }
      const wieder = await page.request.get(`${QUELLE_URL}/stadtratsbeschluesse`)
      expect(wieder.status(), 'der Fehler der Stadtquelle ist nach dem Test noch gesetzt').toBe(200)

      await ensureSyncIdle(page)
      expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
    })
  })

  test.describe('Echtzeit', () => {
    test('Eine in Kontext B gestartete Synchronisation erscheint in Kontext A, ohne dass die Seite neu geladen wird', async ({ browser }) => {
      test.setTimeout(300_000)
      const ctxA = await browser.newContext()
      const ctxB = await browser.newContext()
      const pageA = await ctxA.newPage()
      const pageB = await ctxB.newPage()
      const fehlerA = []
      pageA.on('pageerror', (e) => fehlerA.push(e.message))

      try {
        await login(pageA, ADMIN)
        await login(pageB, ADMIN)
        await ensureSyncIdle(pageB)

        await openAdmin(pageA) // A hat WebSocket + Polling laufend

        const statusA = pageA.locator('#pw-sync-status')
        const progressA = () => pageA.locator('#pw-sync-progress').evaluate((el) => Number(el.value))

        // B startet den Sync über die API — bei A wird NIE reloaded.
        const res = await apiPost(pageB, '/sync')
        expect(res.ok(), 'Sync-Start aus Kontext B fehlgeschlagen').toBeTruthy()

        // A aktualisiert sich ohne Neuladen: Status läuft, Fortschritt bewegt sich.
        await expect(statusA).toHaveText(/Synchronisiere|gestartet|läuft/i, { timeout: 30_000 })
        await expect.poll(progressA, { timeout: 90_000, intervals: [500] }).toBeGreaterThan(0)
        await expect(pageA.locator('#pw-sync-details')).toHaveText(FORTSCHRITT_RE, { timeout: 30_000 })

        expect(fehlerA, `JS-Fehler Kontext A: ${fehlerA.join(' | ')}`).toEqual([])
      } finally {
        // Zuerst den gestarteten Sync sicher abbrechen und die Ruhe bestätigen, DANN
        // die beiden Kontexte (inkl. ihrer offenen WebSocket-Verbindungen) und
        // Seiten schliessen. Das innere finally garantiert das Schliessen auch
        // dann, wenn die Bestätigung der Ruhe fehlschlägt.
        try {
          await ensureSyncIdle(pageB)
        } finally {
          await pageA.close().catch(() => {})
          await pageB.close().catch(() => {})
          await ctxA.close()
          await ctxB.close()
        }
      }
    })
  })
})
