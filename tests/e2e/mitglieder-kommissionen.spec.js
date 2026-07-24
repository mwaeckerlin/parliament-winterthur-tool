import { test, expect } from '@playwright/test'

/**
 * End-to-end browser coverage for the read-only "Mitglieder" and
 * "Kommissionen" views of the parlwin Nextcloud app, driven with Playwright
 * against the real running stack (Nextcloud + DB + API + rendered SPA).
 *
 * Every case below exercises the real user path in a real browser: the cards
 * are rendered from live API data, filters/search/switches are the actual
 * teleported controls, and the assertions read the rendered DOM. Target
 * members/commissions are never hard-coded — each test first reads the real
 * `/mitglieder` resp. `/kommissionen` API through the authenticated browser
 * context and picks a real target from it.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
  u3: { name: process.env.PW_U3 || 'parlwin_mitglied', pass: process.env.PW_P3 || '' },
}

// --- shared helpers -------------------------------------------------------

/** Logs a user in through the Nextcloud login form (same flow as vorstoss spec). */
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

const norm = (s) => (s || '').replace(/\s+/g, ' ').trim()

/** Authenticated JSON GET against the parlwin app API using the page context. */
async function apiGet(page, pfad) {
  const token = await page.evaluate(() => window.OC?.requestToken || '')
  const res = await page.request.get(`${BASE_URL}/index.php/apps/parlwin${pfad}`, {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
  })
  if (!res.ok()) throw new Error(`API ${pfad} → ${res.status()}`)
  return res.json()
}

/** Reads the numeric count badge of the current view. */
async function zaehler(page) {
  return Number(norm(await page.locator('.pw-view-count').first().innerText()))
}

/** Opens the app root and switches to the "Mitglieder" view; waits until ready. */
async function oeffneMitglieder(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByRole('link', { name: 'Mitglieder', exact: true }).click()
  await page.locator('.pw-view-content.pw-mitglieder').waitFor({ state: 'visible', timeout: 30_000 })
  await page.locator('#pw-filter-slot .select').first().waitFor({ state: 'visible', timeout: 30_000 })
  await page.locator('.pw-mitglied-karte').first().waitFor({ state: 'visible', timeout: 30_000 })
}

/** Opens the app root and switches to the "Kommissionen" view; waits until the spinner is gone. */
async function oeffneKommissionen(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByRole('link', { name: 'Kommissionen', exact: true }).click()
  await page.locator('.pw-view-content.pw-kommissionen').waitFor({ state: 'visible', timeout: 30_000 })
  await page.locator('.pw-laden').waitFor({ state: 'hidden', timeout: 30_000 }).catch(() => {})
  await page.locator('#pw-filter-slot .checkbox-radio-switch').first().waitFor({ state: 'visible', timeout: 30_000 })
  await page.locator('.pw-kommission-karte').first().waitFor({ state: 'visible', timeout: 30_000 })
}

// NcSelect helpers: the select roots carry the `select` class and hold a
// `label.select__label` with the input label; options are teleported to body
// as `.vs__dropdown-menu > .vs__dropdown-option[role=option]`.
function ncBox(page, labelText) {
  return page.locator('#pw-filter-slot .select').filter({
    has: page.locator('label.select__label', { hasText: labelText }),
  })
}

async function ncOeffnen(page, labelText) {
  // The select sits in the left nav sidebar, where nav links overlap the
  // control and swallow mouse clicks (the topmost "Sortieren nach" select never
  // opened via click/mousedown). vue-select opens on ArrowDown while the search
  // input is focused — a keyboard path that no overlay intercepts. Confirm the
  // open via the `vs--open` class; do NOT chain clicks (a later one re-closes).
  const sel = ncBox(page, labelText)
  const menu = page.locator('.vs__dropdown-menu').first()
  const such = sel.locator('.vs__search').first()
  await such.scrollIntoViewIfNeeded().catch(() => {})
  await such.focus()
  await page.keyboard.press('ArrowDown')
  try {
    await expect(sel).toHaveClass(/vs--open/, { timeout: 10_000 })
  } catch {
    // Fallback: a real mouse click at the toggle's bounding-box centre.
    const bb = await sel.locator('.vs__dropdown-toggle').boundingBox()
    if (bb) await page.mouse.click(bb.x + bb.width / 2, bb.y + bb.height / 2)
    await expect(sel).toHaveClass(/vs--open/, { timeout: 10_000 })
  }
  await menu.waitFor({ state: 'visible', timeout: 10_000 })
  await menu.locator('.vs__dropdown-option').first().waitFor({ state: 'visible', timeout: 10_000 })
  return menu
}

/**
 * Full option labels of the open menu, in DOM order. NcEllipsisedOption splits
 * long labels into two spans (a newline in the raw text), but keeps the full
 * label in `.name-parts[title]`, so read that attribute — never the raw text.
 */
