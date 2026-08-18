import { test, expect } from '@playwright/test'

/**
 * Feld-Inventar aller «+ Neu»-Einstiege im echten Browser.
 *
 * Zweck: Ein Anlegen-Einstieg muss EXAKT dieselbe vollständige Maske öffnen wie
 * die Bearbeitung desselben Objekts — kein reduziertes Formular, keine später
 * nachgereichten Felder. Die Listen unten sind das verbindliche Inventar:
 * verschwindet ein Feld, wird dieser Test rot und benennt das fehlende Feld.
 * Genau diese Prüfung fehlte, als der Erstellen-Dialog der Vorstösse unbemerkt
 * auf ein einzelnes Titel-Feld zusammenschrumpfte.
 *
 * Beim Erfassen wird noch nichts angelegt: die Maske sammelt die Eingaben, erst
 * «Speichern» legt an und die Maske geht unmittelbar in die Bearbeitung über.
 * Nur die Bereiche, die zwingend eine bestehende ID brauchen (Notizen,
 * Dokumente, Zeitleiste, Beschluss, Votum, Geschäfts-Verknüpfung), fehlen im
 * Neu-Modus — an ihrer Stelle steht ein Hinweis. Die Tests prüfen deshalb beides:
 * das Inventar der Neu-Maske UND dass die ID-gebundenen Bereiche nach dem
 * Speichern da sind.
 *
 * Jedes Feld wird auf EXISTENZ und auf FUNKTION geprüft.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

// --- Verbindliches Feld-Inventar ------------------------------------------

/**
 * Vorstoss: Felder, die schon beim Erfassen sichtbar sind, in genau dieser
 * Reihenfolge von oben nach unten.
 */
const VORSTOSS_FELDER_NEU = [
  'Titel *',
  'Art',
  'Herkunft',
  'Status',
  'Priorität',
  'Zuständigkeit',
  'Inhalt',
]

/**
 * Vorstoss: Bereiche, die an einer bestehenden ID hängen. Sie erscheinen erst
 * nach dem Speichern — im Neu-Modus steht an ihrer Stelle ein Hinweis.
 */
const VORSTOSS_FELDER_NACH_SPEICHERN = [
  'Dokument',
  'Notizen',
  'Aktionszeitleiste',
  'Geschäft',
]

/** Vorstoss: vollständiges Inventar der Bearbeitung, von oben nach unten. */
const VORSTOSS_FELDER = [...VORSTOSS_FELDER_NEU, ...VORSTOSS_FELDER_NACH_SPEICHERN]

/** Vorstoss: zusätzliche Felder, sobald die Herkunft «Fremde» gewählt ist. */
const VORSTOSS_FELDER_FREMDE = [
  'Beschluss (Haltung zum fremden Vorstoss)',
  'Herkunft (fremde Fraktion)',
  'Ansprechpartner',
]

/** Geschäft: Zeilen der öffentlichen Informationstabelle, in dieser Reihenfolge. */
const GESCHAEFT_INFO_ZEILEN = [
  'Nummer',
  'Typ',
  'Status',
  'Fraktionsstatus',
  'Datum',
  'Kommission',
  'Letzte externe Änderung',
  'Letzte Fraktionsentscheidung',
]

/** Geschäft: Bedienfelder, die schon beim Erfassen sichtbar sind. */
const GESCHAEFT_FELDER_NEU = [
  'Priorität',
  'Zuständigkeit',
]

/**
 * Geschäft: Bereiche, die an einer bestehenden ID hängen und deshalb erst nach
 * dem Speichern erscheinen. «Votum im Rat» fehlt hier bewusst: es ist zusätzlich
 * zuständigen-gebunden (leer nur für die zuständige Person sichtbar) und wird
 * darum separat geprüft.
 */
const GESCHAEFT_FELDER_NACH_SPEICHERN = [
  'Beschluss erfassen',
  'Notizen',
  'Dokumente zum Geschäft',
]

/** Geschäft: vollständiges Inventar der fraktionsinternen Bearbeitung, in dieser Reihenfolge. */
const GESCHAEFT_FELDER = [...GESCHAEFT_FELDER_NEU, ...GESCHAEFT_FELDER_NACH_SPEICHERN]

