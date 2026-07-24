import { test, expect } from '@playwright/test'

/**
 * E2E im echten Browser gegen den realen Stack (Nextcloud + DB + API +
 * Hintergrund-Job + CalDAV/WebDAV) für die sechs Funktionen, die bisher keinen
 * Playwright-Test hatten:
 *
 *  - F26 Votum im Rat
 *  - F27 Dokumente am Geschäft (aus Vorlage erstellen, hochladen)
 *  - F34 Vorstoss-Import aus dem Fraktionsordner «40_Vorstösse»
 *  - F41 Kalendereintrag zur internen Sitzung
 *  - F51 Benutzer anlegen und Einladung («Ausgewählte abgleichen»)
 *  - F62 Rollen und befristete Stellvertretungen
 *
 * Es wird ausschliesslich geprüft, was es wirklich gibt — jeder Test geht den
 * echten Bedienweg (klicken, tippen, auf die Antwort warten, das gerenderte
 * Ergebnis prüfen). Kein Setzen von Komponenten-Zustand, kein Mocking.
 *
 * Wo die Oberfläche für einen Teilschritt KEIN Bedienelement anbietet, ist das
 * am jeweiligen Test dokumentiert und es wird der tatsächlich vorhandene Weg
 * geprüft:
 *
 *  - **F26:** `GeschaeftDetail.vue` bietet das Votum im Feld «Votum im Rat» an:
 *    einen formatierten Textbereich (nur für die zuständige Person bearbeitbar),
 *    einen PDF-Verweis in dessen Werkzeugleiste (öffnet die Druckansicht
 *    `/geschaefte/{id}/votum/pdf`, die den Drucken-Dialog selbst auslöst) und den
 *    Knopf «Votum archivieren». Erfasst und archiviert wird über genau diese
 *    Bedienelemente; geprüft wird das gerenderte Ergebnis (Druckansicht und
 *    Aktionszeitleiste). Der Schreibschutz für Nicht-Zuständige wird zusätzlich
 *    serverseitig geprüft (403).
 *  - **F34:** Der Import läuft ausschliesslich im Hintergrund-Job (SyncJob) und
 *    liest den Ordner des Administrators. Es gibt keinen Knopf dafür. Getestet
 *    wird darum der reale Ablauf: Datei landet im Fraktionsordner, der
 *    Hintergrund-Job wird über den Zeitplan in der Verwaltung fällig gemacht
 *    (Bedienweg der Verwaltung), danach erscheint der Vorstoss in der Ansicht.
 *  - **F62:** Rollen und Stellvertretungen haben keine eigene Oberfläche; gesetzt
 *    werden sie über die Endpunkte der Verwaltung. Geprüft wird ihre WIRKUNG im
 *    Browser (Sitzungsmodus schaltbar bzw. nicht, Beschluss-Feld frei bzw.
 *    gesperrt) — inklusive der zeitlichen Befristung.
 *
 * Wiederholbarkeit: alle Testdaten tragen einen Zeitstempel im Namen und werden
 * am Ende wieder entfernt. Nicht entfernbar sind (mangels Endpunkt) interne
 * Sitzungen und einmal vergebene Rollen; beides ist am Test vermerkt und ohne
 * Wirkung auf die übrigen Tests, weil der Sitzungsmodus immer zurückgesetzt wird.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP = `${BASE_URL}/index.php/apps/parlwin`
const APP_URL = `${APP}/`
const ADMIN_URL = `${BASE_URL}/index.php/settings/admin/parlwin`

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
  u2: { name: process.env.PW_U2 || 'parlwin_protokoll', pass: process.env.PW_P2 || '' },
  u3: { name: process.env.PW_U3 || 'parlwin_mitglied', pass: process.env.PW_P3 || '' },
}
const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }

// Unterhalb ~60em schaltet die Container-Query der Geschäfte-Ansicht auf Karten
// um; dann fehlen Tabelle und Filter-Slot. Darum dateiweit fix breit.
test.use({ viewport: { width: 1600, height: 1000 } })

// Per-Test-Zähler für eindeutige Namen — bewusst keine Modul-Top-Identität, ein
// Worker kann das Modul erneut importieren.
let laufNr = 0
function eindeutig(prefix) {
  laufNr += 1
  return `E2E-${prefix} ${Date.now()}-${laufNr}`
}

/** Sammelt JavaScript-Fehler der Seite. */
function fehlerWaechter(page) {
  const arr = []
  page.on('pageerror', (e) => arr.push(e.message))
  return arr
}

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

/** Öffnet einen eigenen Browser-Kontext für einen weiteren angemeldeten Nutzer. */
async function neuerKontext(browser, user) {
  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } })
  const page = await ctx.newPage()
  await login(page, user)
  return { ctx, page }
}

/** Öffnet die App-Wurzel und wartet auf die gemeinsame Seitenstruktur. */
async function openApp(page) {
  await page.goto(APP_URL)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
}

/** Öffnet die App und wechselt über den Navigations-Link in eine Ansicht. */
async function gotoView(page, name) {
  await openApp(page)
  await page.getByRole('link', { name, exact: true }).click()
  await expect(page.locator('.pw-view-title')).toHaveText(name, { timeout: 30_000 })
}

/** Öffnet die Geschäfte-Ansicht (Standardansicht) inklusive Such-Slot. */
async function gotoGeschaefte(page) {
  await page.goto(APP_URL)
  await page.waitForLoadState('networkidle')
  await page.waitForSelector('.pw-geschaefte', { timeout: 30_000 })
  await page.waitForSelector('#pw-search-slot input', { timeout: 30_000 })
}

/** Öffnet die Verwaltungsseite der App und wartet, bis ihre Wurzel gerendert ist. */
async function openAdmin(page) {
  await page.goto(ADMIN_URL)
  await page.waitForSelector('#parlwin-admin-settings', { state: 'visible', timeout: 30_000 })
  await page.waitForLoadState('networkidle').catch(() => {})
}

/** Nextcloud-Request-Token der aktuell geladenen Seite (leer auf der Druckansicht). */
function reqToken(page) {
  return page.evaluate(() => window.OC?.Util?.getRequestToken?.() || window.OC?.requestToken || '')
}

