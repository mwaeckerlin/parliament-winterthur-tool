import { test, expect } from '@playwright/test'

/**
 * E2E-Datenfluss der Vorstösse im echten Browser gegen das reale System
 * (Nextcloud + DB + API). Deckt genau die Fehlklasse ab, die ein isolierter
 * Unit-Test mit Mocks NICHT fing: ein gespeicherter Vorstoss gab beim Laden nur
 * die id zurück (Entity ohne JsonSerializable) → alle Felder leer.
 *
 * Geprüft über den GANZEN Pfad (Speichern → DB → API → Laden → Anzeige):
 *  - neuer Vorstoss mit Titel/Art wird gespeichert und in der Übersicht angezeigt,
 *  - beim erneuten Öffnen sind die Felder NICHT leer (kein Datenverlust),
 *  - eine Notiz lässt sich hinzufügen und erscheint,
 *  - der Vorstoss lässt sich mit einem Geschäft verknüpfen (Vorstufe → Geschäft).
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const USER = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }
const stamp = Date.now()
const TITEL = `E2E-Vorstoss ${stamp}`
const NOTIZ = `E2E-Notiz ${stamp}`

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

/**
 * Legt einen Vorstoss mit eindeutigem Titel an und lässt die Bearbeitung offen.
 * Jeder Test ist damit self-contained (kein test-übergreifender Zustand, der bei
 * Playwright-Parallelität/Retries — jeder Worker lädt das Modul neu — bricht).
 * @returns {Promise<string>} der verwendete Titel
 */
async function erstelleVorstossUndOeffne(page, name) {
  const titel = `E2E-Vorstoss ${name} ${Date.now()}`
  await oeffneVorstoesse(page)
  await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
  const modal = page.locator('.pw-modal').first()
  await modal.locator('input.pw-input').first().waitFor({ state: 'visible', timeout: 30_000 })
  await modal.locator('input.pw-input').first().fill(titel)
  // Die Neu-Maske sammelt nur die Eingaben — erst «Speichern» legt den Vorstoss
  // an und die Maske geht unmittelbar in die Bearbeitung über.
  await modal.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
  await page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' })
    .waitFor({ state: 'visible', timeout: 30_000 })
  return titel
}

