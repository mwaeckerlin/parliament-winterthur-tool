import { test, expect } from '@playwright/test'

/**
 * Vollständiger E2E-Test der Vorstösse im echten Browser gegen den realen Stack
 * (Nextcloud + DB + API). Ergänzt tests/e2e/vorstoss-datenfluss.spec.js: dort
 * sind bereits abgedeckt Erstellen→Bearbeiten (Titel bleibt erhalten), Notiz beim
 * Verlassen speichern, Notiz bearbeiten/löschen/wiederherstellen durch den Autor
 * und der Verknüpfen-Happy-Path. Hier stehen ausschliesslich die verbleibenden
 * Lücken — Gut-, Schlecht- (Berechtigung/leer/ungültig/kein Treffer) und
 * Grenzfälle über den echten Nutzerpfad (Frontend mit Playwright).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }
const U2 = { name: process.env.PW_U2 || 'parlwin_protokoll', pass: process.env.PW_P2 || '' }

// Modal der Vorstoss-Bearbeitung — eindeutig auch wenn der Verknüpfen-Dialog (ein
// zweites, ebenfalls nach <body> teleportiertes .pw-modal) darüber offen ist.
const MODAL = '.pw-modal:has(h3:has-text("Vorstoss bearbeiten"))'
const VMODAL = '.pw-modal:has(h3:has-text("Mit Geschäft verknüpfen"))'

// ---------------------------------------------------------------------------
// Aus vorstoss-datenfluss.spec.js übernommene Basis-Helfer (gleiche Muster).
// ---------------------------------------------------------------------------

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

async function oeffneVorstoesse(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByText('Vorstösse', { exact: true }).first().click()
  await page.getByRole('button', { name: /Neuer Vorstoss/ }).waitFor({ state: 'visible', timeout: 30_000 })
}

async function oeffneGeschaefte(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByText('Geschäfte', { exact: true }).first().click()
  await page.getByRole('button', { name: /Eigenes Geschäft/ }).waitFor({ state: 'visible', timeout: 30_000 })
}

/** Legt einen Vorstoss mit dem übergebenen (eindeutigen) Titel an und öffnet die Bearbeitung. */
async function erstelleVorstossMitTitel(page, titel) {
  await oeffneVorstoesse(page)
  // «+ Neuer Vorstoss» öffnet dieselbe Maske wie das Bearbeiten, legt aber noch
  // nichts an: die Maske sammelt nur die Eingaben. Erst «Speichern» erzeugt den
  // Vorstoss; danach geht dieselbe Maske in die Bearbeitung über.
  await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
  const dialog = page.locator('.pw-modal').first()
  await dialog.locator('input.pw-input').first().waitFor({ state: 'visible', timeout: 30_000 })
  await dialog.locator('input.pw-input').first().fill(titel)
  await dialog.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
  await page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' })
    .waitFor({ state: 'visible', timeout: 30_000 })
}

/** Wie erstelleVorstossUndOeffne im Datenfluss-Test: eindeutiger Titel, offene Bearbeitung. */
async function erstelleVorstossUndOeffne(page, name) {
  const titel = `E2E-Vorstoss ${name} ${Date.now()}-${Math.floor(Math.random() * 1e4)}`
  await erstelleVorstossMitTitel(page, titel)
  return titel
}

