import { test, expect } from '@playwright/test'

/**
 * Querschnitts-E2E der parlwin-Oberfläche im echten Browser gegen den realen
 * Stack (Nextcloud + DB + API). Deckt die gemeinsame Bedienung ab, die keine der
 * bestehenden Suiten prüft:
 *  - Navigation: genau ein aktiver Eintrag, App-Version «v<semver>».
 *  - View-Header: numerischer Zähler == gerenderte Zeilen/Karten, die richtige
 *    Aktions-Schaltfläche je Ansicht (und die drei Ansichten ohne Knopf).
 *  - Suche: Trefferfall in allen sechs Listen, je eigener Leer-Text, ✕ leert.
 *  - Kürzel: in Karten/Zellen der App UND in Auswahl-Dropdowns angewandt, der
 *    gespeicherte Wert bleibt der volle Name.
 *  - Sofort-Speichern-Dialoge ohne «Abbrechen»/«Speichern», genau ein ✕,
 *    Overlay-Klick schliesst; Minimal-Erstell-Dialoge.
 *  - Mobile Karten- vs. Desktop-Tabellen-Darstellung.
 *  - Filter/Suche-Zustand über Navigationswechsel (v-if-Remount → Reset).
 *  - Zentrale Nextcloud-Suche findet ein Geschäft.
 *
 * Bewusst NICHT dupliziert: layout-consistency.spec.js (7-Ansichten-Titel),
 * multi-user-sharing.spec.js (Admin-Kürzel-Zeile, gefüllte Geschäfteliste),
 * vorstoss-datenfluss.spec.js (Vorstoss-Dialog ohne Abbrechen).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }
const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an (wie in den Geschwister-Specs). */
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

/** Öffnet die parlwin-App in einem breiten Desktop-Viewport (Standard-Layout mit Tabellen). */
async function openApp(page) {
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
}

/** Wechselt über den Navigations-Link in die Ansicht und wartet auf ihren Titel. */
async function gotoView(page, name) {
  await page.getByRole('link', { name, exact: true }).click()
  await expect(page.locator('.pw-view-title')).toHaveText(name, { timeout: 20_000 })
  await page.locator('.pw-view-content .pw-laden').first().waitFor({ state: 'hidden', timeout: 20_000 }).catch(() => {})
}

/** Nextcloud-Requesttoken der angemeldeten Seite (für schreibende API-Aufrufe). */
const requestToken = (page) => page.evaluate(() => window.OC?.requestToken || '')

/** Lesender parlwin-API-Aufruf über die (angemeldeten) Cookies der Seite. */
async function apiGet(page, pfad, params) {
  const res = await page.request.get(`${BASE_URL}/index.php/apps/parlwin/${pfad}`, {
    headers: { 'OCS-APIRequest': 'true' },
    params,
  })
  expect(res.ok(), `GET ${pfad} fehlgeschlagen (${res.status()})`).toBeTruthy()
  return res.json()
}

/** Schreibender parlwin-API-Aufruf (JSON) mit Requesttoken. */
async function apiPost(page, pfad, daten) {
  const token = await requestToken(page)
  return page.request.post(`${BASE_URL}/index.php/apps/parlwin/${pfad}`, {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
    data: JSON.stringify(daten),
  })
}

const sucheInput = (page) => page.locator('#pw-search-slot input').first()
const headerCountEl = (page) => page.locator('.pw-view-header .pw-view-count').first()

async function headerCount(page) {
  const t = (await headerCountEl(page).innerText()).trim()
  return Number.parseInt(t || '0', 10)
}

/** Tippt einen Suchbegriff in das (in den Navigationsbereich teleportierte) Suchfeld. */
async function tippeSuche(page, begriff) {
  const inp = sucheInput(page)
  await inp.waitFor({ state: 'visible', timeout: 20_000 })
  await inp.fill(begriff)
}

/** Leert die Suche über den nachgestellten ✕-Knopf des NcTextField. */
async function leereSuche(page) {
  await page.locator('#pw-search-slot .input-field__trailing-button').first().click()
}

/** Längstes Wort (Buchstaben/Ziffern, ≥ 4 Zeichen) eines Textes — als robuster Suchtoken. */
function langesWort(text) {
  const worte = String(text || '').match(/[\p{L}\p{N}]{4,}/gu) || []
  worte.sort((a, b) => b.length - a.length)
  return worte[0] || ''
}

/**
 * Liest den Titel des geöffneten Geschäfts — unabhängig davon, ob er bearbeitbar
 * ist. Bei einem selbst angelegten Geschäft steht er in einem Eingabefeld, bei
 * einem Geschäft von der Parlamentswebseite als Überschrift.
 */