/** Öffnet den «+ Neue Notiz»-Editor im offenen Vorstoss-Dialog und tippt Text. */
async function neueNotizTippen(page, text) {
  // Klassen-Locator: das teleportierte Modal wird von getByRole nicht immer
  // aufgelöst, das gerenderte Element aber sicher über seine Klasse gefunden.
  const neuKnopf = page.locator('.pw-notizen-liste .pw-btn-neue-notiz')
  await neuKnopf.waitFor({ state: 'visible', timeout: 30_000 })
  await neuKnopf.click()
  const editor = page.locator('.pw-notizen-liste .ProseMirror').first()
  await editor.waitFor({ state: 'visible', timeout: 30_000 })
  await editor.click()
  // ProseMirror/Tiptap braucht nach dem Klick einen Moment, bis der Fokus greift;
  // ohne Delay verschluckt keyboard.type führende Zeichen (Tipprace).
  await page.waitForTimeout(300)
  await editor.pressSequentially(text, { delay: 25 })
  // Speichern nur über das Häkchen — kein Blur-Save mehr.
  await page.locator('.pw-notizen-liste .pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()
}

test.describe('Vorstösse: Datenfluss end-to-end (kein Datenverlust)', () => {
  // JavaScript-Fehler der Seite sammeln — ein Fehler beim Erstellen/Speichern
  // würde sonst nur als späterer, irreführender Timeout sichtbar.
  let jsFehler
  test.beforeEach(({ page }) => {
    jsFehler = []
    page.on('pageerror', (e) => jsFehler.push(e.message))
  })

  test.beforeAll(() => {
    expect(USER.pass, `Passwort für ${USER.name} fehlt`).not.toBe('')
  })

  test('Neuer Vorstoss: die volle Maske öffnet sofort, Titel bleibt nach Schliessen und erneutem Öffnen erhalten', async ({ page }) => {
    await login(page, USER)
    await oeffneVorstoesse(page)

    // «+ Neuer Vorstoss» öffnet direkt die vollständige Maske. Sie legt noch
    // nichts an, sondern sammelt die Eingaben und bietet unten «Speichern» und
    // «Abbrechen»; ein ✕ gibt es im Neu-Modus nicht.
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.locator('input.pw-input').first().waitFor({ state: 'visible', timeout: 30_000 })
    const fuss = modal.locator('.pw-modal-footer')
    await expect(fuss.getByRole('button', { name: 'Speichern' })).toBeVisible()
    await expect(fuss.getByRole('button', { name: 'Abbrechen' })).toBeVisible()
    await expect(modal.locator('.pw-btn-schliessen'), 'Im Neu-Modus darf es kein ✕ geben').toHaveCount(0)
    await modal.locator('input.pw-input').first().fill(TITEL)
    await fuss.getByRole('button', { name: 'Speichern' }).click()

    // Danach ist die Bearbeitung offen (jede Eingabe speichert sofort) —
    // es gibt KEINE Abbrechen/Speichern-Buttons mehr, dafür wieder das ✕.
    await expect(
      page.locator('.pw-modal h3', { hasText: 'Vorstoss bearbeiten' }),
      `Nach dem Erstellen öffnet die Bearbeitung nicht (JS-Fehler: ${jsFehler.join(' | ') || 'keine'})`,
    ).toBeVisible({ timeout: 15_000 })
    await expect(
      page.locator('.pw-modal input.pw-input').first(),
      'Die Bearbeitung zeigt den Titel nicht',
    ).toHaveValue(TITEL, { timeout: 15_000 })
    await expect(page.locator('.pw-modal .pw-modal-footer')).toHaveCount(0)
    await expect(page.locator('.pw-modal .pw-btn-schliessen')).toBeVisible()
    expect(jsFehler, `JavaScript-Fehler auf der Seite: ${jsFehler.join(' | ')}`).toEqual([])

    // Schliessen über ✕ — der Vorstoss ist gespeichert und erscheint in der Übersicht.
    await page.locator('.pw-modal .pw-btn-schliessen').click()
    const karte = page.locator('.pw-data-card', { hasText: TITEL })
    await expect(karte, 'Vorstoss erscheint nicht in der Übersicht').toBeVisible({ timeout: 30_000 })

    // Der eigentliche Datenverlust-Bug: erneut öffnen – der Titel darf NICHT leer sein.
    await karte.click()
    await expect(
      page.locator('.pw-modal input.pw-input').first(),
      'Beim Öffnen ist der Titel leer – Datenverlust',
    ).toHaveValue(TITEL, { timeout: 15_000 })
  })

  test('Notiz speichert über das Häkchen und erscheint im Vorstoss', async ({ page }) => {
    await login(page, USER)
    await erstelleVorstossUndOeffne(page, 'Notiz')

    // Geteilte Notizen-Komponente (wie beim Geschäft): erst «+ Neue Notiz», dann
    // im geöffneten Editor tippen, danach speichert das Häkchen (kein Blur, kein Autosave).
    await neueNotizTippen(page, NOTIZ)

    await expect(
      page.locator('.pw-notizen-liste .pw-notiz-eintrag').getByText(NOTIZ, { exact: false }).first(),
      'Notiz erscheint nach dem Speichern nicht',
    ).toBeVisible({ timeout: 15_000 })
    expect(jsFehler, `JavaScript-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Notiz bearbeiten, löschen und wiederherstellen — identisch zum Geschäft', async ({ page }) => {
    const bearbeitet = `${NOTIZ} bearbeitet`
    await login(page, USER)
    await erstelleVorstossUndOeffne(page, 'NotizEdit')
    await neueNotizTippen(page, NOTIZ)

    const liste = page.locator('.pw-notizen-liste')
    const eintrag = liste.locator('.pw-notiz-eintrag', { hasText: NOTIZ }).first()
    await eintrag.waitFor({ state: 'visible', timeout: 30_000 })

    // Bearbeiten: Klick auf den Notiztext öffnet den Inline-Editor; Text ergänzen, das Häkchen speichert (Version).
    await eintrag.locator('.pw-notiz-inhalt').click()
    const editEditor = eintrag.locator('.ProseMirror').first()
    await editEditor.waitFor({ state: 'visible', timeout: 15_000 })
    await editEditor.click()
    await page.waitForTimeout(300)
    await page.keyboard.press('Control+End') // ans Textende, damit angehängt statt vorangestellt wird
    await editEditor.pressSequentially(' bearbeitet', { delay: 25 })
    await eintrag.locator('.pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()
    await expect(
      liste.getByText(bearbeitet, { exact: false }).first(),
      'Bearbeitete Notiz erscheint nicht',
    ).toBeVisible({ timeout: 15_000 })

    // Löschen (Soft-Delete): erscheint als Vermerk mit Wiederherstellen in der
    // Aktionszeitleiste, nicht mehr in der Notizenliste.
    const zeitleiste = page.locator('.pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })
    await liste.locator('.pw-notiz-eintrag', { hasText: bearbeitet }).first()
      .locator('.pw-btn-loeschen').click()
    await expect(
      zeitleiste.getByText('hat seine Notiz gelöscht', { exact: false }).first(),
      'Gelöschte Notiz erscheint nicht als Vermerk in der Aktionszeitleiste',
    ).toBeVisible({ timeout: 15_000 })
    await expect(
      liste.locator('.pw-notiz-eintrag', { hasText: bearbeitet }),
      'Gelöschte Notiz steht noch in der Notizenliste',
    ).toHaveCount(0)

    // Wiederherstellen (Undo) über die Aktionszeitleiste: der Text kommt in die Liste zurück.
    await zeitleiste.locator('button[title="Löschen rückgängig machen"]').first().click()
    await expect(
      liste.getByText(bearbeitet, { exact: false }).first(),
      'Wiederhergestellte Notiz erscheint nicht',
    ).toBeVisible({ timeout: 15_000 })
  })

  test('Vorstoss mit einem Geschäft verknüpfen schliesst ihn ab', async ({ page }) => {
    await login(page, USER)
    await erstelleVorstossUndOeffne(page, 'Verknuepfen')

    const verknuepfenBtn = page.locator('.pw-modal button', { hasText: 'Mit Geschäft verknüpfen' })
    await verknuepfenBtn.waitFor({ state: 'visible', timeout: 30_000 })
    await verknuepfenBtn.click()
    // Auswahl-Dialog: erstes vorgeschlagenes Geschäft wählen.
    const ersterVorschlag = page.locator('.pw-verknuepfen-liste .pw-verknuepfen-eintrag').first()
    await ersterVorschlag.waitFor({ state: 'visible', timeout: 30_000 })
    await ersterVorschlag.click()

    // Zurück im Vorstoss-Dialog: der Vorstoss ist als verknüpft/abgeschlossen markiert.
    await expect(
      page.locator('.pw-modal').getByText(/verknüpft/i).first(),
      'Verknüpfung wird nicht bestätigt',
    ).toBeVisible({ timeout: 15_000 })
  })
})