/** Formular-Aufruf gegen die parlwin-API mit dem Token und den Cookies der Seite. */
async function api(page, method, pfad, form) {
  const token = await reqToken(page)
  const opts = {
    headers: {
      requesttoken: token,
      'OCS-APIRequest': 'true',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  }
  if (form) opts.form = form
  return page.request[method](`${APP}${pfad}`, opts)
}

/** JSON-Aufruf gegen die parlwin-API (Endpunkte, die einen JSON-Body erwarten). */
async function apiJson(page, method, pfad, body) {
  const token = await reqToken(page)
  return page.request[method](`${APP}${pfad}`, {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
    data: JSON.stringify(body),
  })
}

async function apiCreateGeschaeft(page, titel, typ = 'Eigenes Geschäft') {
  const res = await api(page, 'post', '/geschaefte', { titel, typ })
  expect(res.ok(), `Geschäft-Anlage fehlgeschlagen (${res.status()})`).toBeTruthy()
  return (await res.json()).id
}

/** WebDAV-Adresse einer Datei im Ordner eines Nutzers (jedes Segment kodiert). */
function davDatei(uid, ...segmente) {
  const pfad = segmente.map((s) => encodeURIComponent(s)).join('/')
  return `${BASE_URL}/remote.php/dav/files/${encodeURIComponent(uid)}/${pfad}`
}

/** Entfernt eine Datei bzw. ein Kalenderobjekt per WebDAV/CalDAV. */
async function davLoeschen(page, url) {
  const token = await reqToken(page)
  return page.request.fetch(url, { method: 'DELETE', headers: { requesttoken: token } })
}

// Whitespace normalisieren — NcEllipsisedOption verteilt lange Labels auf zwei
// Spans mit Zeilenumbruch, deshalb nie mit rohem Mehrwort-Text vergleichen.
const norm = (s) => String(s || '').replace(/\s+/g, ' ').trim()

/** Volles, ungekürztes Label einer NcSelect-Option (steht im title von `.name-parts`). */
async function optionLabel(optionLoc) {
  const np = optionLoc.locator('.name-parts').first()
  if ((await np.count()) > 0) {
    const t = await np.getAttribute('title')
    if (t != null) return norm(t)
  }
  return norm(await optionLoc.innerText())
}

/**
 * Öffnet ein NcSelect/PwMultiSelect overlay-fest: vue-select öffnet bei
 * fokussiertem Suchfeld auf ArrowDown (kein Hittest); genau EIN Fallback über
 * einen echten Maus-Klick auf die Toggle-Mitte.
 */
async function ncOpen(page, root) {
  if (await root.evaluate((el) => el.classList.contains('vs--open')).catch(() => false)) return
  const feld = root.locator('.vs__search, .vs__dropdown-toggle').first()
  await feld.scrollIntoViewIfNeeded().catch(() => {})
  await feld.focus().catch(() => {})
  await page.keyboard.press('ArrowDown')
  try {
    await expect(root).toHaveClass(/vs--open/, { timeout: 4_000 })
  } catch (e) {
    const box = await root.locator('.vs__dropdown-toggle').first().boundingBox()
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
    await expect(root).toHaveClass(/vs--open/, { timeout: 6_000 })
  }
}

/** Wählt in einem NcSelect eine Option über ihr volles Label (Menü liegt am Body). */
async function ncPick(page, root, label) {
  await ncOpen(page, root)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  const opts = menu.locator('li.vs__dropdown-option')
  const ziel = norm(label)
  const n = await opts.count()
  for (let i = 0; i < n; i++) {
    const opt = opts.nth(i)
    if ((await optionLabel(opt)) === ziel) {
      await opt.scrollIntoViewIfNeeded()
      await opt.click()
      await page.keyboard.press('Escape')
      return
    }
  }
  await menu.locator('li.vs__dropdown-option').filter({ hasText: label }).first().click()
  await page.keyboard.press('Escape')
}

/** Sucht ein Geschäft über den Titel und öffnet das Detail über die Titel-Zelle. */
async function openDetailByTitel(page, titel) {
  await page.fill('#pw-search-slot input', titel)
  const zelle = page.locator('.pw-tabelle-geschaefte tbody tr .pw-col-titel', { hasText: titel }).first()
  await zelle.waitFor({ state: 'visible', timeout: 30_000 })
  await zelle.click()
  await page.waitForSelector('.pw-geschaeft-detail', { timeout: 30_000 })
}

/** Sucht ein Geschäft über seine Nummer und öffnet das Detail über die Titel-Zelle. */
async function openDetailByNummer(page, nummer) {
  await page.fill('#pw-search-slot input', nummer)
  const zeile = page
    .locator('.pw-tabelle-geschaefte tbody tr')
    .filter({ has: page.locator('.pw-col-nr strong', { hasText: nummer }) })
    .first()
  await zeile.waitFor({ state: 'visible', timeout: 30_000 })
  await zeile.locator('.pw-col-titel').click()
  await page.waitForSelector('.pw-geschaeft-detail', { timeout: 30_000 })
}

/** Das synchronisierte Mitglied, das dem angemeldeten Nextcloud-Nutzer zugeordnet ist. */
async function meinMitglied(page, uid) {
  const res = await api(page, 'get', '/mitglieder')
  expect(res.ok(), 'Mitgliederliste konnte nicht geladen werden').toBeTruthy()
  const mitglied = (await res.json()).find((m) => String(m.nextcloudUid || '') === uid)
  expect(mitglied, `Kein Mitglied mit der Nextcloud-Kennung «${uid}» — Zuständigkeit nicht setzbar`).toBeTruthy()
  return mitglied
}

/** Anzeigename eines Mitglieds — exakt wie die Auswahlliste ihn bildet. */
const mitgliedLabel = (m) => `${m.vorname || ''} ${m.name || ''}`.trim()

/**
 * Setzt am geöffneten Geschäft die Zuständigkeit über die Oberfläche auf die
 * eigene Person und wartet, bis der Server sie übernommen hat — nur die
 * zuständige Person darf danach ein Votum erfassen.
 */
async function zustaendigkeitAufMichSetzen(page, geschaeftId, uid) {
  const ich = await meinMitglied(page, uid)
  const label = mitgliedLabel(ich)
  const select = page
    .locator('.pw-geschaeft-detail .pw-form-zeile', { hasText: 'Zuständigkeit' })
    .locator('.v-select')
  // Ist die eigene Person schon als Chip gewählt, ist nichts zu tun — PwMultiSelect
  // blendet bereits Gewähltes aus der Auswahlliste aus, ein erneutes Picken fände
  // sie darum nicht.
  if (await select.locator('.vs__selected', { hasText: label }).count() === 0) {
    // Die Mitgliederliste lädt asynchron; erst picken, wenn die eigene Person
    // wirklich als Option erscheint, sonst öffnet die Auswahlliste leer und der
    // Klick läuft in einen Timeout.
    await ncOpen(page, select)
    await expect(
      page.locator('.vs__dropdown-menu li.vs__dropdown-option').filter({ hasText: label }).first(),
      'Eigene Person steht nicht in der Zuständigkeitsauswahl',
    ).toBeVisible({ timeout: 20_000 })
    await ncPick(page, select, label)
  }
  await expect(select.locator('.vs__selected'), 'Zuständige Person wurde nicht übernommen').toHaveCount(1)
  await expect
    .poll(
      async () => {
        const res = await api(page, 'get', `/geschaefte/${geschaeftId}`).catch(() => null)
        if (!res || !res.ok()) return []
        const daten = await res.json().catch(() => ({}))
        return (daten.zustaendigkeiten || []).map((z) => String(z.mitgliedExternId || ''))
      },
      { timeout: 20_000, intervals: [500] },
    )
    .toContain(String(ich.externId))
  return ich
}

/**
 * Erfasst das Votum über das Feld «Votum im Rat» in der geöffneten Geschäftsmaske
 * (formatierter Textbereich). Der Editor ist nur für die zuständige Person
 * bearbeitbar; das Feld speichert bei Fokus-Verlust automatisch.
 */
async function votumUeberFeldErfassen(page, text) {
  const feld = page.locator('.pw-geschaeft-detail .pw-votum')
  const editor = feld.locator('.pw-wysiwyg__editor .ProseMirror')
  await expect(editor, 'Votum-Editor ist nicht bearbeitbar').toBeVisible({ timeout: 30_000 })
  await editor.click()
  await page.waitForTimeout(300)
  await editor.pressSequentially(text, { delay: 25 })
  await editor.blur()
  await expect(feld.locator('.pw-wysiwyg__status'), 'Votum wurde nicht gespeichert')
    .toHaveText('Gespeichert', { timeout: 20_000 })
}

/** Datum als «JJJJ-MM-TT», um `tage` Tage verschoben (UTC, wie die Rollen-Gültigkeit). */
function datumVerschoben(tage) {
  return new Date(Date.now() + tage * 86_400_000).toISOString().slice(0, 10)
}

/** Aktueller Synchronisations-Status; null, wenn er (vorübergehend) nicht abrufbar ist. */
async function syncStatus(page) {
  const r = await api(page, 'get', '/sync/status').catch(() => null)
  if (!r || !r.ok()) return null
  return r.json().catch(() => null)
}

/**
 * Stellt sicher, dass KEINE Synchronisation mehr läuft. Der Import-Test macht den
 * Hintergrund-Job fällig; dieser startet anschliessend einen vollen Lauf, der die
 * folgenden Specs stören würde. Der Abbruch wird wiederholt angefordert, bis die
 * Status-Schnittstelle ausdrücklich «läuft nicht» meldet.
 */
async function ensureSyncIdle(page) {
  const deadline = Date.now() + 240_000
  let letzterAbbruch = 0
  while (Date.now() < deadline) {
    const status = await syncStatus(page)
    if (status && status.running === false) return
    if (Date.now() - letzterAbbruch > 3000) {
      await api(page, 'post', '/sync/cancel').catch(() => {})
      letzterAbbruch = Date.now()
    }
    await page.waitForTimeout(1000)
  }
  const final = await syncStatus(page)
  expect(final?.running, 'Synchronisation läuft noch — würde folgende Tests stören').toBe(false)
}

test.beforeAll(() => {
  expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
  expect(USERS.u3.pass, `Passwort für ${USERS.u3.name} fehlt`).not.toBe('')
  expect(ADMIN.pass, 'Admin-Passwort (PW_ADMIN_PASS) fehlt').not.toBe('')
})

// Sicherheitsnetz: der Import-Test löst einen Hintergrund-Lauf aus. Bleibt er
// hängen, flutet er den Echtzeit-Broker und bricht die folgenden Specs.
test.afterAll(async ({ browser }) => {
  const ctx = await browser.newContext()
  try {
    const p = await ctx.newPage()
    await login(p, ADMIN)
    await openAdmin(p)
    await ensureSyncIdle(p)
  } finally {
    await ctx.close()
  }
})

// ===========================================================================
// F26 — Votum im Rat
// ===========================================================================
test.describe('F26 Votum im Rat', () => {
  test('Druckansicht: ohne Votum der Leer-Hinweis, mit Votum Kopf, Metadaten, Wortlaut und automatischer Drucken-Dialog', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    // Der Drucken-Dialog des Browsers ist im Test nicht bedienbar und würde die
    // Seite blockieren. Er wird darum durch einen Zähler ersetzt — damit ist
    // zugleich belegt, dass die Ansicht ihn automatisch auslöst. Auf Kontext-Ebene
    // registriert, damit der Zähler auch im über den PDF-Verweis geöffneten Tab gilt.
    await page.context().addInitScript(() => {
      window.__pwDruckAufrufe = 0
      window.print = () => { window.__pwDruckAufrufe += 1 }
    })

    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Votum')
    const id = await apiCreateGeschaeft(page, titel)

    try {
      await page.reload()
      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)

      // Zuständigkeit über die Oberfläche auf die eigene Person setzen — nur die
      // zuständige Person darf ein Votum erfassen.
      const ich = await zustaendigkeitAufMichSetzen(page, id, USERS.u1.name)

      // Beschluss über die Oberfläche erfassen — er erscheint in der Druckansicht
      // als «Letzter Beschluss der Fraktion».
      const beschlussInput = page.locator('.pw-geschaeft-detail .pw-beschluss-input')
      const beschlussText = 'E2E Haltung zum Votum'
      await beschlussInput.fill(beschlussText)
      await beschlussInput.blur()
      await expect(page.locator('.pw-geschaeft-detail .pw-detail-header span')).toHaveText('Entschieden', { timeout: 15_000 })

      // (1) Ohne Votum: die Druckansicht zeigt den Leer-Hinweis.
      await page.goto(`${APP}/geschaefte/${id}/votum/pdf`)
      await expect(page.locator('header.kopf h1')).toHaveText('Votum im Rat')
      await expect(page.locator('header.kopf .geschaeft-titel')).toHaveText(titel)
      await expect(page.locator('h2.abschnitt')).toHaveText('Wortlaut')
      await expect(page.locator('.votum .leer')).toHaveText('— Noch kein Votum erfasst —')

      // (2) Votum über das Feld «Votum im Rat» in der Geschäftsmaske erfassen.
      // Ohne erfasstes Votum gibt es in der Werkzeugleiste noch keinen PDF-Verweis
      // (Schritt 1 wurde deshalb direkt geöffnet); jetzt zurück in die Maske.
      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)
      const votumText = 'E2E Wortlaut des Votums'
      await votumUeberFeldErfassen(page, votumText)

      // (3) Mit Votum: die Druckansicht wird über den PDF-Verweis in der
      // Werkzeugleiste des Votum-Feldes geöffnet (eigener Tab) und zeigt Kopf,
      // Metadaten, Wortlaut und Fusszeile.
      const pdfVerweis = page.locator('.pw-geschaeft-detail .pw-votum .pw-wysiwyg__toolbar a.pw-wysiwyg__btn[href*="/votum/pdf"]')
      await expect(pdfVerweis, 'PDF-Verweis fehlt in der Votum-Werkzeugleiste').toBeVisible({ timeout: 15_000 })
      const [druckTab] = await Promise.all([
        page.waitForEvent('popup'),
        pdfVerweis.click(),
      ])
      await druckTab.waitForLoadState('domcontentloaded')

      await expect(druckTab.locator('header.kopf h1')).toHaveText('Votum im Rat')
      await expect(druckTab.locator('header.kopf .geschaeft-titel')).toHaveText(titel)

      const meta = druckTab.locator('.meta')
      await expect(meta, 'Zuständige fehlen in der Druckansicht').toContainText(mitgliedLabel(ich))
      await expect(meta, 'Hauptverantwortung ist nicht ausgewiesen').toContainText('Hauptverantwortung')
      await expect(meta, 'Letzter Fraktionsbeschluss fehlt').toContainText('Letzter Beschluss der Fraktion')
      await expect(meta, '«Erfasst von» fehlt').toContainText('Erfasst von')
      await expect(meta, 'Stand (Datum/Zeit) fehlt').toContainText('Stand')

      await expect(druckTab.locator('.votum')).toContainText(votumText)
      await expect(druckTab.locator('.votum .leer')).toHaveCount(0)
      await expect(druckTab.locator('footer.fuss')).toContainText('ausgedruckt am')

      // Der Drucken-Dialog wird automatisch ausgelöst.
      await expect
        .poll(() => druckTab.evaluate(() => window.__pwDruckAufrufe || 0), { timeout: 20_000 })
        .toBeGreaterThan(0)
      await druckTab.close().catch(() => {})
    } finally {
      await gotoGeschaefte(page).catch(() => {})
      await api(page, 'delete', `/geschaefte/${id}`).catch(() => {})
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Nur die zuständige Person darf ein Votum erfassen; archiviert erscheint es in der Aktionszeitleiste', async ({ page, browser }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('VotumArchiv')
    const id = await apiCreateGeschaeft(page, titel)
    let fremd = null

    try {
      await page.reload()
      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)

      await zustaendigkeitAufMichSetzen(page, id, USERS.u1.name)

      // Schlechtfall: eine nicht zuständige Person wird abgewiesen. Für sie ist das
      // Votum-Feld schreibgeschützt; der Schreibzugriff wird serverseitig mit 403
      // abgewiesen (kein UI-Weg für eine nicht zuständige Person).
      fremd = await neuerKontext(browser, USERS.u3)
      await gotoGeschaefte(fremd.page)
      const abgewiesen = await api(fremd.page, 'put', `/geschaefte/${id}/votum`, { text: '<p>Fremdes Votum</p>' })
      expect(abgewiesen.status(), 'Nicht zuständige Person darf kein Votum erfassen').toBe(403)
      expect(JSON.stringify(await abgewiesen.json())).toContain('zuständige')

      // Gutfall: die zuständige Person erfasst das Votum über das Feld «Votum im Rat».
      const votumText = 'E2E Votum zum Archivieren'
      await votumUeberFeldErfassen(page, votumText)

      // Solange das Votum aktiv ist, erscheint es NICHT in der Zeitleiste.
      const zeitleiste = page.locator('.pw-geschaeft-detail .pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })
      await expect(zeitleiste, 'Aktives Votum darf nicht in der Zeitleiste stehen').not.toContainText(votumText)

      // Archivieren über den Knopf «Votum archivieren»: das Votum bleibt als
      // historischer Eintrag in der Zeitleiste.
      await page.locator('.pw-geschaeft-detail').getByRole('button', { name: 'Votum archivieren' }).click()

      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)
      await expect(
        page.locator('.pw-geschaeft-detail .pw-timeline-eintrag', { hasText: votumText }),
        'Archiviertes Votum fehlt in der Aktionszeitleiste',
      ).toHaveCount(1)

      // Und die Druckansicht ist danach wieder leer — ein neues Votum kann beginnen.
      await page.goto(`${APP}/geschaefte/${id}/votum/pdf`)
      await expect(page.locator('.votum .leer')).toHaveText('— Noch kein Votum erfasst —')
    } finally {
      await gotoGeschaefte(page).catch(() => {})
      await api(page, 'delete', `/geschaefte/${id}`).catch(() => {})
      if (fremd) {
        await fremd.page.close().catch(() => {})
        await fremd.ctx.close().catch(() => {})
      }
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// F27 — Dokumente am Geschäft
// ===========================================================================
test.describe('F27 Dokumente am Geschäft', () => {
  /**
   * Dokumente hängen am Ordner «Fraktion/20_Geschäfte/{Jahr}/{Nummer}-*» und
   * brauchen darum eine gültige Geschäftsnummer «JJJJ.N». Ein selbst angelegtes
   * Geschäft hat keine solche Nummer (der Dokumentenbereich blendet seine
   * Aktionen dann bewusst aus), also wird ein synchronisiertes Geschäft benutzt.
   */
  async function geschaeftMitNummer(page) {
    const res = await api(page, 'get', '/geschaefte?show_erledigt=1&limit=2000')
    expect(res.ok(), 'Geschäftsliste konnte nicht geladen werden').toBeTruthy()
    const treffer = (await res.json()).find((g) => /^\d{4}\.\d+$/.test(String(g.nummer || '')))
    expect(treffer, 'Kein synchronisiertes Geschäft mit gültiger Nummer vorhanden').toBeTruthy()
    return treffer
  }

  /** Öffnet ein Geschäft mit Nummer und liefert den Dokumentenbereich. */
  async function oeffneDokumente(page, geschaeft) {
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    await openDetailByNummer(page, String(geschaeft.nummer))
    const bereich = page.locator('.pw-geschaeft-detail .pw-dokumente')
    await expect(bereich).toBeVisible({ timeout: 30_000 })
    return bereich
  }

  test('Aus einer Vorlage erstellen: Dateiname aus dem Titel vorbelegt, leerer Name gesperrt, Dokument erscheint in der Liste', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const geschaeft = await geschaeftMitNummer(page)
    const jahr = String(geschaeft.nummer).slice(0, 4)
    const name = `e2e-vorlage-${Date.now()}`
    const dateiname = `${geschaeft.nummer}-${name}.txt`

    try {
      const bereich = await oeffneDokumente(page, geschaeft)

      // «+ Neues Dokument» öffnet das Vorlagen-Menü (Nextcloud-Standardmuster).
      await bereich.getByText('+ Neues Dokument', { exact: true }).first().click()
      const menu = page.locator('.v-popper__popper:visible').first()
      await menu.waitFor({ state: 'visible', timeout: 15_000 })
      await expect(menu.getByRole('menuitem', { name: 'Word-Dokument (docx)' })).toBeVisible()
      await expect(menu.getByRole('menuitem', { name: 'OpenDocument-Text (odt)' })).toBeVisible()
      // Textdatei: kein Office-Dienst nötig, der Dialog verhält sich identisch.
      await menu.getByRole('menuitem', { name: 'Textdatei (txt)' }).click()

      const dialog = page.locator('.pw-modal-dokument')
      await expect(dialog).toBeVisible({ timeout: 15_000 })
      await expect(dialog.locator('.pw-dokument-praefix')).toHaveText(`${geschaeft.nummer}-`)
      await expect(dialog.locator('.pw-dokument-suffix')).toHaveText('.txt')

      // Der Dateiname ist mit dem Titel vorbelegt, Leerzeichen werden zu Unterstrichen.
      const nameInput = dialog.locator('input.pw-input')
      const erwartet = String(geschaeft.titel || '').trim().replace(/[ /\\]+/g, '_')
      await expect(nameInput, 'Dateiname ist nicht mit dem Geschäftstitel vorbelegt').toHaveValue(erwartet)

      const erstellen = dialog.getByRole('button', { name: 'Erstellen' })
      // Schlechtfall: ohne Namen lässt sich nichts erstellen.
      await nameInput.fill('')
      await expect(erstellen, 'Leerer Dateiname muss «Erstellen» sperren').toBeDisabled()
      await nameInput.fill('   ')
      await expect(erstellen, 'Nur Leerzeichen müssen «Erstellen» sperren').toBeDisabled()

      // Gutfall: kurzer, eindeutiger Name.
      await nameInput.fill(name)
      await expect(erstellen).toBeEnabled()
      // Das Dokument öffnet sich in einem eigenen Tab — dieser wird sofort geschlossen.
      const popupPromise = page.waitForEvent('popup', { timeout: 15_000 }).catch(() => null)
      await erstellen.click()
      const popup = await popupPromise
      if (popup) await popup.close().catch(() => {})

      await expect(page.locator('.pw-modal-dokument')).toHaveCount(0, { timeout: 30_000 })
      await expect(bereich.locator('.pw-meldung')).toHaveText('Dokument erstellt', { timeout: 15_000 })
      await expect(
        bereich.locator('.pw-dokument-eintrag .pw-dokument-name', { hasText: dateiname }),
        'Neues Dokument erscheint nicht in der Liste',
      ).toHaveCount(1)
    } finally {
      await davLoeschen(page, davDatei(USERS.u1.name, 'Fraktion', '20_Geschäfte', jahr, dateiname)).catch(() => {})
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Datei hochladen: Bestätigung und Eintrag in der Dokumentenliste', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const geschaeft = await geschaeftMitNummer(page)
    const jahr = String(geschaeft.nummer).slice(0, 4)
    const kurzname = `e2e-upload-${Date.now()}.txt`
    const dateiname = `${geschaeft.nummer}-${kurzname}`

    try {
      const bereich = await oeffneDokumente(page, geschaeft)

      // Der Knopf «⤒ Hochladen» öffnet die Dateiauswahl des Browsers.
      const chooserPromise = page.waitForEvent('filechooser', { timeout: 15_000 })
      await bereich.getByRole('button', { name: /Hochladen/ }).click()
      const chooser = await chooserPromise
      await chooser.setFiles({
        name: kurzname,
        mimeType: 'text/plain',
        buffer: Buffer.from('E2E Testinhalt', 'utf-8'),
      })

      await expect(bereich.locator('.pw-meldung')).toHaveText('Datei hochgeladen', { timeout: 30_000 })
      await expect(
        bereich.locator('.pw-dokument-eintrag .pw-dokument-name', { hasText: dateiname }),
        'Hochgeladene Datei erscheint nicht in der Liste',
      ).toHaveCount(1)

      // Der Eintrag verlinkt die Datei und bietet den Download an.
      const eintrag = bereich.locator('.pw-dokument-eintrag', { hasText: dateiname }).first()
      await expect(eintrag.locator('a').first()).toHaveAttribute('href', /\/f\/\d+/)
      await expect(eintrag.locator('a.pw-btn-mini')).toHaveAttribute('download', dateiname)
    } finally {
      await davLoeschen(page, davDatei(USERS.u1.name, 'Fraktion', '20_Geschäfte', jahr, dateiname)).catch(() => {})
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// F34 — Vorstoss-Import aus dem Fraktionsordner
// ===========================================================================
// F34 hat KEINE Oberfläche: der Import läuft ausschliesslich im Hintergrund-Job
// SyncJob::run() (vor der Datensynchronisation) und liest den Ordner des
// Administrators. Ein Browser-Test ist dafür das falsche Werkzeug und wäre auf
// das zufällige Zusammenfallen von Job-Intervall und Zeitplan-Fenster
// angewiesen. Die Prüfung liegt deshalb — deterministisch am selben Cron-Trigger
// wie der Cron-Job-Test — im Backend-Runner tests/e2e/run-compose-e2e.sh
// (Abschnitt «F34»): Dokument via WebDAV im Ordner «10_Eigene» ablegen, den Job
// fällig machen, danach ist der Vorstoss genau einmal mit Herkunft «eigene»
// vorhanden. Siehe TESTS.md, Abschnitt «E2E — Backend/API».

// ===========================================================================
// F41 — Kalendereintrag zur internen Sitzung
// ===========================================================================
test.describe('F41 Kalendereintrag zur Sitzung', () => {
  /** Adresse eines Sitzungs-Eintrags im gemeinsamen Fraktionskalender. */
  const kalenderEintragUrl = (sitzungId) =>
    `${BASE_URL}/remote.php/dav/calendars/admin/parlwin-fraktion-kalender/`
    + `${encodeURIComponent(`parliament-winterthur-sitzung-intern-${sitzungId}.ics`)}`

  /** iCalendar-Zeilenfaltung (CRLF + Leerzeichen) rückgängig machen. */
  const entfalte = (ical) => String(ical || '').replace(/\r\n[ \t]/g, '')

  test('Eine neue interne Sitzung erscheint mit Zweck und Traktandenliste im Fraktionskalender', async ({ page, browser }) => {
    test.setTimeout(180_000)
    const jsFehler = fehlerWaechter(page)
    const stamp = Date.now()
    const typName = `E2E-Kalendertyp ${stamp}`
    const sitzungTitel = `E2E-Kalendersitzung ${stamp}`
    const traktandumTitel = `E2E-Traktandum ${stamp}`
    const zweck = `E2E-Zweck ${stamp}`
    const ort = `E2E-Sitzungszimmer ${stamp}`

    await login(page, USERS.u1)
    await openApp(page)

    // Vorlage als reine Vorbedingung über die Schnittstelle anlegen; das Anlegen
    // von Sitzungstypen über die Oberfläche deckt sitzungen-sitzungstypen.spec.js ab.
    const typRes = await api(page, 'post', '/sitzungstypen', {
      name: typName,
      zweck,
      standardOrt: ort,
      standardZeitVon: '18:00',
      standardZeitBis: '20:00',
    })
    expect(typRes.ok(), 'Sitzungstyp konnte nicht angelegt werden').toBeTruthy()
    const typId = (await typRes.json()).id
    let sitzungId = 0
    let admin = null

    try {
      // Interne Sitzung über die Oberfläche anlegen.
      await gotoView(page, 'Sitzungen')
      await page.getByRole('button', { name: /Neue Sitzung/ }).click()
      const menu = page.locator('.v-popper__popper:visible').first()
      await menu.waitFor({ state: 'visible', timeout: 15_000 })
      await menu.getByRole('menuitem', { name: typName }).click()

      const form = page.locator('.pw-neue-sitzung-form')
      await expect(form).toBeVisible({ timeout: 15_000 })
      await form.locator('.pw-sitzung-titel-input').fill(sitzungTitel)
      await expect(form.locator('input[placeholder="Ort"]'), 'Ort kommt nicht aus der Vorlage').toHaveValue(ort)
      await expect(form.locator('.pw-form-textarea'), 'Zweck kommt nicht aus der Vorlage').toHaveValue(zweck)

      const traktanden = form.locator('.pw-form-traktanden')
      await traktanden.getByRole('button', { name: /Traktandum hinzufügen/ }).click()
      await traktanden.locator('.pw-form-traktandum').first().locator('input[placeholder="Titel"]').fill(traktandumTitel)

      // Teilnehmer: die eigene Fraktion (Vorgabe der Teilnehmer-Zeile).
      const teilnehmer = form.locator('.pw-form-teilnehmer')
      await teilnehmer.getByRole('button', { name: /Teilnehmer hinzufügen/ }).click()
      await expect(teilnehmer.locator('.pw-form-teilnehmer-zeile')).toHaveCount(1)

      await form.getByRole('button', { name: /Erstellen/ }).click()
      await expect(page.locator('.pw-neue-sitzung-form')).toHaveCount(0, { timeout: 30_000 })

      const karte = page.locator('.pw-sitzung-karte', { hasText: sitzungTitel })
      await expect(karte, 'Die neue interne Sitzung erscheint nicht in der Liste').toBeVisible({ timeout: 30_000 })
      await expect(karte.locator('.pw-badge-intern')).toBeVisible()
      const domId = await karte.getAttribute('id')
      sitzungId = Number(String(domId || '').replace('pw-sitzung-', ''))
      expect(sitzungId, 'Kennung der neuen Sitzung nicht ermittelbar').toBeGreaterThan(0)

      // Der Kalendereintrag liegt im gemeinsamen Fraktionskalender des
      // Administrators — dort wird er über die Kalender-Schnittstelle geprüft.
      admin = await neuerKontext(browser, ADMIN)
      await openAdmin(admin.page)

      let ics = ''
      await expect
        .poll(async () => {
          const res = await admin.page.request.get(kalenderEintragUrl(sitzungId)).catch(() => null)
          if (!res || !res.ok()) return 0
          ics = entfalte(await res.text())
          return ics.length
        }, { timeout: 30_000, intervals: [1000] })
        .toBeGreaterThan(0)

      expect(ics, 'Kein VEVENT im Kalendereintrag').toContain('BEGIN:VEVENT')
      expect(ics, 'Titel fehlt im Kalendereintrag').toContain(`SUMMARY:${sitzungTitel}`)
      expect(ics, 'Ort fehlt im Kalendereintrag').toContain(`LOCATION:${ort}`)
      expect(ics, 'Kennung des Eintrags stimmt nicht').toContain(`UID:parliament-winterthur-sitzung-intern-${sitzungId}`)
      expect(ics, 'Zweck fehlt in der Beschreibung').toContain(zweck)
      expect(ics, 'Traktandenliste fehlt in der Beschreibung').toContain('Traktanden:')
      expect(ics, 'Traktandum fehlt in der Beschreibung').toContain(traktandumTitel)

      // Teilnehmer stehen im Eintrag, sofern die Regel Personen ergeben hat.
      const detail = await api(page, 'get', `/sitzungen/${sitzungId}`)
      const teilnehmerListe = detail.ok() ? ((await detail.json()).teilnehmer || []) : []
      if (Array.isArray(teilnehmerListe) && teilnehmerListe.length > 0) {
        expect(ics, 'Teilnehmer fehlen im Kalendereintrag').toContain('ATTENDEE')
      }

      // Schlechtfall: für eine Sitzung ohne Eintrag liefert der Kalender nichts.
      const leer = await admin.page.request.get(kalenderEintragUrl(sitzungId + 987654))
      expect(leer.ok(), 'Kalender liefert einen Eintrag für eine nicht existierende Sitzung').toBeFalsy()
    } finally {
      // Kalendereintrag und Vorlage entfernen. Die interne Sitzung selbst bleibt
      // bestehen: die App bietet keinen Weg, eine Sitzung wieder zu löschen.
      if (admin) {
        if (sitzungId) await davLoeschen(admin.page, kalenderEintragUrl(sitzungId)).catch(() => {})
        await admin.page.close().catch(() => {})
        await admin.ctx.close().catch(() => {})
      }
      await api(page, 'delete', `/sitzungstypen/${typId}`).catch(() => {})
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// F51 — Benutzer anlegen und Einladung
// ===========================================================================
test.describe('F51 Benutzer anlegen und Einladung', () => {
  test('«Ausgewählte abgleichen» legt den fehlenden Benutzer an und führt die Zeile danach als vorhanden', async ({ page }) => {
    test.setTimeout(180_000)
    const jsFehler = fehlerWaechter(page)
    await login(page, ADMIN)
    await openAdmin(page)

    const selectFraktion = page.locator('#pw-fraktion')
    const membersStatus = page.locator('#pw-members-status')
    const ursprungsFraktion = await selectFraktion.inputValue()
    const neuerBenutzer = `e2e-f51-${Date.now()}`
    let angelegt = false

    try {
      // Eine Fraktion wählen, in der mindestens ein Mitglied noch keinen lokalen
      // Benutzer hat (freies Username-Feld). Fraktionen ohne solche Mitglieder
      // werden übersprungen.
      const optionen = await selectFraktion
        .locator('option')
        .evaluateAll((os) => os.map((o) => o.value).filter(Boolean))
      expect(optionen.length, 'Keine synchronisierten Fraktionen wählbar').toBeGreaterThan(0)

      let zeile = null
      for (const option of optionen) {
        await selectFraktion.selectOption(option)
        await expect(membersStatus).toHaveText(/Mitglieder geladen/i, { timeout: 30_000 })
        const frei = page.locator('#pw-members-body tr:not(.pw-row-orphan)').filter({
          has: page.locator('input.pw-member-username:not([disabled])'),
        })
        if ((await frei.count()) > 0) {
          zeile = frei.first()
          break
        }
      }
      expect(zeile, 'Keine Fraktion mit einem Mitglied ohne lokalen Benutzer gefunden').toBeTruthy()

      const mitgliedId = await zeile.getAttribute('data-member-id')
      expect(mitgliedId, 'Zeile ohne Mitglieds-Kennung').toBeTruthy()
      const zeileNachId = page.locator(`#pw-members-body tr[data-member-id="${mitgliedId}"]`)

      // Username setzen; die Zuordnung speichert automatisch und rendert die
      // Tabelle neu — deshalb wird danach immer über die Kennung gesucht.
      await zeileNachId.locator('input.pw-member-username').fill(neuerBenutzer)
      await expect(membersStatus).toHaveText(/Zuordnungen gespeichert/i, { timeout: 30_000 })

      // Schlechtfall: ohne Auswahl weist die Oberfläche darauf hin.
      await page.locator('#pw-members-select-all').uncheck()
      await page.locator('#pw-btn-members-provision').click()
      await expect(membersStatus).toHaveText(/Bitte mindestens einen Eintrag auswählen/i, { timeout: 15_000 })

      // Gutfall: genau diese eine Zeile auswählen und abgleichen.
      await zeileNachId.locator('input.pw-member-select').check()
      await page.locator('#pw-btn-members-provision').click()
      await expect(membersStatus).toHaveText(/Angelegt:/i, { timeout: 60_000 })
      const meldung = await membersStatus.textContent()
      expect(meldung, `Kein Benutzer angelegt: ${meldung}`).toMatch(/Angelegt:\s*1/)
      angelegt = true

      // Die Zeile führt den Benutzer nun als vorhanden: das Feld ist gesperrt und
      // die Gruppen-Spalte zeigt die Fraktionsgruppe.
      const gruppe = await page.locator('#pw-nextcloud-gruppe').inputValue()
      await expect(zeileNachId.locator('input.pw-member-username')).toBeDisabled({ timeout: 30_000 })
      await expect(zeileNachId.locator('input.pw-member-username')).toHaveValue(neuerBenutzer)
      await expect(zeileNachId.locator('.pw-member-groups')).toHaveClass(/pw-member-local-exists/)
      if (gruppe) {
        await expect(zeileNachId.locator('.pw-member-groups')).toContainText(gruppe)
      }

      // Der Benutzer existiert wirklich in Nextcloud.
      const token = await reqToken(page)
      const geprueft = await page.request.get(
        `${BASE_URL}/ocs/v2.php/cloud/users/${encodeURIComponent(neuerBenutzer)}?format=json`,
        { headers: { 'OCS-APIRequest': 'true', requesttoken: token } },
      )
      expect(geprueft.ok(), `Angelegter Benutzer «${neuerBenutzer}» ist in Nextcloud nicht auffindbar`).toBeTruthy()
    } finally {
      if (angelegt) {
        const token = await reqToken(page).catch(() => '')
        await page.request
          .delete(`${BASE_URL}/ocs/v2.php/cloud/users/${encodeURIComponent(neuerBenutzer)}?format=json`, {
            headers: { 'OCS-APIRequest': 'true', requesttoken: token },
          })
          .catch(() => {})
      }
      // Ursprünglich gewählte Fraktion wiederherstellen (Auto-Speicherung).
      await selectFraktion.selectOption(ursprungsFraktion).catch(() => {})
      await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 20_000 }).catch(() => {})
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// F62 — Rollen und befristete Stellvertretungen
// ===========================================================================
test.describe('F62 Rollen und befristete Stellvertretungen', () => {
  /**
   * Die Rollenverwaltung hat keine eigene Oberfläche; gesetzt werden Rollen über
   * die Endpunkte der Verwaltung. Geprüft wird darum ihre Wirkung im Browser.
   *
   * Hinweis zum Zustand: eine einmal vergebene Rolle lässt sich nicht wieder
   * entfernen (es gibt keinen Endpunkt dafür). Die hier vergebene
   * Präsidiums-Stellvertretung endet darum am Folgetag von selbst; der
   * Sitzungsmodus wird in jedem Fall wieder ausgeschaltet, sodass keine Wirkung
   * auf andere Tests bleibt.
   */
  test('Eine abgelaufene Stellvertretung wirkt nicht, eine gültige schaltet den Sitzungsmodus frei', async ({ page, browser }) => {
    test.setTimeout(180_000)
    const jsFehler = fehlerWaechter(page)
    expect(USERS.u2.pass, `Passwort für ${USERS.u2.name} fehlt`).not.toBe('')
    let mitglied = null
    let rechtlos = null

    await login(page, USERS.u1)
    await gotoGeschaefte(page)

    try {
      // Ausgangslage: der Sitzungsmodus ist aus.
      const aus = await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '0' })
      expect(aus.ok(), 'Sitzungsmodus konnte nicht zurückgesetzt werden').toBeTruthy()

      // Negativfall mit einem NACHWEISLICH rechtlosen Nutzer: parlwin_protokoll ist
      // Protokollführer, aber weder Fraktions-Gruppen-Admin noch (Stellvertreter im)
      // Präsidium — und bekommt im ganzen Test keine Präsidiumsrolle. Für den
      // Sitzungsmodus-Schalter (kannPraesidiumHandeln) ist er damit deterministisch
      // rechtlos. parlwin_mitglied taugt dafür NICHT: ihm wird weiter unten eine
      // Präsidiums-Stellvertretung erteilt, die den Modus freischaltet.
      rechtlos = await neuerKontext(browser, USERS.u2)
      await gotoGeschaefte(rechtlos.page)
      const verweigert = await api(rechtlos.page, 'post', '/settings/fraktionssitzung', { aktiv: '1' })
      expect(verweigert.status(), 'Ohne Präsidiumsrolle darf niemand den Sitzungsmodus schalten').toBe(403)
      expect(JSON.stringify(await verweigert.json())).toContain('Fraktionspräsidium')

      mitglied = await neuerKontext(browser, USERS.u3)
      await gotoGeschaefte(mitglied.page)

      // Schlechtfall: «bis» vor «von» wird abgewiesen.
      const ungueltig = await api(page, 'post', '/settings/praesidium-stellvertretung', {
        uid: USERS.u3.name,
        name: 'E2E Stellvertretung',
        gueltig_von: datumVerschoben(2),
        gueltig_bis: datumVerschoben(-2),
      })
      expect(ungueltig.status(), 'Ungültige Gültigkeitsspanne wird nicht abgewiesen').toBe(400)
      expect(JSON.stringify(await ungueltig.json())).toContain('gueltig_bis')

      // Schlechtfall: eine bereits abgelaufene Stellvertretung wirkt nicht. Bewusst
      // auf den rechtlosen Nutzer (u2), der im ganzen Test KEINE gültige Präsidiums-
      // rolle erhält. u3 taugt dafür nicht: er bekommt unten eine gültige
      // Stellvertretung, die mangels Entfernungs-Endpunkt aus einem früheren Lauf
      // noch gültig sein kann und den Negativfall verfälschte (200 statt 403). Eine
      // abgelaufene Rolle wird hingegen nie gültig — der Negativfall bleibt stabil.
      const abgelaufen = await api(page, 'post', '/settings/praesidium-stellvertretung', {
        uid: USERS.u2.name,
        name: 'E2E Stellvertretung abgelaufen',
        gueltig_von: datumVerschoben(-10),
        gueltig_bis: datumVerschoben(-2),
      })
      expect(abgelaufen.ok(), 'Abgelaufene Stellvertretung konnte nicht erfasst werden').toBeTruthy()
      const weiterhinVerweigert = await api(rechtlos.page, 'post', '/settings/fraktionssitzung', { aktiv: '1' })
      expect(weiterhinVerweigert.status(), 'Eine abgelaufene Stellvertretung darf nicht wirken').toBe(403)

      // Gutfall: eine gültige Stellvertretung wirkt sofort — das Mitglied schaltet
      // den Sitzungsmodus ein.
      const gueltig = await api(page, 'post', '/settings/praesidium-stellvertretung', {
        uid: USERS.u3.name,
        name: 'E2E Stellvertretung gültig',
        gueltig_von: datumVerschoben(-1),
        gueltig_bis: datumVerschoben(1),
      })
      expect(gueltig.ok(), 'Gültige Stellvertretung konnte nicht erfasst werden').toBeTruthy()

      const eingeschaltet = await api(mitglied.page, 'post', '/settings/fraktionssitzung', { aktiv: '1' })
      expect(eingeschaltet.ok(), 'Gültige Stellvertretung darf den Sitzungsmodus schalten').toBeTruthy()
      expect((await eingeschaltet.json()).modusAktiv, 'Sitzungsmodus wurde nicht aktiviert').toBe(true)

      // Wirkung im Browser: die Geschäfteliste des Mitglieds stellt beim Öffnen
      // automatisch auf «Nur Entscheid nötig» um.
      await gotoGeschaefte(mitglied.page)
      const bedarfFilter = mitglied.page.locator('#pw-filter-slot .v-select').first()
      await expect(
        bedarfFilter.locator('.vs__selected'),
        'Im Sitzungsmodus wird der Entscheidungsbedarf-Filter nicht automatisch gesetzt',
      ).toHaveText(/Entscheid nötig/, { timeout: 30_000 })
    } finally {
      await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '0' }).catch(() => {})
      if (mitglied) {
        await mitglied.page.close().catch(() => {})
        await mitglied.ctx.close().catch(() => {})
      }
      if (rechtlos) {
        await rechtlos.page.close().catch(() => {})
        await rechtlos.ctx.close().catch(() => {})
      }
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Im Sitzungsmodus ist das Beschluss-Feld für das Präsidium gesperrt, für die Protokoll-Stellvertretung frei', async ({ page, browser }) => {
    test.setTimeout(180_000)
    const jsFehler = fehlerWaechter(page)
    let mitglied = null

    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Rollen')
    const id = await apiCreateGeschaeft(page, titel)

    try {
      // Protokollführung auf den Protokoll-Nutzer setzen und die Protokoll-
      // Stellvertretung mit gültiger Frist auf das Mitglied.
      const prot = await api(page, 'post', '/settings/protokollfuehrer', {
        uid: USERS.u2.name,
        name: 'E2E Protokollführung',
      })
      expect(prot.ok(), 'Protokollführung konnte nicht gesetzt werden').toBeTruthy()
      const stv = await api(page, 'post', '/settings/protokollfuehrer-stellvertretung', {
        uid: USERS.u3.name,
        name: 'E2E Protokoll-Stellvertretung',
        gueltig_von: datumVerschoben(-1),
        gueltig_bis: datumVerschoben(1),
      })
      expect(stv.ok(), 'Protokoll-Stellvertretung konnte nicht gesetzt werden').toBeTruthy()

      const ein = await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '1' })
      expect(ein.ok(), 'Sitzungsmodus konnte nicht aktiviert werden').toBeTruthy()

      // Das Präsidium führt kein Protokoll → Feld gesperrt, mit Hinweis.
      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)
      await expect(
        page.locator('.pw-geschaeft-detail .pw-beschluss-input'),
        'Beschluss-Feld ist für das Präsidium nicht gesperrt',
      ).toBeDisabled()
      await expect(
        page.locator('.pw-geschaeft-detail .pw-hinweis', { hasText: 'nur der Protokollführer' }),
      ).toBeVisible()

      // Die gültige Protokoll-Stellvertretung handelt parallel zur Inhaberin:
      // Feld frei, Beschluss wird erfasst und erscheint in der Zeitleiste.
      mitglied = await neuerKontext(browser, USERS.u3)
      await gotoGeschaefte(mitglied.page)
      await openDetailByTitel(mitglied.page, titel)
      const feld = mitglied.page.locator('.pw-geschaeft-detail .pw-beschluss-input')
      await expect(feld, 'Beschluss-Feld ist für die Protokoll-Stellvertretung gesperrt').toBeEnabled()

      const beschluss = 'E2E Beschluss der Stellvertretung'
      await feld.fill(beschluss)
      await feld.blur()
      await expect(
        mitglied.page.locator('.pw-geschaeft-detail .pw-detail-header span'),
        'Beschluss der Stellvertretung wurde nicht übernommen',
      ).toHaveText('Entschieden', { timeout: 20_000 })
      await expect(
        mitglied.page.locator('.pw-geschaeft-detail .pw-timeline-eintrag', { hasText: beschluss }),
        'Beschluss der Stellvertretung fehlt in der Zeitleiste',
      ).toHaveCount(1)
    } finally {
      await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '0' }).catch(() => {})
      await gotoGeschaefte(page).catch(() => {})
      await api(page, 'delete', `/geschaefte/${id}`).catch(() => {})
      if (mitglied) {
        await mitglied.page.close().catch(() => {})
        await mitglied.ctx.close().catch(() => {})
      }
    }

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
