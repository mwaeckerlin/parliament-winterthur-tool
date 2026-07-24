import { test, expect } from '@playwright/test'

/**
 * E2E-Datenfluss der GESCHÄFTE im echten Browser gegen das reale System
 * (Nextcloud + DB + API). Deckt die Lücken ab, die die bestehenden Specs NICHT
 * abdecken:
 *  - multi-user-sharing.spec.js: Zeile sichtbar, Echtzeit-Beschluss/-Notiz, Detail öffnen
 *  - vorstoss-datenfluss.spec.js: geteilte NotizenListe über den Vorstoss
 *  - layout-consistency.spec.js: gemeinsame Seitenstruktur
 *
 * Hier: Liste (Spalten, Sortierung, externer Link, Status-Kürzel, mobile Karten),
 * Inline-Bearbeitung (Priorität/Beschluss), Filter und Suche, «+ Eigenes Geschäft»,
 * Detailansicht (Priorität, Zuständigkeit, Fraktionsstatus), BeschlussWidget
 * (Datalist, Freitext, Zurücknehmen, Fraktionssitzungsmodus), Notizen am Geschäft,
 * Sitzungsnotizen, Aktionszeitleiste und verknüpfte Vorstösse.
 *
 * Jeder Test ist self-contained: eigene Daten mit eindeutigem Titel (per-Test
 * erzeugt, nie Modul-Top-Identität – ein Worker importiert das Modul u.U. neu).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP_URL = `${BASE_URL}/index.php/apps/parlwin/`

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
  u2: { name: process.env.PW_U2 || 'parlwin_protokoll', pass: process.env.PW_P2 || '' },
  u3: { name: process.env.PW_U3 || 'parlwin_mitglied', pass: process.env.PW_P3 || '' },
}
const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }

// Per-Test-Zähler für eindeutige Titel – bewusst KEINE Modul-Top-Date.now()-Identität.
let laufNr = 0
function eindeutig(prefix) {
  laufNr += 1
  return `E2E-${prefix} ${Date.now()}-${laufNr}`
}

/** Sammelt JavaScript-Fehler der Seite (für die Hauptflüsse). */
function fehlerWaechter(page) {
  const arr = []
  page.on('pageerror', (e) => arr.push(e.message))
  return arr
}

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an (verbatim aus der Referenz). */
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

/** Öffnet die parlwin-App in der Geschäfte-Ansicht (Standardansicht). */
async function gotoGeschaefte(page) {
  await page.goto(APP_URL)
  await page.waitForLoadState('networkidle')
  await page.waitForSelector('.pw-geschaefte', { timeout: 30_000 })
  await page.waitForSelector('#pw-search-slot input', { timeout: 30_000 })
}

/** Nextcloud-Request-Token der aktuellen App-Seite. */
async function reqToken(page) {
  return page.evaluate(() => (window.OC && window.OC.requestToken) || '')
}

/** Formular-POST/PUT/DELETE gegen die parlwin-API mit dem Token der Seite (gleiche Cookies). */
async function api(page, method, pfad, form) {
  const token = await reqToken(page)
  const opts = {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded' },
  }
  if (form) opts.form = form
  return page.request[method](`${BASE_URL}/index.php/apps/parlwin${pfad}`, opts)
}

async function apiCreateGeschaeft(page, titel, typ = 'Eigenes Geschäft') {
  const res = await api(page, 'post', '/geschaefte', { titel, typ })
  expect(res.ok(), `Geschäft-Anlage fehlgeschlagen (${res.status()})`).toBeTruthy()
  return (await res.json()).id
}

async function apiAddNotiz(page, gid, text, kategorie = 'notiz') {
  const res = await api(page, 'post', `/geschaefte/${gid}/notizen`, { text, kategorie })
  expect(res.ok(), `Notiz-Seed fehlgeschlagen (${res.status()})`).toBeTruthy()
  return res.json()
}

async function apiAddBeschluss(page, gid, text, code = '') {
  const res = await api(page, 'post', `/geschaefte/${gid}/beschluesse`, { code, text })
  expect(res.ok(), `Beschluss-Seed fehlgeschlagen (${res.status()})`).toBeTruthy()
  return res.json()
}

/**
 * Sucht ein Geschäft anhand seines eindeutigen Titels und öffnet die Detailansicht
 * über die TITEL-Zelle (die Inline-Edit-Zellen fangen den Klick per @click.stop ab).
 */
async function openDetailByTitel(page, titel) {
  await page.fill('#pw-search-slot input', titel)
  const zelle = page.locator('.pw-tabelle-geschaefte tbody tr .pw-col-titel', { hasText: titel }).first()
  await zelle.waitFor({ state: 'visible', timeout: 30_000 })
  await zelle.click()
  await page.waitForSelector('.pw-geschaeft-detail', { timeout: 30_000 })
}

// Whitespace normalisieren – NcEllipsisedOption verteilt lange Labels auf zwei
// Spans mit Zeilenumbruch, deshalb nie mit rohem Mehrwort-Text vergleichen.
const norm = (s) => String(s || '').replace(/\s+/g, ' ').trim()

/**
 * Volles, ungekürztes Label einer Option: NcSelect rendert jede Option über
 * NcEllipsisedOption; das komplette Label steht im title-Attribut von
 * `.name-parts` (der sichtbare Text ist evtl. gekürzt oder mehrzeilig). Fällt auf
 * den sichtbaren Text zurück, wenn keine `.name-parts` existieren (einfache Optionen).
 */
async function optionLabel(optionLoc) {
  const np = optionLoc.locator('.name-parts').first()
  if (await np.count() > 0) {
    const t = await np.getAttribute('title')
    if (t != null) return norm(t)
  }
  return norm(await optionLoc.innerText())
}

/**
 * Öffnet ein NcSelect/PwMultiSelect overlay-fest: im linken Nav-Slot fangen die
 * Navigations-Links Koordinaten-Klicks auf `.v-select`/Toggle ab. vue-select öffnet
 * bei fokussiertem Suchfeld auf ArrowDown (kein Hittest). Genau EIN Fallback: ein
 * echter Maus-Klick auf die Toggle-Mitte. Es wird immer der Zustand `vs--open`
 * bestätigt, statt mehrere Strategien blind zu verketten (die letzte könnte die
 * bereits offene Auswahl wieder schliessen). Das Menü teleportiert an den Body.
 */