async function detailTitelLesen(page) {
  // Der Kopf erscheint erst, wenn das Geschäft geladen ist — vorher zeigt die
  // Maske «Lade Geschäft...». Erst darauf warten, dann den Titel lesen, sonst
  // ist weder das Eingabefeld noch die Überschrift schon im DOM.
  const kopf = page.locator('.pw-geschaeft-detail .pw-detail-header')
  await kopf.waitFor({ state: 'visible', timeout: 30_000 })
  const eingabe = kopf.locator('.pw-detail-titel-input')
  if (await eingabe.count()) return (await eingabe.inputValue()).trim()
  return (await kopf.locator('h3').innerText()).trim()
}

/** Prüft, dass ALLE aktuell sichtbaren Einträge den Token (case-insensitiv) enthalten. */
async function alleEnthalten(locator, token) {
  const n = await locator.count()
  if (n === 0) return { ok: false, n: 0 }
  const t = token.toLowerCase()
  for (let i = 0; i < n; i++) {
    const txt = (await locator.nth(i).innerText()).toLowerCase()
    if (!txt.includes(t)) return { ok: false, i, txt }
  }
  return { ok: true, n }
}

/** Öffnet ein Filter-Auswahlfeld (NcSelect) im Navigationsbereich anhand seines Labels. */
async function oeffneFilterSelect(page, labelText) {
  const label = page.locator('#pw-filter-slot label')
    .filter({ hasText: new RegExp(`^\\s*${labelText}\\s*$`) }).first()
  await label.waitFor({ state: 'attached', timeout: 15_000 })
  const forId = await label.getAttribute('for')
  const vsel = forId
    ? page.locator('#pw-filter-slot .v-select').filter({ has: page.locator(`[id="${forId}"]`) }).first()
    : page.locator('#pw-filter-slot .v-select').first()
  await vsel.click()
  await page.locator('.vs__dropdown-option').first().waitFor({ state: 'visible', timeout: 10_000 })
}

// Reihenfolge wie in App.vue (ansichten). rows = Selektor für einen gerenderten
// Eintrag; button = erwartete Primär-Aktion (null = kein Header-Knopf).
const ANSICHTEN = [
  { name: 'Geschäfte', rows: '.pw-geschaefte .pw-table-desktop tbody tr', button: '+ Eigenes Geschäft' },
  { name: 'Sitzungen', rows: '.pw-sitzungen .pw-sitzung-karte', button: '+ Neue Sitzung' },
  { name: 'Mitglieder', rows: '.pw-mitglieder .pw-mitglied-karte', button: null },
  { name: 'Kommissionen', rows: '.pw-kommissionen .pw-kommission-karte', button: null },
  { name: 'Vorstösse', rows: '.pw-vorstoesse article.pw-data-card', button: '+ Neuer Vorstoss' },
  { name: 'Sitzungstypen', rows: '.pw-sitzungstypen .pw-sitzungstyp-karte', button: '+ Neuer Typ' },
  { name: 'Änderungsverlauf', rows: '.pw-changelog .pw-data-card', button: null },
]

// ---------------------------------------------------------------------------

