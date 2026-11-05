import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei,
// weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * F150: Die Liste der Geschäfte, die «Kommissionen», «Geschäfte» und
 * «Sitzungen» teilen, zeigt nach jeder Änderung den neuen Stand, ohne dass
 * jemand die Seite neu lädt:
 *  - die Priorität, die eine Verknüpfung mit einem Vorstoss ins Geschäft
 *    schreibt, erscheint in einem anderen Fenster und im eigenen;
 *  - die Priorität, die eine Verknüpfung eines eigenen Geschäfts ins
 *    offizielle schreibt, erscheint im eigenen Fenster;
 *  - ein Geschäft, das aus einem Traktandum geöffnet und geändert wird, steht
 *    danach mit dem neuen Stand in der Traktandenzeile;
 *  - «Neues Geschäft» an einer Kommission führt nach dem Anlegen in die
 *    Bearbeitung und steht nur an aktiven Kommissionen.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP_URL = `${BASE_URL}/index.php/apps/parlwin/`
const USER = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }
// Die Kommissionen legt tests/e2e/run-compose-e2e.sh an.
const AKTIVE_KOMMISSION = 'E2E Gemischte Kommission'
const INAKTIVE_KOMMISSION = 'E2E Inaktive Kommission'

let laufNr = 0
function eindeutig(prefix) {
  laufNr += 1
  return `E2E-${prefix} ${Date.now()}-${laufNr}`
}

async function reqToken(page) {
  return page.evaluate(() => (window.OC && window.OC.requestToken) || '')
}

/** Formular-Anfrage gegen die API von parlwin mit dem Token und den Cookies der Seite. */
async function api(page, method, pfad, form) {
  const token = await reqToken(page)
  const opts = {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded' },
  }
  if (form) opts.form = form
  return page.request[method](`${BASE_URL}/index.php/apps/parlwin${pfad}`, opts)
}

/**
 * Legt ein eigenes Geschäft mit denselben Feldern an, die die Maske beim
 * Speichern schickt (GeschaeftDetail.vue, `neuesGeschaeftSpeichern`), das
 * Datum wie dort vorbelegt auf heute (F141).
 */
async function geschaeftAnlegen(page, titel) {
  const res = await api(page, 'post', '/geschaefte', {
    titel,
    typ: 'Eigenes Geschäft',
    status: 'Pendent',
    inhalt: '',
    kommission: '',
    datum: new Date().toISOString().slice(0, 10),
  })
  expect(res.ok(), `Geschäft-Anlage fehlgeschlagen (${res.status()})`).toBeTruthy()
  return (await res.json()).id
}

async function apiJson(page, pfad) {
  const res = await api(page, 'get', pfad)
  expect(res.ok(), `GET ${pfad} fehlgeschlagen (${res.status()})`).toBeTruthy()
  return res.json()
}

async function oeffneApp(page) {
  await page.goto(APP_URL)
  await page.waitForLoadState('networkidle')
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
}

async function oeffneAnsicht(page, name) {
  await page.getByRole('link', { name, exact: true }).click()
  await expect(page.locator('.pw-view-title')).toHaveText(name, { timeout: 30_000 })
  await page.waitForLoadState('networkidle')
}

async function schalterUmlegen(page, text) {
  await page.locator('#pw-filter-slot .checkbox-radio-switch', { hasText: text })
    .locator('.checkbox-radio-switch__content').first().click()
}

/**
 * Eine Parlamentssitzung mit einem Traktandum, dessen Geschäft keine hohe
 * Priorität hat; die Ansicht «Sitzungen» lädt dieselben 100 Sitzungen.
 */
async function sitzungMitGeschaeftOhneHohePrioritaet(page) {
  const sitzungen = await apiJson(page, '/sitzungen?limit=100')
  for (const s of sitzungen.filter((x) => Number(x.typId) === 0 && !x.geloescht)) {
    const traktanden = await apiJson(page, `/sitzungen/${s.id}/traktanden`)
    const t = traktanden.find((x) => x.geschaeft && x.geschaeft.id > 0 && !x.geschaeft.geloescht
      && x.geschaeft.prioritaet !== 'hoch' && x.geschaeft.titel)
    if (t) {
      return { sitzungId: s.id, nummer: t.nummer, geschaeftId: t.geschaeft.id, vorher: t.geschaeft.prioritaet || '' }
    }
  }
  return null
}