async function ncOpen(page, root) {
  if (await root.evaluate((el) => el.classList.contains('vs--open')).catch(() => false)) return
  const feld = root.locator('.vs__search, .vs__dropdown-toggle').first()
  await feld.scrollIntoViewIfNeeded().catch(() => {})
  await feld.focus().catch(() => {})
  await page.keyboard.press('ArrowDown')
  try {
    await expect(root).toHaveClass(/vs--open/, { timeout: 4_000 })
    return
  } catch (e) {
    // Fallback: echter Maus-Klick auf die Toggle-Mitte (Koordinaten-Klick).
    const box = await root.locator('.vs__dropdown-toggle').first().boundingBox()
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
    await expect(root).toHaveClass(/vs--open/, { timeout: 6_000 })
  }
}

/** Wählt in einem NcSelect eine Option über ihr volles (title-)Label (Menü am Body). */
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
  // Fallback: exakter title-Treffer, sonst über den sichtbaren (normalisierten) Text.
  const byTitle = menu.locator('li.vs__dropdown-option', { has: page.locator(`.name-parts[title=${JSON.stringify(label)}]`) })
  if (await byTitle.count() > 0) {
    await byTitle.first().click()
  } else {
    await menu.locator('li.vs__dropdown-option').filter({ hasText: label }).first().click()
  }
  await page.keyboard.press('Escape')
}

/** Öffnet ein NcSelect und liefert die vollen Options-Labels (title) für Struktur-Checks. */
async function ncOptions(page, root) {
  await ncOpen(page, root)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  const opts = menu.locator('li.vs__dropdown-option')
  const n = await opts.count()
  const labels = []
  for (let i = 0; i < n; i++) labels.push(await optionLabel(opts.nth(i)))
  await page.keyboard.press('Escape')
  return labels.filter(Boolean)
}

/** Fügt am offenen Geschäft eine Notiz in der OBEREN NotizenListe hinzu (Blur speichert). */
async function notizAmGeschaeftTippen(page, text) {
  const liste = page.locator('.pw-geschaeft-detail .pw-notizen-liste').first()
  const neu = liste.locator('.pw-btn-neue-notiz')
  await neu.waitFor({ state: 'visible', timeout: 30_000 })
  await neu.click()
  const editor = liste.locator('.ProseMirror').first()
  await editor.waitFor({ state: 'visible', timeout: 30_000 })
  await editor.click()
  await page.waitForTimeout(300)
  await editor.pressSequentially(text, { delay: 25 })
  await editor.blur()
}

/** Sortier-Schlüssel wie Geschaeftsliste.sortWert (Nummer: 2. Komponente auf 4 Stellen auffüllen). */
function nrKey(v) {
  return String(v).replace(/^(\d+)\.(\d+)/, (_, jahr, nr) => `${jahr}.${nr.padStart(4, '0')}`)
}

test.beforeAll(() => {
  expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
})

// Alle Tests brauchen einen breiten Content-Bereich: unterhalb ~60em schaltet die
// Container-Query (@container pw-geschaefte) auf Karten um – dann fehlen die
// Desktop-Tabelle UND der linke Filter-/Suchslot ist zu schmal gerendert. Darum
// dateiweit fix 1600×1000 (der Karten-Test verkleinert lokal selbst auf 480).
test.use({ viewport: { width: 1600, height: 1000 } })