/** Sitzung: Bedienelemente des vollständigen Formulars. */
const SITZUNG_PLATZHALTER = [
  'Titel eingeben …',
  'Ort',
  'Zweck / Beschreibung …',
]

// --- Basis-Helfer (identisch zu den übrigen Specs) --------------------------

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

async function oeffneAnsicht(page, name, knopf) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle')
  await page.getByText(name, { exact: true }).first().click()
  await page.getByRole('button', { name: knopf }).waitFor({ state: 'visible', timeout: 30_000 })
}

/** vue-select overlay-fest öffnen (ArrowDown bei fokussiertem Suchfeld, Fallback Maus-Klick). */
async function ncOpen(page, root) {
  if (await root.evaluate((el) => el.classList.contains('vs--open')).catch(() => false)) return
  const feldEl = root.locator('.vs__search, .vs__dropdown-toggle').first()
  await feldEl.scrollIntoViewIfNeeded().catch(() => {})
  await feldEl.focus().catch(() => {})
  await page.keyboard.press('ArrowDown')
  try {
    await expect(root).toHaveClass(/vs--open/, { timeout: 4_000 })
  } catch (e) {
    const box = await root.locator('.vs__dropdown-toggle').first().boundingBox()
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
    await expect(root).toHaveClass(/vs--open/, { timeout: 6_000 })
  }
}

async function ncPick(page, root, label) {
  await ncOpen(page, root)
  const menu = page.locator('.vs__dropdown-menu')
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  await menu.locator('li.vs__dropdown-option', { hasText: label }).first().click()
}

/** Alle Feld-Beschriftungen (PwField) eines Bereichs, getrimmt. */
async function feldLabels(wurzel) {
  return (await wurzel.locator('.pw-field-label').allTextContents()).map((s) => s.trim())
}

/** Das PwField zu einer Beschriftung — exakter Textvergleich, damit «Herkunft» nicht «Herkunft (fremde Fraktion)» trifft. */
function feld(wurzel, label) {
  return wurzel.locator(`.pw-field:has(> .pw-field-label:text-is(${JSON.stringify(label)}))`).first()
}

/** «Speichern» der Neu-Maske (links, hervorgehoben) — erst dieser Klick legt an. */
function speichernKnopf(wurzel) {
  return wurzel.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' })
}

/** «Abbrechen» der Neu-Maske (rechts) — der einzige Weg, die Eingaben zu verwerfen. */
function abbrechenKnopf(wurzel) {
  return wurzel.locator('.pw-modal-footer').getByRole('button', { name: 'Abbrechen' })
}

// ===========================================================================
// Vorstösse
// ===========================================================================

