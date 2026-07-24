import { test, expect } from '@playwright/test'

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
 *  - Fraktion/Gruppe: Optionen, Autosave, Persistenz, Gruppen-Status,
 *  - Kürzel über die UI (hinzufügen/löschen) und Datalist-Vorschläge,
 *  - Mitglieder-Zuordnung (Hinweise, Zeilen, Username-Autosave),
 *  - Änderungsverlauf (Karten, neueste offen, ältere zugeklappt, Toggle),
 *  - Echtzeit: ein aus Kontext B gestarteter Sync erscheint in Kontext A ohne Reload.
 *
 * Die Sync-Tests verändern globalen Zustand; jeder stellt zu Beginn und am Ende
 * sicher, dass KEIN Lauf hängen bleibt (ensureSyncIdle + afterAll-Sicherung).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP = `${BASE_URL}/index.php/apps/parlwin`
const ADMIN_URL = `${BASE_URL}/index.php/settings/admin/parlwin`

const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

const stamp = Date.now()

// Live-Fortschritt in #pw-sync-details: «… - 5/10 | Gesamt 20/40 (50%) | …».
const FORTSCHRITT_RE = /\d+\/\d+ \| Gesamt \d+\/\d+ \(\d+%\)/

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an (wie die Schwester-Specs). */
async function login(page, user) {
  await page.goto(`${BASE_URL}/index.php/login`)
  await page.waitForSelector('input[name="user"]', { state: 'visible', timeout: 30_000 })
  await page.fill('input[name="user"]', user.name)
  await page.fill('input[name="password"]', user.pass)
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

      // Diese Zeile wieder löschen → nach Reload verschwunden.
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

      // Persistenz: nach Reload ist die gewählte Fraktion gesetzt.
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

      // Löschen → nach Reload verschwunden.
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
    test('Provision ohne Fraktion warnt; mit Fraktion rendern Zeilen; Username-Änderung speichert automatisch', async ({ page }) => {
      test.setTimeout(120_000)
      await login(page, ADMIN)
      await openAdmin(page)

      const aktive = await activeFraktionen(page)
      expect(aktive.length, 'keine aktiven Fraktionen synchronisiert').toBeGreaterThan(0)

      const selectFraktion = page.locator('#pw-fraktion')
      const original = await selectFraktion.inputValue()
      const membersStatus = page.locator('#pw-members-status')

      // Provision ohne Fraktion → Hinweis.
      await selectFraktion.selectOption('')
      await page.locator('#pw-btn-members-provision').click()
      await expect(membersStatus).toHaveText(/Bitte zuerst eine Fraktion wählen\./i, { timeout: 10_000 })

      // Fraktion wählen → Mitglieder-Zeilen rendern. Bevorzugt die aktuell
      // konfigurierte Fraktion (Fraktionsraum hat sicher Mitglieder).
      const ziel = original && aktive.includes(original) ? original : aktive[0]
      await selectFraktion.selectOption(ziel)
      await expect(page.locator('#pw-members-body tr').first()).toBeVisible({ timeout: 30_000 })

      // Username-Autosave an einer nicht gesperrten Zeile (falls vorhanden).
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
    test('Manuell starten: Live-Fortschritt und «läuft bereits»; danach Abbruch und Idle', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      // Nach dem Idle-Stellen neu laden, damit der Button-Zustand garantiert der
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

    test('Laufenden Sync abbrechen: UI meldet Abbruch, Status-API bestätigt den Stopp', async ({ page }) => {
      test.setTimeout(360_000)
      await login(page, ADMIN)
      await openAdmin(page)
      await ensureSyncIdle(page)
      await openAdmin(page) // frischer Button-Zustand nach dem Idle-Stellen

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
  })

  test.describe('Echtzeit', () => {
    test('Ein aus Kontext B gestarteter Sync erscheint in Kontext A ohne Reload', async ({ browser }) => {
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

        // A aktualisiert sich ohne Reload: Status läuft, Fortschritt bewegt sich.
        await expect(statusA).toHaveText(/Synchronisiere|gestartet|läuft/i, { timeout: 30_000 })
        await expect.poll(progressA, { timeout: 90_000, intervals: [500] }).toBeGreaterThan(0)
        await expect(pageA.locator('#pw-sync-details')).toHaveText(FORTSCHRITT_RE, { timeout: 30_000 })

        expect(fehlerA, `JS-Fehler Kontext A: ${fehlerA.join(' | ')}`).toEqual([])
      } finally {
        // Zuerst den gestarteten Sync sicher abbrechen und Idle bestätigen, DANN
        // die beiden Kontexte (inkl. ihrer offenen WebSocket-Verbindungen) und
        // Seiten schliessen. Das innere finally garantiert das Schliessen auch
        // dann, wenn die Idle-Bestätigung fehlschlägt.
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