// ---------------------------------------------------------------------------
test.describe('Geschäfteliste: Tabelle, Spalten, Sortierung, externer Link', () => {
  test('Spalten rendern, Standardsortierung nach Datum absteigend, externer ↗-Link mit @click.stop', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)

    // Erledigte einblenden, damit die Liste reich befüllt ist (viele synchronisierte Geschäfte).
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    await page.locator('.pw-tabelle-geschaefte tbody tr').first().waitFor({ state: 'visible', timeout: 30_000 })

    // Spaltenüberschriften.
    const thead = page.locator('.pw-tabelle-geschaefte thead')
    for (const titel of ['Nr.', 'Titel', 'Prio', 'Status', 'Zuständig', 'Beschluss']) {
      await expect(thead.getByText(titel, { exact: true }), `Spalte "${titel}" fehlt`).toBeVisible()
    }

    // Standardsortierung: Datum absteigend (nur nicht-leere Datumszellen prüfen).
    const datumsTexte = (await page.locator('.pw-tabelle-geschaefte tbody .pw-col-nr-datum').allInnerTexts())
      .map((t) => t.trim()).filter(Boolean)
    const alsZahl = datumsTexte.map((t) => {
      const [tag, monat, jahr] = t.split('.')
      return Number(jahr) * 10000 + Number(monat) * 100 + Number(tag)
    })
    for (let i = 1; i < alsZahl.length; i++) {
      expect(alsZahl[i] <= alsZahl[i - 1], `Datum nicht absteigend sortiert bei Zeile ${i}`).toBeTruthy()
    }

    // Externer Link: target=_blank, und @click.stop öffnet NICHT das Detail.
    const link = page.locator('.pw-tabelle-geschaefte tbody tr .pw-inline-link').first()
    await link.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(link).toHaveAttribute('target', '_blank')
    await expect(link).toHaveAttribute('href', /.+/)
    const popupPromise = page.waitForEvent('popup', { timeout: 4_000 }).catch(() => null)
    await link.click()
    const popup = await popupPromise
    if (popup) await popup.close().catch(() => {})
    await expect(page.locator('.pw-geschaeft-detail'), 'Klick auf ↗ hat das Detail geöffnet (fehlendes @click.stop)').toHaveCount(0)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Spaltensortierung Nr./Titel/Status schaltet auf- und absteigend um', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    await page.locator('.pw-tabelle-geschaefte tbody tr').first().waitFor({ state: 'visible', timeout: 30_000 })

    const nummern = async () => (await page.locator('.pw-tabelle-geschaefte tbody .pw-col-nr strong').allInnerTexts())
      .map((t) => t.trim()).filter(Boolean)

    // Nr. aufsteigend.
    await page.locator('.pw-tabelle-geschaefte th.pw-col-nr').click()
    await page.waitForTimeout(300)
    const asc = await nummern()
    for (let i = 1; i < asc.length; i++) {
      expect(nrKey(asc[i - 1]).localeCompare(nrKey(asc[i])) <= 0, `Nr. nicht aufsteigend bei ${i}`).toBeTruthy()
    }

    // Nochmals Nr. → absteigend.
    await page.locator('.pw-tabelle-geschaefte th.pw-col-nr').click()
    await page.waitForTimeout(300)
    const desc = await nummern()
    for (let i = 1; i < desc.length; i++) {
      expect(nrKey(desc[i - 1]).localeCompare(nrKey(desc[i])) >= 0, `Nr. nicht absteigend bei ${i}`).toBeTruthy()
    }
    expect(asc.join('|'), 'Auf-/Absteigend liefern dieselbe Reihenfolge').not.toBe(desc.join('|'))

    // Titel-Header wechselt die Reihenfolge.
    const vorTitel = await nummern()
    await page.locator('.pw-tabelle-geschaefte th.pw-col-titel').click()
    await page.waitForTimeout(300)
    expect((await nummern()).join('|'), 'Klick auf Titel-Spalte ändert die Sortierung nicht').not.toBe(vorTitel.join('|'))

    // Status-Header wechselt die Reihenfolge.
    const vorStatus = await nummern()
    await page.locator('.pw-tabelle-geschaefte th.pw-col-status').click()
    await page.waitForTimeout(300)
    expect((await nummern()).join('|'), 'Klick auf Status-Spalte ändert die Sortierung nicht').not.toBe(vorStatus.join('|'))
  })

  test('Status-Kürzel in der Zelle: title zeigt den vollen Status', async ({ browser, page }) => {
    expect(ADMIN.pass, 'Admin-Passwort (PW_ADMIN_PASS) fehlt').not.toBe('')
    const adminCtx = await browser.newContext()
    const adminPage = await adminCtx.newPage()
    let setzen = null
    let kuerzelGesetzt = false
    try {
      await login(page, USERS.u1)
      await gotoGeschaefte(page)

      // Realen Statuswert aus der API lesen – Status sind NICHT literal «Pendent»
      // (z.B. «Beim Stadtrat pendent», «Erledigt», …).
      const res = await api(page, 'get', '/geschaefte?limit=200&show_erledigt=1')
      expect(res.ok(), 'Geschäfte lesen fehlgeschlagen').toBeTruthy()
      const status = (await res.json())
        .map((g) => g.status)
        .find((s) => typeof s === 'string' && s.trim() !== '' && !s.includes('"'))
      expect(status, 'Kein Geschäft mit Status gefunden').toBeTruthy()
      const kuerzel = 'E2E-KZ'

      // Admin konfiguriert das Kürzel für genau diesen Status.
      await login(adminPage, ADMIN)
      await adminPage.goto(`${BASE_URL}/index.php/settings/admin/parlwin`)
      await adminPage.waitForLoadState('networkidle')
      const token = await reqToken(adminPage)
      setzen = (liste) => adminPage.request.post(`${BASE_URL}/index.php/apps/parlwin/settings/status-kuerzel`, {
        headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
        data: JSON.stringify({ status_kuerzel: liste }),
      })
      const gespeichert = await setzen([{ suche: status, kuerzel }])
      expect(gespeichert.ok(), 'Status-Kürzel speichern fehlgeschlagen').toBeTruthy()
      kuerzelGesetzt = true

      // Ansicht neu laden, erledigte einblenden (Status kann ein erledigter sein), Zelle prüfen.
      await gotoGeschaefte(page)
      await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
      await page.waitForLoadState('networkidle')
      const zelle = page.locator(`.pw-col-status .pw-status-text[title="${status}"]`).first()
      await zelle.waitFor({ state: 'visible', timeout: 30_000 })
      await expect(zelle, 'Status-Zelle zeigt nicht das konfigurierte Kürzel').toHaveText(kuerzel)
    } finally {
      // Kürzel IMMER wieder entfernen (auch bei Fehlschlag), sonst beeinflusst es spätere Specs.
      if (setzen && kuerzelGesetzt) await setzen([]).catch(() => {})
      await adminCtx.close()
    }
  })

  test('Status-Spalte ist ausgeblendet, wenn genau ein Status gefiltert ist', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    // Erledigte einblenden, damit die Tabelle sicher Zeilen hat (Standardansicht kann leer sein).
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    await page.locator('.pw-tabelle-geschaefte tbody tr').first().waitFor({ state: 'visible', timeout: 30_000 })
    await expect(page.locator('.pw-tabelle-geschaefte th.pw-col-status')).toHaveCount(1)

    // Einen real vorkommenden Status wählen (nicht hartcodiert).
    const res = await api(page, 'get', '/geschaefte?limit=200&show_erledigt=1')
    const status = (await res.json()).map((g) => g.status).find((s) => typeof s === 'string' && s.trim() !== '')
    expect(status, 'Kein sichtbarer Status gefunden').toBeTruthy()

    const statusFilter = page.locator('#pw-filter-slot .pw-filter-body .v-select').nth(1)
    await ncPick(page, statusFilter, status)
    await page.waitForTimeout(300)

    await expect(page.locator('.pw-tabelle-geschaefte th.pw-col-status'), 'Status-Spalte bei genau einem Status-Filter nicht ausgeblendet').toHaveCount(0)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr').first(), 'Gefilterte Liste ist leer').toBeVisible()
  })

  test('Schmaler Viewport zeigt Karten statt Tabelle', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')

    // Breiter Start (1600 via test.use): die Desktop-Tabelle ist sichtbar.
    await page.locator('.pw-tabelle-geschaefte tbody tr').first().waitFor({ state: 'visible', timeout: 30_000 })
    await expect(page.locator('.pw-table-desktop').first(), 'Tabelle nicht sichtbar bei breitem Viewport').toBeVisible()

    // Container-Query (@container pw-geschaefte max-width: 60em): schmaler Content → Karten.
    await page.setViewportSize({ width: 480, height: 900 })
    await page.waitForTimeout(400)
    await expect(page.locator('.pw-card-mobile').first(), 'Kartendarstellung nicht sichtbar auf schmalem Viewport').toBeVisible()
    await expect(page.locator('.pw-geschaeft-card').first()).toBeVisible()
    await expect(page.locator('.pw-table-desktop').first(), 'Tabelle bleibt auf schmalem Viewport sichtbar').toBeHidden()
    // Kein Viewport-Reset nötig: jeder Test erhält einen frischen Kontext (Standard bzw. test.use).
  })
})

