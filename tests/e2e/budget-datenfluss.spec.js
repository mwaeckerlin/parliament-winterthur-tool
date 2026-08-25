import { test, expect } from '@playwright/test'

/**
 * E2E-Datenfluss des BUDGET-Bereichs im echten Browser gegen das reale System
 * (Nextcloud + DB + API + der aus den Büchern erzeugten reference/budget-Daten).
 *
 * Deckt F74–F93 ab: Budget-Ansicht mit vier Tabs, Filterleiste (Budgetjahr,
 * Departement/Kommission, Kostensteigerung), sticky Summenzeile, Globalbudget-
 * Anträge auf Produktegruppen + Live-Entscheid, anteilige Verteilung/Automatik,
 * Personalbestand-Anträge, Investitionsrechnung je Projekt, Steuerfuss-Automatik,
 * Import vergangener Jahre («+ Neu») und Anträge-PDF.
 *
 * Jeder Test ist self-contained; das Budgetjahr wird idempotent sichergestellt.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP_URL = `${BASE_URL}/index.php/apps/parlwin/`

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
}

const JAHR = 2026

function eindeutig(prefix) {
  return `E2E-${prefix} ${Date.now()}-${Math.floor(performance.now())}`
}

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

async function reqToken(page) {
  return page.evaluate(() => (window.OC && window.OC.requestToken) || '')
}

async function api(page, method, pfad, form) {
  const token = await reqToken(page)
  const opts = {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded' },
  }
  if (form) opts.form = form
  return page.request[method](`${BASE_URL}/index.php/apps/parlwin${pfad}`, opts)
}

/** Stellt sicher, dass ein Budgetjahr importiert ist (idempotent, aus den Büchern). */
async function sorgeFuerBudgetjahr(page, jahr) {
  const res = await api(page, 'get', '/budget/jahre')
  const jahre = res.ok() ? await res.json() : []
  if (!jahre.some((j) => Number(j.jahr) === jahr)) {
    const imp = await api(page, 'post', `/budget/${jahr}/import`, {})
    expect(imp.ok(), `Budget-Import ${jahr} fehlgeschlagen (${imp.status()})`).toBeTruthy()
  }
}

/** Öffnet die parlwin-App und wechselt in die Budget-Ansicht. */
async function gotoBudget(page) {
  await page.goto(APP_URL)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
  await page.getByRole('link', { name: 'Budget', exact: true }).click()
  await page.waitForSelector('.pw-budget', { timeout: 30_000 })
}

function fehlerWaechter(page) {
  const arr = []
  page.on('pageerror', (e) => arr.push(e.message))
  return arr
}

async function tabOeffnen(page, label) {
  // Vor dem Klick nach oben scrollen: der sticky Summen-Balken darf den Tab nicht
  // überlagern (sonst wird der Klick abgefangen).
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.locator('.pw-budget-tabs .pw-budget-tab', { hasText: label }).click()
}

test.beforeAll(() => {
  expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
})

test.use({ viewport: { width: 1600, height: 1000 } })