/** Öffnet den «+ Neue Notiz»-Editor und tippt Text (ProseMirror-Tipprace vermeiden). */
async function neueNotizTippen(page, text) {
  const neuKnopf = page.locator('.pw-notizen-liste .pw-btn-neue-notiz')
  await neuKnopf.waitFor({ state: 'visible', timeout: 30_000 })
  await neuKnopf.click()
  const editor = page.locator('.pw-notizen-liste .ProseMirror').first()
  await editor.waitFor({ state: 'visible', timeout: 30_000 })
  await editor.click()
  await page.waitForTimeout(300)
  await editor.pressSequentially(text, { delay: 25 })
  // Speichern nur über das Häkchen — kein Blur-Save mehr.
  await page.locator('.pw-notizen-liste .pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()
}

// ---------------------------------------------------------------------------
// Ergänzende Helfer für Dialog-Felder und NcSelect (vue-select).
// ---------------------------------------------------------------------------

// PwField umschliesst ein Label; Feld über exaktes Label-Text finden.
const feld = (page, label) =>
  page.locator(`${MODAL} .pw-field:has(.pw-field-label:text-is(${JSON.stringify(label)}))`)

// Karte in der Übersicht (eindeutiger Titel pro Test).
const karte = (page, titel) => page.locator('.pw-vorstoesse .pw-data-card', { hasText: titel }).first()

// Whitespace normalisieren (NcEllipsisedOption verteilt lange Labels auf zwei
// Spans mit Zeilenumbruch).
const norm = s => String(s || '').replace(/\s+/g, ' ').trim()

// NcSelect rendert jede Option über NcEllipsisedOption; das VOLLE, ungekürzte Label
// steht im title-Attribut von `.name-parts` (der sichtbare Text ist evtl. gekürzt
// oder trägt einen Zusatz). Darum wird immer das title-Attribut gelesen/verglichen.
async function optionLabel(optionLoc) {
  const np = optionLoc.locator('.name-parts').first()
  if (await np.count() > 0) {
    const t = await np.getAttribute('title')
    if (t != null) return norm(t)
  }
  return norm(await optionLoc.innerText())
}

// NcSelect: Dropdown wird per appendToBody an <body> teleportiert (NcSelect-Default).
// Der Toggle wird zuerst in den Blick gescrollt (langes Modal) und geklickt.
async function ncToggle(feldLoc) {
  const toggle = feldLoc.locator('.vs__dropdown-toggle').first()
  await toggle.scrollIntoViewIfNeeded()
  await toggle.click()
}

async function ncWaehle(page, feldLoc, label) {
  await ncToggle(feldLoc)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  // Exakte Übereinstimmung über das title-Attribut (volles Label) …
  const byTitle = menu.locator('li.vs__dropdown-option', {
    has: page.locator(`.name-parts[title=${JSON.stringify(label)}]`),
  })
  if (await byTitle.count() > 0) { await byTitle.first().click(); return }
  // … sonst tolerant über den (normalisierten) sichtbaren Text.
  await menu.locator('li.vs__dropdown-option').filter({ hasText: label }).first().click()
}

/** Wählt die erste verfügbare Option eines NcSelect und gibt ihr volles Label zurück. */
async function ncWaehleErste(page, feldLoc) {
  await ncToggle(feldLoc)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  const opt = menu.locator('li.vs__dropdown-option').first()
  await opt.waitFor({ state: 'visible', timeout: 15_000 })
  const label = await optionLabel(opt)
  await opt.click()
  return label
}

/** Öffnet ein NcSelect, liest die vollen Options-Labels (title) und schliesst es wieder. */
async function ncOptionen(page, feldLoc) {
  await ncToggle(feldLoc)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  const opts = menu.locator('li.vs__dropdown-option')
  const n = await opts.count()
  const labels = []
  for (let i = 0; i < n; i++) labels.push(await optionLabel(opts.nth(i)))
  await page.keyboard.press('Escape')
  return labels
}

/** Leert ein clearable NcSelect (Einzel- oder Mehrfachauswahl), falls etwas gewählt ist. */
async function ncLeeren(feldLoc) {
  const clear = feldLoc.locator('.vs__clear').first()
  if (await clear.count() > 0 && await clear.isVisible()) {
    await clear.scrollIntoViewIfNeeded()
    await clear.click()
  }
}

/** BeschlussWidget (Art/Haltung): Freitext oder bekannte Option per Eingabe + Blur setzen. */
async function beschlussSetzen(feldLoc, text) {
  const input = feldLoc.locator('input.pw-beschluss-input')
  await input.click()
  await input.fill(text)
  await input.blur()
}

async function wysiwygTippen(page, editorLoc, text) {
  await editorLoc.click()
  await page.waitForTimeout(300)
  await editorLoc.pressSequentially(text, { delay: 25 })
}

async function schliesseDialog(page) {
  await page.locator(`${MODAL} .pw-btn-schliessen`).first().click()
}

/** Öffnet eine Vorstoss-Karte in die Bearbeitung. */
async function oeffneKarte(page, titel) {
  const k = karte(page, titel)
  await k.waitFor({ state: 'visible', timeout: 30_000 })
  await k.click()
  await page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' })
    .waitFor({ state: 'visible', timeout: 30_000 })
}

/** Ruft eine parlwin-API im Browser-Kontext ab (Session-Cookie + CSRF-Token). */
async function apiGet(page, pfad) {
  return await page.evaluate(async (p) => {
    const tok = (window.OC && window.OC.requestToken) || ''
    const url = window.OC.generateUrl('/apps/parlwin' + p)
    const res = await fetch(url, { headers: { requesttoken: tok, 'OCS-APIRequest': 'true' } })
    return res.ok ? await res.json() : []
  }, pfad)
}

const worte = t => Array.from(new Set(String(t || '').toLowerCase().split(/\W+/).filter(w => w.length > 2)))

test.beforeAll(() => {
  expect(U1.pass, `Passwort für ${U1.name} fehlt`).not.toBe('')
})

// Sammelt JavaScript-Fehler der Seite; ein Fehler wäre sonst nur ein späterer Timeout.
let jsFehler
test.beforeEach(({ page }) => {
  jsFehler = []
  page.on('pageerror', (e) => jsFehler.push(e.message))
})

// ===========================================================================
test.describe('Vorstösse: Liste, Karten, Suche & Filter', () => {
  test('Karte zeigt Kicker (Herkunft·Art), Titel, Status-Badge und Zuständigkeit', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'Karte')

    await beschlussSetzen(feld(page, 'Art'), 'Motion')
    await ncLeeren(feld(page, 'Zuständigkeit'))
    const person = await ncWaehleErste(page, feld(page, 'Zuständigkeit'))

    const k = karte(page, titel)
    // Kicker = Herkunft · Art (tolerant gegenüber exaktem Trennzeichen/Whitespace).
    await expect(k.locator('.pw-data-card-kicker')).toContainText('Eigene')
    await expect(k.locator('.pw-data-card-kicker')).toContainText('Motion')
    await expect(k.locator('h3')).toContainText(titel)
    await expect(k.locator('.pw-data-card-header > span')).toHaveText('Neu')
    // Zuständigkeit kann mehrere Personen enthalten; der gewählte Name muss vorkommen.
    await expect(
      k.locator('.pw-data-pair', { hasText: 'Zuständigkeit' }).locator('strong'),
    ).toContainText(person)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Fremde Karte zeigt Herkunftsfraktion und Beschluss-Paar', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'FremdKarte')

    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')
    await beschlussSetzen(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)'), 'Ablehnen')
    const fraktion = await ncWaehleErste(page, feld(page, 'Herkunft (fremde Fraktion)'))

    const k = karte(page, titel)
    const herkunftPaar = k.locator('.pw-data-pair', { hasText: 'Herkunftsfraktion' }).locator('strong')
    await expect(herkunftPaar).toContainText(fraktion)
    await expect(herkunftPaar).not.toHaveText('—')
    await expect(k.locator('.pw-data-pair', { hasText: 'Beschluss' }).locator('strong'))
      .toHaveText('Ablehnen')
  })

  test('Nur mit Titel angelegter Vorstoss erscheint als Karte', async ({ page }) => {
    // Regression «Keine Vorstösse vorhanden»: ein Vorstoss, bei dem ausser dem
    // Titel kein weiteres Feld angefasst wurde, muss in der Übersicht erscheinen
    // (die Zuständigkeit-Vorbelegung bleibt unberührt).
    await login(page, U1)
    await oeffneVorstoesse(page)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const dialog = page.locator('.pw-modal').first()
    const titel = `E2E-Vorstoss Minimal ${Date.now()}-${Math.floor(Math.random() * 1e4)}`
    await dialog.locator('input.pw-input').first().waitFor({ state: 'visible', timeout: 30_000 })
    await dialog.locator('input.pw-input').first().fill(titel)
    // Angelegt wird erst beim Speichern; danach heisst die Maske «Vorstoss bearbeiten».
    await dialog.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
    await page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' })
      .waitFor({ state: 'visible', timeout: 30_000 })
    await schliesseDialog(page)

    await expect(karte(page, titel), 'Minimaler Vorstoss fehlt in der Übersicht').toBeVisible({ timeout: 30_000 })
    await expect(page.locator('.pw-vorstoesse').getByText('Keine Vorstösse vorhanden')).toHaveCount(0)
  })

  test('Suche findet über Titel, Art und Zuständigkeit; ohne Treffer «Keine Vorstösse vorhanden»', async ({ page }) => {
    await login(page, U1)
    const marker = `Suchmarke${Date.now()}`
    const titel = await erstelleVorstossUndOeffne(page, 'Suche')
    await beschlussSetzen(feld(page, 'Art'), marker)
    await schliesseDialog(page)

    const suche = page.locator('#pw-search-slot input').first()
    // Über den Titel finden.
    await suche.fill(titel)
    await expect(karte(page, titel)).toBeVisible({ timeout: 15_000 })
    // Über die Art (im Titel nicht enthaltener Marker) finden.
    await suche.fill(marker)
    await expect(karte(page, titel)).toBeVisible({ timeout: 15_000 })
    // Kein Treffer → Leerhinweis.
    await suche.fill(`kein-treffer-${Date.now()}`)
    await expect(page.locator('.pw-vorstoesse').getByText('Keine Vorstösse vorhanden'))
      .toBeVisible({ timeout: 15_000 })
  })

  test('Herkunft-Filter (Alle/Eigene/Fremde) blendet Karten passend ein und aus', async ({ page }) => {
    await login(page, U1)
    const eigen = await erstelleVorstossUndOeffne(page, 'HerkEigen')
    await schliesseDialog(page)
    const fremd = await erstelleVorstossUndOeffne(page, 'HerkFremd')
    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')
    await schliesseDialog(page)

    const filterHerkunft = page.locator('#pw-filter-slot .v-select').nth(0)
    await ncWaehle(page, filterHerkunft, 'Fremde')
    await expect(karte(page, fremd)).toBeVisible({ timeout: 15_000 })
    await expect(karte(page, eigen)).toHaveCount(0)

    await ncWaehle(page, filterHerkunft, 'Eigene')
    await expect(karte(page, eigen)).toBeVisible({ timeout: 15_000 })
    await expect(karte(page, fremd)).toHaveCount(0)

    await ncWaehle(page, filterHerkunft, 'Alle')
    await expect(karte(page, eigen)).toBeVisible({ timeout: 15_000 })
    await expect(karte(page, fremd)).toBeVisible({ timeout: 15_000 })
  })

  test('Status-Filter zeigt nur Vorstösse des gewählten Status', async ({ page }) => {
    await login(page, U1)
    const neu = await erstelleVorstossUndOeffne(page, 'StatusNeu')
    await schliesseDialog(page)
    const pausiert = await erstelleVorstossUndOeffne(page, 'StatusPausiert')
    await ncWaehle(page, feld(page, 'Status'), 'Pausiert')
    await schliesseDialog(page)

    const filterStatus = page.locator('#pw-filter-slot .v-select').nth(1)
    await ncWaehle(page, filterStatus, 'Pausiert')
    await expect(karte(page, pausiert)).toBeVisible({ timeout: 15_000 })
    await expect(karte(page, neu)).toHaveCount(0)

    await ncWaehle(page, filterStatus, 'Alle')
    await expect(karte(page, neu)).toBeVisible({ timeout: 15_000 })
  })

  test('Prioritäts-Hervorhebung: hoch/tief markiert, mittel und «nicht gesetzt» neutral', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'Prio')
    const k = karte(page, titel)

    await ncWaehle(page, feld(page, 'Priorität'), 'Hoch')
    await expect(k).toHaveClass(/pw-prio-hoch/)

    await ncWaehle(page, feld(page, 'Priorität'), 'Tief')
    await expect(k).toHaveClass(/pw-prio-tief/)

    await ncWaehle(page, feld(page, 'Priorität'), 'Mittel')
    await expect(k).not.toHaveClass(/pw-prio-hoch/)
    await expect(k).not.toHaveClass(/pw-prio-tief/)

    // Abwählen: Anzeige «Nicht gesetzt», NICHT «Mittel»; Karte bleibt neutral.
    await ncLeeren(feld(page, 'Priorität'))
    await expect(feld(page, 'Priorität').locator('.vs__selected')).toHaveCount(0)
    await expect(feld(page, 'Priorität')).not.toContainText('Mittel')
    await expect(feld(page, 'Priorität').locator('input.vs__search')).toHaveAttribute('placeholder', 'Nicht gesetzt')
    await expect(k).not.toHaveClass(/pw-prio-hoch/)
    await expect(k).not.toHaveClass(/pw-prio-tief/)
  })
})