async function optTitel(menu) {
  const roh = await menu.locator('.vs__dropdown-option').evaluateAll((els) => els.map((el) => {
    const np = el.querySelector('.name-parts')
    return (np && np.getAttribute('title')) || el.textContent || ''
  }))
  return roh.map(norm)
}

/** Picks an option by its exact full label. */
async function ncWaehlenExakt(page, labelText, optionText) {
  const menu = await ncOeffnen(page, labelText)
  const idx = (await optTitel(menu)).indexOf(optionText)
  if (idx < 0) throw new Error(`Option «${optionText}» in «${labelText}» nicht gefunden`)
  await menu.locator('.vs__dropdown-option').nth(idx).click()
  await menu.waitFor({ state: 'hidden', timeout: 10_000 }).catch(() => {})
}

/** Picks the option at `idx` (0 = the "Alle …" reset entry) and returns its full label. */
async function ncWaehlenIndex(page, labelText, idx) {
  const menu = await ncOeffnen(page, labelText)
  const txt = (await optTitel(menu))[idx]
  await menu.locator('.vs__dropdown-option').nth(idx).click()
  await menu.waitFor({ state: 'hidden', timeout: 10_000 }).catch(() => {})
  return txt
}

/** The NcCheckboxRadioSwitch renders a real checkbox; targets it via its label. */
function switchCheckbox(page, name) {
  return page.locator('#pw-filter-slot').getByRole('checkbox', { name, exact: true })
}

async function switchUmschalten(page, text) {
  await page.locator('#pw-filter-slot .checkbox-radio-switch', { hasText: text })
    .locator('.checkbox-radio-switch__content').first().click()
}

/** Reads the rendered "Fraktion" value of every visible member card (null if absent). */
async function karteFraktionen(page) {
  return page.$$eval('.pw-mitglied-karte', (cards) => cards.map((c) => {
    const pair = [...c.querySelectorAll('.pw-data-pair')]
      .find((p) => p.querySelector('span')?.textContent.trim() === 'Fraktion')
    return pair ? pair.querySelector('strong').textContent.replace(/\s+/g, ' ').trim() : null
  }))
}

/** Expands every currently rendered Kommission card. */
async function alleAufklappen(page) {
  const n = await page.locator('.pw-kommission-karte').count()
  for (let i = 0; i < n; i++) {
    const karte = page.locator('.pw-kommission-karte').nth(i)
    if (await karte.locator('.pw-kommission-details').count() === 0) {
      await karte.locator('.pw-kommission-kopf').click()
      await karte.locator('.pw-kommission-details').waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {})
    }
  }
}

/**
 * Computes the expected display order for a sort mode inside the page's own JS
 * engine, so the collation matches the component exactly (no Node/Firefox drift).
 * Replicates App.vue's member pre-sort and the view comparator faithfully.
 */
async function erwarteteReihenfolge(page, members, modus) {
  return page.evaluate(({ members, modus }) => {
    const key = (m) => `${m.vorname || ''} ${m.name || ''}`.trim()
    const app = [...members].sort((a, b) => {
      const aa = a.aktiv !== false; const ba = b.aktiv !== false
      if (aa !== ba) return aa ? -1 : 1
      return key(a).localeCompare(key(b))
    })
    const basis = app.filter((m) => m.aktiv)
    const nameKey = (m) => `${m.name} ${m.vorname}`
    const cmpName = (a, b) => nameKey(a).localeCompare(nameKey(b), 'de')
    const cmpPartei = (a, b) => (a.partei || '').localeCompare(b.partei || '', 'de')
    const cmpFraktion = (a, b) => (a.fraktion || '').localeCompare(b.fraktion || '', 'de')
    let cmp = cmpName
    if (modus === 'partei') cmp = (a, b) => cmpPartei(a, b) || cmpName(a, b)
    else if (modus === 'fraktion') cmp = (a, b) => cmpFraktion(a, b) || cmpName(a, b)
    return [...basis].sort(cmp).map((m) => `${m.vorname || ''} ${m.name || ''}`.replace(/\s+/g, ' ').trim())
  }, { members, modus })
}