/** Wählt in einem NcSelect eine Option über die Tastatur; das Menü steht am Body. */
async function auswaehlen(page, select, text) {
  const feld = select.locator('.vs__search').first()
  await feld.scrollIntoViewIfNeeded().catch(() => {})
  await feld.focus()
  await page.keyboard.press('ArrowDown')
  try {
    await expect(select).toHaveClass(/vs--open/, { timeout: 4_000 })
  } catch {
    const box = await select.locator('.vs__dropdown-toggle').first().boundingBox()
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
    await expect(select).toHaveClass(/vs--open/, { timeout: 6_000 })
  }
  await page.locator('.vs__dropdown-menu li.vs__dropdown-option', { hasText: text }).first().click()
}

test.describe('Geteilte Liste der Geschäfte: Nachladen ohne Neuladen (F150)', () => {
  // Breit, damit Geschäfte, Vorstösse und Traktanden als Tabelle stehen.
  test.use({ viewport: { width: 1600, height: 1000 } })

  test.beforeAll(() => {
    expect(USER.pass, `Passwort für ${USER.name} fehlt`).not.toBe('')
  })

  test('Die Verknüpfung mit einem Vorstoss zeigt seine Priorität in «Geschäfte» eines anderen Fensters', async ({ browser }) => {
    const schreiber = await (await browser.newContext()).newPage()
    const leser = await (await browser.newContext()).newPage()
    try {
      const geschaeftTitel = eindeutig('Verknuepft-Geschaeft')
      const vorstossTitel = eindeutig('Verknuepft-Vorstoss')
      await login(schreiber, USER)
      await oeffneApp(schreiber)
      await geschaeftAnlegen(schreiber, geschaeftTitel)
      const v = await api(schreiber, 'post', '/vorstoesse', { titel: vorstossTitel, herkunft: 'eigene', prioritaet: 'hoch' })
      expect(v.ok(), `Vorstoss-Anlage fehlgeschlagen (${v.status()})`).toBeTruthy()

      // Der Leser hat die Liste geladen, bevor verknüpft wird.
      await login(leser, USER)
      await oeffneApp(leser)
      await oeffneAnsicht(leser, 'Geschäfte')
      await leser.fill('#pw-search-slot input', geschaeftTitel)
      const zeile = leser.locator('.pw-tabelle-geschaefte tbody tr', { hasText: geschaeftTitel }).first()
      await zeile.waitFor({ state: 'visible', timeout: 30_000 })
      await expect(zeile).not.toHaveClass(/pw-prio-hoch/)

      // Der Schreiber verknüpft den Vorstoss in der Maske mit dem Geschäft.
      await oeffneApp(schreiber)
      await oeffneAnsicht(schreiber, 'Vorstösse')
      await schreiber.fill('#pw-search-slot input', vorstossTitel)
      const vorstossZeile = schreiber.locator('.pw-tabelle-vorstoesse tbody tr', { hasText: vorstossTitel }).first()
      await vorstossZeile.locator('.pw-col-titel').click()
      await schreiber.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' }).waitFor({ state: 'visible', timeout: 30_000 })
      await schreiber.locator('.pw-modal button', { hasText: 'Mit Geschäft verknüpfen' }).click()
      const dialog = schreiber.locator('.pw-modal', { has: schreiber.locator('.pw-verknuepfen-liste') })
      await dialog.locator('input').first().fill(geschaeftTitel)
      await dialog.locator('.pw-verknuepfen-eintrag', { hasText: geschaeftTitel }).click()
      await expect(schreiber.locator('.pw-modal .pw-hinweis', { hasText: 'Mit einem Geschäft verknüpft' }))
        .toBeVisible({ timeout: 30_000 })

      await expect(zeile, 'die Priorität aus dem Vorstoss erscheint im anderen Fenster nicht')
        .toHaveClass(/pw-prio-hoch/, { timeout: 30_000 })
    } finally {
      await schreiber.context().close()
      await leser.context().close()
    }
  })

  test('Die Verknüpfung mit einem Vorstoss zeigt seine Priorität in «Geschäfte» desselben Fensters', async ({ page }) => {
    const geschaeftTitel = eindeutig('Verknuepft-Geschaeft-Selbst')
    const vorstossTitel = eindeutig('Verknuepft-Vorstoss-Selbst')
    await login(page, USER)
    await oeffneApp(page)
    await geschaeftAnlegen(page, geschaeftTitel)
    const v = await api(page, 'post', '/vorstoesse', { titel: vorstossTitel, herkunft: 'eigene', prioritaet: 'hoch' })
    expect(v.ok(), `Vorstoss-Anlage fehlgeschlagen (${v.status()})`).toBeTruthy()

    // «Geschäfte» lädt die geteilte Liste, bevor verknüpft wird.
    await oeffneApp(page)
    await oeffneAnsicht(page, 'Geschäfte')
    await page.fill('#pw-search-slot input', geschaeftTitel)
    const zeile = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: geschaeftTitel }).first()
    await zeile.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(zeile).not.toHaveClass(/pw-prio-hoch/)

    await oeffneAnsicht(page, 'Vorstösse')
    await page.fill('#pw-search-slot input', vorstossTitel)
    await page.locator('.pw-tabelle-vorstoesse tbody tr', { hasText: vorstossTitel }).first().locator('.pw-col-titel').click()
    await page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' }).waitFor({ state: 'visible', timeout: 30_000 })
    await page.locator('.pw-modal button', { hasText: 'Mit Geschäft verknüpfen' }).click()
    const dialog = page.locator('.pw-modal', { has: page.locator('.pw-verknuepfen-liste') })
    await dialog.locator('input').first().fill(geschaeftTitel)
    await dialog.locator('.pw-verknuepfen-eintrag', { hasText: geschaeftTitel }).click()
    await expect(page.locator('.pw-modal .pw-hinweis', { hasText: 'Mit einem Geschäft verknüpft' })).toBeVisible({ timeout: 30_000 })
    await page.waitForLoadState('networkidle')
    await page.locator('.pw-modal .pw-btn-schliessen').first().click()

    // Zurück in «Geschäfte», ohne die Seite neu zu laden.
    await oeffneAnsicht(page, 'Geschäfte')
    await page.fill('#pw-search-slot input', geschaeftTitel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: geschaeftTitel }).first(),
      'die Priorität aus dem Vorstoss fehlt im selben Fenster').toHaveClass(/pw-prio-hoch/, { timeout: 30_000 })
  })

  test('Die Verknüpfung eines eigenen mit einem offiziellen Geschäft zeigt dessen neue Priorität im selben Fenster', async ({ page }) => {
    await login(page, USER)
    await oeffneApp(page)
    // Die Testdaten führen die offiziellen Geschäfte als erledigt; gesucht ist eines ohne Priorität.
    const alle = await apiJson(page, '/geschaefte?show_erledigt=1&limit=500')
    const ziel = alle.find((x) => !x.geloescht && x.titel && !x.prioritaet && !String(x.externId || '').startsWith('eigen:'))
    expect(ziel, 'kein offizielles Geschäft ohne Priorität').toBeTruthy()
    const titel = eindeutig('Eigen-Verknuepft')
    try {
      const eigenId = await geschaeftAnlegen(page, titel)
      const p = await api(page, 'put', `/geschaefte/${eigenId}/prioritaet`, { prioritaet: 'hoch' })
      expect(p.ok(), `Priorität setzen fehlgeschlagen (${p.status()})`).toBeTruthy()

      await oeffneApp(page)
      await oeffneAnsicht(page, 'Geschäfte')
      await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
      await page.fill('#pw-search-slot input', ziel.titel)
      const zielZeile = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: ziel.titel }).first()
      await zielZeile.waitFor({ state: 'visible', timeout: 30_000 })
      await expect(zielZeile).not.toHaveClass(/pw-prio-hoch/)

      await page.fill('#pw-search-slot input', titel)
      const zelle = page.locator('.pw-tabelle-geschaefte tbody tr .pw-col-titel', { hasText: titel }).first()
      await zelle.waitFor({ state: 'visible', timeout: 30_000 })
      await zelle.click()
      const detail = page.locator('.pw-modal-overlay .pw-geschaeft-detail')
      await detail.waitFor({ state: 'visible', timeout: 30_000 })
      await detail.getByRole('button', { name: 'Mit offiziellem Geschäft verknüpfen' }).click()
      const dialog = page.locator('.pw-modal', { has: page.locator('.pw-verknuepfen-liste') })
      await dialog.locator('input').first().fill(ziel.titel.slice(0, Math.min(20, ziel.titel.length)))
      await dialog.locator('.pw-verknuepfen-eintrag', { hasText: ziel.titel }).first().click()
      await expect(detail.getByText(/verknüpft und abgeschlossen/i).first()).toBeVisible({ timeout: 30_000 })
      await page.waitForLoadState('networkidle')
      await page.locator('.pw-modal-overlay .pw-btn-schliessen').first().click()

      await page.fill('#pw-search-slot input', ziel.titel)
      await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: ziel.titel }).first(),
        'das offizielle Geschäft zeigt im selben Fenster die alte Priorität').toHaveClass(/pw-prio-hoch/, { timeout: 30_000 })
    } finally {
      await api(page, 'put', `/geschaefte/${ziel.id}/prioritaet`, { prioritaet: '' })
    }
  })

  // Der Titel speichert beim Verlassen des Feldes, und das Verlassen ist hier
  // der Klick auf ✕: Die Maske ist zu, bevor der Server antwortet.
  for (const [ansicht, zeilen] of [
    ['Geschäfte', '.pw-tabelle-geschaefte tbody tr'],
    ['Kommissionen', '.pw-kommission-geschaeft-eintrag'],
  ]) {
    test(`Ein Titel, den ✕ speichert, steht danach in «${ansicht}»`, async ({ page }) => {
      const titel = eindeutig('Titel-vorher')
      const neuerTitel = eindeutig('Titel-nachher')
      await login(page, USER)
      await oeffneApp(page)
      const id = await geschaeftAnlegen(page, titel)
      // Die Kommission bringt das Geschäft auf die Karte «E2E Gemischte Kommission».
      const k = await api(page, 'put', `/geschaefte/${id}/stammdaten`, {
        titel, typ: 'Eigenes Geschäft', status: 'Pendent', datum: new Date().toISOString().slice(0, 10), inhalt: '', kommission: AKTIVE_KOMMISSION,
      })
      expect(k.ok(), `Kommission setzen fehlgeschlagen (${k.status()})`).toBeTruthy()
      await oeffneApp(page)
      await oeffneAnsicht(page, ansicht)
      if (ansicht === 'Geschäfte') { await page.fill('#pw-search-slot input', titel) }
      const zeile = page.locator(zeilen, { hasText: titel }).first()
      await zeile.waitFor({ state: 'visible', timeout: 30_000 })
      await zeile.locator(ansicht === 'Geschäfte' ? '.pw-col-titel' : '.pw-titel').click()

      const detail = page.locator('.pw-modal-overlay .pw-geschaeft-detail')
      await detail.waitFor({ state: 'visible', timeout: 30_000 })
      await detail.locator('.pw-detail-titel-input').fill(neuerTitel)
      await page.locator('.pw-modal-overlay .pw-btn-schliessen').first().click()
      await expect(detail, 'das ✕ schliesst die Maske nicht').toHaveCount(0)

      if (ansicht === 'Geschäfte') { await page.fill('#pw-search-slot input', neuerTitel) }
      await expect(page.locator(zeilen, { hasText: neuerTitel }).first(), `«${ansicht}» zeigt den alten Titel`)
        .toBeVisible({ timeout: 30_000 })
    })
  }

  test('Mit «Erledigte anzeigen» findet die Suche auch ein Geschäft ausserhalb der neuesten 500', async ({ page }) => {
    await login(page, USER)
    await oeffneApp(page)
    // Dieselbe Reihenfolge wie die Ansicht: neueste zuerst nach Datum.
    const alle = (await apiJson(page, '/geschaefte?show_erledigt=1&limit=2000'))
      .filter((g) => !g.geloescht && g.titel)
      .sort((a, b) => String(b.datum || '').localeCompare(String(a.datum || '')))
    expect(alle.length, 'die Testdaten haben nicht mehr als 500 Geschäfte').toBeGreaterThan(500)
    const gleicherTitel = (g) => alle.filter((x) => x.titel === g.titel).length
    const ziel = alle.slice(500).find((g) => gleicherTitel(g) === 1)
    expect(ziel, 'kein Geschäft mit eigenem Titel ausserhalb der neuesten 500').toBeTruthy()

    await oeffneAnsicht(page, 'Geschäfte')
    await page.locator('#pw-filter-slot').getByText('Erledigte anzeigen').click()
    await expect(page.locator('#pw-filter-slot').getByRole('checkbox', { name: 'Erledigte anzeigen' })).toBeChecked()
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr[role="button"]')).toHaveCount(500, { timeout: 60_000 })
    await page.fill('#pw-search-slot input', ziel.titel)
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr[role="button"]', { hasText: ziel.titel }).first(),
      'die Suche findet nur unter den neuesten 500').toBeVisible({ timeout: 30_000 })
  })

  test('Ein aus dem Traktandum geändertes Geschäft steht danach neu in der Traktandenzeile', async ({ page }) => {
    await login(page, USER)
    await oeffneApp(page)
    const treffer = await sitzungMitGeschaeftOhneHohePrioritaet(page)
    expect(treffer, 'keine Parlamentssitzung mit einem Geschäft ohne hohe Priorität').not.toBeNull()
    try {
      await oeffneAnsicht(page, 'Sitzungen')
      const nurKuenftige = page.locator('#pw-filter-slot .checkbox-radio-switch')
      await nurKuenftige.waitFor({ state: 'visible', timeout: 30_000 })
      if (((await nurKuenftige.getAttribute('class')) || '').includes('checkbox-radio-switch--checked')) {
        await nurKuenftige.locator('.checkbox-radio-switch__text').click({ force: true })
        await expect(nurKuenftige).not.toHaveClass(/checkbox-radio-switch--checked/)
      }
      const karte = page.locator(`#pw-sitzung-${treffer.sitzungId}`)
      await karte.scrollIntoViewIfNeeded()
      await karte.locator('.pw-sitzung-kopf').click()
      const zeile = karte.locator(`.pw-table-desktop .pw-tabelle-traktanden tr[aria-label="Traktandum ${treffer.nummer} öffnen"]`).first()
      await zeile.waitFor({ state: 'visible', timeout: 30_000 })
      await expect(zeile).not.toHaveClass(/pw-prio-hoch/)

      // Geschäft aus der Zeile öffnen und in der Maske die Priorität setzen.
      await zeile.locator('.pw-col-titel').click()
      const detail = page.locator('.pw-modal-overlay .pw-geschaeft-detail')
      await detail.waitFor({ state: 'visible', timeout: 30_000 })
      const prio = detail.locator('.pw-form-zeile', { hasText: 'Priorität' }).first().locator('.v-select')
      await auswaehlen(page, prio, 'Hoch')
      await expect(prio.locator('.vs__selected')).toHaveText('Hoch')
      await page.waitForLoadState('networkidle')
      await page.locator('.pw-modal-overlay .pw-btn-schliessen').first().click()

      await expect(zeile, 'die Traktandenzeile zeigt die alte Priorität').toHaveClass(/pw-prio-hoch/, { timeout: 30_000 })
    } finally {
      if (treffer) { await api(page, 'put', `/geschaefte/${treffer.geschaeftId}/prioritaet`, { prioritaet: treffer.vorher }) }
    }
  })

  test('«Neues Geschäft» an der Kommission führt nach dem Anlegen in die Bearbeitung', async ({ page }) => {
    const titel = eindeutig('Kommission-Bearbeitung')
    await login(page, USER)
    await oeffneApp(page)
    await oeffneAnsicht(page, 'Kommissionen')
    const karte = page.locator('.pw-kommission-karte', { hasText: AKTIVE_KOMMISSION }).first()
    await karte.getByRole('button', { name: 'Neues Geschäft' }).click()

    const detail = page.locator('.pw-modal-overlay .pw-geschaeft-detail')
    await detail.waitFor({ state: 'visible', timeout: 30_000 })
    await detail.getByLabel('Titel').fill(titel)
    await page.locator('.pw-modal').first().locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()

    // Dieselbe Maske bearbeitet jetzt das neue Geschäft: Notizen stehen bereit,
    // das ✕ schliesst, und der Titel ist der eingegebene.
    await expect(detail.locator('.pw-btn-neue-notiz').first(), 'die Maske geht nicht in die Bearbeitung').toBeVisible({ timeout: 30_000 })
    await expect(detail.getByLabel('Titel')).toHaveValue(titel)
    await expect(page.locator('.pw-modal-overlay .pw-modal-footer')).toHaveCount(0)
    await page.locator('.pw-modal-overlay .pw-btn-schliessen').first().click()
    await expect(
      page.locator('.pw-kommission-karte', { hasText: AKTIVE_KOMMISSION }).first()
        .locator('.pw-kommission-geschaeft-eintrag', { hasText: titel }),
      'das neue Geschäft steht nicht bei seiner Kommission',
    ).toHaveCount(1, { timeout: 30_000 })
  })

  test('«Neues Geschäft» steht nur an aktiven Kommissionen, auch wenn die inaktiven sichtbar sind', async ({ page }) => {
    await login(page, USER)
    await oeffneApp(page)
    await oeffneAnsicht(page, 'Kommissionen')
    await schalterUmlegen(page, 'Nur aktive Kommissionen')
    await expect(page.locator('#pw-filter-slot').getByRole('checkbox', { name: 'Nur aktive Kommissionen', exact: true })).not.toBeChecked()

    const inaktiv = page.locator('.pw-kommission-karte.ist-inaktiv', { hasText: INAKTIVE_KOMMISSION }).first()
    await expect(inaktiv, `die Kommission «${INAKTIVE_KOMMISSION}» erscheint nicht`).toBeVisible({ timeout: 30_000 })
    await expect(inaktiv.getByRole('button', { name: 'Neues Geschäft' }), 'die inaktive Kommission bietet «Neues Geschäft» an').toHaveCount(0)
    await expect(page.locator('.pw-kommission-karte', { hasText: AKTIVE_KOMMISSION }).first()
      .getByRole('button', { name: 'Neues Geschäft' })).toBeVisible()
  })
})