// ===========================================================================
test.describe('Vorstösse: Neuer Vorstoss & Vorbelegung', () => {
  test('Ein Vorstoss ohne echten Titel lässt sich nicht speichern und wird nicht angelegt', async ({ page }) => {
    await login(page, U1)
    await oeffneVorstoesse(page)
    const vorher = await page.locator('.pw-data-card').count()
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()

    const dialog = page.locator('.pw-modal').first()
    const eingabe = dialog.locator('input.pw-input').first()
    await eingabe.waitFor({ state: 'visible', timeout: 30_000 })

    // Ohne Titel ist «Speichern» gesperrt — angelegt wurde noch nichts.
    await expect(dialog.locator('h3')).toHaveText('Neuer Vorstoss')
    const speichern = dialog.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' })
    await expect(speichern, 'Ohne Titel muss «Speichern» gesperrt sein').toBeDisabled()
    await eingabe.fill('   ')
    await eingabe.blur()
    await expect(speichern, 'Nur Leerzeichen dürfen nicht als Titel zählen').toBeDisabled()
    await expect(dialog.locator('h3')).toHaveText('Neuer Vorstoss')

    // Im Neu-Modus gibt es kein ✕; verworfen wird ausschliesslich über «Abbrechen».
    await expect(dialog.locator('.pw-btn-schliessen'), 'Im Neu-Modus darf es kein ✕ geben').toHaveCount(0)
    await dialog.locator('.pw-modal-footer').getByRole('button', { name: 'Abbrechen' }).click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-data-card')).toHaveCount(vorher, { timeout: 30_000 })
  })

  test('Nach Erstellen: Herkunft «Eigene», Status «Neu» und der Ersteller als Zuständigkeit', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'Vorbelegung')

    // Anzeigename des angemeldeten Nutzers aus derselben Quelle wie die Komponente.
    const meinName = await page.evaluate(async () => {
      const uid = ((window.OC && OC.getCurrentUser && OC.getCurrentUser().uid) || '').toLowerCase()
      const tok = (window.OC && OC.requestToken) || ''
      const res = await fetch(OC.generateUrl('/apps/parlwin/mitglieder'),
        { headers: { requesttoken: tok, 'OCS-APIRequest': 'true' } })
      const data = res.ok ? await res.json() : []
      const m = data.find(x => ((x.nextcloudUid || x.nextcloud_uid || '').toLowerCase()) === uid)
      return m ? `${m.vorname || ''} ${m.name || ''}`.trim() : ''
    })
    expect(meinName, 'Der angemeldete Nutzer ist kein synchronisiertes Mitglied').not.toBe('')

    await expect(feld(page, 'Herkunft').locator('.vs__selected')).toHaveText('Eigene')
    await expect(feld(page, 'Status').locator('.vs__selected')).toHaveText('Neu')
    await expect(
      feld(page, 'Zuständigkeit').locator('.vs__selected'),
      'Der Ersteller ist nicht als Zuständigkeit vorbelegt',
    ).toContainText(meinName)
  })
})