// ---------------------------------------------------------------------------
test.describe('Geschäfteliste: Inline-Bearbeitung', () => {
  test('Priorität inline: «—» ohne Wert, kein Detail-Öffnen, Highlight, bleibt nach Reload', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('InlinePrio')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)

    await page.fill('#pw-search-slot input', titel)
    const zeile = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()
    await zeile.waitFor({ state: 'visible', timeout: 30_000 })

    // Ohne Priorität: Platzhalter «—» (NICHT «Mittel»), keine Highlight-Klasse.
    const prioInput = zeile.locator('.pw-col-prio input.vs__search')
    await expect(prioInput).toHaveAttribute('placeholder', '—')
    await expect(zeile).not.toHaveClass(/pw-prio-hoch/)
    await expect(zeile).not.toHaveClass(/pw-prio-tief/)

    // Inline «Hoch» setzen – @click.stop verhindert das Detail-Öffnen.
    // ncOpen bestätigt `vs--open` am `.v-select`, darum den Select IN der Zelle übergeben.
    await ncPick(page, zeile.locator('.pw-col-prio .v-select'), 'Hoch')
    await expect(page.locator('.pw-geschaeft-detail'), 'Inline-Priorität hat das Detail geöffnet').toHaveCount(0)
    await page.waitForLoadState('networkidle')
    await page.fill('#pw-search-slot input', titel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()).toHaveClass(/pw-prio-hoch/)

    // Nach Reload weiterhin hoch.
    await page.reload()
    await gotoGeschaefte(page)
    await page.fill('#pw-search-slot input', titel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first(), 'Priorität nach Reload verloren').toHaveClass(/pw-prio-hoch/)

    // Auf «Tief» wechseln → pw-prio-tief.
    const zeile2 = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()
    await ncPick(page, zeile2.locator('.pw-col-prio .v-select'), 'Tief')
    await page.waitForLoadState('networkidle')
    await page.fill('#pw-search-slot input', titel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()).toHaveClass(/pw-prio-tief/)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Beschluss inline als Freitext: kein Detail-Öffnen, bleibt nach Reload', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('InlineBeschluss')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)

    await page.fill('#pw-search-slot input', titel)
    const zeile = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()
    await zeile.waitFor({ state: 'visible', timeout: 30_000 })

    const beschlussText = 'E2E Inline Freitext'
    const input = zeile.locator('.pw-col-beschluss input.pw-beschluss-input')
    await input.click()
    await input.fill(beschlussText)
    await input.blur()
    await expect(page.locator('.pw-geschaeft-detail'), 'Inline-Beschluss hat das Detail geöffnet').toHaveCount(0)
    await page.waitForLoadState('networkidle')

    await page.reload()
    await gotoGeschaefte(page)
    await page.fill('#pw-search-slot input', titel)
    const zeile2 = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first()
    await zeile2.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(zeile2.locator('.pw-col-beschluss input.pw-beschluss-input'), 'Inline-Beschluss nach Reload verloren').toHaveValue(beschlussText)
  })
})

// ---------------------------------------------------------------------------
test.describe('Geschäfteliste: Filter und Suche', () => {
  test('Suche nach Titel und Nummer, ✕ setzt zurück, ohne Treffer erscheint die Leermeldung', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Suche')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)

    // Suche nach Titel → genau ein Treffer (das eigene Geschäft).
    await page.fill('#pw-search-slot input', titel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel })).toHaveCount(1)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr')).toHaveCount(1)

    // Ohne Treffer → «Keine Geschäfte gefunden».
    await page.fill('#pw-search-slot input', `KEIN_TREFFER_${Date.now()}`)
    await expect(page.getByText('Keine Geschäfte gefunden')).toBeVisible()

    // Trailing-✕ leert die Suche und stellt die Liste wieder her.
    await page.locator('#pw-search-slot button').click()
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr').first()).toBeVisible()

    // Suche nach Nummer: erledigte einblenden, eine echte Nummer nehmen.
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    const ersteNr = (await page.locator('.pw-tabelle-geschaefte tbody .pw-col-nr strong').first().innerText()).trim()
    expect(ersteNr, 'Keine Geschäftsnummer gefunden').not.toBe('')
    await page.fill('#pw-search-slot input', ersteNr)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: ersteNr }).first()).toBeVisible()
  })

  test('«Erledigte anzeigen» ist standardmässig aus und blendet erledigte Geschäfte ein', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    await page.locator('.pw-tabelle-geschaefte tbody tr').first().waitFor({ state: 'visible', timeout: 30_000 })
    const vorher = await page.locator('.pw-tabelle-geschaefte tbody tr').count()

    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')
    await expect
      .poll(async () => page.locator('.pw-tabelle-geschaefte tbody tr').count(), { timeout: 30_000 })
      .toBeGreaterThan(vorher)
  })

  test('«Filter zurücksetzen» leert Suche und Erledigte-Schalter', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    await page.fill('#pw-search-slot input', 'irgendwas')
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await page.waitForLoadState('networkidle')

    await page.locator('#pw-filter-slot').getByRole('button', { name: 'Filter zurücksetzen' }).click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('#pw-search-slot input')).toHaveValue('')
    // Schalter wieder aus: erledigte nicht mehr gelistet – der Zähler geht zurück auf die kleine Standardmenge.
    await expect(page.locator('#pw-filter-slot .checkbox-radio-switch input[type="checkbox"]')).not.toBeChecked()
  })

  test('Entscheidungsbedarf-Auswahl lädt die Liste neu und behält Geschäfte', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    // Sicherstellen, dass der teleportierte Filter-Slot mitsamt Selects gemountet ist.
    const entscheidung = page.locator('#pw-filter-slot .pw-filter-body .v-select').first()
    await entscheidung.waitFor({ state: 'attached', timeout: 30_000 })
    await ncPick(page, entscheidung, 'Nur Entscheid nötig')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('#pw-filter-slot .pw-filter-body .v-select').first().locator('.vs__selected')).toHaveText(/Entscheid nötig/)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr').first(), 'Kein Geschäft mit Entscheidungsbedarf sichtbar').toBeVisible()
  })
})

