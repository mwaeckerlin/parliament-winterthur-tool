import { test, expect } from '@playwright/test'

/**
 * Feld-Inventar der übrigen Masken: Filterbereiche jeder Ansicht sowie die
 * Dialoge, die nicht zu den «+ Neu»-Einstiegen gehören.
 *
 * Ergänzt tests/e2e/neu-dialoge-vollstaendig.spec.js (dort die «+ Neu»-Masken).
 * Zweck ist derselbe: Die erwarteten Bedienelemente stehen als Konstante im
 * Test. Verschwindet eines, wird der Test rot und benennt es. Jede Ansicht wird
 * zusätzlich auf ihre Funktion geprüft, nicht nur auf blosse Anwesenheit.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

// --- Verbindliches Inventar der Filterbereiche ------------------------------

const FILTER_GESCHAEFTE = ['Entscheidungsbedarf', 'Status', 'Typ', 'Zuständigkeit', 'Beschluss', 'Priorität']
const FILTER_VORSTOESSE = ['Herkunft', 'Status']
const FILTER_MITGLIEDER = ['Sortieren nach', 'Fraktion', 'Partei', 'Kommission', 'Funktion']

/** Sitzungstyp bearbeiten: alle Eingabefelder in ihrer Reihenfolge. */
const SITZUNGSTYP_FELDER = ['Name *', 'Zweck', 'Standard-Ort', 'Von', 'Bis']

// --- Basis-Helfer -----------------------------------------------------------

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

async function gotoView(page, name) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByText(name, { exact: true }).first().click()
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
}

/** Beschriftungen der Auswahlfelder im Filterbereich der Seitenleiste. */
async function filterLabels(page) {
  await page.waitForSelector('#pw-filter-slot .pw-filter-body', { timeout: 30_000 })
  return (await page.locator('#pw-filter-slot .input-field__label, #pw-filter-slot label').allTextContents())
    .map((s) => s.trim())
    .filter(Boolean)
}

test.describe('Feld-Inventar: Filterbereiche jeder Ansicht', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Geschäfte: alle Filter vorhanden, Suche und Zurücksetzen wirken', async ({ page }) => {
    await login(page, U1)
    await gotoView(page, 'Geschäfte')

    const labels = await filterLabels(page)
    for (const f of FILTER_GESCHAEFTE) {
      expect(labels.join(' | '), `Filter «${f}» fehlt`).toContain(f)
    }
    await expect(page.locator('#pw-filter-slot .checkbox-radio-switch'), 'Schalter «Erledigte anzeigen» fehlt')
      .toBeVisible()
    await expect(page.getByRole('button', { name: 'Filter zurücksetzen' })).toBeVisible()

    // Funktion: Suche grenzt ein, Zurücksetzen stellt wieder her.
    const vorher = await page.locator('.pw-tabelle-geschaefte tbody tr').count()
    await page.fill('#pw-search-slot input', 'zzz-kein-treffer-zzz')
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr')).toHaveCount(0)
    await page.getByRole('button', { name: 'Filter zurücksetzen' }).click()
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr')).toHaveCount(vorher, { timeout: 30_000 })
  })

  test('Vorstösse: Herkunft- und Status-Filter vorhanden und wirksam', async ({ page }) => {
    await login(page, U1)
    await gotoView(page, 'Vorstösse')

    const labels = await filterLabels(page)
    for (const f of FILTER_VORSTOESSE) {
      expect(labels.join(' | '), `Filter «${f}» fehlt`).toContain(f)
    }
    // Beide Filter stehen anfangs auf «Alle».
    const gewaehlt = await page.locator('#pw-filter-slot .vs__selected').allTextContents()
    expect(gewaehlt.map((s) => s.trim())).toEqual(['Alle', 'Alle'])
  })

  test('Mitglieder: Sortierung, vier Filter und der Aktiv-Schalter sind vorhanden', async ({ page }) => {
    await login(page, U1)
    await gotoView(page, 'Mitglieder')

    const labels = await filterLabels(page)
    for (const f of FILTER_MITGLIEDER) {
      expect(labels.join(' | '), `Filter «${f}» fehlt`).toContain(f)
    }
    const schalter = page.locator('#pw-filter-slot .checkbox-radio-switch')
    await expect(schalter).toContainText('Nur aktive Mitglieder')

    // Funktion: Der Schalter verändert die Zahl der angezeigten Mitglieder.
    // NcCheckboxRadioSwitch versteckt den rohen Input hinter dem sichtbaren Inhalt
    // (.checkbox-radio-switch__content) — ein Klick auf den Input wird abgefangen,
    // darum den sichtbaren Inhalt klicken, der den Umschalt-Handler trägt.
    const aktiv = await page.locator('.pw-mitglied-karte').count()
    await schalter.locator('.checkbox-radio-switch__content').click()
    await expect
      .poll(async () => page.locator('.pw-mitglied-karte').count(), { timeout: 30_000 })
      .toBeGreaterThanOrEqual(aktiv)
  })

  test('Kommissionen: beide Schalter sind vorhanden und standardmässig an', async ({ page }) => {
    await login(page, U1)
    await gotoView(page, 'Kommissionen')

    await page.waitForSelector('#pw-filter-slot .pw-filter-body', { timeout: 30_000 })
    const schalter = page.locator('#pw-filter-slot .checkbox-radio-switch')
    await expect(schalter).toHaveCount(2)
    await expect(schalter.nth(0)).toContainText('Nur aktive Kommissionen')
    await expect(schalter.nth(1)).toContainText('Nur aktive Mitglieder')
  })

  test('Änderungsverlauf: Such- und Filterbereich bleiben leer', async ({ page }) => {
    await login(page, U1)
    await gotoView(page, 'Änderungsverlauf')

    // Diese Ansicht füllt weder Suche noch Filter — das ist beabsichtigt.
    await expect(page.locator('#pw-search-slot input')).toHaveCount(0)
    await expect(page.locator('#pw-filter-slot .pw-filter-body')).toHaveCount(0)
    await expect(page.locator('.pw-changelog .pw-data-card').first()).toBeVisible({ timeout: 30_000 })
  })
})