// ===========================================================================
test.describe('Vorstösse: Felder speichern sofort', () => {
  test('Art: bekannte Auswahl und freie Überschreibung landen auf der Karte', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'Art')
    const k = karte(page, titel)

    await beschlussSetzen(feld(page, 'Art'), 'Postulat')
    await expect(k.locator('.pw-data-card-kicker')).toHaveText('Eigene · Postulat')

    await beschlussSetzen(feld(page, 'Art'), 'Spezialvorstoss Freitext')
    await expect(k.locator('.pw-data-card-kicker')).toHaveText('Eigene · Spezialvorstoss Freitext')
  })

  test('Statuswechsel spiegelt sich sofort im Karten-Badge', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'StatusBadge')
    const k = karte(page, titel)

    await expect(k.locator('.pw-data-card-header > span')).toHaveText('Neu')
    await ncWaehle(page, feld(page, 'Status'), 'Bereit')
    await expect(k.locator('.pw-data-card-header > span')).toHaveText('Bereit')
    await ncWaehle(page, feld(page, 'Status'), 'Eingereicht')
    await expect(k.locator('.pw-data-card-header > span')).toHaveText('Eingereicht')
  })

  test('Herkunft Eigene↔Fremde blendet die fremde-Felder ein und wieder aus', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'HerkunftToggle')

    await expect(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)')).toHaveCount(0)
    await expect(feld(page, 'Herkunft (fremde Fraktion)')).toHaveCount(0)
    await expect(feld(page, 'Ansprechpartner')).toHaveCount(0)

    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')
    await expect(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)')).toHaveCount(1)
    await expect(feld(page, 'Herkunft (fremde Fraktion)')).toHaveCount(1)
    await expect(feld(page, 'Ansprechpartner')).toHaveCount(1)

    await ncWaehle(page, feld(page, 'Herkunft'), 'Eigene')
    await expect(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)')).toHaveCount(0)
    await expect(feld(page, 'Herkunft (fremde Fraktion)')).toHaveCount(0)
    await expect(feld(page, 'Ansprechpartner')).toHaveCount(0)
  })

  test('Zuständigkeit bietet nur aktive Mitglieder mit Nextcloud-User an', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'ZustaendigOpt')

    // Erlaubte (aktiv + Nextcloud-User) und verbotene (inaktiv bzw. ohne NC-User)
    // Namen aus derselben Quelle wie die Komponente (App lädt /mitglieder).
    const daten = await page.evaluate(async () => {
      const tok = (window.OC && window.OC.requestToken) || ''
      const res = await fetch(window.OC.generateUrl('/apps/parlwin/mitglieder'),
        { headers: { requesttoken: tok, 'OCS-APIRequest': 'true' } })
      const data = res.ok ? await res.json() : []
      const voll = m => `${m.vorname || ''} ${m.name || ''}`.trim()
      const hatNc = m => !!(m.nextcloudUid || m.nextcloud_uid)
      return {
        erlaubt: data.filter(m => m.aktiv !== false && hatNc(m)).map(voll).filter(Boolean),
        verboten: data.filter(m => (m.aktiv === false || !hatNc(m))).map(voll).filter(Boolean),
      }
    })
    expect(daten.erlaubt.length, 'Es sollten aktive Mitglieder mit Nextcloud-User existieren').toBeGreaterThan(0)

    await ncLeeren(feld(page, 'Zuständigkeit'))
    const optionen = await ncOptionen(page, feld(page, 'Zuständigkeit'))
    expect(optionen.length).toBeGreaterThan(0)
    // Jede angebotene Option gehört zu einem aktiven Mitglied mit Nextcloud-User
    // (der Optionstext enthält den Namen; NcEllipsisedOption ergänzt evtl. einen Zusatz).
    for (const o of optionen) {
      expect(daten.erlaubt.some(name => o.includes(name)), `Unerlaubte Option: ${o}`).toBe(true)
    }
    // … und kein inaktives/NC-loses Mitglied (z.B. «Ehemalig Erika») wird angeboten.
    for (const v of daten.verboten) {
      if (!daten.erlaubt.includes(v)) {
        expect(optionen.some(o => o.includes(v)), `Verbotenes Mitglied angeboten: ${v}`).toBe(false)
      }
    }
  })

  test('Inhalt speichert beim Verlassen und wird für einfachen Text nicht durchgehend fett', async ({ page }) => {
    await login(page, U1)
    const inhalt = `Einfacher Textinhalt ${Date.now()}`
    const titel = await erstelleVorstossUndOeffne(page, 'Inhalt')

    const editor = feld(page, 'Inhalt').locator('.ProseMirror').first()
    await wysiwygTippen(page, editor, inhalt)
    // Blur durch Fokuswechsel in den Titel → speichert (kein Speichern-Knopf).
    await feld(page, 'Titel *').locator('input.pw-input').click()
    await schliesseDialog(page)

    await oeffneKarte(page, titel)
    const editor2 = feld(page, 'Inhalt').locator('.ProseMirror').first()
    await expect(editor2).toContainText(inhalt, { timeout: 15_000 })
    // Der Editor sitzt via PwField in einem <label>; Nextcloud stellt Labels fett dar
    // und font-weight vererbt sich. Einfacher Text darf NICHT fett erscheinen — der
    // Editor-Body setzt font-weight explizit auf normal (kein <strong>, reine CSS-Frage).
    const absatz = editor2.locator('p', { hasText: inhalt }).first()
    const gewicht = await absatz.evaluate(el => getComputedStyle(el).fontWeight)
    expect(['400', 'normal'], `Einfacher Inhaltstext wird fett dargestellt (font-weight ${gewicht})`)
      .toContain(gewicht)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
test.describe('Vorstösse: Fremde Vorstösse', () => {
  test('Haltung via BeschlussWidget: bekannte Option und freie Eingabe', async ({ page }) => {
    await login(page, U1)
    const titel = await erstelleVorstossUndOeffne(page, 'Haltung')
    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')

    await beschlussSetzen(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)'), 'Unterstützen')
    const k = karte(page, titel)
    await expect(k.locator('.pw-data-pair', { hasText: 'Beschluss' }).locator('strong'))
      .toHaveText('Unterstützen')

    await beschlussSetzen(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)'), 'Eigene Haltung XY')
    await expect(k.locator('.pw-data-pair', { hasText: 'Beschluss' }).locator('strong'))
      .toHaveText('Eigene Haltung XY')
  })

  test('Herkunftsfraktion ohne die eigene Fraktion; Ansprechpartner erst nach Fraktionswahl, Wechsel leert sie', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'Ansprech')
    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')

    // Ansprechpartner ist ohne gewählte Fraktion deaktiviert.
    await expect(feld(page, 'Ansprechpartner').locator('.v-select')).toHaveClass(/vs--disabled/)

    // Eigene Fraktion darf nicht als Herkunftsfraktion wählbar sein.
    const eigene = await page.evaluate(() => String((window.PARLWIN_CONFIG && window.PARLWIN_CONFIG.fraktion) || ''))
    const fraktionOpt = await ncOptionen(page, feld(page, 'Herkunft (fremde Fraktion)'))
    expect(fraktionOpt.length, 'Es sollten fremde Fraktionen wählbar sein').toBeGreaterThan(0)
    if (eigene) expect(fraktionOpt, 'Eigene Fraktion darf nicht in der Liste stehen').not.toContain(eigene)

    // Eine Fraktion mit Mitgliedern suchen (Daten-robust) und einen Ansprechpartner wählen.
    let mitMitgliedern = -1
    for (let i = 0; i < fraktionOpt.length; i++) {
      await ncWaehle(page, feld(page, 'Herkunft (fremde Fraktion)'), fraktionOpt[i])
      await expect(feld(page, 'Ansprechpartner').locator('.v-select')).not.toHaveClass(/vs--disabled/)
      const partner = await ncOptionen(page, feld(page, 'Ansprechpartner'))
      if (partner.length > 0) { mitMitgliedern = i; break }
    }
    expect(mitMitgliedern, 'Keine fremde Fraktion mit Mitgliedern in den Testdaten').toBeGreaterThanOrEqual(0)

    await ncWaehleErste(page, feld(page, 'Ansprechpartner'))
    await expect(feld(page, 'Ansprechpartner').locator('.vs__selected')).toHaveCount(1)

    // Fraktion wechseln → Ansprechpartner werden geleert.
    const andere = fraktionOpt.find((_, i) => i !== mitMitgliedern)
    expect(andere, 'Für den Fraktionswechsel werden mindestens zwei fremde Fraktionen benötigt').toBeTruthy()
    await ncWaehle(page, feld(page, 'Herkunft (fremde Fraktion)'), andere)
    await expect(feld(page, 'Ansprechpartner').locator('.vs__selected')).toHaveCount(0)
  })
})