// ---------------------------------------------------------------------------
test.describe('Eigenes Geschäft: Anlegen', () => {
  test('«+ Eigenes Geschäft» öffnet die volle Maske; Stammdaten sind bearbeitbar und bleiben erhalten', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)

    const oeffnen = () => page.getByRole('button', { name: '+ Eigenes Geschäft' }).click()
    const detail = () => page.locator('.pw-geschaeft-detail')

    // Der Neu-Knopf öffnet direkt die vollständige Detailmaske — es gibt
    // keinen reduzierten Zwischendialog mehr. Angelegt wird noch nichts.
    await oeffnen()
    await expect(detail()).toBeVisible({ timeout: 30_000 })
    await expect(page.locator('.pw-modal', { hasText: 'Eigenes Geschäft erstellen' })).toHaveCount(0)

    // Notizen, Dokumente, Beschluss und Votum brauchen eine bestehende ID und
    // erscheinen deshalb erst nach dem Speichern; vorher steht dort ein Hinweis.
    await expect(detail().locator('.pw-btn-neue-notiz')).toHaveCount(0)
    await expect(detail().locator('.pw-dokumente')).toHaveCount(0)
    await expect(detail().locator('.pw-beschluss-input')).toHaveCount(0)
    await expect(detail().locator('.pw-hinweis', { hasText: 'sobald das Geschäft gespeichert ist' })).toBeVisible()

    // Ohne Titel ist «Speichern» gesperrt.
    const fuss = page.locator('.pw-modal .pw-modal-footer')
    await expect(fuss.getByRole('button', { name: 'Speichern' })).toBeDisabled()

    const titel = eindeutig('EigenesVoll')
    await detail().getByLabel('Titel').fill(titel)
    await fuss.getByRole('button', { name: 'Speichern' }).click()

    // Nach dem Speichern ist es ein bestehendes Geschäft: die ID-gebundenen
    // Bereiche sind da und jede Eingabe speichert wieder sofort.
    // NotizenListe kommt zweimal vor (normale Notizen + Sitzungsnotizen), der
    // Knopf also doppelt — der erste belegt das Erscheinen.
    await expect(detail().locator('.pw-btn-neue-notiz').first()).toBeVisible({ timeout: 30_000 })
    await expect(detail().locator('.pw-dokumente')).toBeVisible()
    await expect(page.locator('.pw-modal .pw-modal-footer')).toHaveCount(0)

    // Bei einem eigenen Geschäft sind Titel, Typ, Status und Datum bearbeitbar
    // (bei Geschäften von der Webseite stammen sie aus der Quelle).
    await detail().getByLabel('Typ').fill('E2E-Typ')
    await detail().getByLabel('Typ').blur()
    await detail().getByLabel('Status').fill('E2E-Status')
    await detail().getByLabel('Status').blur()
    await page.waitForLoadState('networkidle')

    // Schliessen und erneut öffnen: alle Stammdaten sind gespeichert.
    await page.locator('.pw-modal .pw-btn-schliessen').first().click()
    await page.fill('#pw-search-slot input', titel)
    await page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }).first().click()
    await expect(detail()).toBeVisible({ timeout: 30_000 })
    await expect(detail().getByLabel('Titel')).toHaveValue(titel)
    await expect(detail().getByLabel('Typ')).toHaveValue('E2E-Typ')
    await expect(detail().getByLabel('Status')).toHaveValue('E2E-Status')

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------
test.describe('GeschaeftDetail: Ansicht und Fraktionsarbeit', () => {
  test('Detail zeigt öffentliche und fraktionsinterne Abschnitte', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('DetailAnsicht')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    await expect(page.locator('.pw-geschaeft-detail .pw-oeffentlich h4', { hasText: 'Öffentliche Informationen' })).toBeVisible()
    await expect(page.locator('.pw-geschaeft-detail .pw-fraktion h4', { hasText: 'Fraktionsinterne Bearbeitung' })).toBeVisible()
    await expect(page.locator('.pw-geschaeft-detail .pw-info-tabelle')).toBeVisible()
    // Bei einem selbst angelegten Geschäft ist der Titel bearbeitbar (Eingabefeld),
    // bei einem Geschäft von der Webseite steht er als Überschrift.
    await expect(page.locator('.pw-geschaeft-detail').getByLabel('Titel')).toHaveValue(titel)
  })

  test('Priorität im Detail setzen und wieder zurücksetzen', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('DetailPrio')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    const prioZeile = page.locator('.pw-geschaeft-detail .pw-form-zeile', { hasText: 'Priorität' }).first()
    const prioSelect = prioZeile.locator('.v-select')
    await ncPick(page, prioSelect, 'Hoch')
    await expect(prioSelect.locator('.vs__selected')).toHaveText('Hoch')

    // Zurücksetzen über den Clear-Knopf des NcSelect.
    await prioSelect.locator('.vs__clear').click()
    await expect(prioSelect.locator('.vs__selected')).toHaveCount(0)
    await expect(prioSelect.locator('input.vs__search')).toHaveAttribute('placeholder', '—')
  })

  test('Zuständigkeit: NC-Mitglieder wählbar, inaktive mit «(inaktiv)», erste Auswahl ist Hauptzuständigkeit', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('DetailZust')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    // Hinweis dokumentiert die Hauptzuständigkeit (erste Auswahl).
    await expect(page.locator('.pw-geschaeft-detail .pw-hinweis', { hasText: 'Hauptzuständigkeit' })).toBeVisible()

    // Die Zuständigkeit ist ein PwMultiSelect in der «Zuständigkeit»-Formzeile; die
    // Klasse `pw-zustaendigkeit-select` liegt auf dem `.v-select` selbst (nicht als
    // Elternelement) – darum über die Formzeile zum `.v-select` navigieren.
    const zustSelect = page.locator('.pw-geschaeft-detail .pw-form-zeile', { hasText: 'Zuständigkeit' }).locator('.v-select')
    const optionen = await ncOptions(page, zustSelect)
    expect(optionen.length, 'Keine wählbaren zuständigen Personen angeboten').toBeGreaterThan(0)

    // Ein inaktives NC-Mitglied («Ehemalig Erika») ist mit «(inaktiv)» markiert und
    // steht NACH allen aktiven (aktive zuerst).
    const inaktivIdx = optionen.findIndex((o) => o.includes('(inaktiv)'))
    expect(inaktivIdx, 'Kein inaktives Mitglied mit «(inaktiv)»-Suffix angeboten').toBeGreaterThanOrEqual(0)
    for (let i = 0; i < inaktivIdx; i++) {
      expect(optionen[i], 'Aktive Mitglieder stehen nicht vor den inaktiven').not.toContain('(inaktiv)')
    }

    // Erste (aktive) Person wählen → wird intern als Hauptzuständigkeit geführt.
    await ncPick(page, zustSelect, optionen[0])
    await expect(zustSelect.locator('.vs__selected'), 'Zuständige Person wurde nicht übernommen').toHaveCount(1)
  })

  test('Fraktionsstatus wechselt nach einem Beschluss auf «Entschieden»', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('DetailStatus')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    const kopfStatus = page.locator('.pw-geschaeft-detail .pw-detail-header span')
    await expect(kopfStatus, 'Neues Geschäft ist nicht «Offen»').toHaveText('Offen')

    const input = page.locator('.pw-geschaeft-detail .pw-beschluss-input')
    await input.fill('E2E Detail Beschluss')
    await input.blur()
    await expect(kopfStatus, 'Fraktionsstatus wechselt nach Beschluss nicht auf «Entschieden»').toHaveText('Entschieden', { timeout: 15_000 })
    await expect(page.locator('.pw-geschaeft-detail .pw-info-tabelle', { hasText: 'Fraktionsstatus' })).toContainText('Entschieden')

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------
test.describe('BeschlussWidget am Geschäft', () => {
  test('Typabhängige Datalist-Optionen, Freitext-Beschluss und Zurücknehmen', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Beschluss')
    const id = await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    // Datalist-Optionen müssen den erlaubten Beschlüssen des Geschäfts entsprechen
    // (typabhängig – aus der API gelesen, nie hartcodiert).
    const detailRes = await api(page, 'get', `/geschaefte/${id}`)
    expect(detailRes.ok(), 'Geschäft-Detail lesen fehlgeschlagen').toBeTruthy()
    const erlaubt = ((await detailRes.json()).erlaubteBeschluesse || []).map((b) => b.label || b.code).filter(Boolean)
    expect(erlaubt.length, 'Geschäft hat keine erlaubten Beschlüsse').toBeGreaterThan(0)
    const optionWerte = await page.locator('.pw-geschaeft-detail datalist option').evaluateAll((els) => els.map((e) => e.value))
    for (const label of erlaubt) {
      expect(optionWerte, `Datalist-Option "${label}" fehlt`).toContain(label)
    }

    const kopfStatus = page.locator('.pw-geschaeft-detail .pw-detail-header span')
    const input = page.locator('.pw-geschaeft-detail .pw-beschluss-input')

    // Freitext-Beschluss speichern.
    await input.fill('E2E Freitext Haltung')
    await input.blur()
    await expect(kopfStatus).toHaveText('Entschieden', { timeout: 15_000 })
    await expect(page.locator('.pw-geschaeft-detail .pw-timeline-eintrag'), 'Beschluss erscheint nicht in der Zeitleiste').toContainText('E2E Freitext Haltung')

    // Zurücknehmen: Feld leeren + Blur.
    await input.fill('')
    await input.blur()
    await expect(kopfStatus, 'Fraktionsstatus nach Zurücknehmen nicht «Offen»').toHaveText('Offen', { timeout: 15_000 })
    await expect(page.locator('.pw-geschaeft-detail .pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })).toContainText('zurückgenommen')
  })

  test('Fraktionssitzungsmodus: Beschluss-Widget für Nicht-Protokollführer deaktiviert mit Hinweis', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Sitzungsmodus')
    await apiCreateGeschaeft(page, titel)

    try {
      // Modus aktivieren (Präsidium darf), Protokollführer = parlwin_protokoll setzen.
      const aktiv = await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '1' })
      expect(aktiv.ok(), 'Fraktionssitzungsmodus aktivieren fehlgeschlagen').toBeTruthy()
      expect((await aktiv.json()).modusAktiv, 'Modus nicht aktiv').toBeTruthy()
      const prot = await api(page, 'post', '/settings/protokollfuehrer', { uid: USERS.u2.name, name: 'Protokoll E2E' })
      expect(prot.ok(), 'Protokollführer setzen fehlgeschlagen').toBeTruthy()

      // parlwin_praesidium ist NICHT Protokollführer → Widget gesperrt.
      await page.reload()
      await gotoGeschaefte(page)
      await openDetailByTitel(page, titel)
      await expect(page.locator('.pw-geschaeft-detail .pw-beschluss-input'), 'Beschluss-Widget nicht deaktiviert').toBeDisabled()
      await expect(page.locator('.pw-geschaeft-detail .pw-hinweis', { hasText: 'nur der Protokollführer' })).toBeVisible()
    } finally {
      // Modus zwingend zurücksetzen (sonst brechen spätere Specs, die Beschlüsse setzen).
      await api(page, 'post', '/settings/fraktionssitzung', { aktiv: '0' })
    }
  })
})