// ---------------------------------------------------------------------------
test.describe('Budget: Ansicht, Tabs, Filter, Summen (F74, F75, F76, F77, F79)', () => {
  test('Budget-Ansicht mit vier Tabs, Filterleiste im Nav-Slot und Summenzeile', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // F74: die vier Tabs.
    for (const label of ['Globalbudgets', 'Personalbestand', 'Investitionsrechnung', 'Steuerfuss']) {
      await expect(page.locator('.pw-budget-tabs .pw-budget-tab', { hasText: label }), `Tab "${label}" fehlt`).toBeVisible()
    }

    // F75/F76/F77: Filter liegen im gemeinsamen #pw-filter-slot (kein eigenes Band).
    const filter = page.locator('#pw-filter-slot .pw-filter-body')
    await expect(filter, 'Budget-Filter nicht im gemeinsamen Filter-Slot').toBeVisible()
    await expect(filter.getByLabel('Anstieg ab %'), 'Kostensteigerungs-Filter (%) fehlt').toBeVisible()
    await expect(filter.getByLabel('Anstieg ab CHF'), 'Kostensteigerungs-Filter (CHF) fehlt').toBeVisible()
    await expect(page.locator('.pw-budget .pw-budget-filter'), 'Es darf kein eigenes Budget-Filterband auf der Seite geben').toHaveCount(0)

    // F79: Summenzeile mit allen Kennzahlen (nach dem Laden der Ansicht).
    await page.locator('.pw-budget-tabpanel .pw-data-card').first().waitFor({ state: 'visible', timeout: 30_000 })
    const summen = page.locator('.pw-budget-summen')
    await expect(summen).toBeVisible()
    for (const label of ['Stellen', 'Ausgaben', 'Einnahmen', 'Steuerfuss']) {
      await expect(summen.getByText(label, { exact: true }), `Summe "${label}" fehlt`).toBeVisible()
    }
    await expect(summen.getByText(/Defizit|Ertrag/)).toBeVisible()

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Kostensteigerungs-Filter grenzt die Produktegruppen ein (F77)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const gruppen = page.locator('.pw-budget-tabpanel:visible .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) })
    await gruppen.first().waitFor({ state: 'visible', timeout: 30_000 })
    const vorher = await gruppen.count()
    expect(vorher, 'Keine Produktegruppen sichtbar').toBeGreaterThan(0)

    // Sehr hohe Schwelle → weniger (oder keine) Gruppen.
    await page.locator('#pw-filter-slot').getByLabel('Anstieg ab CHF').fill('999999999')
    await page.waitForLoadState('networkidle')
    await expect
      .poll(async () => gruppen.count(), { timeout: 15_000 })
      .toBeLessThan(vorher)
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Globalbudgets — Anträge und Live-Entscheid (F80, F81, F82, F83, F84, F93)', () => {
  test('Antrag auf eine Produktegruppe stellen und den Entscheid live setzen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // F80: eine Produktegruppen-Karte mit Globalkredit.
    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(karte.locator('.pw-budget-betrag')).toBeVisible()

    // F81/F82: einen Kürzungsantrag stellen.
    const begruendung = eindeutig('Kürzung')
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-50000')
    await karte.locator('.pw-antrag-form').getByLabel('Begründung').fill(begruendung)
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')

    const antrag = karte.locator('.pw-budget-antraege li', { hasText: begruendung }).first()
    await expect(antrag, 'Antrag erscheint nicht an der Produktegruppe').toBeVisible({ timeout: 15_000 })

    // F93: Entscheid «angenommen» setzen (✓) → Status-Klasse wechselt.
    await antrag.locator('.pw-entscheid-knoepfe button', { hasText: '✓' }).click()
    await page.waitForLoadState('networkidle')
    await expect(
      karte.locator('.pw-budget-antraege li', { hasText: begruendung }).locator('.pw-entscheid-angenommen'),
      'Entscheid «angenommen» nicht sichtbar',
    ).toBeVisible({ timeout: 15_000 })

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Produkte und Auftrag erscheinen als Information an der Produktegruppe (F80)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Eine Karte mit Produkte-Information (nicht jede Gruppe hat Produkte).
    const mitProdukten = page.locator('.pw-budget-tabpanel .pw-data-card', {
      has: page.locator('.pw-budget-info summary', { hasText: 'Produkte' }),
    }).first()
    await mitProdukten.waitFor({ state: 'visible', timeout: 30_000 })
    const produkte = mitProdukten.locator('.pw-budget-info', { has: page.locator('summary', { hasText: 'Produkte' }) })
    await produkte.locator('summary').click()
    await expect(produkte.locator('li').first(), 'Kein Produkt als Information gelistet').toBeVisible()

    // Auftragstext-Information ist ebenfalls vorhanden.
    const mitAuftrag = page.locator('.pw-budget-tabpanel .pw-data-card', {
      has: page.locator('.pw-budget-info summary', { hasText: 'Auftrag' }),
    }).first()
    await expect(mitAuftrag, 'Keine Produktegruppe mit Auftragstext').toBeVisible({ timeout: 30_000 })
  })

  test('Automatik-Schalter und Zielmodus der anteiligen Verteilung sind bedienbar (F83, F84)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const verteilung = page.locator('.pw-verteilung')
    await verteilung.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(verteilung.getByText('Defizit automatisch als Pauschalkürzung verteilen')).toBeVisible()
    // Automatik aus → der explizite Verteil-Knopf erscheint. Das Umschalten löst
    // einen Server-Round-Trip (PUT + Reload) aus und ist auf Firefox rennanfällig:
    // deterministisch umschalten — erneut klicken NUR solange der Schalter noch an
    // ist (kein Doppel-Toggle), bis der Knopf da ist.
    const automatik = verteilung.locator('.checkbox-radio-switch__input').first()
    const verteilKnopf = verteilung.getByRole('button', { name: 'Defizit verteilen' })
    await expect.poll(async () => {
      if (await verteilKnopf.count() > 0) { return true }
      if (await automatik.isChecked().catch(() => false)) {
        await verteilung.locator('.checkbox-radio-switch__content').first().click().catch(() => {})
        await page.waitForTimeout(1200)
      }
      return await verteilKnopf.count() > 0
    }, { timeout: 30_000, message: 'Verteil-Knopf erscheint nach Abschalten der Automatik nicht' }).toBe(true)
    await expect(verteilKnopf).toBeVisible()
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Personalbestand und Investitionen (F86, F87)', () => {
  test('Personalbestand zeigt Stellen und nimmt einen Personalantrag an (F86)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Personalbestand')

    const karte = page.locator('.pw-budget-tabpanel:visible .pw-data-card').first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(karte.locator('.pw-budget-betrag', { hasText: 'Stellen' })).toBeVisible()

    // Personalkürzung: Stellen − und Betrag (Vorgabe-Betrag ist vorbelegt).
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-1')
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    await expect(karte.locator('.pw-budget-antraege li').first(), 'Personalantrag erscheint nicht').toBeVisible({ timeout: 15_000 })
  })

  test('Investitionsrechnung listet Projekte mit Budgetwert (F87)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Investitionsrechnung')

    const panel = page.locator('.pw-budget-tabpanel:visible')
    await expect(panel.locator('.pw-budget-dep-titel').first(), 'Kein Departement in der Investitionsrechnung').toBeVisible({ timeout: 30_000 })
    const projekt = panel.locator('.pw-data-card').first()
    await expect(projekt.locator('h3'), 'Kein Projekt sichtbar').toBeVisible()
    await expect(projekt.locator('.pw-budget-betrag')).toBeVisible()

    // Einen Investitionsantrag stellen.
    await projekt.getByRole('button', { name: '+ Antrag' }).click()
    await projekt.locator('.pw-antrag-form input[type="number"]').first().fill('-10000')
    await projekt.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Steuerfuss, Import und PDF (F88, F89, F91, F92)', () => {
  test('Steuerfuss-Tab zeigt geltenden Fuss und Wert je Prozent, Automatik schaltbar (F88)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Steuerfuss')

    const panel = page.locator('.pw-budget-tabpanel:visible .pw-steuerfuss')
    await panel.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(panel.getByText('Geltender Steuerfuss:')).toBeVisible()
    await expect(panel.getByText('1 Steuerprozent')).toBeVisible()
    await expect(panel.getByText('Steuerfuss bei Überschuss automatisch senken')).toBeVisible()

    // Automatik aus (auf das Schalter-Label klicken) → manuelles Feld + Antrag-Knopf.
    await panel.locator('.checkbox-radio-switch__content').first().click()
    await expect(panel.getByRole('button', { name: 'Steuerfuss-Antrag stellen' })).toBeVisible({ timeout: 15_000 })
  })

  test('«+ Neu» öffnet den Import-Dialog mit vorhandenen Jahren (F89, F91)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    await page.getByRole('button', { name: '+ Neu' }).click()
    const modal = page.locator('.pw-modal', { hasText: 'Vergangenes Budgetjahr importieren' })
    await expect(modal, 'Import-Dialog nicht geöffnet').toBeVisible({ timeout: 15_000 })
    // Es gibt ein Jahr-Auswahlfeld ODER den Hinweis, dass keine weiteren Jahre da sind.
    const hatAuswahl = await modal.locator('.v-select').count()
    const hatHinweis = await modal.getByText('Keine weiteren Jahre').count()
    expect(hatAuswahl + hatHinweis, 'Weder Jahr-Auswahl noch Leerhinweis im Import-Dialog').toBeGreaterThan(0)
    await modal.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(modal).toHaveCount(0)
  })

  test('Sitzungsmodus trennt Fraktions- von Sitzungsanträgen (F93)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // In der Vorbereitung einen Fraktionsantrag stellen.
    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    const fraktionText = eindeutig('Fraktion')
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-11000')
    await karte.locator('.pw-antrag-form').getByLabel('Begründung').fill(fraktionText)
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    await expect(karte.locator('.pw-budget-antraege li', { hasText: fraktionText })).toBeVisible({ timeout: 15_000 })

    // Sitzungsmodus einschalten (Schalter per Label-Text) → der Fraktionsantrag
    // verschwindet, die Automatik-Karte auch.
    await page.locator('#pw-filter-slot .checkbox-radio-switch__content').first().click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-verteilung'), 'Automatik-Karte im Sitzungsmodus weiterhin sichtbar').toHaveCount(0)
    await expect(
      page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: fraktionText }),
      'Fraktionsantrag im Sitzungsmodus weiterhin sichtbar',
    ).toHaveCount(0)

    // Im Sitzungsmodus einen offiziellen Sitzungsantrag erfassen.
    const karteS = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karteS.waitFor({ state: 'visible', timeout: 30_000 })
    const sitzungText = eindeutig('Sitzung')
    await karteS.getByRole('button', { name: '+ Antrag' }).click()
    await karteS.locator('.pw-antrag-form input[type="number"]').first().fill('-12000')
    await karteS.locator('.pw-antrag-form').getByLabel('Begründung').fill(sitzungText)
    await karteS.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: sitzungText }), 'Sitzungsantrag nicht sichtbar').toBeVisible({ timeout: 15_000 })

    // Zurück in die Vorbereitung → der Fraktionsantrag ist wieder da, der Sitzungsantrag weg.
    await page.locator('#pw-filter-slot .checkbox-radio-switch__content').first().click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: fraktionText })).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: sitzungText })).toHaveCount(0)
  })

  test('«Anträge als PDF» öffnet die Druckansicht (F92)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const popupPromise = page.waitForEvent('popup', { timeout: 15_000 }).catch(() => null)
    await page.getByRole('button', { name: 'Anträge als PDF' }).click()
    const popup = await popupPromise
    expect(popup, 'PDF-Druckansicht wurde nicht geöffnet').not.toBeNull()
    if (popup) {
      await popup.waitForLoadState('domcontentloaded').catch(() => {})
      await popup.close().catch(() => {})
    }
  })
})