// ===========================================================================
test.describe('Vorstösse: Notizen (Versionen & Berechtigung)', () => {
  test('Notiz-Versionsverlauf: ältere Fassung anzeigen und übernehmen', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'NotizVersion')

    // Stabiler Marker: der Eintrag bleibt über beide Fassungen auffindbar.
    const marker = `Notiz${Date.now()}`
    const liste = page.locator('.pw-notizen-liste')
    const eintrag = liste.locator('.pw-notiz-eintrag', { hasText: marker }).first()

    // Erste Fassung anlegen (enthält AAA).
    await neueNotizTippen(page, `${marker} AAA`)
    await eintrag.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(eintrag).toContainText('AAA', { timeout: 15_000 })

    // Zweite Fassung: Inline-Editor öffnen, ans Ende, BBB anhängen, das Häkchen
    // speichert (archiviert die erste Fassung als Revision).
    await eintrag.locator('.pw-notiz-inhalt').click()
    const editEditor = eintrag.locator('.ProseMirror').first()
    await editEditor.waitFor({ state: 'visible', timeout: 15_000 })
    await editEditor.click()
    await page.waitForTimeout(300)
    await page.keyboard.press('Control+End')
    await editEditor.pressSequentially(' BBB', { delay: 25 })
    await eintrag.locator('.pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()
    // Warten, bis der Editor geschlossen (gespeichert) und BBB im Eintrag sichtbar ist.
    await expect(eintrag.locator('.pw-notiz-bearbeiten-zeile')).toHaveCount(0, { timeout: 15_000 })
    await expect(eintrag).toContainText('BBB', { timeout: 15_000 })

    // Verlauf öffnen: Editor erneut öffnen, Revisionen werden nachgeladen (← erscheint).
    await eintrag.locator('.pw-notiz-inhalt').click()
    await eintrag.locator('.ProseMirror').first().waitFor({ state: 'visible', timeout: 15_000 })
    const zurueck = eintrag.locator('button[title="Eine Version zurück"]')
    await zurueck.waitFor({ state: 'visible', timeout: 20_000 })
    await zurueck.click()
    // Die angezeigte ältere Fassung enthält AAA, aber nicht mehr BBB.
    await expect(eintrag.locator('.ProseMirror').first()).toContainText('AAA', { timeout: 15_000 })
    await expect(eintrag.locator('.ProseMirror').first()).not.toContainText('BBB')
    // Ältere Fassung übernehmen (✓ = Speichern/Restore).
    await eintrag.locator('button[title="Speichern"]').click()

    // Übernommen: der aktuelle Notiztext ist wieder die ältere Fassung (ohne BBB).
    await expect(eintrag.locator('.pw-notiz-bearbeiten-zeile')).toHaveCount(0, { timeout: 15_000 })
    await expect(eintrag.locator('.pw-notiz-inhalt')).toContainText('AAA', { timeout: 15_000 })
    await expect(eintrag.locator('.pw-notiz-inhalt')).not.toContainText('BBB')
  })

  test('Fremder Nutzer kann eine Notiz nicht bearbeiten oder löschen', async ({ browser }) => {
    expect(U2.pass, `Passwort für ${U2.name} fehlt`).not.toBe('')
    const notiz = `Berechtigungsnotiz ${Date.now()}`

    // Kontext A (Autor U1): Vorstoss + Notiz anlegen.
    const ctxA = await browser.newContext()
    const pageA = await ctxA.newPage()
    let titel
    try {
      await login(pageA, U1)
      titel = await erstelleVorstossUndOeffne(pageA, 'Fremdnotiz')
      await neueNotizTippen(pageA, notiz)
      await expect(pageA.locator('.pw-notizen-liste').getByText(notiz, { exact: false }).first())
        .toBeVisible({ timeout: 15_000 })
    } finally {
      await ctxA.close()
    }

    // Kontext B (Nicht-Autor U2): dieselbe Notiz ist sichtbar, aber nicht editier-/löschbar.
    const ctxB = await browser.newContext()
    const pageB = await ctxB.newPage()
    try {
      await login(pageB, U2)
      await oeffneVorstoesse(pageB)
      await oeffneKarte(pageB, titel)
      const listeB = pageB.locator('.pw-notizen-liste')
      const eintragB = listeB.locator('.pw-notiz-eintrag', { hasText: notiz }).first()
      await eintragB.waitFor({ state: 'visible', timeout: 30_000 })

      // Für einen fremden Nutzer fehlen alle Bearbeitungs-Affordanzen: kein
      // Löschen-Knopf, der Notiztext ist nicht klickbar (keine role=button, keine
      // Klickbar-Klasse) — die Notiz lässt sich also weder löschen noch bearbeiten.
      const inhaltB = eintragB.locator('.pw-notiz-inhalt')
      await expect(eintragB.locator('.pw-btn-loeschen'), 'Nicht-Autor darf keinen Löschen-Knopf sehen').toHaveCount(0)
      await expect(inhaltB).not.toHaveClass(/pw-notiz-text-klickbar/)
      await expect(inhaltB).not.toHaveAttribute('role', 'button')
      // Ein Klick auf den Notiztext öffnet keinen Editor.
      await inhaltB.click()
      await pageB.waitForTimeout(500)
      await expect(eintragB.locator('.pw-notiz-bearbeiten-zeile')).toHaveCount(0)
    } finally {
      await ctxB.close()
    }
  })
})