// ---------------------------------------------------------------------------
test.describe('Notizen am Geschäft (über GeschaeftDetail)', () => {
  test('Hinzufügen bei Blur, leerer Editor erzeugt nichts, Bearbeiten (Version), Löschen und Wiederherstellen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Notiz')
    await apiCreateGeschaeft(page, titel)
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    const liste = page.locator('.pw-geschaeft-detail .pw-notizen-liste').first()
    const notiz = 'E2E Geschäftsnotiz'

    // Hinzufügen bei Blur.
    await notizAmGeschaeftTippen(page, notiz)
    await expect(liste.getByText(notiz, { exact: false }).first(), 'Notiz erscheint nach dem Blur nicht').toBeVisible({ timeout: 15_000 })
    await expect(liste.locator('.pw-notiz-eintrag', { hasText: notiz }), 'Notiz doppelt angelegt').toHaveCount(1)

    // Notizen sind NICHT in der Aktionszeitleiste (leerer Hinweis bleibt).
    await expect(page.locator('.pw-geschaeft-detail .pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })).toContainText('Noch keine Aktionen vorhanden')

    // Leerer Editor erzeugt nichts.
    const vorher = await liste.locator('.pw-notiz-eintrag').count()
    await liste.locator('.pw-btn-neue-notiz').click()
    const leerEditor = liste.locator('.ProseMirror').first()
    await leerEditor.waitFor({ state: 'visible', timeout: 15_000 })
    await leerEditor.click()
    await page.waitForTimeout(300)
    await leerEditor.blur()
    await page.waitForTimeout(500)
    await expect(liste.locator('.pw-notiz-eintrag'), 'Leerer Editor hat eine Notiz erzeugt').toHaveCount(vorher)

    // Bearbeiten → Version.
    const eintrag = liste.locator('.pw-notiz-eintrag', { hasText: notiz }).first()
    await eintrag.locator('.pw-notiz-inhalt').click()
    const editEditor = eintrag.locator('.ProseMirror').first()
    await editEditor.waitFor({ state: 'visible', timeout: 15_000 })
    await editEditor.click()
    await page.waitForTimeout(300)
    await page.keyboard.press('Control+End')
    await editEditor.pressSequentially(' bearbeitet', { delay: 25 })
    await editEditor.blur()
    await expect(liste.getByText(`${notiz} bearbeitet`, { exact: false }).first(), 'Bearbeitete Notiz erscheint nicht').toBeVisible({ timeout: 15_000 })

    // Löschen (Soft-Delete) → Vermerk.
    await liste.locator('.pw-notiz-eintrag', { hasText: `${notiz} bearbeitet` }).first().locator('.pw-btn-loeschen').click()
    await expect(liste.getByText('hat seine Notiz gelöscht', { exact: false }).first()).toBeVisible({ timeout: 15_000 })

    // Wiederherstellen (Undo).
    await liste.locator('button[title="Löschen rückgängig machen"]').first().click()
    await expect(liste.getByText(`${notiz} bearbeitet`, { exact: false }).first(), 'Wiederhergestellte Notiz fehlt').toBeVisible({ timeout: 15_000 })

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Nicht-Autor sieht keine Bearbeiten-/Löschen-Möglichkeit', async ({ browser }) => {
    const ctx1 = await browser.newContext()
    const ctx2 = await browser.newContext()
    try {
      const page1 = await ctx1.newPage()
      const page2 = await ctx2.newPage()
      await login(page1, USERS.u1)
      await login(page2, USERS.u2)
      expect(USERS.u2.pass, `Passwort für ${USERS.u2.name} fehlt`).not.toBe('')

      await gotoGeschaefte(page1)
      const titel = eindeutig('NotizAutor')
      await apiCreateGeschaeft(page1, titel)
      await page1.reload()
      await gotoGeschaefte(page1)
      await openDetailByTitel(page1, titel)

      const notiz = 'E2E Autor-Notiz'
      await notizAmGeschaeftTippen(page1, notiz)
      const liste1 = page1.locator('.pw-geschaeft-detail .pw-notizen-liste').first()
      await expect(liste1.getByText(notiz).first()).toBeVisible({ timeout: 15_000 })
      // Autor: Löschen-Knopf und klickbarer Text vorhanden.
      const eintrag1 = liste1.locator('.pw-notiz-eintrag', { hasText: notiz }).first()
      await expect(eintrag1.locator('.pw-btn-loeschen')).toHaveCount(1)
      await expect(eintrag1.locator('.pw-notiz-inhalt.pw-notiz-text-klickbar')).toHaveCount(1)

      // Nicht-Autor öffnet dasselbe Geschäft.
      await gotoGeschaefte(page2)
      await openDetailByTitel(page2, titel)
      const liste2 = page2.locator('.pw-geschaeft-detail .pw-notizen-liste').first()
      await expect(liste2.getByText(notiz).first(), 'Nicht-Autor sieht die Notiz nicht').toBeVisible({ timeout: 15_000 })
      const eintrag2 = liste2.locator('.pw-notiz-eintrag', { hasText: notiz }).first()
      await expect(eintrag2.locator('.pw-btn-loeschen'), 'Nicht-Autor sieht einen Löschen-Knopf').toHaveCount(0)
      await expect(eintrag2.locator('.pw-notiz-inhalt.pw-notiz-text-klickbar'), 'Nicht-Autor kann die Notiz bearbeiten').toHaveCount(0)
    } finally {
      await ctx1.close()
      await ctx2.close()
    }
  })
})