test.describe('Navigation: aktiver Eintrag und App-Version', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })
  test.beforeAll(() => expect(U1.pass, `Passwort für ${U1.name} fehlt`).not.toBe(''))

  test('genau ein Navigationseintrag ist aktiv; Footer zeigt «v<semver>»', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    // App-Version im Navigations-Footer.
    const version = page.locator('.pw-nav-version')
    await expect(version).toBeVisible()
    await expect(version).toHaveText(/^v\d+\.\d+\.\d+/)

    // Die sieben Standard-Listenansichten plus «Budget» (getabbte Sonderansicht,
    // im Detail in budget-datenfluss.spec.js geprüft) = acht Navigationseinträge.
    await expect(page.locator('.app-navigation-entry')).toHaveCount(ANSICHTEN.length + 1)

    for (const { name } of [...ANSICHTEN, { name: 'Budget' }]) {
      await gotoView(page, name)
      // Genau ein aktiver Eintrag, und zwar der gerade geöffnete.
      await expect(page.locator('.app-navigation-entry.active')).toHaveCount(1)
      await expect(page.locator('a[aria-current="page"]')).toHaveCount(1)
      await expect(page.locator('.app-navigation-entry.active .app-navigation-entry__name')).toHaveText(name)
    }
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('View-Header: Zähler und Aktions-Schaltfläche', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('jede Ansicht: Zähler == gerenderte Einträge und die richtige (bzw. keine) Aktion', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    for (const { name, rows, button } of ANSICHTEN) {
      await gotoView(page, name)

      // Der numerische Zähler im Header entspricht der Zahl gerenderter Zeilen/Karten.
      await expect.poll(async () => {
        const hc = await headerCount(page)
        const rc = await page.locator(rows).count()
        return hc === rc
      }, { message: `${name}: Header-Zähler muss der Anzahl gerenderter Einträge entsprechen`, timeout: 20_000 })
        .toBe(true)

      if (button) {
        await expect(page.locator('.pw-view-header button', { hasText: button }),
          `${name} muss die Aktion «${button}» zeigen`).toBeVisible()
        await expect(page.locator('.pw-view-header button'),
          `${name} hat genau eine Header-Aktion`).toHaveCount(1)
      } else {
        await expect(page.locator('.pw-view-header button'),
          `${name} hat keine Header-Aktion`).toHaveCount(0)
      }
    }
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('Suche: Trefferfall, Leerzustände und ✕-Leeren', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('Geschäfte: Titel-Token filtert, Gibberish zeigt «Keine Geschäfte gefunden», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')

    // Suchtoken aus der eigenen API — mit denselben Parametern, die die Liste lädt.
    const fs = await apiGet(page, 'settings/fraktionssitzung').catch(() => ({}))
    const params = { limit: 500, show_erledigt: '0' }
    if (fs?.modusAktiv) params.entscheidungsbedarf = '1'
    const gs = await apiGet(page, 'geschaefte', params)
    const g0 = gs.find((g) => g.titel && g.titel.trim())
    expect(g0, 'Keine Geschäfte mit Titel für die Suche').toBeTruthy()
    const token = langesWort(g0.titel)
    expect(token, 'Kein brauchbarer Suchtoken im Titel').not.toBe('')

    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    const rows = page.locator('.pw-geschaefte .pw-table-desktop tbody tr')
    await tippeSuche(page, token)
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 15_000 }).toBe(true)
    await expect(rows.filter({ hasText: token }).first()).toBeVisible()
    expect((await alleEnthalten(rows, token)).ok, 'Nicht jeder sichtbare Treffer enthält den Token').toBe(true)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${Date.now()}`)
    await expect(page.getByText('Keine Geschäfte gefunden')).toBeVisible()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Mitglieder: Namens-Token filtert, Gibberish zeigt «Keine Mitglieder gefunden», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Mitglieder')

    const ms = await apiGet(page, 'mitglieder')
    const m0 = ms.find((m) => m.aktiv && ((m.name || '').trim() || (m.vorname || '').trim()))
    expect(m0, 'Kein aktives Mitglied für die Suche').toBeTruthy()
    const token = langesWort(`${m0.vorname || ''} ${m0.name || ''}`)
    expect(token).not.toBe('')

    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    const karten = page.locator('.pw-mitglieder .pw-mitglied-karte')
    await tippeSuche(page, token)
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 15_000 }).toBe(true)
    await expect(karten.filter({ hasText: token }).first()).toBeVisible()
    expect((await alleEnthalten(karten, token)).ok).toBe(true)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${Date.now()}`)
    await expect(page.getByText('Keine Mitglieder gefunden')).toBeVisible()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Kommissionen: Namens-Token klappt Treffer auf, Gibberish zeigt «Keine Kommissionen gefunden», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Kommissionen')

    const ks = await apiGet(page, 'kommissionen')
    const k0 = ks.find((k) => k.aktiv !== false && (k.name || '').trim())
    expect(k0, 'Keine aktive Kommission für die Suche').toBeTruthy()
    const token = langesWort(k0.name)
    expect(token).not.toBe('')

    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    await tippeSuche(page, token)
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 15_000 }).toBe(true)
    const treffer = page.locator('.pw-kommissionen .pw-kommission-karte', { hasText: token }).first()
    await expect(treffer).toBeVisible()
    // Suche klappt die Treffer-Kommission automatisch auf.
    await expect(treffer.locator('.pw-kommission-details')).toBeVisible()

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${Date.now()}`)
    await expect(page.getByText('Keine Kommissionen gefunden')).toBeVisible()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Vorstösse: Titel-Token filtert, Gibberish zeigt «Keine Vorstösse vorhanden», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    // Deterministische Daten: einen Vorstoss mit eindeutigem Token anlegen.
    const token = `E2ESuche${Date.now()}`
    const erstellt = await apiPost(page, 'vorstoesse', { titel: `${token} Testvorstoss`, zustaendigkeit: [] })
    expect(erstellt.ok(), 'Vorstoss anlegen fehlgeschlagen').toBeTruthy()

    await gotoView(page, 'Vorstösse')
    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    const karten = page.locator('.pw-vorstoesse article.pw-data-card')
    await tippeSuche(page, token)
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 15_000 }).toBe(true)
    await expect(karten.filter({ hasText: token }).first()).toBeVisible()
    expect((await alleEnthalten(karten, token)).ok).toBe(true)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${Date.now()}`)
    await expect(page.getByText('Keine Vorstösse vorhanden')).toBeVisible()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Sitzungen: Titel-Token filtert, Gibberish zeigt «Keine Sitzungen gefunden.», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    // Deterministische Daten: einen Sitzungstyp und eine künftige Sitzung anlegen.
    const stamp = Date.now()
    const typRes = await apiPost(page, 'sitzungstypen', {
      name: `E2E-Typ-${stamp}`, zweck: '', kalenderAnlegen: false, einladungVersenden: false,
      verknuepfen: false, kommissionen: [], standardOrt: '', standardZeitVon: '', standardZeitBis: '',
      traktanden: [], teilnehmer: [],
    })
    expect(typRes.ok(), 'Sitzungstyp anlegen fehlgeschlagen').toBeTruthy()
    const typId = (await typRes.json()).id
    const token = `E2ESitz${stamp}`
    const datum = new Date(stamp + 30 * 24 * 3600 * 1000).toISOString().slice(0, 10)
    const sitzRes = await apiPost(page, 'sitzungen', {
      typId, datum, titel: `${token} Sitzung`, ort: '', zeitVon: '', zeitBis: '',
      bemerkungen: '', traktanden: [], teilnehmer: [],
    })
    expect(sitzRes.ok(), 'Sitzung anlegen fehlgeschlagen').toBeTruthy()

    await gotoView(page, 'Sitzungen')
    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    await tippeSuche(page, token)
    await expect(page.locator('.pw-sitzungen .pw-sitzung-karte', { hasText: token }).first()).toBeVisible()
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 20_000 }).toBe(true)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${stamp}`)
    // Sitzungssuche lädt Traktanden asynchron nach; der Leerzustand stellt sich verzögert ein.
    await expect(page.getByText('Keine Sitzungen gefunden.')).toBeVisible({ timeout: 25_000 })
    await expect.poll(() => headerCount(page), { timeout: 25_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Sitzungstypen: Namens-Token filtert, Gibberish zeigt «Keine Sitzungstypen vorhanden…», ✕ stellt her', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    const token = `E2ETyp${Date.now()}`
    const typRes = await apiPost(page, 'sitzungstypen', {
      name: `${token} Typ`, zweck: '', kalenderAnlegen: false, einladungVersenden: false,
      verknuepfen: false, kommissionen: [], standardOrt: '', standardZeitVon: '', standardZeitBis: '',
      traktanden: [], teilnehmer: [],
    })
    expect(typRes.ok(), 'Sitzungstyp anlegen fehlgeschlagen').toBeTruthy()

    await gotoView(page, 'Sitzungstypen')
    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    const karten = page.locator('.pw-sitzungstypen .pw-sitzungstyp-karte')
    await tippeSuche(page, token)
    await expect.poll(async () => {
      const c = await headerCount(page)
      return c >= 1 && c <= basis
    }, { timeout: 15_000 }).toBe(true)
    await expect(karten.filter({ hasText: token }).first()).toBeVisible()

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)

    await tippeSuche(page, `zzznix${Date.now()}`)
    await expect(page.getByText('Keine Sitzungstypen vorhanden', { exact: false })).toBeVisible()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(0)

    await leereSuche(page)
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('Sofort-Speichern-Dialoge und Minimal-Erstellung', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('Sitzungstyp: Neu öffnet die volle Maske mit Speichern/Abbrechen, danach speichert jede Eingabe sofort und bleibt nach Reload', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Sitzungstypen')

    const stamp = Date.now()
    const name = `E2E-Editier-Typ-${stamp}`
    const ort = `E2E-Ort-${stamp}`

    // «+ Neuer Typ» öffnet sofort die vollständige Maske. Sie legt noch nichts
    // an: der Name wird erfasst, erst «Speichern» erzeugt den Sitzungstyp.
    await page.getByRole('button', { name: '+ Neuer Typ' }).click()
    const editModal = page.locator('.pw-modal').filter({ has: page.locator('.pw-modal-kopf h3') }).first()
    await expect(editModal).toBeVisible({ timeout: 30_000 })
    await expect(editModal.locator('.pw-modal-kopf h3')).toHaveText('Neuer Sitzungstyp')
    // Im Neu-Modus: Speichern (gesperrt ohne Name) und Abbrechen, kein ✕.
    await expect(editModal.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' })).toBeDisabled()
    await expect(editModal.locator('.pw-btn-schliessen')).toHaveCount(0)
    await editModal.locator('.pw-modal-body input.pw-input').first().fill(name)
    await editModal.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
    await expect(editModal.locator('.pw-modal-kopf h3')).toHaveText('Sitzungstyp bearbeiten', { timeout: 30_000 })
    // Beim Bearbeiten gilt das Sofort-Speichern-Prinzip: kein Abbrechen, kein
    // Speichern, genau ein ✕.
    await expect(editModal.getByText('Abbrechen')).toHaveCount(0)
    await expect(editModal.getByRole('button', { name: 'Speichern' })).toHaveCount(0)
    await expect(editModal.locator('.pw-btn-schliessen')).toHaveCount(1)

    // Feld ändern → @change speichert sofort (PUT), ohne Knopf.
    const ortInput = editModal.locator('label.pw-field', { hasText: 'Standard-Ort' }).locator('input')
    await ortInput.fill(ort)
    const [putRes] = await Promise.all([
      page.waitForResponse((r) => /\/apps\/parlwin\/sitzungstypen\/\d+/.test(r.url()) && r.request().method() === 'PUT', { timeout: 20_000 }),
      ortInput.press('Tab'),
    ])
    expect(putRes.ok(), 'Standard-Ort wurde nicht sofort gespeichert').toBeTruthy()

    // Schliessen über ✕ (kein Datenverlust).
    await editModal.locator('.pw-btn-schliessen').click()
    await expect(page.locator('.pw-modal', { hasText: 'Sitzungstyp bearbeiten' })).toHaveCount(0)

    // Reload → Karte erneut öffnen → Wert ist persistent.
    await page.reload()
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await gotoView(page, 'Sitzungstypen')
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).first().click()
    const editModal2 = page.locator('.pw-modal', { hasText: 'Sitzungstyp bearbeiten' })
    await expect(editModal2.locator('label.pw-field', { hasText: 'Standard-Ort' }).locator('input')).toHaveValue(ort)

    // Overlay-Klick ausserhalb des Modals schliesst (@click.self).
    await page.locator('.pw-modal-overlay').first().click({ position: { x: 5, y: 5 } })
    await expect(page.locator('.pw-modal', { hasText: 'Sitzungstyp bearbeiten' })).toHaveCount(0)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Geschäft-Detail: keine Abbrechen/Speichern-Knöpfe, ✕ schliesst ohne Zeile neu zu öffnen, Priorität bleibt nach Reload', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')

    const ersteZeile = page.locator('.pw-geschaefte .pw-table-desktop tbody tr').first()
    await expect(ersteZeile).toBeVisible()

    // Öffnen über die Titel-Zelle (Zeilenmitte kann eine Inline-Edit-Zelle sein).
    await ersteZeile.locator('.pw-col-titel').click()
    await expect(page.locator('.pw-geschaeft-detail')).toBeVisible()
    // Titel als Identität für den Re-Open — stabiler als eine Nummer (eigene
    // Geschäfte haben keine). Bei einem selbst angelegten Geschäft ist der Titel
    // ein Eingabefeld, bei einem Geschäft von der Webseite eine Überschrift.
    const detailTitel = await detailTitelLesen(page)
    const detailModal = page.locator('.pw-modal', { has: page.locator('.pw-geschaeft-detail') })
    await expect(detailModal.getByText('Abbrechen')).toHaveCount(0)
    await expect(detailModal.getByRole('button', { name: 'Speichern' })).toHaveCount(0)
    await expect(detailModal.locator('.pw-btn-schliessen')).toHaveCount(1)

    // Inline-Bearbeitung im Detail: Priorität «Hoch» speichert sofort (PUT).
    const prioSelect = page.locator('.pw-geschaeft-detail .pw-form-zeile').first().locator('.v-select')
    await prioSelect.click()
    const [prioRes] = await Promise.all([
      page.waitForResponse((r) => /\/apps\/parlwin\/geschaefte\/\d+\/prioritaet/.test(r.url()) && r.request().method() === 'PUT', { timeout: 20_000 }),
      page.locator('.vs__dropdown-option', { hasText: 'Hoch' }).first().click(),
    ])
    expect(prioRes.ok(), 'Priorität wurde nicht sofort gespeichert').toBeTruthy()

    // ✕ (@click.stop): schliesst und öffnet die darunterliegende Zeile NICHT erneut.
    await detailModal.locator('.pw-btn-schliessen').click()
    await expect(page.locator('.pw-geschaeft-detail')).toHaveCount(0)
    await page.waitForTimeout(400)
    await expect(page.locator('.pw-geschaeft-detail')).toHaveCount(0)

    // Reload → dasselbe Geschäft (Standardsortierung nach Datum unverändert) → Priorität persistent.
    await page.reload()
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await gotoView(page, 'Geschäfte')
    await page.locator('.pw-geschaefte .pw-table-desktop tbody tr').first().locator('.pw-col-titel').click()
    await expect(page.locator('.pw-geschaeft-detail')).toBeVisible({ timeout: 30_000 })
    expect(await detailTitelLesen(page), 'Nach dem Reload ist ein anderes Geschäft offen').toBe(detailTitel)
    await expect(page.locator('.pw-geschaeft-detail .pw-form-zeile').first().locator('.vs__selected')).toContainText('Hoch')
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Eigenes Geschäft: «+ Eigenes Geschäft» öffnet direkt die vollständige Detailmaske', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')

    const titel = `E2E-Eigenes-Geschäft ${Date.now()}`
    await page.getByRole('button', { name: '+ Eigenes Geschäft' }).click()

    // Kein reduzierter Zwischendialog — sofort die volle Maske. Angelegt wird
    // noch nichts, erst «Speichern» erzeugt das Geschäft.
    const detail = page.locator('.pw-geschaeft-detail')
    await expect(detail).toBeVisible({ timeout: 30_000 })
    await expect(page.locator('.pw-modal', { hasText: 'Eigenes Geschäft erstellen' })).toHaveCount(0)

    // Im Neu-Modus wird nur der Titel erfasst; erst «Speichern» legt das Geschäft
    // an. Danach sind die Stammdaten (Typ …) bearbeitbar.
    await detail.getByLabel('Titel').fill(titel)
    await page.locator('.pw-modal .pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()

    // Danach ist das Geschäft angelegt: Notizen und Dokumente stehen bereit.
    // NotizenListe kommt zweimal vor (normale Notizen + Sitzungsnotizen), der
    // Knopf also doppelt — der erste belegt das Erscheinen.
    await expect(detail.locator('.pw-btn-neue-notiz').first()).toBeVisible({ timeout: 30_000 })
    await expect(detail.locator('.pw-dokumente')).toBeVisible()
    await expect(detail.getByLabel('Titel')).toHaveValue(titel)

    // Die Stammdaten sind jetzt pflegbar und speichern sofort. Der Typ ist eine
    // Auswahl (Vorbelegung «Eigenes Geschäft»); der frei überschreibbare Status
    // belegt hier, dass eine Stammdaten-Eingabe sofort gespeichert wird.
    await expect(detail.getByLabel('Typ')).toContainText('Eigenes Geschäft')
    await detail.getByLabel('Status').fill('E2E-Status')
    await detail.getByLabel('Status').blur()
    await page.waitForLoadState('networkidle')
    await expect(detail.getByLabel('Status')).toHaveValue('E2E-Status')
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('Mobile Karten vs. Desktop-Tabelle', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('Geschäfte: breit zeigt Tabelle (Karten verborgen), schmal zeigt Karten (Tabelle verborgen)', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')
    expect(await page.locator('.pw-geschaefte .pw-table-desktop tbody tr').count(),
      'Für den Layout-Test müssen Geschäfte vorhanden sein').toBeGreaterThan(0)

    // Breit: Tabelle sichtbar, Karten verborgen.
    await page.setViewportSize({ width: 1440, height: 900 })
    await expect(page.locator('.pw-geschaefte .pw-table-desktop')).toBeVisible()
    await expect(page.locator('.pw-geschaefte .pw-card-mobile')).toBeHidden()

    // Schmal: Karten sichtbar, Tabelle verborgen.
    await page.setViewportSize({ width: 480, height: 900 })
    await expect(page.locator('.pw-geschaefte .pw-card-mobile')).toBeVisible()
    await expect(page.locator('.pw-geschaefte .pw-table-desktop')).toBeHidden()
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('Filter-/Suche-Zustand über Navigationswechsel', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('Suchbegriff wird bei Ansichtswechsel zurückgesetzt (Ansichten sind v-if-Remounts)', async ({ page }) => {
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')

    const basis = await headerCount(page)
    expect(basis).toBeGreaterThan(0)

    // Suchbegriff aus einem echten Titel setzen.
    const gs = await apiGet(page, 'geschaefte', { limit: 50, show_erledigt: '0' })
    const token = langesWort((gs.find((g) => g.titel && g.titel.trim()) || {}).titel) || 'a'
    await tippeSuche(page, token)
    await expect(sucheInput(page)).toHaveValue(token)

    // Weg und zurück: die Ansicht wird neu gemountet → Zustand auf Default.
    await gotoView(page, 'Mitglieder')
    await gotoView(page, 'Geschäfte')

    // Beobachtetes Verhalten festgenagelt: leeres Suchfeld, Liste wieder komplett.
    await expect(sucheInput(page)).toHaveValue('')
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBe(basis)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------

test.describe('Zentrale Nextcloud-Suche', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  test('findet ein Geschäft und verlinkt in /apps/parlwin', async ({ page }) => {
    await login(page, U1)
    await openApp(page)

    const gs = await apiGet(page, 'geschaefte', { limit: 20, show_erledigt: '1' })
    const g0 = gs.find((g) => g.titel && g.titel.trim())
    expect(g0, 'Kein Geschäft für die zentrale Suche').toBeTruthy()
    const term = langesWort(g0.titel)
    expect(term).not.toBe('')

    // Verlässlicher Nachweis: der Unified-Search-Provider liefert den Treffer, der in die App verlinkt.
    const res = await page.request.get(`${BASE_URL}/ocs/v2.php/search/providers/parlwin-geschaefte/search`, {
      params: { term },
      headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
    })
    expect(res.ok(), `Unified-Search-API fehlgeschlagen (${res.status()})`).toBeTruthy()
    const json = await res.json()
    const entries = json?.ocs?.data?.entries || []
    expect(entries.length, 'Zentrale Suche liefert keinen parlwin-Treffer').toBeGreaterThan(0)
    expect(entries.some((e) => (e.resourceUrl || '').includes('/apps/parlwin')),
      'Kein Treffer verlinkt in /apps/parlwin').toBe(true)

    // Best-effort über die Kern-Such-UI; der Result-Selektor variiert je NC-Version,
    // daher gilt die API-Assertion als verbindlicher Nachweis (siehe Auftrag).
    let uiSichtbar = false
    try {
      await page.locator('button[aria-label*="earch" i], .unified-search__trigger, .header-menu__trigger[aria-label*="earch" i]')
        .first().click({ timeout: 5_000 })
      await page.locator('input[type="search"], input.unified-search__form-input').first().fill(term, { timeout: 5_000 })
      await page.locator('a[href*="/apps/parlwin"]').first().waitFor({ state: 'visible', timeout: 10_000 })
      uiSichtbar = true
    } catch {
      uiSichtbar = false
    }
    test.info().annotations.push({
      type: 'unified-search-ui',
      description: uiSichtbar ? 'Treffer in der Such-UI sichtbar' : 'Such-UI-Selektor instabil — API-Nachweis gilt',
    })
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------
// Zuletzt: setzt eine GLOBALE Kürzel-Konfiguration und macht sie am Ende wieder
// rückgängig — daher nach allen anderen Gruppen, die ohne Kürzel arbeiten.
test.describe('Kürzel: in App und in Auswahl-Dropdowns, gespeicherter Wert bleibt voll', () => {
  let jsFehler
  test.beforeEach(({ page }) => { jsFehler = []; page.on('pageerror', (e) => jsFehler.push(e.message)) })

  const KURZ = { status: 'KZ-STATUS', partei: 'KZ-PARTEI', fraktion: 'KZ-FRAK', komm: 'KZ-KOMM' }
  const voll = { status: '', partei: '', fraktion: '', komm: '' }
  let adminCtx, adminPage

  test.beforeAll(async ({ browser }) => {
    expect(ADMIN.pass, 'Admin-Passwort (PW_ADMIN_PASS) fehlt').not.toBe('')

    // Echte Werte über einen normalen Nutzer lesen (sieht die synchronisierten Daten).
    const u1ctx = await browser.newContext()
    const u1 = await u1ctx.newPage()
    await login(u1, U1)
    await openApp(u1)
    const fs = await apiGet(u1, 'settings/fraktionssitzung').catch(() => ({}))
    const params = { limit: 500, show_erledigt: '0' }
    if (fs?.modusAktiv) params.entscheidungsbedarf = '1'
    const gs = await apiGet(u1, 'geschaefte', params)
    voll.status = (gs.find((g) => g.status && g.status.trim()) || {}).status || ''
    const ms = await apiGet(u1, 'mitglieder')
    voll.partei = (ms.find((m) => m.aktiv && (m.partei || '').trim()) || {}).partei || ''
    voll.fraktion = (ms.find((m) => m.aktiv && (m.fraktion || '').trim()) || {}).fraktion || ''
    const ks = await apiGet(u1, 'kommissionen')
    voll.komm = (ks.find((k) => k.aktiv !== false && (k.name || '').trim()) || {}).name || ''
    await u1ctx.close()

    // Konfiguration als Admin schreiben (setStatusKuerzel ist admin-only).
    adminCtx = await browser.newContext()
    adminPage = await adminCtx.newPage()
    await login(adminPage, ADMIN)
    await adminPage.goto(`${BASE_URL}/index.php/settings/admin/parlwin`)
    await adminPage.waitForLoadState('networkidle')
    const rules = [
      voll.status && { suche: voll.status, kuerzel: KURZ.status },
      voll.partei && { suche: voll.partei, kuerzel: KURZ.partei },
      voll.fraktion && { suche: voll.fraktion, kuerzel: KURZ.fraktion },
      voll.komm && { suche: voll.komm, kuerzel: KURZ.komm },
    ].filter(Boolean)
    const res = await apiPost(adminPage, 'settings/status-kuerzel', { status_kuerzel: rules })
    expect(res.ok(), 'Kürzel speichern fehlgeschlagen').toBeTruthy()
  })

  test.afterAll(async () => {
    if (adminPage) {
      await apiPost(adminPage, 'settings/status-kuerzel', { status_kuerzel: [] }).catch(() => {})
    }
    await adminCtx?.close()
  })

  test('Status: Kurzform in der Status-Zelle (title = voller Status) und in den Filter-Optionen (Wert bleibt voll)', async ({ page }) => {
    expect(voll.status, 'Kein Status in den Daten').not.toBe('')
    await login(page, U1)
    await openApp(page)
    await gotoView(page, 'Geschäfte')

    // In der App: Zelle zeigt die Kurzform, der volle Status steckt im title.
    const zelle = page.locator('.pw-geschaefte .pw-col-status .pw-status-text', { hasText: KURZ.status }).first()
    await expect(zelle).toBeVisible()
    await expect(zelle).toContainText(KURZ.status)
    expect(await zelle.getAttribute('title')).toBe(voll.status)

    // Im Dropdown: Option zeigt die Kurzform; nach Auswahl filtert der volle Wert (>= 1 Treffer).
    await oeffneFilterSelect(page, 'Status')
    await expect(page.locator('.vs__dropdown-option', { hasText: KURZ.status }).first()).toBeVisible()
    await page.locator('.vs__dropdown-option', { hasText: KURZ.status }).first().click()
    await page.keyboard.press('Escape')
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBeGreaterThan(0)
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Partei/Fraktion/Kommission: Kurzform in den Karten; Fraktion-Filter beweist vollen Wert', async ({ page }) => {
    expect(voll.fraktion, 'Keine Fraktion in den Daten').not.toBe('')
    await login(page, U1)
    await openApp(page)

    // Mitglieder-Karten: Partei- und Fraktions-Kurzform.
    await gotoView(page, 'Mitglieder')
    if (voll.partei) {
      await expect(page.locator('.pw-mitglieder .pw-mitglied-partei', { hasText: KURZ.partei }).first()).toBeVisible()
    }
    await expect(page.locator('.pw-mitglieder .pw-mitglied-karte', { hasText: KURZ.fraktion }).first()).toBeVisible()

    // Fraktion-Filter: Option zeigt Kurzform (Kürzel im Dropdown); Auswahl filtert
    // über den vollen Wert (sonst 0 Treffer), Anzeige bleibt die Kurzform.
    const basis = await headerCount(page)
    await oeffneFilterSelect(page, 'Fraktion')
    await expect(page.locator('.vs__dropdown-option', { hasText: KURZ.fraktion }).first()).toBeVisible()
    await page.locator('.vs__dropdown-option', { hasText: KURZ.fraktion }).first().click()
    await expect.poll(() => headerCount(page), { timeout: 15_000 }).toBeGreaterThan(0)
    const gefiltert = await headerCount(page)
    expect(gefiltert).toBeLessThanOrEqual(basis)
    const karten = page.locator('.pw-mitglieder .pw-mitglied-karte')
    expect((await alleEnthalten(karten, KURZ.fraktion)).ok,
      'Nach Fraktions-Filter zeigen nicht alle Karten die Kurzform').toBe(true)

    // Kommissionen-Karte: Namens-Kurzform.
    if (voll.komm) {
      await gotoView(page, 'Kommissionen')
      await expect(page.locator('.pw-kommissionen .pw-kommission-kopf h3', { hasText: KURZ.komm }).first()).toBeVisible()
    }
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