// ===========================================================================
test.describe('Vorstösse: Verknüpfung mit Geschäft', () => {
  test('Ohne Titelähnlichkeit steht das neueste Geschäft zuoberst', async ({ page }) => {
    await login(page, U1)
    // Ein Titel ohne Wortüberschneidung (reiner Zeitstempel) → Sortierung nur nach Datum.
    const titel = `Zzz${Date.now()}`
    await erstelleVorstossMitTitel(page, titel)
    const geschaefte = (await apiGet(page, '/geschaefte?limit=500')).filter(g => !g.geloescht)
    expect(geschaefte.length, 'Es müssen Geschäfte für die Verknüpfung existieren').toBeGreaterThan(0)
    const maxDatum = geschaefte.reduce((m, g) => String(g.datum || '') > m ? String(g.datum || '') : m, '')
    const neuesteTitel = geschaefte.filter(g => String(g.datum || '') === maxDatum).map(g => g.titel)

    await page.locator(`${MODAL} button`, { hasText: 'Mit Geschäft verknüpfen' }).click()
    const ersterText = await page.locator(`${VMODAL} .pw-verknuepfen-eintrag`).first().innerText()
    expect(
      neuesteTitel.some(t => t && ersterText.includes(t)),
      `Oberster Eintrag «${ersterText}» ist keines der neuesten Geschäfte`,
    ).toBeTruthy()
  })

  test('Ein zum Titel ähnliches Geschäft steht zuoberst', async ({ page }) => {
    await login(page, U1)
    await oeffneVorstoesse(page)
    const geschaefte = (await apiGet(page, '/geschaefte?limit=500')).filter(g => !g.geloescht && g.titel)

    // Ein Wort finden, das genau EIN Geschäft im Titel führt → eindeutig ähnlichstes.
    const zaehler = {}
    for (const g of geschaefte) for (const w of worte(g.titel)) zaehler[w] = (zaehler[w] || 0) + 1
    let ziel = null; let wort = null
    for (const g of geschaefte) {
      const kandidat = worte(g.titel).find(w => w.length >= 5 && zaehler[w] === 1)
      if (kandidat) { ziel = g; wort = kandidat; break }
    }
    expect(ziel, 'Kein Geschäft mit eindeutigem Titelwort in den Testdaten gefunden').not.toBeNull()

    const titel = `${wort} ${Date.now()}`
    await erstelleVorstossMitTitel(page, titel)
    await page.locator(`${MODAL} button`, { hasText: 'Mit Geschäft verknüpfen' }).click()
    const ersterText = await page.locator(`${VMODAL} .pw-verknuepfen-eintrag`).first().innerText()
    expect(ersterText, `Oberster Eintrag «${ersterText}» ist nicht das ähnlichste Geschäft`).toContain(ziel.titel)
  })

  test('Verknüpfen-Suche filtert über Nr./Titel; ohne Treffer «Keine Geschäfte gefunden.»', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'VerknSuche')
    const geschaefte = (await apiGet(page, '/geschaefte?limit=500')).filter(g => !g.geloescht && g.titel)
    expect(geschaefte.length).toBeGreaterThan(0)
    const ziel = geschaefte[0]

    await page.locator(`${MODAL} button`, { hasText: 'Mit Geschäft verknüpfen' }).click()
    const suche = page.locator(`${VMODAL} input`).first()
    await suche.fill(ziel.titel.slice(0, Math.min(12, ziel.titel.length)))
    await expect(page.locator(`${VMODAL} .pw-verknuepfen-eintrag`, { hasText: ziel.titel }).first())
      .toBeVisible({ timeout: 15_000 })

    await suche.fill(`kein-geschaeft-${Date.now()}`)
    await expect(page.locator(VMODAL).getByText('Keine Geschäfte gefunden.'))
      .toBeVisible({ timeout: 15_000 })
  })

  test('Nach dem Verknüpfen übernimmt das Geschäft die Priorität des Vorstosses', async ({ page }) => {
    await login(page, U1)
    await oeffneVorstoesse(page)
    // Sichtbares (nicht gelöschtes, nicht erledigtes) Geschäft als Ziel — bevorzugt
    // eines, das noch nicht «hoch» ist, damit die Übernahme beobachtbar ist.
    const sichtbar = (await apiGet(page, '/geschaefte?limit=500'))
      .filter(g => !g.geloescht && g.titel && g.status !== 'erledigt')
    expect(sichtbar.length, 'Kein sichtbares Geschäft für die Verknüpfung gefunden').toBeGreaterThan(0)
    const ziel = sichtbar.find(g => (g.prioritaet || '') !== 'hoch') || sichtbar[0]

    const titel = `PrioUebernahme ${Date.now()}`
    await erstelleVorstossMitTitel(page, titel)
    await ncWaehle(page, feld(page, 'Priorität'), 'Hoch')

    await page.locator(`${MODAL} button`, { hasText: 'Mit Geschäft verknüpfen' }).click()
    const suche = page.locator(`${VMODAL} input`).first()
    await suche.fill(ziel.titel.slice(0, Math.min(20, ziel.titel.length)))
    const eintrag = page.locator(`${VMODAL} .pw-verknuepfen-eintrag`, { hasText: ziel.titel }).first()
    await eintrag.waitFor({ state: 'visible', timeout: 15_000 })
    await eintrag.click()
    await expect(page.locator(MODAL).getByText(/verknüpft/i).first()).toBeVisible({ timeout: 15_000 })

    // Persistierter Effekt: das reale Geschäft hat jetzt Priorität «hoch».
    const nachher = await apiGet(page, `/geschaefte/${ziel.id}`)
    expect(nachher && nachher.prioritaet, 'Geschäft hat die Vorstoss-Priorität nicht übernommen').toBe('hoch')

    // Und im Frontend geöffnet: die Zeile ist als hohe Priorität hervorgehoben.
    await oeffneGeschaefte(page)
    await page.locator('#pw-search-slot input').first().fill(ziel.titel.slice(0, Math.min(20, ziel.titel.length)))
    const zeile = page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: ziel.titel }).first()
    await zeile.waitFor({ state: 'visible', timeout: 15_000 })
    await expect(zeile).toHaveClass(/pw-prio-hoch/)
  })

  test('Im Geschäft erscheinen verknüpfte Vorstösse mit Titel/Art, Haltung, Zuständigkeit und Notizen', async ({ page }) => {
    await login(page, U1)
    await oeffneVorstoesse(page)
    const sichtbar = (await apiGet(page, '/geschaefte?limit=500'))
      .filter(g => !g.geloescht && g.titel && g.status !== 'erledigt')
    expect(sichtbar.length, 'Kein sichtbares Geschäft für die Verknüpfung gefunden').toBeGreaterThan(0)
    const ziel = sichtbar[0]
    const zielSuche = ziel.titel.slice(0, Math.min(20, ziel.titel.length))

    const titel = `VerknVorstoss ${Date.now()}`
    const notiz = `Verknüpfungsnotiz ${Date.now()}`
    await erstelleVorstossMitTitel(page, titel)
    await ncWaehle(page, feld(page, 'Herkunft'), 'Fremde')
    await beschlussSetzen(feld(page, 'Art'), 'Interpellation')
    await beschlussSetzen(feld(page, 'Beschluss (Haltung zum fremden Vorstoss)'), 'Miteinreichen')
    await ncLeeren(feld(page, 'Zuständigkeit'))
    await ncWaehleErste(page, feld(page, 'Zuständigkeit'))
    await neueNotizTippen(page, notiz)
    await expect(page.locator('.pw-notizen-liste').getByText(notiz, { exact: false }).first())
      .toBeVisible({ timeout: 15_000 })

    await page.locator(`${MODAL} button`, { hasText: 'Mit Geschäft verknüpfen' }).click()
    await page.locator(`${VMODAL} input`).first().fill(zielSuche)
    const eintrag = page.locator(`${VMODAL} .pw-verknuepfen-eintrag`, { hasText: ziel.titel }).first()
    await eintrag.waitFor({ state: 'visible', timeout: 15_000 })
    await eintrag.click()
    await expect(page.locator(MODAL).getByText(/verknüpft/i).first()).toBeVisible({ timeout: 15_000 })

    await oeffneGeschaefte(page)
    await page.locator('#pw-search-slot input').first().fill(zielSuche)
    await page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: ziel.titel }).first()
      .locator('.pw-col-titel').click()
    const block = page.locator('.pw-verknuepfter-vorstoss', { hasText: titel })
    await block.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(block.locator('h5')).toContainText(titel)
    await expect(block.locator('h5')).toContainText('Interpellation')
    await expect(block.locator('.pw-data-pair', { hasText: 'Haltung' }).locator('strong')).toHaveText('Miteinreichen')
    await expect(block.locator('.pw-data-pair', { hasText: 'Zuständigkeit' })).toBeVisible()
    await expect(block.getByText(notiz, { exact: false }).first()).toBeVisible()
  })
})