// ---------------------------------------------------------------------------
test.describe('Sitzungsnotizen am Geschäft', () => {
  test('separat, standardmässig eingeklappt, Zahl in der Summary, nicht in der oberen Notizliste, aufklappbar', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Sitzungsnotiz')
    const id = await apiCreateGeschaeft(page, titel)
    const sitzungsnotiz = 'E2E Sitzungsnotiz Inhalt'
    await apiAddNotiz(page, id, sitzungsnotiz, 'sitzungsnotiz')
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    const details = page.locator('.pw-geschaeft-detail .pw-sitzungsnotizen-details')
    await expect(details, 'Sitzungsnotizen-Details fehlen').toHaveCount(1)
    // Standardmässig eingeklappt.
    await expect(details).toHaveJSProperty('open', false)
    // Zahl in der Summary.
    await expect(details.locator('.pw-sitzungsnotizen-zahl')).toHaveText('1')

    // NICHT in der oberen (normalen) Notizliste.
    const obereListe = page.locator('.pw-geschaeft-detail .pw-notizen-liste').first()
    await expect(obereListe, 'Sitzungsnotiz taucht fälschlich in der oberen Notizliste auf').not.toContainText(sitzungsnotiz)
    // Eingeklappt → Text nicht sichtbar.
    await expect(details.getByText(sitzungsnotiz, { exact: false }).first()).toBeHidden()

    // Aufklappen → Text sichtbar.
    await details.locator('summary').click()
    await expect(details).toHaveJSProperty('open', true)
    await expect(details.getByText(sitzungsnotiz, { exact: false }).first(), 'Sitzungsnotiz nach dem Aufklappen nicht sichtbar').toBeVisible()
  })
})