test.describe('Budget: Antragsmodell — Herkunft, Betrag, Haltung, Pauschal, Notizen (F94–F104)', () => {
  test('Antragsform und Pauschal-Bedienung zeigen die neuen Felder und sind bedienbar', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })

    // Das Antragsformular ist lazy — erst über «+ Antrag» einblenden.
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    const form = karte.locator('.pw-antrag-form')
    await form.waitFor({ state: 'visible', timeout: 15_000 })

    // F94/F95/F97/F98: die neuen Formularfelder sind vorhanden.
    await expect(form.getByText('Herkunft', { exact: true }), 'Herkunft-Auswahl fehlt (F94)').toBeVisible()
    await expect(form.getByLabel('Betrag CHF'), 'CHF-Feld fehlt (F95)').toBeVisible()
    await expect(form.getByLabel('Betrag %'), 'Prozent-Feld fehlt (F95)').toBeVisible()
    await expect(form.getByText('Unsere Haltung', { exact: true }), 'Haltung-Auswahl fehlt (F97)').toBeVisible()
    await expect(form.getByText('Unterstützende Fraktionen', { exact: true }), 'Unterstützer-Auswahl fehlt (F98)').toBeVisible()

    // F100: der Einreichen-Entscheid des Pauschalantrags ist bedienbar. Die
    // Automatik kann durch einen früheren Test aus sein → nötigenfalls einschalten
    // (der Schalter erscheint nur bei eingeschalteter Automatik).
    const verteilung = page.locator('.pw-verteilung')
    if (await verteilung.getByText('Pauschalantrag einreichen').count() === 0) {
      await verteilung.locator('.checkbox-radio-switch__content').first().click()
      await page.waitForLoadState('networkidle')
    }
    await expect(verteilung.getByText('Pauschalantrag einreichen'), 'Einreichen-Schalter fehlt (F100)').toBeVisible()

    // F101: die Ausnahme-Checkbox ist an der Produktegruppe vorhanden.
    await expect(karte.getByText('Ausnahme vom Pauschalantrag'), 'Ausnahme-Checkbox fehlt (F101)').toBeVisible()

    // F103: die Notizen lassen sich an einem (automatisch erzeugten) Antrag öffnen.
    const antrag = karte.locator('.pw-budget-antraege li').first()
    await antrag.waitFor({ state: 'visible', timeout: 15_000 })
    await antrag.locator('.pw-antrag-notizen summary').click()
    await expect(antrag.locator('.pw-antrag-notizen[open]'), 'Notiz-Bereich öffnet nicht (F103)').toBeVisible()

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