test.describe('«+ Neu» öffnet dieselbe vollständige Maske wie die Bearbeitung', () => {
  // Desktop-Tabellen/Dialoge werden unterhalb ~52em per Container-Query ausgeblendet.
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Neuer Vorstoss: jedes Feld der Bearbeitung ist vorhanden, ID-gebundene Bereiche nach dem Speichern', async ({ page }) => {
    const titel = `E2E Inventar ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()

    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(modal.locator('h3')).toHaveText('Neuer Vorstoss')

    // Neu-Modus: alle Erfassungsfelder da, in der Reihenfolge der Bearbeitung.
    let labels = await feldLabels(modal)
    for (const f of VORSTOSS_FELDER_NEU) {
      expect(labels, `Feld «${f}» fehlt im Dialog «Neuer Vorstoss»`).toContain(f)
    }
    expect(labels.filter((l) => VORSTOSS_FELDER.includes(l))).toEqual(VORSTOSS_FELDER_NEU)

    // Die ID-gebundenen Bereiche brauchen eine bestehende ID; statt ihnen steht
    // hier ein Hinweis, dass sie nach dem Speichern bereitstehen.
    for (const f of VORSTOSS_FELDER_NACH_SPEICHERN) {
      expect(labels, `Bereich «${f}» darf vor dem Speichern nicht erscheinen`).not.toContain(f)
    }
    await expect(modal.locator('.pw-btn-neue-notiz')).toHaveCount(0)
    await expect(modal.locator('.pw-dokumente')).toHaveCount(0)
    await expect(modal.locator('.pw-hinweis', { hasText: 'sobald der Vorstoss gespeichert ist' })).toBeVisible()

    // Unten «Speichern» (links, hervorgehoben) und «Abbrechen» (rechts); im
    // Neu-Modus gibt es kein ✕.
    await expect(speichernKnopf(modal), 'Ohne Titel muss «Speichern» gesperrt sein').toBeDisabled()
    await expect(abbrechenKnopf(modal)).toBeVisible()
    await expect(modal.locator('.pw-btn-schliessen')).toHaveCount(0)

    // Erst «Speichern» legt an — danach ist dieselbe Maske die Bearbeitung.
    await modal.locator('input.pw-input').first().fill(titel)
    await expect(speichernKnopf(modal)).toBeEnabled()
    await speichernKnopf(modal).click()
    await expect(modal.locator('h3')).toHaveText('Vorstoss bearbeiten', { timeout: 30_000 })

    // Jetzt ist das volle Inventar da, in genau der bekannten Reihenfolge.
    labels = await feldLabels(modal)
    for (const f of VORSTOSS_FELDER) {
      expect(labels, `Feld «${f}» fehlt nach dem Speichern`).toContain(f)
    }
    expect(labels.filter((l) => VORSTOSS_FELDER.includes(l))).toEqual(VORSTOSS_FELDER)

    // Die ID-gebundenen Bereiche sind wirklich funktionsfähig, nicht nur beschriftet.
    await expect(modal.locator('.pw-btn-neue-notiz')).toBeVisible()
    await expect(modal.locator('.pw-dokumente')).toBeVisible()
    // Der Knopf steckt in einem PwField-<label>; sein zugänglicher Name ist die
    // Beschriftung «Geschäft», nicht sein Text — deshalb über den Text ansprechen.
    await expect(modal.locator('button', { hasText: 'Mit Geschäft verknüpfen' })).toBeVisible()

    // Beim Bearbeiten schliesst das ✕ und es gibt keinen Fuss mehr.
    await expect(modal.locator('.pw-modal-footer')).toHaveCount(0)
    await modal.locator('.pw-btn-schliessen').click()
  })

  test('Neuer Vorstoss: Herkunft «Fremde» ergänzt Beschluss, Fraktion und Ansprechpartner', async ({ page }) => {
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })

    // Ohne «Fremde» dürfen die drei Zusatzfelder NICHT da sein.
    let labels = await feldLabels(modal)
    for (const f of VORSTOSS_FELDER_FREMDE) {
      expect(labels, `Feld «${f}» darf bei Herkunft «Eigene» nicht sichtbar sein`).not.toContain(f)
    }

    await ncPick(page, feld(modal, 'Herkunft').locator('.v-select'), 'Fremde')

    labels = await feldLabels(modal)
    for (const f of VORSTOSS_FELDER_FREMDE) {
      expect(labels, `Feld «${f}» fehlt nach Wahl der Herkunft «Fremde»`).toContain(f)
    }

    // Ansprechpartner ist ohne gewählte Fraktion gesperrt und wird danach nutzbar:
    // die Auswahl zeigt dann die Mitglieder genau dieser Fraktion.
    const ansprech = feld(modal, 'Ansprechpartner').locator('.v-select')
    await expect(ansprech).toHaveClass(/vs--disabled/)

    const fraktion = feld(modal, 'Herkunft (fremde Fraktion)').locator('.v-select')
    await ncOpen(page, fraktion)
    await page.locator('.vs__dropdown-menu li.vs__dropdown-option').first().click()

    await expect(ansprech, 'Ansprechpartner bleibt nach der Fraktionswahl gesperrt')
      .not.toHaveClass(/vs--disabled/)

    // Im Neu-Modus gibt es kein ✕ — verworfen wird über «Abbrechen».
    await abbrechenKnopf(modal).click()
  })

  test('Neuer Vorstoss: Eingaben werden gespeichert und die Bearbeitung zeigt dieselben Felder', async ({ page }) => {
    const titel = `E2E Neu-Dialog ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })

    // Die Maske sammelt die Eingaben; erst «Speichern» legt den Vorstoss an.
    await modal.locator('input.pw-input').first().fill(titel)
    await ncPick(page, feld(modal, 'Status').locator('.v-select'), 'Entwurf')
    await ncPick(page, feld(modal, 'Priorität').locator('.v-select'), 'Hoch')
    await speichernKnopf(modal).click()
    await expect(modal.locator('h3')).toHaveText('Vorstoss bearbeiten', { timeout: 30_000 })

    await modal.locator('.pw-btn-schliessen').click()
    await page.waitForLoadState('networkidle')

    const karte = page.locator('.pw-data-card', { hasText: titel }).first()
    await expect(karte).toBeVisible({ timeout: 30_000 })
    await expect(karte, 'Priorität «Hoch» wurde nicht gespeichert').toHaveClass(/pw-prio-hoch/)
    await karte.click()

    const wieder = page.locator('.pw-modal').first()
    await wieder.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(wieder.locator('h3')).toHaveText('Vorstoss bearbeiten')
    await expect(wieder.locator('input.pw-input').first()).toHaveValue(titel)
    const labels = await feldLabels(wieder)
    for (const f of VORSTOSS_FELDER) {
      expect(labels, `Feld «${f}» fehlt im Dialog «Vorstoss bearbeiten»`).toContain(f)
    }
  })

  test('Neuer Vorstoss: ein Klick neben die Maske verwirft nichts und schliesst nicht', async ({ page }) => {
    const titel = `E2E Klick-daneben ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })
    await modal.locator('input.pw-input').first().fill(titel)

    // Der Klick daneben darf im Neu-Modus NICHTS tun — weder schliessen noch
    // die bereits erfassten Eingaben verwerfen.
    await page.locator('.pw-modal-overlay').first().click({ position: { x: 5, y: 5 } })
    await expect(modal, 'Der Klick daneben hat die Neu-Maske geschlossen').toBeVisible()
    await expect(modal.locator('h3')).toHaveText('Neuer Vorstoss')
    await expect(modal.locator('input.pw-input').first(), 'Der erfasste Titel ging verloren')
      .toHaveValue(titel)

    // Und es wurde nichts angelegt, solange nicht gespeichert ist.
    await abbrechenKnopf(modal).click()
    await page.waitForLoadState('networkidle')
    // Nicht die globale Kartenzahl prüfen (nebenläufige Testinstanz): der Dialog ist
    // geschlossen und der abgebrochene Vorstoss darf nicht angelegt sein.
    await expect(page.locator('.pw-modal-overlay'), 'Abbrechen muss die Maske schliessen').toHaveCount(0, { timeout: 30_000 })
    await expect(page.locator('.pw-data-card', { hasText: titel }), 'Der abgebrochene Vorstoss wurde angelegt').toHaveCount(0)
  })

  test('Neuer Vorstoss: «Abbrechen» verwirft die Eingaben restlos', async ({ page }) => {
    const titel = `E2E Abbrechen ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })
    await modal.locator('input.pw-input').first().fill(titel)
    await abbrechenKnopf(modal).click()
    await page.waitForLoadState('networkidle')

    await expect(page.locator('.pw-modal-overlay')).toHaveCount(0)
    // Robust statt globaler Kartenzahl (nebenläufige Testinstanz): der konkret
    // erfasste Titel und ein «Ohne Titel»-Platzhalter dürfen nicht als Karte auftauchen.
    await expect(page.locator('.pw-data-card', { hasText: titel })).toHaveCount(0)
    await expect(page.locator('.pw-data-card', { hasText: 'Ohne Titel' })).toHaveCount(0)
  })

  test('Vorstoss lässt sich über die Karte löschen', async ({ page }) => {
    const titel = `E2E Loeschen ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
    await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
    const modal = page.locator('.pw-modal').first()
    await modal.waitFor({ state: 'visible', timeout: 30_000 })
    await modal.locator('input.pw-input').first().fill(titel)
    await speichernKnopf(modal).click()
    await expect(modal.locator('h3')).toHaveText('Vorstoss bearbeiten', { timeout: 30_000 })
    await modal.locator('.pw-btn-schliessen').click()
    await page.waitForLoadState('networkidle')

    const karte = page.locator('.pw-data-card', { hasText: titel }).first()
    await expect(karte).toBeVisible({ timeout: 30_000 })

    page.once('dialog', (d) => d.accept())
    await karte.getByRole('button', { name: 'Löschen' }).click()
    await expect(page.locator('.pw-data-card', { hasText: titel })).toHaveCount(0, { timeout: 30_000 })
  })

  // =========================================================================
  // Geschäfte
  // =========================================================================

  test('Eigenes Geschäft: «+ Eigenes Geschäft» öffnet die vollständige Detailmaske; ID-gebundene Bereiche nach dem Speichern', async ({ page }) => {
    const titel = `E2E Geschaeft Inventar ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Geschäfte', /Eigenes Geschäft/)
    await page.getByRole('button', { name: /Eigenes Geschäft/ }).click()

    const detail = page.locator('.pw-geschaeft-detail')
    await detail.waitFor({ state: 'visible', timeout: 30_000 })

    // Öffentliche Informationen: jede Zeile vorhanden.
    const infoZeilen = (await detail.locator('.pw-info-tabelle th, .pw-info-tabelle td:first-child').allTextContents())
      .map((s) => s.replace(/:$/, '').trim())
    for (const z of GESCHAEFT_INFO_ZEILEN) {
      expect(infoZeilen, `Zeile «${z}» fehlt in den öffentlichen Informationen`).toContain(z)
    }

    // Neu-Modus: die Erfassungsfelder sind da, die ID-gebundenen Bereiche nicht;
    // an ihrer Stelle steht ein Hinweis.
    const felderLesen = async () =>
      (await detail.locator('.pw-fraktion .pw-form-zeile > label').allTextContents()).map((s) => s.trim())
    let felder = await felderLesen()
    for (const f of GESCHAEFT_FELDER_NEU) {
      expect(felder, `Feld «${f}» fehlt in der fraktionsinternen Bearbeitung`).toContain(f)
    }
    for (const f of GESCHAEFT_FELDER_NACH_SPEICHERN) {
      expect(felder, `Bereich «${f}» darf vor dem Speichern nicht erscheinen`).not.toContain(f)
    }
    await expect(detail.locator('.pw-zustaendigkeit-select')).toBeVisible()
    await expect(detail.locator('.pw-beschluss-input')).toHaveCount(0)
    await expect(detail.locator('.pw-btn-neue-notiz')).toHaveCount(0)
    await expect(detail.locator('.pw-dokumente')).toHaveCount(0)
    await expect(detail.getByText('Aktionszeitleiste')).toHaveCount(0)
    await expect(detail.locator('.pw-hinweis', { hasText: 'sobald das Geschäft gespeichert ist' })).toBeVisible()

    // Unten «Speichern» (ohne Titel gesperrt) und «Abbrechen», oben kein ✕.
    const modal = page.locator('.pw-modal').first()
    await expect(speichernKnopf(modal), 'Ohne Titel muss «Speichern» gesperrt sein').toBeDisabled()
    await expect(abbrechenKnopf(modal)).toBeVisible()
    await expect(modal.locator('.pw-btn-schliessen')).toHaveCount(0)

    // Erst «Speichern» legt an — danach erscheint die Detailmaske mit der neuen ID.
    await detail.getByLabel('Titel').fill(titel)
    await expect(speichernKnopf(modal)).toBeEnabled()
    await speichernKnopf(modal).click()
    // NotizenListe kommt zweimal vor (normale Notizen + Sitzungsnotizen), der
    // Knopf also doppelt — hier reicht der erste als Beleg fürs Erscheinen.
    await expect(detail.locator('.pw-btn-neue-notiz').first()).toBeVisible({ timeout: 30_000 })

    // Jetzt ist das volle Inventar da und die Bedienelemente funktionieren wirklich.
    felder = await felderLesen()
    for (const f of GESCHAEFT_FELDER) {
      expect(felder, `Feld «${f}» fehlt nach dem Speichern`).toContain(f)
    }
    await expect(detail.locator('.pw-zustaendigkeit-select')).toBeVisible()
    await expect(detail.locator('.pw-beschluss-input')).toBeVisible()
    await expect(detail.locator('.pw-dokumente')).toBeVisible()
    await expect(detail.getByText('Aktionszeitleiste')).toBeVisible()
    // «Votum im Rat» ist zuständigen-gebunden. Beim Anlegen eines eigenen
    // Geschäfts ist die erfassende Person per Default zuständig (Zuständigkeits-
    // Vorauswahl), darum erscheint das Votum-Feld hier bearbeitbar. Dass es für
    // NICHT zuständige Personen verborgen bleibt, prüft der Votum-Test (F26).
    await expect(detail.locator('.pw-votum'), 'Für die per Default zuständige erfassende Person erscheint das Votum-Feld').toBeVisible()
    await expect(detail.getByLabel('Titel')).toHaveValue(titel)

    // Beim Bearbeiten schliesst wieder das ✕ und es gibt keinen Fuss mehr.
    await expect(modal.locator('.pw-modal-footer')).toHaveCount(0)
    await page.locator('.pw-modal .pw-btn-schliessen').first().click()
  })

  test('Eigenes Geschäft: Datum auf heute, Beschreibung unter dem Titel, Titel volle Breite, Kommission wählbar', async ({ page }) => {
    const titel = `E2E Eigenes Voll ${Date.now()}`
    const heute = new Date().toISOString().slice(0, 10)
    await login(page, U1)
    await oeffneAnsicht(page, 'Geschäfte', /Eigenes Geschäft/)
    await page.getByRole('button', { name: /Eigenes Geschäft/ }).click()

    const detail = page.locator('.pw-geschaeft-detail')
    await detail.waitFor({ state: 'visible', timeout: 30_000 })

    // Datum ist auf heute vorbelegt.
    await expect(detail.getByLabel('Datum')).toHaveValue(heute)

    // Der Beschreibungstext steht direkt unter dem Titel, VOR den öffentlichen
    // Informationen — als echter Editor bedienbar.
    const inhalt = detail.locator('.pw-detail-inhalt')
    await expect(inhalt).toBeVisible()
    const inhaltBox = await inhalt.boundingBox()
    const oeffentlichBox = await detail.locator('.pw-oeffentlich').boundingBox()
    expect(inhaltBox.y, 'Beschreibungstext steht nicht vor den öffentlichen Informationen')
      .toBeLessThan(oeffentlichBox.y)

    // Der Titel nutzt die ganze Breite der Kopfzeile (Status daneben, nicht darunter).
    const titelBox = await detail.getByLabel('Titel').boundingBox()
    const headerBox = await detail.locator('.pw-detail-header').boundingBox()
    expect(titelBox.width, 'Titel nutzt nicht annähernd die ganze Breite')
      .toBeGreaterThan(headerBox.width * 0.6)

    // Beschreibungstext füllen (contenteditable), Titel setzen.
    await inhalt.locator('[contenteditable="true"]').first().click()
    await page.keyboard.type('Worum es geht')
    await detail.getByLabel('Titel').fill(titel)

    // Kommission: nur aktive; genau eine wählbar. Erste Option übernehmen — dass
    // sie anklickbar ist, belegt zugleich, dass die Auswahlliste sichtbar liegt.
    const kommission = detail.locator('tr', { hasText: 'Kommission' }).locator('.v-select')
    await ncPick(page, kommission, '')
    const gewaehlteKommission = (await kommission.locator('.vs__selected').textContent())?.trim()
    expect(gewaehlteKommission, 'Es wurde keine Kommission übernommen').toBeTruthy()

    // Nach dem Speichern geht dieselbe Maske in die Bearbeitung über und behält
    // die erfassten Werte (DB-Persistenz von Inhalt/Kommission prüft der
    // Backend-e2e-Lauf, Status/Datum der Datenfluss-Test).
    const modal = page.locator('.pw-modal').first()
    await speichernKnopf(modal).click()
    await expect(detail.locator('.pw-btn-neue-notiz').first()).toBeVisible({ timeout: 30_000 })
    await expect(detail.locator('.pw-detail-inhalt')).toContainText('Worum es geht')
    await expect(detail.locator('tr', { hasText: 'Kommission' }).locator('.vs__selected'))
      .toContainText(gewaehlteKommission)
    await expect(detail.getByLabel('Datum')).toHaveValue(heute)

    await page.locator('.pw-modal .pw-btn-schliessen').first().click()
  })

  test('Eigenes Geschäft: ein Klick neben die Maske verwirft nichts, «Abbrechen» schliesst ohne anzulegen', async ({ page }) => {
    const titel = `E2E Geschaeft Abbrechen ${Date.now()}`
    await login(page, U1)
    await oeffneAnsicht(page, 'Geschäfte', /Eigenes Geschäft/)

    await page.getByRole('button', { name: /Eigenes Geschäft/ }).click()
    const detail = page.locator('.pw-geschaeft-detail')
    await detail.waitFor({ state: 'visible', timeout: 30_000 })
    await detail.getByLabel('Titel').fill(titel)

    // Der Klick daneben darf im Neu-Modus NICHTS tun.
    await page.locator('.pw-modal-overlay').first().click({ position: { x: 5, y: 5 } })
    await expect(detail, 'Der Klick daneben hat die Neu-Maske geschlossen').toBeVisible()
    await expect(detail.getByLabel('Titel'), 'Der erfasste Titel ging verloren').toHaveValue(titel)

    // Verworfen wird nur über «Abbrechen» — angelegt wurde dabei nichts.
    await abbrechenKnopf(page.locator('.pw-modal').first()).click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-geschaeft-detail')).toHaveCount(0)
    // Robust statt globaler Zeilenzahl (nebenläufige Testinstanz): das konkret
    // erfasste Geschäft darf nicht als Tabellenzeile auftauchen.
    await expect(page.locator('.pw-tabelle-geschaefte tbody tr', { hasText: titel }), 'Das abgebrochene Geschäft wurde angelegt').toHaveCount(0)
  })

  // =========================================================================
  // Sitzungen
  // =========================================================================

  test('Neue Sitzung: «+ Neue Sitzung» ist ein Aktionsmenü mit den Sitzungstypen', async ({ page }) => {
    await login(page, U1)
    await oeffneAnsicht(page, 'Sitzungen', /Neue Sitzung/)

    // Nextcloud-Standard: der Neu-Knopf öffnet ein Menü, keinen eigenen Dialog.
    await page.getByRole('button', { name: /Neue Sitzung/ }).click()
    const menu = page.locator('.v-popper__popper .action-item__menu, .v-popper__popper ul').first()
    await menu.waitFor({ state: 'visible', timeout: 15_000 })
    const eintraege = await menu.locator('li').allTextContents()
    expect(eintraege.length, 'Das Neu-Menü enthält keinen Sitzungstyp').toBeGreaterThan(0)
    await expect(page.locator('.pw-modal h3', { hasText: 'Neue Sitzung' }), 'Es darf kein eigener Auswahl-Dialog erscheinen')
      .toHaveCount(0)

    // Erster Sitzungstyp öffnet direkt das vollständige Formular.
    await menu.locator('button').first().click()
    const form = page.locator('.pw-neue-sitzung-form')
    await form.waitFor({ state: 'visible', timeout: 30_000 })

    for (const p of SITZUNG_PLATZHALTER) {
      await expect(form.locator(`[placeholder=${JSON.stringify(p)}]`), `Element mit Platzhalter «${p}» fehlt`)
        .toBeVisible()
    }
    await expect(form.locator('input[type="date"]'), 'Datumsfeld fehlt').toBeVisible()
    await expect(form.locator('input[type="time"]'), 'Von-/Bis-Zeitfelder fehlen').toHaveCount(2)
    await expect(form.getByRole('button', { name: '+ Traktandum hinzufügen' })).toBeVisible()
    await expect(form.getByRole('button', { name: '+ Teilnehmer hinzufügen' })).toBeVisible()
    await expect(form.getByRole('button', { name: 'Erstellen' })).toBeVisible()
    await expect(form.getByRole('button', { name: 'Abbrechen' }), 'Abbrechen fehlt im Sitzungsformular').toBeVisible()

    await form.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(form).toHaveCount(0)
  })
})