// =========================================================================
// Mitglieder
// =========================================================================
test.describe('Mitglieder: Ansicht end-to-end', () => {
  let jsFehler
  test.beforeEach(({ page }) => {
    jsFehler = []
    page.on('pageerror', (e) => jsFehler.push(e.message))
  })
  test.beforeAll(() => {
    expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
  })

  test('Karten zeigen Name, Partei, Fraktion, Fraktionsrolle, Kommission und E-Mail; Zähler = Kartenzahl', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)

    const badge = await zaehler(page)
    const cards = await page.locator('.pw-mitglied-karte').count()
    expect(cards, 'Zähler-Badge weicht von der Kartenzahl ab').toBe(badge)
    expect(badge).toBeGreaterThan(0)

    const erste = page.locator('.pw-mitglied-karte').first()
    await expect(erste.locator('.pw-mitglied-kopftext > strong')).not.toHaveText('')
    await expect(erste.locator('.pw-mitglied-partei')).toBeVisible()
    await expect(erste.locator('.pw-data-pair', { hasText: 'Fraktion' })).toBeVisible()

    // The four richer fields must render for at least some members.
    expect(await page.locator('a.pw-mitglied-email[href^="mailto:"]').count(), 'keine mailto-E-Mail gerendert').toBeGreaterThan(0)
    expect(await page.locator('.pw-mitglied-fraktionsrolle').count(), 'keine Fraktionsrolle gerendert').toBeGreaterThan(0)
    expect(await page.locator('.pw-mitglied-kommissionen').count(), 'kein Kommissionen-Block gerendert').toBeGreaterThan(0)
    expect(norm(await page.locator('.pw-mitglied-kommission-name').first().textContent())).not.toBe('')

    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('«Nur aktive Mitglieder» ist Standard an; Ausschalten zeigt ehemalige und erhöht den Zähler', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const api = await apiGet(page, '/mitglieder')
    const aktivAnzahl = api.filter((m) => m.aktiv).length

    await expect(switchCheckbox(page, 'Nur aktive Mitglieder')).toBeChecked()
    await expect(page.locator('.pw-mitglied-karte.inaktiv')).toHaveCount(0)
    await expect(page.locator('.pw-badge.inaktiv')).toHaveCount(0)
    expect(await zaehler(page), 'Standardzähler entspricht nicht der Zahl aktiver Mitglieder').toBe(aktivAnzahl)

    await switchUmschalten(page, 'Nur aktive Mitglieder')
    await expect(switchCheckbox(page, 'Nur aktive Mitglieder')).not.toBeChecked()
    await expect.poll(async () => await zaehler(page)).toBe(api.length)

    // A seeded inactive member exists, so ehemalige are always revealed here.
    expect(api.length, 'Kein inaktives Mitglied in den Daten (Seed fehlt)').toBeGreaterThan(aktivAnzahl)
    await expect(page.locator('.pw-badge.inaktiv', { hasText: 'Ehemaliges Mitglied' }).first()).toBeVisible()
    expect(await page.locator('.pw-mitglied-karte.inaktiv').count()).toBeGreaterThan(0)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Sortierung: Standard Funktion (Präsidium zuerst), dann Name, Fraktion, Partei', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const api = await apiGet(page, '/mitglieder')

    // Default is "Funktion".
    await expect(ncBox(page, 'Sortieren').locator('.vs__selected')).toContainText('Funktion')

    // Präsidium first: Fraktionspräsident (rank 0) before Vize (1) before the rest (2).
    const rollen = await page.$$eval('.pw-mitglied-karte', (cards) => cards.map((c) => {
      const el = c.querySelector('.pw-mitglied-fraktionsrolle')
      return el ? el.textContent.trim() : ''
    }))
    expect(rollen.includes('Fraktionspräsident'), 'Kein Fraktionspräsident in den Daten').toBeTruthy()
    const rang = (r) => (r === 'Fraktionspräsident' ? 0 : r === 'Vize Fraktionspräsident' ? 1 : 2)
    const raenge = rollen.map(rang)
    for (let i = 1; i < raenge.length; i++) {
      expect(raenge[i], 'Funktions-Sortierung nicht monoton (Präsidium zuerst)').toBeGreaterThanOrEqual(raenge[i - 1])
    }

    // Name / Fraktion / Partei: exact DOM order against browser-computed expectation.
    for (const modus of ['name', 'fraktion', 'partei']) {
      const label = modus === 'name' ? 'Name' : modus === 'fraktion' ? 'Fraktion' : 'Partei'
      await ncWaehlenExakt(page, 'Sortieren', label)
      await expect(ncBox(page, 'Sortieren').locator('.vs__selected')).toContainText(label)
      await page.waitForTimeout(80)
      const erwartet = await erwarteteReihenfolge(page, api, modus)
      const domNamen = (await page.$$eval('.pw-mitglied-karte .pw-mitglied-kopftext > strong', (els) => els.map((e) => e.textContent)))
        .map(norm)
      expect(domNamen, `Sortierung nach ${label} weicht ab`).toEqual(erwartet)
    }
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Filter Fraktion und Partei grenzen ein; Optionsliste enthält nur aktive Werte', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const api = await apiGet(page, '/mitglieder')
    const active = api.filter((m) => m.aktiv)
    const base = await zaehler(page)

    const fWahl = await ncWaehlenIndex(page, 'Fraktion', 1)
    await expect.poll(async () => await zaehler(page)).toBeLessThanOrEqual(base)
    expect(await zaehler(page)).toBeGreaterThan(0)
    expect((await karteFraktionen(page)).every((v) => v === fWahl), 'Nicht alle Karten haben die gewählte Fraktion').toBeTruthy()
    await ncWaehlenIndex(page, 'Fraktion', 0)

    const pWahl = await ncWaehlenIndex(page, 'Partei', 1)
    expect(await zaehler(page)).toBeGreaterThan(0)
    const pk = await page.$$eval('.pw-mitglied-karte .pw-mitglied-partei', (els) => els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()))
    expect(pk.every((v) => v === pWahl), 'Nicht alle Karten haben die gewählte Partei').toBeTruthy()
    await ncWaehlenIndex(page, 'Partei', 0)

    // Option count equals the number of distinct active Fraktionen (+ "Alle").
    const menu = await ncOeffnen(page, 'Fraktion')
    const optCount = await menu.locator('.vs__dropdown-option').count()
    await page.keyboard.press('Escape')
    const distinktAktiv = new Set(active.map((m) => m.fraktion).filter(Boolean)).size
    expect(optCount - 1, 'Fraktion-Optionen enthalten nicht nur aktive Werte').toBe(distinktAktiv)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Filter Kommission, Funktion=Fraktionspräsident (ohne Vize) und Funktion=Kommissionspräsident', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const base = await zaehler(page)

    const kWahl = await ncWaehlenIndex(page, 'Kommission', 1)
    await expect.poll(async () => await zaehler(page)).toBeLessThanOrEqual(base)
    expect(await page.locator('.pw-mitglied-karte').count()).toBeGreaterThan(0)
    const kTreffer = await page.$$eval('.pw-mitglied-karte', (cards, name) => cards.map((c) =>
      [...c.querySelectorAll('.pw-mitglied-kommission-name')].map((n) => n.textContent.trim()).includes(name),
    ), kWahl)
    expect(kTreffer.every(Boolean), 'Nicht alle Karten enthalten die gewählte Kommission').toBeTruthy()
    await ncWaehlenIndex(page, 'Kommission', 0)

    await ncWaehlenExakt(page, 'Funktion', 'Fraktionspräsident')
    const fpCount = await page.locator('.pw-mitglied-karte').count()
    expect(fpCount, 'Kein Fraktionspräsident gefiltert').toBeGreaterThan(0)
    const fpRollen = await page.$$eval('.pw-mitglied-karte .pw-mitglied-fraktionsrolle', (els) => els.map((e) => e.textContent.trim()))
    expect(fpRollen.length, 'Nicht jede Karte trägt eine Fraktionsrolle').toBe(fpCount)
    expect(fpRollen.every((r) => r === 'Fraktionspräsident'), 'Vize wird nicht ausgeschlossen').toBeTruthy()
    await ncWaehlenExakt(page, 'Funktion', 'Alle Funktionen')

    await ncWaehlenExakt(page, 'Funktion', 'Kommissionspräsident')
    const kpCount = await page.locator('.pw-mitglied-karte').count()
    expect(kpCount, 'Kein Kommissionspräsident gefiltert').toBeGreaterThan(0)
    const alleSindPraes = await page.$$eval('.pw-mitglied-karte', (cards) => cards.every((c) =>
      [...c.querySelectorAll('.pw-mitglied-kommission-rolle')].some((r) => r.textContent.trim() === 'Präsident'),
    ))
    expect(alleSindPraes, 'Nicht alle gefilterten Karten sind Kommissionspräsident').toBeTruthy()
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Suche über Name/Partei/Fraktion/E-Mail grenzt ein; ohne Treffer Leermeldung; ✕ setzt zurück', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const base = await zaehler(page)
    const api = await apiGet(page, '/mitglieder')
    const active = api.filter((m) => m.aktiv)
    const ziel = active.find((m) => m.email && m.fraktion && m.partei) || active.find((m) => m.email) || active[0]
    expect(ziel, 'Kein aktives Mitglied vorhanden').toBeTruthy()
    const zielKarte = () => page.locator('.pw-mitglied-karte', { hasText: `${ziel.vorname || ''} ${ziel.name || ''}`.trim() }).first()
    const such = page.locator('#pw-search-slot input').first()

    // Name (search matches "Nachname Vorname" → search by surname).
    await such.fill(ziel.name)
    await expect.poll(async () => await zaehler(page)).toBeLessThanOrEqual(base)
    expect(await zaehler(page)).toBeGreaterThan(0)
    await expect(zielKarte()).toBeVisible()

    // E-Mail, Fraktion, Partei each find the target.
    for (const term of [ziel.email, ziel.fraktion, ziel.partei].filter(Boolean)) {
      await such.fill(term)
      await expect(zielKarte()).toBeVisible()
    }

    // No hit → empty state and zero count.
    await such.fill(`zzz-keintreffer-${Date.now()}`)
    await expect(page.locator('.pw-view-count').first()).toHaveText('0')
    await expect(page.getByText('Keine Mitglieder gefunden')).toBeVisible()

    // Trailing ✕ clears and restores the full list.
    await page.locator('#pw-search-slot button').first().click()
    await expect(such).toHaveValue('')
    await expect.poll(async () => await zaehler(page)).toBe(base)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Randfälle: ohne Fraktion «—», ohne Partei «Ohne Partei», ohne Kommission kein Block (kein JS-Fehler)', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneMitglieder(page)
    const api = await apiGet(page, '/mitglieder')
    const active = api.filter((m) => m.aktiv)

    // Cross-check the rendered fallback strings against the real member data.
    const karten = await page.$$eval('.pw-mitglied-karte', (cards) => cards.map((c) => {
      const pair = [...c.querySelectorAll('.pw-data-pair')]
        .find((p) => p.querySelector('span')?.textContent.trim() === 'Fraktion')
      return {
        name: c.querySelector('.pw-mitglied-kopftext > strong')?.textContent.replace(/\s+/g, ' ').trim() || '',
        partei: c.querySelector('.pw-mitglied-partei')?.textContent.replace(/\s+/g, ' ').trim() || '',
        fraktion: pair ? pair.querySelector('strong').textContent.replace(/\s+/g, ' ').trim() : null,
        hatKommission: !!c.querySelector('.pw-mitglied-kommissionen'),
      }
    }))

    for (const card of karten) {
      const m = active.find((x) => norm(`${x.vorname || ''} ${x.name || ''}`) === card.name)
      if (!m) continue
      if (!m.partei) expect(card.partei, `«Ohne Partei» fehlt bei ${card.name}`).toBe('Ohne Partei')
      else expect(card.partei, `«Ohne Partei» fälschlich bei ${card.name}`).not.toBe('Ohne Partei')
      if (!m.fraktion) expect(card.fraktion, `«—» fehlt bei ${card.name}`).toBe('—')
      else expect(card.fraktion, `«—» fälschlich bei ${card.name}`).not.toBe('—')
    }

    // Members without a Kommission simply omit the block; the view stays error-free.
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Kürzel: konfigurierte Partei- und Fraktions-Kürzel erscheinen in Karten und Fraktion-Filter', async ({ page }) => {
    // Der e2e-Stack setzt das Admin-Passwort und synchronisiert echte Daten —
    // beides ist Voraussetzung dieses Tests und wird hart geprüft, nie
    // übersprungen.
    expect(process.env.PW_ADMIN_PASS, 'Admin-Passwort (PW_ADMIN_PASS) nicht gesetzt').toBeTruthy()
    await login(page, { name: 'admin', pass: process.env.PW_ADMIN_PASS })
    await oeffneMitglieder(page)
    const api = await apiGet(page, '/mitglieder')
    const active = api.filter((m) => m.aktiv)
    const partei = (active.find((m) => m.partei) || {}).partei
    const fraktion = (active.find((m) => m.fraktion
      && (!partei || (!m.fraktion.includes(partei) && !partei.includes(m.fraktion)))) || {}).fraktion
    expect(partei || fraktion, 'Weder Partei noch Fraktion in den synchronisierten Daten').toBeTruthy()

    // Short unique markers (<10 chars) so NcEllipsisedOption does not split them.
    const stamp = String(Date.now()).slice(-5)
    const parteiKrz = `PKZ${stamp}`
    const fraktionKrz = `FKZ${stamp}`
    const regeln = []
    if (partei) regeln.push({ suche: partei, kuerzel: parteiKrz })
    if (fraktion) regeln.push({ suche: fraktion, kuerzel: fraktionKrz })

    const setzeKuerzel = async (liste) => {
      const token = await page.evaluate(() => window.OC?.requestToken || '')
      return page.request.post(`${BASE_URL}/index.php/apps/parlwin/settings/status-kuerzel`, {
        headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
        data: JSON.stringify({ status_kuerzel: liste }),
      })
    }

    try {
      expect((await setzeKuerzel(regeln)).ok(), 'Kürzel speichern fehlgeschlagen').toBeTruthy()
      // Reload so window.PARLWIN_CONFIG carries the new shortenings.
      await oeffneMitglieder(page)

      if (partei) {
        await expect(page.locator('.pw-mitglied-partei', { hasText: parteiKrz }).first(),
          'Partei-Kürzel erscheint auf keiner Karte').toBeVisible()
      }
      if (fraktion) {
        expect((await karteFraktionen(page)).includes(fraktionKrz), 'Fraktions-Kürzel erscheint auf keiner Karte').toBeTruthy()
        const menu = await ncOeffnen(page, 'Fraktion')
        await expect(menu.locator(`.name-parts[title="${fraktionKrz}"]`).first(),
          'Fraktions-Kürzel fehlt als Filter-Option').toBeVisible()
        await page.keyboard.press('Escape')
      }
    } finally {
      await setzeKuerzel([]).catch(() => {})
    }
  })
})