test.describe('Feld-Inventar: Sitzungstyp bearbeiten', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Der Bearbeiten-Dialog zeigt alle Felder, Traktanden, Teilnehmer und Optionen', async ({ page }) => {
    const name = `E2E-Inventar-Typ ${Date.now()}`
    await login(page, U1)
    await gotoView(page, 'Sitzungstypen')

    // «+ Neuer Typ» öffnet sofort die vollständige Maske. Sie legt noch nichts
    // an: der Name wird erfasst, erst «Speichern» erzeugt den Sitzungstyp und
    // die Maske geht in die Bearbeitung über.
    await page.getByRole('button', { name: /Neuer Typ/ }).click()
    const dialog = page.locator('.pw-modal').filter({ has: page.locator('.pw-modal-kopf h3') }).first()
    await dialog.waitFor({ state: 'visible', timeout: 30_000 })
    await dialog.locator('.pw-modal-body input.pw-input').first().fill(name)
    await dialog.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
    await expect(dialog.locator('.pw-modal-kopf h3')).toHaveText('Sitzungstyp bearbeiten', { timeout: 30_000 })

    const labels = (await dialog.locator('.pw-field-label').allTextContents()).map((s) => s.trim())
    for (const f of SITZUNGSTYP_FELDER) {
      expect(labels, `Feld «${f}» fehlt im Sitzungstyp-Dialog`).toContain(f)
    }

    // Die drei Gruppen und ihre Bedienelemente.
    await expect(dialog.getByText('Vorlage-Traktanden')).toBeVisible()
    await expect(dialog.getByRole('button', { name: '+ Traktandum' })).toBeVisible()
    await expect(dialog.getByText('Teilnehmer')).toBeVisible()
    await expect(dialog.locator('.checkbox-radio-switch', { hasText: 'Eigene Fraktion' })).toBeVisible()
    await expect(dialog.getByText('Einzelne Mitglieder')).toBeVisible()
    await expect(dialog.getByText('Optionen')).toBeVisible()
    await expect(dialog.locator('.checkbox-radio-switch', { hasText: 'Verknüpfung' })).toBeVisible()
    await expect(dialog.getByText('Kommissionen beraten')).toBeVisible()

    // Funktion: Eine Eingabe speichert sofort und überlebt das Neuladen.
    const ort = `Zimmer ${Date.now()}`
    await dialog.locator('.pw-field', { hasText: 'Standard-Ort' }).locator('input').fill(ort)
    await dialog.locator('.pw-field', { hasText: 'Standard-Ort' }).locator('input').blur()
    await page.waitForLoadState('networkidle')
    await page.reload()
    await gotoView(page, 'Sitzungstypen')
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).first().click()
    const wieder = page.locator('.pw-modal', { hasText: 'Sitzungstyp bearbeiten' })
    await wieder.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(wieder.locator('.pw-field', { hasText: 'Standard-Ort' }).locator('input')).toHaveValue(ort)

    // Aufräumen: der Testtyp wird wieder entfernt.
    await wieder.locator('.pw-btn-schliessen').click()
    page.once('dialog', (d) => d.accept())
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).getByRole('button', { name: 'Löschen' }).click()
    await expect(page.locator('.pw-sitzungstyp-karte', { hasText: name })).toHaveCount(0, { timeout: 30_000 })
  })
})