// ---------------------------------------------------------------------------
test.describe('Aktionszeitleiste am Geschäft', () => {
  test('Beschlüsse mit Autor/Datum/Zeit, Notizen/Sitzungsnotizen ausgeblendet, Umsortieren per Griff', async ({ page }, testInfo) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const titel = eindeutig('Zeitleiste')
    const id = await apiCreateGeschaeft(page, titel)
    // Zwei Beschlüsse (eigene Einträge), plus eine Notiz und eine Sitzungsnotiz (dürfen NICHT erscheinen).
    await apiAddBeschluss(page, id, 'E2E Zeitleiste Beschluss A')
    await apiAddBeschluss(page, id, 'E2E Zeitleiste Beschluss B')
    await apiAddNotiz(page, id, 'E2E Zeitleiste Notiz', 'notiz')
    await apiAddNotiz(page, id, 'E2E Zeitleiste Sitzungsnotiz', 'sitzungsnotiz')
    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, titel)

    const tl = page.locator('.pw-geschaeft-detail .pw-timeline-eintrag')
    await expect(tl.first()).toBeVisible({ timeout: 30_000 })
    await expect(tl, 'Zu wenige Zeitleisten-Einträge').toHaveCount(2)

    // Autor/Datum/Zeit vorhanden.
    await expect(tl.first().locator('.pw-timeline-autor')).not.toHaveText('')
    await expect(tl.first().locator('.pw-timeline-datum-tag')).not.toHaveText('')
    await expect(tl.first().locator('.pw-timeline-datum-uhrzeit')).not.toHaveText('')

    // Beschlüsse sichtbar, Notiz/Sitzungsnotiz NICHT in der Zeitleiste.
    const zeitleiste = page.locator('.pw-geschaeft-detail .pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })
    await expect(zeitleiste).toContainText('E2E Zeitleiste Beschluss A')
    await expect(zeitleiste).toContainText('E2E Zeitleiste Beschluss B')
    await expect(zeitleiste, 'Notiz erscheint fälschlich in der Zeitleiste').not.toContainText('E2E Zeitleiste Notiz')
    await expect(zeitleiste, 'Sitzungsnotiz erscheint fälschlich in der Zeitleiste').not.toContainText('E2E Zeitleiste Sitzungsnotiz')

    // Umsortier-Affordanz: Griff ist draggable.
    await expect(tl.first().locator('.pw-notiz-griff')).toHaveAttribute('draggable', 'true')

    // Reihenfolge per synthetischem HTML5-Drag umsortieren (client-only). Firefox setzt
    // dataTransfer synthetischer DragEvents nicht wie Chromium – der eigentliche Swap
    // wird darum nur in Chromium geprüft; die Affordanz oben gilt für beide Engines.
    if (testInfo.project.name === 'chromium') {
      const vorher = await tl.allInnerTexts()
      await page.evaluate(() => {
        const items = document.querySelectorAll('.pw-geschaeft-detail .pw-timeline-eintrag')
        const dt = new DataTransfer()
        const mk = (type) => new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt })
        items[0].dispatchEvent(mk('dragstart'))
        items[1].dispatchEvent(mk('dragover'))
        items[1].dispatchEvent(mk('drop'))
        items[0].dispatchEvent(mk('dragend'))
      })
      await page.waitForTimeout(400)
      const nachher = await tl.allInnerTexts()
      expect(nachher.join('|'), 'Umsortieren per Griff hat die Reihenfolge nicht geändert').not.toBe(vorher.join('|'))
    }
  })
})

// ---------------------------------------------------------------------------
test.describe('Verknüpfte Vorstösse am Geschäft', () => {
  test('Block zeigt Haltung, Zuständigkeit und Notizen des verknüpften Vorstosses', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoGeschaefte(page)
    const gTitel = eindeutig('VerknGeschaeft')
    const gId = await apiCreateGeschaeft(page, gTitel)

    // Vorstoss anlegen, Haltung + Zuständigkeit setzen, Notiz ergänzen, mit dem Geschäft verknüpfen.
    const vTitel = eindeutig('VerknVorstoss')
    const vRes = await api(page, 'post', '/vorstoesse', { titel: vTitel })
    expect(vRes.ok(), 'Vorstoss-Anlage fehlgeschlagen').toBeTruthy()
    const vId = (await vRes.json()).id
    const vUpd = await api(page, 'put', `/vorstoesse/${vId}`, { beschluss: 'Unterstützen', zustaendigkeit: 'E2E Zuständige Person', herkunft: 'fremde' })
    expect(vUpd.ok(), 'Vorstoss-Update fehlgeschlagen').toBeTruthy()
    const vNotiz = 'E2E Vorstoss Notiz am Geschäft'
    const nRes = await api(page, 'post', `/vorstoesse/${vId}/notizen`, { text: vNotiz })
    expect(nRes.ok(), 'Vorstoss-Notiz fehlgeschlagen').toBeTruthy()
    const link = await api(page, 'post', `/vorstoesse/${vId}/verknuepfen`, { geschaeftId: String(gId) })
    expect(link.ok(), 'Verknüpfen fehlgeschlagen').toBeTruthy()

    await page.reload()
    await gotoGeschaefte(page)
    await openDetailByTitel(page, gTitel)

    const block = page.locator('.pw-geschaeft-detail .pw-detail-abschnitt', { hasText: 'Verknüpfte Vorstösse' })
    await expect(block, 'Verknüpfte-Vorstösse-Block fehlt').toBeVisible({ timeout: 30_000 })
    await expect(block.locator('.pw-verknuepfter-vorstoss h5', { hasText: vTitel })).toBeVisible()
    await expect(block, 'Haltung fehlt').toContainText('Unterstützen')
    await expect(block, 'Zuständigkeit fehlt').toContainText('E2E Zuständige Person')
    await expect(block, 'Vorstoss-Notiz fehlt').toContainText(vNotiz)
  })
})