// =========================================================================
// Kommissionen
// =========================================================================
test.describe('Kommissionen: Ansicht end-to-end', () => {
  let jsFehler
  test.beforeEach(({ page }) => {
    jsFehler = []
    page.on('pageerror', (e) => jsFehler.push(e.message))
  })
  test.beforeAll(() => {
    expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
  })

  test('Karten laden mit Name und Status; Zähler = Kartenzahl; mindestens fünf Kommissionen', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)
    const koms = await apiGet(page, '/kommissionen')
    expect(koms.length, 'Weniger als fünf Kommissionen synchronisiert').toBeGreaterThanOrEqual(5)

    const badge = await zaehler(page)
    const cards = await page.locator('.pw-kommission-karte').count()
    expect(cards).toBe(badge)
    expect(badge).toBeGreaterThan(0)

    await expect(page.locator('.pw-kommission-karte').first().locator('h3')).not.toHaveText('')
    const stati = await page.$$eval('.pw-kommission-karte .pw-kommission-status', (els) => els.map((e) => e.textContent.trim()))
    expect(stati.every((s) => s === 'Aktiv'), 'Nicht alle Standard-Karten sind aktiv').toBeTruthy()
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Aufklappen zeigt Mitglieder mit Funktion/Partei/Fraktion/E-Mail; Toggle-Glyph kippt; Zuklappen', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)

    // Find a card that actually has members by expanding candidates.
    const karten = page.locator('.pw-kommission-karte')
    const n = await karten.count()
    let ziel = null
    for (let i = 0; i < n; i++) {
      const k = karten.nth(i)
      await k.locator('.pw-kommission-kopf').click()
      await k.locator('.pw-kommission-details').waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {})
      if (await k.locator('.pw-kommission-mitglied-karte').count() > 0) { ziel = k; break }
      await k.locator('.pw-kommission-kopf').click()
    }
    expect(ziel, 'Keine Kommission mit Mitgliedern gefunden').not.toBeNull()

    await expect(ziel.locator('.pw-toggle')).toHaveText('▲')
    await expect(ziel.locator('.pw-kommission-mitglied-karte').first().locator('.pw-kommission-mitglied-kopf strong')).not.toHaveText('')

    // The four member field types must render somewhere across all commissions.
    await alleAufklappen(page)
    expect(await page.locator('.pw-kommission-mitglied-rolle').count(), 'keine Funktion angezeigt').toBeGreaterThan(0)
    expect(await page.locator('.pw-kommission-mitglied-email[href^="mailto:"]').count(), 'keine E-Mail angezeigt').toBeGreaterThan(0)
    expect(await page.locator('.pw-kommission-mitglied-zeile:not(.pw-kommission-mitglied-kopf):not(.pw-kommission-mitglied-email)').count(),
      'keine Partei-/Fraktionszeile angezeigt').toBeGreaterThan(0)

    // Collapse: glyph flips back and details are removed.
    await ziel.locator('.pw-kommission-kopf').click()
    await expect(ziel.locator('.pw-toggle')).toHaveText('▼')
    await expect(ziel.locator('.pw-kommission-details')).toHaveCount(0)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Eigene Fraktion ist hervorgehoben und konsistent zur Fraktion des angemeldeten Nutzers', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)
    const api = await apiGet(page, '/mitglieder')
    const self = api.find((m) => String(m.nextcloudUid || m.nextcloud_uid || '').toLowerCase() === USERS.u1.name.toLowerCase())
    const eigeneFraktion = self ? (self.fraktion || '') : ''

    await alleAufklappen(page)

    const zeilenVon = (sel) => page.$$eval(sel, (els) => els.map((c) =>
      [...c.querySelectorAll('.pw-kommission-mitglied-zeile:not(.pw-kommission-mitglied-kopf):not(.pw-kommission-mitglied-email)')]
        .map((z) => z.textContent.trim())))

    const eigenCards = await zeilenVon('.pw-kommission-mitglied-karte.pw-kommission-mitglied-eigen')
    // Every highlighted card must show the user's own Fraktion.
    for (const zeilen of eigenCards) {
      expect(zeilen, 'Hervorgehobenes Mitglied gehört nicht zur eigenen Fraktion').toContain(eigeneFraktion)
    }

    if (eigeneFraktion) {
      const alleZeilen = await zeilenVon('.pw-kommission-mitglied-karte')
      const kommtVor = alleZeilen.some((zs) => zs.includes(eigeneFraktion))
      if (kommtVor) expect(eigenCards.length, 'Eigene Fraktion kommt vor, ist aber nicht hervorgehoben').toBeGreaterThan(0)
    } else {
      expect(eigenCards.length, 'Hervorhebung ohne bekannte eigene Fraktion').toBe(0)
    }
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Pendentes Geschäft öffnet die Detailansicht; der externe ↗-Link öffnet sie NICHT', async ({ page }) => {
    await login(page, USERS.u1)

    // Die von der Kommissionsliste tatsächlich geladene /geschaefte-Antwort
    // festhalten, um zu sehen, ob das seeded «Gemischte»-Geschäft im Browser
    // ankommt (die API-Diagnose im Runner bestätigt es serverseitig).
    const geladeneGeschaefte = []
    page.on('response', async (resp) => {
      if (/\/apps\/parlwin\/geschaefte(?:\?|$)/.test(resp.url()) && resp.request().method() === 'GET') {
        try { geladeneGeschaefte.push(await resp.json()) } catch (_e) { /* ignore */ }
      }
    })

    // The pendente-Geschäfte list renders only inside an expanded card and is fed
    // by an asynchronous `/geschaefte` load; a pendent Geschäft on "E2E Gemischte
    // Kommission" is seeded (guaranteed via the API), so it MUST appear. If the
    // first attempt's async load didn't complete, one fresh reload forces a new
    // load. It never skips — if still empty it fails.
    const eintrag = page.locator('.pw-kommission-geschaeft-eintrag')
    const ladeUndFinde = async () => {
      await alleAufklappen(page)
      // Vorbedingung: das seeded «Gemischte»-Geschäft muss in die Kommissionsliste
      // geladen worden sein (es überlebt den Sync, weil eigene Geschäfte davon
      // ausgenommen sind). Fehlt es, kann der Eintrag nie erscheinen.
      const letzte = geladeneGeschaefte[geladeneGeschaefte.length - 1] || []
      const gem = letzte.filter((g) => String(g.status || '').includes('Gemischte')).map((g) => `${g.id}=${g.status}`)
      expect(
        gem.length,
        `Das seeded «Gemischte»-Geschäft fehlt in der Kommissionsliste (${letzte.length} Geschäfte geladen: ${JSON.stringify(gem)})`,
      ).toBeGreaterThan(0)
      await expect.poll(async () => await eintrag.count(), {
        timeout: 25_000,
        message: 'Kein pendentes Kommissions-Geschäft (kein .pw-kommission-geschaeft-eintrag) gefunden',
      }).toBeGreaterThan(0)
    }
    await oeffneKommissionen(page)
    try {
      await ladeUndFinde()
    } catch (_e) {
      await oeffneKommissionen(page)
      await ladeUndFinde()
    }

    // Clicking the entry opens the GeschaeftDetail modal.
    await eintrag.first().locator('.pw-titel').click()
    await expect(page.locator('.pw-modal-overlay')).toBeVisible()
    await expect(page.locator('.pw-modal-overlay .pw-modal')).toBeVisible()
    await page.locator('.pw-modal-overlay .pw-btn-schliessen').click()
    await expect(page.locator('.pw-modal-overlay')).toHaveCount(0)

    // The extern ↗ link uses @click.stop → must NOT open the detail (may open a popup tab).
    const link = page.locator('.pw-kommission-geschaeft-eintrag a.pw-inline-link').first()
    if (await link.count() > 0) {
      const [popup] = await Promise.all([
        page.context().waitForEvent('page').catch(() => null),
        link.click(),
      ])
      await page.waitForTimeout(400)
      await expect(page.locator('.pw-modal-overlay'), 'Extern-Link öffnete fälschlich die Detailansicht').toHaveCount(0)
      if (popup) await popup.close().catch(() => {})
    }
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Zwei Schalter Standard an; «Nur aktive Kommissionen» aus zeigt inaktive; «Nur aktive Mitglieder» aus zeigt «/ N aktiv»', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)
    const koms = await apiGet(page, '/kommissionen')
    const heute = new Date().toISOString().slice(0, 10)
    const istAktiv = (k) => {
      if (!k || k.geloescht === true) return false
      if (k.aktiv === false) return false
      const bis = k.datumBis || ''
      return !(bis && bis < heute)
    }
    const inaktiveKom = koms.filter((k) => !istAktiv(k)).length
    const hatInaktivM = (k) => {
      let a
      try { a = typeof k.mitglieder === 'string' ? JSON.parse(k.mitglieder) : k.mitglieder } catch { return false }
      return Array.isArray(a) && a.some((e) => e && typeof e === 'object' && e.aktiv === false)
    }
    const inaktiveMKom = koms.some(hatInaktivM)

    // Defaults: both switches on, no inactive commission card.
    await expect(switchCheckbox(page, 'Nur aktive Kommissionen')).toBeChecked()
    await expect(switchCheckbox(page, 'Nur aktive Mitglieder')).toBeChecked()
    await expect(page.locator('.pw-kommission-karte.ist-inaktiv')).toHaveCount(0)
    const aktivKom = await zaehler(page)

    // Turn off "Nur aktive Kommissionen": all commissions show; inactive ones appear.
    await switchUmschalten(page, 'Nur aktive Kommissionen')
    await expect(switchCheckbox(page, 'Nur aktive Kommissionen')).not.toBeChecked()
    await expect.poll(async () => await zaehler(page)).toBe(koms.length)
    if (inaktiveKom > 0) {
      const inaktivCard = page.locator('.pw-kommission-karte.ist-inaktiv').first()
      await expect(inaktivCard).toBeVisible()
      await inaktivCard.locator('.pw-kommission-kopf').click()
      await expect(inaktivCard.locator('.pw-kommission-status')).toHaveText('Aufgelöst oder inaktiv')
      await expect(inaktivCard.locator('.pw-inline-note')).toBeVisible()
    }
    // Back on.
    await switchUmschalten(page, 'Nur aktive Kommissionen')
    await expect.poll(async () => await zaehler(page)).toBe(aktivKom)

    // Turn off "Nur aktive Mitglieder": the "/ N aktiv" suffix appears where inactive members exist.
    await switchUmschalten(page, 'Nur aktive Mitglieder')
    await expect(switchCheckbox(page, 'Nur aktive Mitglieder')).not.toBeChecked()
    await alleAufklappen(page)
    const suffix = page.locator('.pw-kommission-mitglieder strong', { hasText: /\/\s*\d+\s*aktiv/ })
    if (inaktiveMKom) {
      expect(await suffix.count(), 'Kein «/ N aktiv»-Suffix trotz inaktiver Mitglieder').toBeGreaterThan(0)
      const block = page.locator('.pw-kommission-mitglieder')
        .filter({ has: page.locator('strong', { hasText: /\/\s*\d+\s*aktiv/ }) }).first()
      const flags = await block.locator('.pw-kommission-mitglied-karte')
        .evaluateAll((els) => els.map((e) => e.classList.contains('pw-kommission-mitglied-inaktiv')))
      const ersterInaktiv = flags.indexOf(true)
      const letzterAktiv = flags.lastIndexOf(false)
      if (ersterInaktiv !== -1 && letzterAktiv !== -1) {
        expect(ersterInaktiv, 'Inaktive Mitglieder stehen nicht nach den aktiven').toBeGreaterThan(letzterAktiv)
      }
    }
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Suche klappt Treffer-Karten automatisch auf; ohne Treffer Leermeldung; ✕ setzt zurück', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)
    const koms = await apiGet(page, '/kommissionen')
    const heute = new Date().toISOString().slice(0, 10)
    const istAktiv = (k) => k && k.geloescht !== true && k.aktiv !== false && !((k.datumBis || '') && (k.datumBis < heute))
    const ziel = koms.find(istAktiv) || koms[0]
    const base = await zaehler(page)
    const such = page.locator('#pw-search-slot input').first()
    const token = (ziel.name || '').split(/\s+/).filter((w) => w.length >= 5)[0] || ziel.name

    await such.fill(token)
    await expect.poll(async () => await zaehler(page)).toBeLessThanOrEqual(base)
    const treffer = page.locator('.pw-kommission-karte', { hasText: token }).first()
    await expect(treffer).toBeVisible()
    await expect(treffer.locator('.pw-kommission-details'), 'Treffer-Karte wird nicht automatisch aufgeklappt').toBeVisible()

    await such.fill(`zzz-keintreffer-${Date.now()}`)
    await expect(page.locator('.pw-view-count').first()).toHaveText('0')
    await expect(page.getByText('Keine Kommissionen gefunden')).toBeVisible()

    await page.locator('#pw-search-slot button').first().click()
    await expect(such).toHaveValue('')
    await expect.poll(async () => await zaehler(page)).toBe(base)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })

  test('Hinweise: ohne Mitglieder «Keine Mitglieder synchronisiert», ohne pendente Geschäfte der Geschäfte-Hinweis', async ({ page }) => {
    await login(page, USERS.u1)
    await oeffneKommissionen(page)
    await alleAufklappen(page)

    const details = page.locator('.pw-kommission-karte .pw-kommission-details')
    const dn = await details.count()
    expect(dn).toBeGreaterThan(0)

    let ohneGeschaefte = 0
    for (let i = 0; i < dn; i++) {
      const d = details.nth(i)
      if (await d.locator('.pw-kommission-mitglied-karte').count() === 0) {
        await expect(d.getByText('Keine Mitglieder synchronisiert.')).toBeVisible()
      }
      if (await d.locator('.pw-kommission-geschaeft-eintrag').count() === 0) {
        await expect(d.locator('.pw-hinweis', { hasText: 'Keine Geschäfte mit Status' })).toBeVisible()
        ohneGeschaefte++
      }
    }
    expect(ohneGeschaefte, 'Kein Geschäfte-Hinweis exerziert').toBeGreaterThan(0)
    expect(jsFehler, jsFehler.join(' | ')).toEqual([])
  })
})