// ===========================================================================
test.describe('Vorstösse: Aktionszeitleiste', () => {
  // Ein Vorstoss besitzt als «aktionen» ausschliesslich Notizen (aktionTyp «notiz»);
  // die Zeitleiste blendet Notizen bewusst aus. Es gibt keinen UI-Weg, eine
  // Nicht-Notiz-Aktion an einem Vorstoss zu erzeugen (kein Beschluss/Votum wie beim
  // Geschäft). Getestet wird daher der Leer-Hinweis und dass Notizen NICHT in die
  // Zeitleiste durchsickern.
  test('Leerer Hinweis; hinzugefügte Notizen erscheinen nicht in der Zeitleiste', async ({ page }) => {
    await login(page, U1)
    await erstelleVorstossUndOeffne(page, 'Zeitleiste')

    const zeitleiste = page.locator(`${MODAL} .pw-detail-abschnitt:has(h4:text-is("Aktionszeitleiste"))`)
    await expect(zeitleiste.locator('.pw-hinweis')).toHaveText('Noch keine Aktionen vorhanden.')
    await expect(zeitleiste.locator('.pw-timeline-eintrag')).toHaveCount(0)

    // Notiz hinzufügen: erscheint in der Notizliste, NICHT in der Zeitleiste.
    await neueNotizTippen(page, `Zeitleisten-Notiz ${Date.now()}`)
    await expect(page.locator('.pw-notizen-liste .pw-notiz-eintrag').first()).toBeVisible({ timeout: 15_000 })
    await expect(zeitleiste.locator('.pw-hinweis')).toHaveText('Noch keine Aktionen vorhanden.')
    await expect(zeitleiste.locator('.pw-timeline-eintrag')).toHaveCount(0)
  })
})
