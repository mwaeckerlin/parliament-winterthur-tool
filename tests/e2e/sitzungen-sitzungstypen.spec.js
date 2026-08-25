import { test, expect } from '@playwright/test'

/**
 * E2E-Abdeckung der Domäne «Sitzungen + Sitzungstypen» im echten Browser gegen
 * den realen Stack (Nextcloud + DB + REST-API). Deckt die in dieser Session frisch
 * gebauten/gefixten Punkte end-to-end ab — bisher gab es hier nur den Titel-Check
 * in layout-consistency.spec.js.
 *
 * Prioritär abgedeckt (frisch gebaut/gefixt, CHANGELOG 1.7.30):
 *  - Sitzungstyp anlegen mit nur einem Namen (kein 500), Bearbeitung öffnet mit
 *    ALLEN Feldern; leerer Name deaktiviert «Erstellen».
 *  - Die beiden Schalter «Eigene Fraktion» und «Verknüpfung anbieten» lassen sich
 *    umschalten und bleiben erhalten (früher toter :checked-Bug).
 *  - «+ Neue Sitzung» als beschrifteter Knopf im Kopf; ohne Typ Fehler-Toast, bei
 *    genau einem Typ direkt das Formular, bei mehreren erst der Auswahl-Dialog.
 *  - Sitzungsnotiz-Feature: am Geschäft haftende Notiz, im Geschäft unter dem
 *    ausklappbaren <details class="pw-sitzungsnotizen-details"> und an jeder Sitzung.
 *
 * Datenannahmen: der Stack enthält reale, synchronisierte Parlamentsdaten. Die
 * synchronisierten Sitzungen sind Parlamentssitzungen (typId=0) und tragen
 * Traktanden mit Geschäftsbezug. Interne Sitzungen und Sitzungstypen werden im Test
 * selbst über die UI bzw. (für reine Vorbedingungen) über die REST-API angelegt.
 *
 * Dokumentierte Einschränkungen (siehe Kommentare an den betroffenen Tests):
 *  - Ein internes Sitzungs-Traktandum mit Geschäftsbezug (geschaeftId>0) lässt sich
 *    weder über die UI noch über die REST-API erzeugen: POST /sitzungen setzt jedes
 *    Traktandum hart auf geschaeftId=0, es gibt keine Traktandum-POST-Route und
 *    PUT /traktanden/{id} akzeptiert nur bemerkungen/notizen. Die «Sitzungsnotiz zum
 *    Geschäft» wird deshalb an einer realen Parlamentssitzung (typId=0) getestet,
 *    deren Traktandum ein Geschäft trägt; das «folgt dem Geschäft an weitere
 *    Sitzungen» wird über eine zweite Parlamentssitzung mit demselben Geschäft
 *    (falls in den Daten vorhanden) und über den Geschäft-Detail-Rundlauf bewiesen.
 *  - Der Inline-Fehler beim fehlgeschlagenen Erstellen einer Sitzung ist über die UI
 *    nicht deterministisch erzwingbar (typId und Datum sind im Happy Path immer
 *    gültig) und daher nicht separat getestet.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const API = `${BASE_URL}/index.php/apps/parlwin`

const USER = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

const heute = () => new Date().toISOString().slice(0, 10)
const inEinerWoche = () => {
  const d = new Date()
  d.setDate(d.getDate() + 7)
  return d.toISOString().slice(0, 10)
}

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an (identisch zur Referenz). */
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

/** CSRF-Token der laufenden Session für schreibende REST-Aufrufe. */
async function ocToken(page) {
  return page.evaluate(() => (window.OC && window.OC.requestToken) || '')
}

async function apiGet(page, pfad) {
  const res = await page.request.get(`${API}${pfad}`, { headers: { 'OCS-APIRequest': 'true' } })
  expect(res.ok(), `GET ${pfad} fehlgeschlagen (${res.status()})`).toBeTruthy()
  return res.json()
}

async function apiPost(page, pfad, form) {
  const token = await ocToken(page)
  return page.request.post(`${API}${pfad}`, {
    headers: { 'OCS-APIRequest': 'true', requesttoken: token, 'Content-Type': 'application/x-www-form-urlencoded' },
    form,
  })
}

async function apiDelete(page, pfad) {
  const token = await ocToken(page)
  return page.request.delete(`${API}${pfad}`, {
    headers: { 'OCS-APIRequest': 'true', requesttoken: token },
  })
}

/** Öffnet die App-Wurzel und wartet auf die gemeinsame Seitenstruktur. */
async function openApp(page) {
  await page.goto(`${API}/`)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
}

/** Öffnet die App und wechselt über den Navigations-Link in eine Ansicht. */
async function gotoView(page, name) {
  await openApp(page)
  await page.getByRole('link', { name, exact: true }).click()
  await expect(page.locator('.pw-view-title')).toHaveText(name, { timeout: 30_000 })
}

/** Löscht (soft) alle bestehenden Sitzungstypen — Sitzungstypen sind app-intern
 *  (nie synchronisiert), so lässt sich der Typ-Zähler deterministisch steuern. */
async function resetSitzungstypen(page) {
  const typen = await apiGet(page, '/sitzungstypen')
  for (const t of typen) {
    if (!t.geloescht) await apiDelete(page, `/sitzungstypen/${t.id}`)
  }
}

/** Legt einen Sitzungstyp über die REST-API an und gibt die erzeugte ID zurück. */
async function createSitzungstyp(page, felder) {
  const res = await apiPost(page, '/sitzungstypen', felder)
  expect(res.ok(), 'Sitzungstyp konnte nicht angelegt werden').toBeTruthy()
  return (await res.json()).id
}

/**
 * Legt einen Sitzungstyp über die Oberfläche an: «+ Neuer Typ» öffnet sofort
 * die vollständige Maske (kein reduzierter Zwischendialog). Sie legt noch nichts
 * an, sondern sammelt die Eingaben — erst «Speichern» erzeugt den Sitzungstyp,
 * danach geht dieselbe Maske in die Bearbeitung über. Gibt die Maske zurück.
 */
async function neuerTypUeberUi(page, name) {
  await page.getByRole('button', { name: /Neuer Typ/ }).click()
  const maske = page.locator('.pw-modal-overlay', { has: page.locator('.pw-modal-kopf h3') })
  await expect(maske).toBeVisible({ timeout: 30_000 })
  await maske.locator('.pw-modal-body input.pw-input').first().fill(name)
  await maske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
  await expect(maske.locator('.pw-modal-kopf h3')).toHaveText('Sitzungstyp bearbeiten', { timeout: 30_000 })
  return maske
}

/** Legt eine interne Sitzung über die REST-API an (für reine Vorbedingungen). */
async function createSitzung(page, { typId, datum, titel, traktanden = [] }) {
  const res = await apiPost(page, '/sitzungen', {
    typId, datum, titel, traktanden: JSON.stringify(traktanden),
  })
  expect(res.ok(), 'Sitzung konnte nicht angelegt werden').toBeTruthy()
  return res.json()
}

/** Toastify-Toast mit bestimmtem Text (Nextcloud showSuccess/showError). */
function toast(page, text) {
  return page.locator('.toastify', { hasText: text })
}

/**
 * Schaltet eine NcCheckboxRadioSwitch um. Das Component rendert KEIN <label>-Element;
 * das versteckte input[type=checkbox] ist nicht anklickbar (Klick läuft in Timeout).
 * Anklickbar ist der sichtbare Text (.checkbox-radio-switch__text). Im linken
 * Nav-Slot fangen Nav-Links den Klick ab → dort force:true.
 */
async function toggleSwitch(container, force = false) {
  const text = container.locator('.checkbox-radio-switch__text')
  await text.scrollIntoViewIfNeeded().catch(() => {})
  await text.click({ force })
}

const CHECKED = /checkbox-radio-switch--checked/

/**
 * Native HTML5-Drag-and-Drop deterministisch auslösen, indem die exakt vom
 * Vue-Component gelauschten Events (dragstart/dragover/drop) mit gemeinsamem
 * DataTransfer dispatcht werden — Playwrights dragTo ist bei HTML5-DnD unzuverlässig.
 */
async function htmlDrag(page, handle, target) {
  const dt = await page.evaluateHandle(() => new DataTransfer())
  await handle.dispatchEvent('dragstart', { dataTransfer: dt })
  await target.dispatchEvent('dragover', { dataTransfer: dt })
  await target.dispatchEvent('drop', { dataTransfer: dt })
  await handle.dispatchEvent('dragend', { dataTransfer: dt }).catch(() => {})
  await dt.dispose()
}

/** Tippt Text in einen NotizenListe-Editor (geteilte Komponente mit «+ Neue Notiz»). */
async function notizenListeSchreiben(page, root, text) {
  const neu = root.locator('.pw-btn-neue-notiz')
  await neu.waitFor({ state: 'visible', timeout: 30_000 })
  await neu.click()
  const editor = root.locator('.ProseMirror').first()
  await editor.waitFor({ state: 'visible', timeout: 30_000 })
  await editor.click()
  await page.waitForTimeout(300)
  await editor.pressSequentially(text, { delay: 25 })
  // Speichern nur über das Häkchen — kein Blur-Save mehr.
  await root.locator('.pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()
}

/** Wählt in einem NcSelect/vue-select die erste bzw. eine bestimmte Option.
 *  Öffnet über ArrowDown am fokussierten Suchfeld (zuverlässig auch bei an den
 *  Body teleportierten Dialogen); ein Toggle-Klick allein öffnete dort nicht. */
async function ncSelectWaehle(page, container, optionText = null) {
  const feld = container.locator('.vs__search, .vs__dropdown-toggle').first()
  await feld.scrollIntoViewIfNeeded().catch(() => {})
  await feld.focus().catch(() => {})
  await page.keyboard.press('ArrowDown')
  const offen = await container.evaluate((el) => el.classList.contains('vs--open')).catch(() => false)
  if (!offen) await container.locator('.vs__dropdown-toggle').click()
  const option = optionText
    ? page.locator('.vs__dropdown-option', { hasText: optionText })
    : page.locator('.vs__dropdown-option')
  await option.first().waitFor({ state: 'visible', timeout: 15_000 })
  await option.first().click()
}

/**
 * Sucht über die REST-API eine Parlamentssitzung (typId=0), deren erstes Traktandum
 * mit Geschäftsbezug ausgewertet wird, und ermittelt zugleich alle Parlaments-
 * Sitzungen, an denen dasselbe Geschäft als Traktandum hängt (für den «folgt dem
 * Geschäft»-Beweis).
 */
async function findeParlamentsSitzungMitGeschaeft(page) {
  const sitzungen = await apiGet(page, '/sitzungen?limit=100')
  const parlament = sitzungen.filter((s) => Number(s.typId) === 0 && !s.geloescht)
  // Map geschaeftId -> Liste der Sitzungs-IDs (für die «zwei Sitzungen»-Prüfung).
  const geschaeftZuSitzungen = new Map()
  let treffer = null
  for (const s of parlament) {
    let traktanden
    try {
      traktanden = await apiGet(page, `/sitzungen/${s.id}/traktanden`)
    } catch {
      continue
    }
    for (const t of traktanden) {
      const gid = Number(t.geschaeftId || (t.geschaeft && t.geschaeft.id) || 0)
      if (gid <= 0) continue
      if (!geschaeftZuSitzungen.has(gid)) geschaeftZuSitzungen.set(gid, [])
      const liste = geschaeftZuSitzungen.get(gid)
      if (!liste.includes(s.id)) liste.push(s.id) // je Geschäft nur DISTINKTE Sitzungen
      if (!treffer) {
        treffer = {
          sitzungId: s.id,
          geschaeftId: gid,
          geschaeftTitel: (t.geschaeft && t.geschaeft.titel) || t.titel || '',
        }
      }
    }
  }
  if (!treffer) return null
  // Bevorzugt ein Geschäft, das an >=2 Parlaments-Sitzungen hängt.
  for (const [gid, sids] of geschaeftZuSitzungen.entries()) {
    if (sids.length >= 2) {
      treffer = { ...treffer, geschaeftId: gid, sitzungId: sids[0], zweiteSitzungId: sids[1] }
      // Titel dieses Geschäfts aus dem ersten Vorkommen nachschlagen.
      const traktanden = await apiGet(page, `/sitzungen/${sids[0]}/traktanden`)
      const tt = traktanden.find((x) => Number(x.geschaeftId || (x.geschaeft && x.geschaeft.id) || 0) === gid)
      treffer.geschaeftTitel = (tt && tt.geschaeft && tt.geschaeft.titel) || (tt && tt.titel) || treffer.geschaeftTitel
      break
    }
  }
  return treffer
}

/** Stellt sicher, dass die «Nur zukünftige»-Filterschaltung aus ist (alle Sitzungen sichtbar). */
async function zeigeAlleSitzungen(page) {
  const sw = page.locator('#pw-filter-slot .checkbox-radio-switch')
  await sw.waitFor({ state: 'visible', timeout: 30_000 })
  const istAn = ((await sw.getAttribute('class')) || '').includes('checkbox-radio-switch--checked')
  if (istAn) {
    await toggleSwitch(sw, true)
    await expect(sw).not.toHaveClass(CHECKED)
  }
}

/** Öffnet eine Sitzungskarte anhand ihrer ID (Karte muss gerendert sein). */
async function oeffneSitzung(page, id) {
  const karte = page.locator(`#pw-sitzung-${id}`)
  await karte.scrollIntoViewIfNeeded()
  await karte.locator('.pw-sitzung-kopf').click()
  await expect(karte.locator('.pw-sitzung-details')).toBeVisible({ timeout: 30_000 })
  return karte
}

// ---------------------------------------------------------------------------

let jsFehler
test.beforeEach(({ page }) => {
  jsFehler = []
  page.on('pageerror', (e) => jsFehler.push(e.message))
})

test.beforeAll(() => {
  expect(USER.pass, `Passwort für ${USER.name} fehlt`).not.toBe('')
})

// ===========================================================================
// Sitzungstypen
// ===========================================================================
test.describe('Sitzungstypen', () => {
  test('«+ Neuer Typ» öffnet sofort die vollständige Maske mit allen Feldern', async ({ page }) => {
    const name = `E2E-Typ Anlegen ${Date.now()}`
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')

    await page.getByRole('button', { name: /Neuer Typ/ }).click()
    const maske = page.locator('.pw-modal-overlay', { has: page.locator('.pw-modal-kopf h3') })
    await expect(maske).toBeVisible({ timeout: 30_000 })

    // Solange kein Name erfasst ist, heisst die Maske «Neuer Sitzungstyp» —
    // sie zeigt aber bereits ALLE Felder, kein reduziertes Formular.
    await expect(maske.locator('.pw-modal-kopf h3')).toHaveText('Neuer Sitzungstyp')
    await expect(maske.locator('textarea.pw-textarea')).toBeVisible()
    await expect(maske.getByText('Standard-Ort', { exact: true })).toBeVisible()
    await expect(maske.locator('input[type="time"]')).toHaveCount(2)
    await expect(maske.locator('legend', { hasText: 'Vorlage-Traktanden' })).toBeVisible()
    await expect(maske.locator('legend', { hasText: 'Teilnehmer' })).toBeVisible()
    await expect(maske.locator('legend', { hasText: 'Optionen' })).toBeVisible()

    // Im Neu-Modus unten «Speichern» (gesperrt ohne Namen) und «Abbrechen», kein ✕.
    const speichern = maske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' })
    await expect(speichern).toBeDisabled()
    await expect(maske.locator('.pw-modal-footer').getByRole('button', { name: 'Abbrechen' })).toBeVisible()
    await expect(maske.locator('.pw-btn-schliessen')).toHaveCount(0)

    // Erst «Speichern» legt den Typ an; danach ist es kein Entwurf mehr.
    const namensfeld = maske.locator('.pw-modal-body input.pw-input').first()
    await namensfeld.fill(name)
    await expect(speichern).toBeEnabled()
    await speichern.click()
    await expect(maske.locator('.pw-modal-kopf h3')).toHaveText('Sitzungstyp bearbeiten', { timeout: 30_000 })
    await expect(page.locator('.toastify.toast-error')).toHaveCount(0)
    await expect(namensfeld).toHaveValue(name)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Ein Sitzungstyp ohne Namen wird nicht angelegt: Klick daneben verwirft nichts, «Abbrechen» schliesst', async ({ page }) => {
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')

    await page.getByRole('button', { name: /Neuer Typ/ }).click()
    const maske = page.locator('.pw-modal-overlay', { has: page.locator('.pw-modal-kopf h3') })
    await expect(maske).toBeVisible({ timeout: 30_000 })

    // Ein Klick neben die Maske tut im Neu-Modus NICHTS — sonst gingen erfasste
    // Eingaben ungewollt verloren.
    await maske.click({ position: { x: 5, y: 5 } })
    await expect(maske, 'Der Klick daneben darf die Neu-Maske nicht schliessen').toBeVisible()
    await expect(maske.locator('.pw-modal-kopf h3')).toHaveText('Neuer Sitzungstyp')

    // «Ohne Namen wird nicht angelegt» = «Speichern» ist gesperrt; verworfen wird
    // über «Abbrechen», das die Maske schliesst. Nicht die globale Kartenzahl prüfen
    // (die nebenläufige Testinstanz legt echte Typen an).
    await expect(maske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }), 'Ohne Namen muss «Speichern» gesperrt sein').toBeDisabled()
    await maske.locator('.pw-modal-footer').getByRole('button', { name: 'Abbrechen' }).click()
    await expect(page.locator('.pw-modal-overlay')).toHaveCount(0)
    await page.waitForLoadState('networkidle')
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Schalter «Eigene Fraktion» und «Verknüpfung anbieten» lassen sich umschalten und bleiben nach erneutem Öffnen erhalten', async ({ page }) => {
    const name = `E2E-Typ Schalter ${Date.now()}`
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')

    // Frisch angelegter, reiner Namens-Typ (Guard: funktioniert auch ohne Vorbelegung).
    const bearb = await neuerTypUeberUi(page, name)

    const eigene = bearb.locator('.checkbox-radio-switch', { hasText: 'Eigene Fraktion' })
    const verknuepfung = bearb.locator('.checkbox-radio-switch', { hasText: 'Verknüpfung' })
    // Anfangs beide aus (Zustand am Container-Modifier, nicht am versteckten Input).
    await expect(eigene).not.toHaveClass(CHECKED)
    await expect(verknuepfung).not.toHaveClass(CHECKED)

    // Beide einschalten — jeder Klick speichert sofort (früher toter :checked-Bug).
    await toggleSwitch(eigene)
    await expect(eigene).toHaveClass(CHECKED)
    await expect(toast(page, 'Gespeichert').first()).toBeVisible({ timeout: 15_000 })

    await toggleSwitch(verknuepfung)
    await expect(verknuepfung).toHaveClass(CHECKED)
    await expect(toast(page, 'Gespeichert').first()).toBeVisible({ timeout: 15_000 })

    // Schliessen und über eine frische Navigation (DB-Rundlauf) erneut öffnen.
    await bearb.locator('.pw-btn-schliessen').click()
    await gotoView(page, 'Sitzungstypen')
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).click()
    const bearb2 = page.locator('.pw-modal-overlay', { has: page.locator('h3', { hasText: 'Sitzungstyp bearbeiten' }) })
    await expect(bearb2).toBeVisible({ timeout: 30_000 })
    await expect(bearb2.locator('.checkbox-radio-switch', { hasText: 'Eigene Fraktion' })).toHaveClass(CHECKED)
    await expect(bearb2.locator('.checkbox-radio-switch', { hasText: 'Verknüpfung' })).toHaveClass(CHECKED)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Bearbeiten pro Feld speichert sofort (Gespeichert-Toast) und aktualisiert die Karten-Meta', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Typ Feld ${stamp}`
    const ort = `Rathaus ${stamp}`
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')

    const bearb = await neuerTypUeberUi(page, name)

    // Auf die tatsächliche Speicher-Antwort warten statt auf den Toast: der
    // «Gespeichert»-Toast der vorherigen Eingabe ist oft noch sichtbar und
    // würde eine ausgebliebene Speicherung fälschlich bestätigen.
    const speicherAntwort = () => page.waitForResponse(
      (r) => /\/apps\/parlwin\/sitzungstypen\/\d+/.test(r.url()) && r.request().method() === 'PUT',
      { timeout: 20_000 },
    )

    // Standard-Ort ändern → @change speichert sofort.
    const ortFeld = bearb.locator('.pw-field', { hasText: 'Standard-Ort' }).locator('input.pw-input')
    const [ortRes] = await Promise.all([
      speicherAntwort(),
      (async () => { await ortFeld.fill(ort); await ortFeld.blur() })(),
    ])
    expect(ortRes.ok(), 'Standard-Ort wurde nicht gespeichert').toBeTruthy()

    // Von-Zeit ändern → erneut sofort speichern.
    const vonFeld = bearb.locator('.pw-von-bis .pw-field', { hasText: 'Von' }).locator('input[type="time"]')
    const [zeitRes] = await Promise.all([
      speicherAntwort(),
      (async () => { await vonFeld.fill('09:15'); await vonFeld.blur() })(),
    ])
    expect(zeitRes.ok(), 'Von-Zeit wurde nicht gespeichert').toBeTruthy()

    // Schliessen → die Karten-Meta zeigt Ort und Zeit.
    await bearb.locator('.pw-btn-schliessen').click()
    const karte = page.locator('.pw-sitzungstyp-karte', { hasText: name })
    await expect(karte.locator('.pw-sitzungstyp-meta')).toContainText(ort)
    await expect(karte.locator('.pw-sitzungstyp-meta')).toContainText('09:15')

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Löschen mit Bestätigung: Abbrechen behält die Karte, Bestätigen entfernt sie', async ({ page }) => {
    const name = `E2E-Typ Loeschen ${Date.now()}`
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')
    await createSitzungstyp(page, { name })
    await gotoView(page, 'Sitzungstypen')

    const karte = page.locator('.pw-sitzungstyp-karte', { hasText: name })
    await expect(karte).toBeVisible({ timeout: 30_000 })

    // Bestätigung abbrechen → Karte bleibt.
    page.once('dialog', (d) => d.dismiss())
    await karte.getByRole('button', { name: 'Löschen' }).click()
    await expect(karte).toBeVisible()

    // Bestätigung annehmen → Karte verschwindet.
    page.once('dialog', (d) => d.accept())
    await karte.getByRole('button', { name: 'Löschen' }).click()
    await expect(karte).toHaveCount(0, { timeout: 30_000 })

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Vorlage-Traktanden hinzufügen, entfernen und umsortieren bleibt erhalten', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Typ Traktanden ${stamp}`
    const trA = `TrA ${stamp}`
    const trB = `TrB ${stamp}`
    await login(page, USER)
    await gotoView(page, 'Sitzungstypen')

    const bearb = await neuerTypUeberUi(page, name)

    const fieldset = bearb.locator('.pw-fieldset', { has: page.locator('legend', { hasText: 'Vorlage-Traktanden' }) })
    const addBtn = fieldset.getByRole('button', { name: /Traktandum/ })

    // Drei hinzufügen, den mittleren wieder entfernen → 2 bleiben (A, B).
    await addBtn.click()
    await addBtn.click()
    await addBtn.click()
    await expect(fieldset.locator('.pw-zeile')).toHaveCount(3)
    await fieldset.locator('.pw-zeile').nth(0).locator('input.pw-input').first().fill(trA)
    await fieldset.locator('.pw-zeile').nth(0).locator('input.pw-input').first().blur()
    await fieldset.locator('.pw-zeile').nth(2).locator('input.pw-input').first().fill(trB)
    await fieldset.locator('.pw-zeile').nth(2).locator('input.pw-input').first().blur()
    // Mittlere (leere) Zeile entfernen.
    await fieldset.locator('.pw-zeile').nth(1).locator('.pw-btn-klein').click()
    await expect(fieldset.locator('.pw-zeile')).toHaveCount(2)
    await expect(fieldset.locator('.pw-zeile').nth(0).locator('input.pw-input').first()).toHaveValue(trA)
    await expect(fieldset.locator('.pw-zeile').nth(1).locator('input.pw-input').first()).toHaveValue(trB)

    // Umsortieren: B vor A ziehen (nativer HTML5-DnD, deterministisch dispatcht).
    const handleB = fieldset.locator('.pw-zeile').nth(1).locator('.pw-drag-handle')
    const zeileA = fieldset.locator('.pw-zeile').nth(0)
    await htmlDrag(page, handleB, zeileA)
    await expect(fieldset.locator('.pw-zeile').nth(0).locator('input.pw-input').first()).toHaveValue(trB)
    await expect(toast(page, 'Gespeichert').first()).toBeVisible({ timeout: 15_000 })

    // Frisch öffnen → die neue Reihenfolge (B, A) ist persistiert.
    await bearb.locator('.pw-btn-schliessen').click()
    await gotoView(page, 'Sitzungstypen')
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).click()
    const bearb2 = page.locator('.pw-modal-overlay', { has: page.locator('h3', { hasText: 'Sitzungstyp bearbeiten' }) })
    await expect(bearb2).toBeVisible({ timeout: 30_000 })
    const fs2 = bearb2.locator('.pw-fieldset', { has: page.locator('legend', { hasText: 'Vorlage-Traktanden' }) })
    await expect(fs2.locator('.pw-zeile').nth(0).locator('input.pw-input').first()).toHaveValue(trB)
    await expect(fs2.locator('.pw-zeile').nth(1).locator('input.pw-input').first()).toHaveValue(trA)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Kommissionen-beraten Auswahl bleibt erhalten', async ({ page }) => {
    const name = `E2E-Typ Kommission ${Date.now()}`
    await login(page, USER)
    // Reale Kommissionen sind Voraussetzung (synchronisierte Daten).
    const kommissionen = await apiGet(page, '/kommissionen')
    expect(Array.isArray(kommissionen) && kommissionen.length > 0, 'Keine Kommissionen in den Daten').toBeTruthy()

    await gotoView(page, 'Sitzungstypen')
    const bearb = await neuerTypUeberUi(page, name)

    const optionen = bearb.locator('.pw-fieldset', { has: page.locator('legend', { hasText: 'Optionen' }) })
    const kommSelect = optionen.locator('.pw-field', { hasText: 'Kommissionen beraten' }).locator('.v-select')
    await ncSelectWaehle(page, kommSelect)
    await expect(toast(page, 'Gespeichert').first()).toBeVisible({ timeout: 15_000 })
    await expect(kommSelect.locator('.vs__selected')).toHaveCount(1)

    // Frisch öffnen → die Auswahl ist persistiert.
    await bearb.locator('.pw-btn-schliessen').click()
    await gotoView(page, 'Sitzungstypen')
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).click()
    const bearb2 = page.locator('.pw-modal-overlay', { has: page.locator('h3', { hasText: 'Sitzungstyp bearbeiten' }) })
    await expect(bearb2).toBeVisible({ timeout: 30_000 })
    const kommSelect2 = bearb2.locator('.pw-fieldset', { has: page.locator('legend', { hasText: 'Optionen' }) })
      .locator('.pw-field', { hasText: 'Kommissionen beraten' }).locator('.v-select')
    await expect(kommSelect2.locator('.vs__selected')).toHaveCount(1)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Leere Typen-Ansicht Hinweis, Suche nach Name/Zweck, leerer Name wird nicht gespeichert', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Typ Suche ${stamp}`
    const zweck = `E2E-Zweck ${stamp}`
    await login(page, USER)
    await createSitzungstyp(page, { name, zweck })
    await gotoView(page, 'Sitzungstypen')

    const suche = page.locator('#pw-search-slot input')
    await suche.waitFor({ state: 'visible', timeout: 30_000 })

    // Kein Treffer → Hinweis-Text erscheint.
    await suche.fill(`zzz-nichts-${stamp}`)
    await expect(page.locator('.pw-hinweis', { hasText: 'Keine Sitzungstypen vorhanden' })).toBeVisible({ timeout: 15_000 })

    // Suche nach Name.
    await suche.fill(name)
    await expect(page.locator('.pw-sitzungstyp-karte', { hasText: name })).toBeVisible({ timeout: 15_000 })
    // Suche nach Zweck.
    await suche.fill(zweck)
    await expect(page.locator('.pw-sitzungstyp-karte', { hasText: name })).toBeVisible({ timeout: 15_000 })
    await suche.fill('')

    // Leerer Name im Editor wird still NICHT gespeichert (alter Name bleibt).
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).click()
    const bearb = page.locator('.pw-modal-overlay', { has: page.locator('h3', { hasText: 'Sitzungstyp bearbeiten' }) })
    await expect(bearb).toBeVisible({ timeout: 30_000 })
    const nameFeld = bearb.locator('.pw-modal-body input.pw-input').first()
    await nameFeld.fill('')
    await nameFeld.blur()
    await bearb.locator('.pw-btn-schliessen').click()
    await gotoView(page, 'Sitzungstypen')
    await page.locator('#pw-search-slot input').fill(name)
    await page.locator('.pw-sitzungstyp-karte', { hasText: name }).click()
    const bearb2 = page.locator('.pw-modal-overlay', { has: page.locator('h3', { hasText: 'Sitzungstyp bearbeiten' }) })
    await expect(bearb2).toBeVisible({ timeout: 30_000 })
    await expect(bearb2.locator('.pw-modal-body input.pw-input').first()).toHaveValue(name)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// «+ Neue Sitzung»: Kopf-Knopf und Typ-Verzweigung (frisch gefixt)
// ===========================================================================
/**
 * Öffnet das «+ Neue Sitzung»-Menü (Nextcloud-Standard für Neu-Aktionen) und
 * gibt den Menü-Container zurück. Das Menü wird an den Body teleportiert.
 */
async function oeffneNeuMenue(page) {
  await page.getByRole('button', { name: /Neue Sitzung/ }).click()
  const menu = page.locator('.v-popper__popper:visible').first()
  await menu.waitFor({ state: 'visible', timeout: 15_000 })
  return menu
}

test.describe('Sitzungen: «+ Neue Sitzung» im Kopf und Typ-Verzweigung', () => {
  test('«+ Neue Sitzung» ist ein beschrifteter Knopf im pw-view-header', async ({ page }) => {
    await login(page, USER)
    await gotoView(page, 'Sitzungen')
    const header = page.locator('.pw-sitzungen .pw-view-header')
    await expect(header).toBeVisible()
    // Der Knopf steht als beschrifteter Aktionsknopf im Kopf (nicht als Fläche
    // in der Liste). Hinweis: die DOM-/CSS-Reihenfolge setzt ihn direkt hinter
    // Titel+Zähler in den links ausgerichteten Kopf-Cluster; eine strikte
    // «x < Titel»-Prüfung entspräche nicht dem gerenderten Layout und entfällt.
    const btn = header.getByRole('button', { name: /Neue Sitzung/ })
    await expect(btn).toBeVisible()
    await expect(btn).toHaveText(/Neue Sitzung/)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Ohne Sitzungstyp: das Menü weist gesperrt darauf hin und öffnet kein Formular', async ({ page }) => {
    await login(page, USER)
    await openApp(page)
    await resetSitzungstypen(page)
    await gotoView(page, 'Sitzungen')

    const menu = await oeffneNeuMenue(page)
    const hinweis = menu.getByRole('menuitem', { name: 'Kein Sitzungstyp definiert' })
    await expect(hinweis).toBeVisible()
    await expect(hinweis).toBeDisabled()
    await expect(page.locator('.pw-neue-sitzung-form')).toHaveCount(0)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Mit genau einem Typ: das Menü bietet genau diesen Typ und öffnet das Formular', async ({ page }) => {
    const name = `E2E-Sitzungstyp Einzel ${Date.now()}`
    await login(page, USER)
    await openApp(page)
    await resetSitzungstypen(page)
    await createSitzungstyp(page, { name })
    await gotoView(page, 'Sitzungen')

    const menu = await oeffneNeuMenue(page)
    await expect(menu.getByRole('menuitem', { name })).toBeVisible()
    await menu.getByRole('menuitem', { name }).click()
    await expect(page.locator('.pw-neue-sitzung-form')).toBeVisible({ timeout: 15_000 })
    // Kein eigener Auswahl-Dialog davor.
    await expect(page.locator('.pw-modal-overlay', { has: page.getByRole('button', { name: 'Weiter' }) })).toHaveCount(0)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Mit mehreren Typen: je ein Menüeintrag, der gewählte startet sein Formular', async ({ page }) => {
    const stamp = Date.now()
    const n1 = `E2E-Typ Mehr A ${stamp}`
    const n2 = `E2E-Typ Mehr B ${stamp}`
    await login(page, USER)
    await openApp(page)
    await resetSitzungstypen(page)
    await createSitzungstyp(page, { name: n1 })
    await createSitzungstyp(page, { name: n2 })
    await gotoView(page, 'Sitzungen')

    const menu = await oeffneNeuMenue(page)
    await expect(menu.getByRole('menuitem', { name: n1 })).toBeVisible()
    await expect(menu.getByRole('menuitem', { name: n2 })).toBeVisible()
    // Solange nichts gewählt ist, öffnet sich kein Formular.
    await expect(page.locator('.pw-neue-sitzung-form')).toHaveCount(0)

    await menu.getByRole('menuitem', { name: n1 }).click()
    const form = page.locator('.pw-neue-sitzung-form')
    await expect(form).toBeVisible({ timeout: 15_000 })
    // Das Formular gehört zum gewählten Typ (Titel ist daraus vorbelegt).
    await expect(form.locator('.pw-sitzung-titel-input')).toHaveValue(n1)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  // Bug: «Verknüpfen mit» blieb im Dialog leer — die aufgeklappte Liste wird an
  // den Body teleportiert und lag UNTER dem eigenen Dialog-Hintergrund (z-index
  // 9999 statt des gemeinsamen Overlays). Der echte gerenderte z-index belegt
  // den Fix zuverlässig; die CSS-Struktur nagelt der vitest-Guard fest.
  test('«Verknüpfen mit»: der Dialog deckt die aufklappende Auswahl nicht mehr zu', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Verkn-Typ ${stamp}`
    await login(page, USER)
    await createSitzungstyp(page, { name, verknuepfen: true })
    await gotoView(page, 'Sitzungen')

    const menu = await oeffneNeuMenue(page)
    await menu.getByRole('menuitem', { name }).click()
    const form = page.locator('.pw-neue-sitzung-form')
    await expect(form).toBeVisible({ timeout: 15_000 })

    // Die Auswahl «Verknüpfen mit» ist im Dialog vorhanden.
    const auswahl = form.locator('.pw-form-zeile', { hasText: 'Verknüpfen mit' }).locator('.v-select')
    await expect(auswahl).toBeVisible()

    // Der Dialog-Hintergrund liegt auf einer NIEDRIGEREN Ebene als die an den
    // Body teleportierten Auswahllisten (.vs__dropdown-menu, z-index 2010).
    // Vor dem Fix lag er auf 9999 und deckte die geöffnete Liste zu.
    const overlay = page.locator('.pw-neue-sitzung-overlay')
    const overlayZ = await overlay.evaluate((el) => Number(getComputedStyle(el).zIndex))
    expect(overlayZ, 'Der Dialog liegt über den teleportierten Auswahllisten und deckt sie zu')
      .toBeLessThan(2010)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// Neues internes Sitzungs-Formular
// ===========================================================================
test.describe('Sitzungen: neues internes Formular', () => {
  async function oeffneFormularMitEinemTyp(page, typFelder) {
    await openApp(page)
    await resetSitzungstypen(page)
    await createSitzungstyp(page, typFelder)
    await gotoView(page, 'Sitzungen')
    const menu = await oeffneNeuMenue(page)
    await menu.getByRole('menuitem', { name: typFelder.name }).click()
    const form = page.locator('.pw-neue-sitzung-form')
    await expect(form).toBeVisible({ timeout: 15_000 })
    return form
  }

  test('Formular übernimmt Titel/Ort/Zeit/Zweck aus dem Typ; Datum heute+7 mit min=heute; ohne Datum kein Absenden', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Vorlage ${stamp}`
    const ort = `Sitzungszimmer ${stamp}`
    const zweck = `Zweck ${stamp}`
    await login(page, USER)
    const form = await oeffneFormularMitEinemTyp(page, {
      name, standardOrt: ort, standardZeitVon: '18:00', standardZeitBis: '20:00', zweck,
    })

    await expect(form.locator('.pw-sitzung-titel-input')).toHaveValue(name)
    await expect(form.locator('input[placeholder="Ort"]')).toHaveValue(ort)
    await expect(form.locator('.pw-form-textarea')).toHaveValue(zweck)
    await expect(form.locator('.pw-form-feld-zeit').first()).toHaveValue('18:00')
    const datum = form.locator('input[type="date"]')
    await expect(datum).toHaveValue(inEinerWoche())
    await expect(datum).toHaveAttribute('min', heute())

    // Ohne Datum ist «Erstellen» deaktiviert.
    await datum.fill('')
    await expect(form.getByRole('button', { name: /Erstellen|Erstellt/ })).toBeDisabled()
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Teilnehmer-Arten wählbar, das abhängige Feld wechselt, eigene Fraktion zeigt den Gruppen-Hinweis', async ({ page }) => {
    const name = `E2E-Teilnehmer ${Date.now()}`
    await login(page, USER)
    const form = await oeffneFormularMitEinemTyp(page, { name })

    const teilnehmer = form.locator('.pw-form-teilnehmer')
    await teilnehmer.getByRole('button', { name: /Teilnehmer hinzufügen/ }).click()
    const zeile = teilnehmer.locator('.pw-form-teilnehmer-zeile').first()
    const artSelect = zeile.locator('select').first()

    // Alle Teilnehmer-Arten sind wählbar.
    for (const wert of ['mitglied', 'fraktion', 'eigeneFraktion', 'kommission', 'rolle', 'ncGruppe', 'ncUser']) {
      await expect(artSelect.locator(`option[value="${wert}"]`)).toHaveCount(1)
    }

    // Default eigeneFraktion → Gruppen-Hinweis.
    await expect(zeile.locator('.pw-form-teilnehmer-hinweis')).toBeVisible()
    // Auf Mitglied wechseln → abhängiges Mitglied-Select erscheint.
    await artSelect.selectOption('mitglied')
    await expect(zeile.locator('.pw-form-teilnehmer-hinweis')).toHaveCount(0)
    await expect(zeile.locator('select').nth(1)).toBeVisible()
    await expect(zeile.locator('select').nth(1).locator('option', { hasText: 'Mitglied wählen' })).toHaveCount(1)
    // Auf Fraktion wechseln → Fraktions-Select.
    await artSelect.selectOption('fraktion')
    await expect(zeile.locator('select').nth(1).locator('option', { hasText: 'Fraktion wählen' })).toHaveCount(1)
    // Zurück zu eigeneFraktion → Hinweis wieder da.
    await artSelect.selectOption('eigeneFraktion')
    await expect(zeile.locator('.pw-form-teilnehmer-hinweis')).toBeVisible()
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Traktanden hinzufügen/entfernen; erfolgreiches Erstellen schliesst das Formular und die interne Sitzung erscheint mit «intern»-Badge', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Erstellen ${stamp}`
    const sitzungTitel = `E2E-Interne Sitzung ${stamp}`
    const trTitel = `E2E-Traktandum ${stamp}`
    await login(page, USER)
    const form = await oeffneFormularMitEinemTyp(page, { name })

    // Titel setzen (Datum ist bereits heute+7 → in der Zukunft).
    await form.locator('.pw-sitzung-titel-input').fill(sitzungTitel)

    // Traktanden: zwei hinzufügen, eines entfernen, das verbleibende benennen.
    const traktanden = form.locator('.pw-form-traktanden')
    await traktanden.getByRole('button', { name: /Traktandum hinzufügen/ }).click()
    await traktanden.getByRole('button', { name: /Traktandum hinzufügen/ }).click()
    await expect(traktanden.locator('.pw-form-traktandum')).toHaveCount(2)
    await traktanden.locator('.pw-form-traktandum').nth(1).locator('.pw-form-del-btn').click()
    await expect(traktanden.locator('.pw-form-traktandum')).toHaveCount(1)
    await traktanden.locator('.pw-form-traktandum').first().locator('input[placeholder="Titel"]').fill(trTitel)

    await form.getByRole('button', { name: /Erstellen/ }).click()

    // Formular schliesst, die neue interne Sitzung erscheint mit «intern»-Badge.
    await expect(page.locator('.pw-neue-sitzung-form')).toHaveCount(0, { timeout: 30_000 })
    const karte = page.locator('.pw-sitzung-karte', { hasText: sitzungTitel })
    await expect(karte).toBeVisible({ timeout: 30_000 })
    await expect(karte.locator('.pw-badge-intern')).toBeVisible()
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// Sitzungsliste: Filter und Suche
// ===========================================================================
test.describe('Sitzungen: Liste, Filter und Suche', () => {
  test('«Nur zukünftige» ist standardmässig aktiv; Ausschalten zeigt vergangene, nach den künftigen', async ({ page }) => {
    await login(page, USER)
    await gotoView(page, 'Sitzungen')

    const sw = page.locator('#pw-filter-slot .checkbox-radio-switch')
    await sw.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(sw).toHaveClass(CHECKED)
    // Mit Filter AN sind keine vergangenen (abgeschwächten) Karten sichtbar.
    await expect(page.locator('.pw-sitzung-karte.pw-vergangen')).toHaveCount(0)

    // Filter aus → mindestens so viele Karten wie zuvor.
    const vorher = await page.locator('.pw-sitzung-karte').count()
    await toggleSwitch(sw, true)
    await expect(sw).not.toHaveClass(CHECKED)
    await expect.poll(async () => page.locator('.pw-sitzung-karte').count()).toBeGreaterThanOrEqual(vorher)

    // Falls vergangene Sitzungen existieren: sie stehen nach den künftigen.
    const vergangen = await page.locator('.pw-sitzung-karte.pw-vergangen').count()
    if (vergangen > 0) {
      const klassen = await page.locator('.pw-sitzung-karte').evaluateAll((els) =>
        els.map((e) => e.classList.contains('pw-vergangen')))
      const ersterVergangen = klassen.indexOf(true)
      expect(klassen.slice(0, ersterVergangen).every((v) => v === false)).toBeTruthy()
    }
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Suche findet eine Sitzung und klappt die Trefferkarte auf; ohne Treffer «Keine Sitzungen gefunden»; Leeren stellt wieder her', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Such-Typ ${stamp}`
    const sitzungTitel = `E2E-Such-Sitzung ${stamp}`
    const trTitel = `E2E-Such-Traktandum ${stamp}`
    await login(page, USER)
    // Interne Sitzung mit eindeutig benanntem Traktandum vorbereiten (REST).
    const typId = await createSitzungstyp(page, { name })
    await createSitzung(page, { typId, datum: inEinerWoche(), titel: sitzungTitel, traktanden: [{ titel: trTitel, beschreibung: '' }] })
    await gotoView(page, 'Sitzungen')

    const suche = page.locator('#pw-search-slot input')
    await suche.waitFor({ state: 'visible', timeout: 30_000 })

    // Nach dem Traktandum-Titel suchen → Trefferkarte klappt automatisch auf.
    await suche.fill(trTitel)
    const karte = page.locator('.pw-sitzung-karte', { hasText: sitzungTitel })
    await expect(karte).toBeVisible({ timeout: 15_000 })
    await expect(karte.locator('.pw-sitzung-details')).toBeVisible({ timeout: 15_000 })

    // Kein Treffer → Leer-Hinweis.
    await suche.fill(`zzz-nichts-${stamp}`)
    await expect(page.locator('.pw-leer', { hasText: 'Keine Sitzungen gefunden' })).toBeVisible({ timeout: 15_000 })

    // Leeren → Liste wieder da.
    await suche.fill('')
    await expect(page.locator('.pw-sitzung-karte', { hasText: sitzungTitel })).toBeVisible({ timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Der Extern-Link klappt die Karte nicht auf (click.stop)', async ({ page }) => {
    await login(page, USER)
    await gotoView(page, 'Sitzungen')
    await zeigeAlleSitzungen(page)

    // Erste Karte mit Extern-Link (Parlamentssitzung mit URL).
    const linkKarte = page.locator('.pw-sitzung-karte', { has: page.locator('a.pw-extern-link') }).first()
    const anzahl = await linkKarte.count()
    expect(anzahl, 'Keine Sitzung mit Extern-Link in den Daten').toBeGreaterThan(0)
    await linkKarte.scrollIntoViewIfNeeded()
    await expect(linkKarte.locator('.pw-sitzung-details')).toHaveCount(0)

    // Klick auf den Extern-Link darf die Karte NICHT aufklappen (öffnet einen Tab).
    const link = linkKarte.locator('a.pw-extern-link')
    const [popup] = await Promise.all([
      page.waitForEvent('popup').catch(() => null),
      link.click(),
    ])
    if (popup) await popup.close().catch(() => {})
    await expect(linkKarte.locator('.pw-sitzung-details')).toHaveCount(0)
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// Sitzung öffnen und Traktanden
// ===========================================================================
test.describe('Sitzungen: Öffnen und Traktanden', () => {
  // Breites Viewport erzwingt die Tabellen- statt der Karten-Darstellung
  // (Container-Query .pw-sitzungen: unter 52em Breite blendet .pw-table-desktop aus).
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Interne Sitzung zeigt die vereinfachte Traktandentabelle; leere Sitzung zeigt den Hinweis', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Intern-Typ ${stamp}`
    const mitTr = `E2E-Intern mit Traktandum ${stamp}`
    const trTitel = `E2E-Intern-Traktandum ${stamp}`
    const leer = `E2E-Intern leer ${stamp}`
    await login(page, USER)
    const typId = await createSitzungstyp(page, { name })
    const s1 = await createSitzung(page, { typId, datum: inEinerWoche(), titel: mitTr, traktanden: [{ titel: trTitel, beschreibung: '' }] })
    const s2 = await createSitzung(page, { typId, datum: inEinerWoche(), titel: leer, traktanden: [] })
    await gotoView(page, 'Sitzungen')

    const k1 = await oeffneSitzung(page, s1.id)
    await expect(k1.locator('table.pw-tabelle-intern')).toBeVisible()
    await expect(k1.locator('.pw-tabelle-intern')).toContainText(trTitel)

    const k2 = await oeffneSitzung(page, s2.id)
    await expect(k2.getByText('Keine Traktanden vorhanden.')).toBeVisible()
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Parlamentssitzung zeigt die volle Traktandentabelle; Klick auf eine Titel-Zeile öffnet das Geschäft', async ({ page }) => {
    await login(page, USER)
    const treffer = await findeParlamentsSitzungMitGeschaeft(page)
    expect(treffer, 'Keine Parlamentssitzung mit Geschäfts-Traktandum in den Daten').not.toBeNull()

    await gotoView(page, 'Sitzungen')
    await zeigeAlleSitzungen(page)
    const karte = await oeffneSitzung(page, treffer.sitzungId)
    const tabelle = karte.locator('.pw-table-desktop .pw-tabelle-traktanden')
    await expect(tabelle).toBeVisible({ timeout: 30_000 })

    // Klick auf die Titel-Zelle der ersten Geschäfts-Zeile öffnet GeschaeftDetail.
    const geschaeftNotizZeile = tabelle.locator('tr.pw-traktandum-notizen-zeile')
      .filter({ has: page.locator('.pw-sitzungsnotiz-hinweis') }).first()
    const datenRow = geschaeftNotizZeile.locator('xpath=preceding-sibling::tr[1]')
    await datenRow.locator('.pw-col-titel').click()
    await expect(page.locator('.pw-modal .pw-sitzungsnotizen-details')).toBeVisible({ timeout: 30_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// Verknüpfungen und To-do
// ===========================================================================
test.describe('Sitzungen: Verknüpfungen und To-do', () => {
  test('Verknüpfte Geschäfte hinzufügen und wieder lösen', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Verkn-Typ ${stamp}`
    const titel = `E2E-Verkn-Sitzung ${stamp}`
    await login(page, USER)
    const geschaefte = await apiGet(page, '/geschaefte?limit=5')
    expect(Array.isArray(geschaefte) && geschaefte.length > 0, 'Keine Geschäfte in den Daten').toBeTruthy()

    const typId = await createSitzungstyp(page, { name })
    const s = await createSitzung(page, { typId, datum: inEinerWoche(), titel })
    await gotoView(page, 'Sitzungen')
    const karte = await oeffneSitzung(page, s.id)

    const bereich = karte.locator('.pw-sitzung-geschaefte')
    await expect(bereich).toBeVisible()
    // Ein Geschäft über das Select verknüpfen.
    await ncSelectWaehle(page, bereich.locator('.v-select'))
    await expect(bereich.locator('.pw-verknuepfte-geschaefte li')).toHaveCount(1, { timeout: 15_000 })

    // Wieder lösen (✕).
    await bereich.locator('.pw-verknuepfte-geschaefte li button[title="Verknüpfung lösen"]').click()
    await expect(bereich.locator('.pw-verknuepfte-geschaefte li')).toHaveCount(0, { timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Verknüpfte Vorstösse hinzufügen und wieder lösen', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-VerknV-Typ ${stamp}`
    const titel = `E2E-VerknV-Sitzung ${stamp}`
    await login(page, USER)
    // Einen Vorstoss anlegen, damit die Auswahl sicher etwas bietet.
    const vorstoss = await (await apiPost(page, '/vorstoesse', { titel: `E2E-Vorstoss ${stamp}` })).json()
    expect(vorstoss && vorstoss.id, 'Vorstoss nicht angelegt').toBeTruthy()

    const typId = await createSitzungstyp(page, { name })
    const s = await createSitzung(page, { typId, datum: inEinerWoche(), titel })
    await gotoView(page, 'Sitzungen')
    const karte = await oeffneSitzung(page, s.id)

    const bereich = karte.locator('.pw-sitzung-vorstoesse')
    await expect(bereich).toBeVisible()
    // Einen Vorstoss über das Select verknüpfen.
    await ncSelectWaehle(page, bereich.locator('.v-select'))
    await expect(bereich.locator('.pw-verknuepfte-vorstoesse li')).toHaveCount(1, { timeout: 15_000 })

    // Wieder lösen (✕).
    await bereich.locator('.pw-verknuepfte-vorstoesse li button[title="Verknüpfung lösen"]').click()
    await expect(bereich.locator('.pw-verknuepfte-vorstoesse li')).toHaveCount(0, { timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('To-do zu Deck: nach dem Hinzufügen ist das Eingabefeld leer bzw. es erscheint ein Fehler-Toast', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Todo-Typ ${stamp}`
    const titel = `E2E-Todo-Sitzung ${stamp}`
    await login(page, USER)
    const typId = await createSitzungstyp(page, { name })
    const s = await createSitzung(page, { typId, datum: inEinerWoche(), titel })
    await gotoView(page, 'Sitzungen')
    const karte = await oeffneSitzung(page, s.id)

    const todo = karte.locator('.pw-sitzung-todo')
    const eingabe = todo.locator('input.pw-input')
    await eingabe.fill(`E2E-Aufgabe ${stamp}`)
    // Reale Antwort abwarten: mit Deck-Integration räumt der Erfolg das Feld,
    // ohne Deck erscheint ein Fehler-Toast (env-abhängig, aber deterministisch).
    const [resp] = await Promise.all([
      page.waitForResponse((r) => /\/todo$/.test(r.url()) && r.request().method() === 'POST', { timeout: 30_000 }),
      todo.getByRole('button', { name: 'Hinzufügen' }).click(),
    ])
    if (resp.ok()) {
      await expect(eingabe).toHaveValue('')
    } else {
      await expect(toast(page, 'To-do').first()).toBeVisible({ timeout: 15_000 })
    }
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Notizen zur Sitzung nutzen die geteilte NotizenListe (einheitlich, F38): schreiben und persistieren', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-SNotiz-Typ ${stamp}`
    const titel = `E2E-SNotiz-Sitzung ${stamp}`
    const notiz = `E2E-Sitzungsnotiz ${stamp}`
    await login(page, USER)
    const typId = await createSitzungstyp(page, { name })
    const s = await createSitzung(page, { typId, datum: inEinerWoche(), titel })
    await gotoView(page, 'Sitzungen')
    const karte = await oeffneSitzung(page, s.id)

    // Der Sitzungs-Notizbereich zeigt die geteilte NotizenListe (kein eigener Editor mehr).
    const bereich = karte.locator('.pw-sitzung-notizen')
    await expect(bereich.locator('h4', { hasText: 'Notizen zur Sitzung' })).toBeVisible({ timeout: 30_000 })
    const nl = bereich.locator('.pw-notizen-liste')
    await expect(nl, 'geteilte NotizenListe fehlt bei den Sitzungs-Notizen').toBeVisible()

    // Notiz über die geteilte Liste schreiben → erscheint und persistiert an der Sitzung.
    await notizenListeSchreiben(page, nl, notiz)
    await expect(nl.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    await gotoView(page, 'Sitzungen')
    const karte2 = await oeffneSitzung(page, s.id)
    const nl2 = karte2.locator('.pw-sitzung-notizen .pw-notizen-liste')
    await expect(nl2.getByText(notiz, { exact: false }).first(), 'Sitzungs-Notiz nicht persistiert').toBeVisible({ timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Verknüpfte Sitzungen zeigen fremde Notizen nur lesend; Entkoppeln entfernt den Block', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Link-Typ ${stamp}`
    const titelA = `E2E-Sitzung A ${stamp}`
    const titelB = `E2E-Sitzung B ${stamp}`
    const notizA = `E2E-NotizA ${stamp}`
    await login(page, USER)
    const typId = await createSitzungstyp(page, { name, verknuepfen: true })
    const a = await createSitzung(page, { typId, datum: inEinerWoche(), titel: titelA })
    const b = await createSitzung(page, { typId, datum: inEinerWoche(), titel: titelB })
    // A eine Sitzungs-Notiz geben (über den geteilten NotizService, wie überall),
    // dann B mit A verknüpfen.
    const notizResp = await apiPost(page, `/sitzungen/${a.id}/notizen`, { text: notizA })
    expect(notizResp.ok(), 'Sitzungs-Notiz konnte nicht angelegt werden').toBeTruthy()
    const vk = await apiPost(page, `/sitzungen/${b.id}/verknuepfen`, { zielId: a.id })
    expect(vk.ok(), 'Verknüpfen fehlgeschlagen').toBeTruthy()

    await gotoView(page, 'Sitzungen')
    const karteB = await oeffneSitzung(page, b.id)
    const block = karteB.locator('.pw-verknuepfte-sitzungen')
    await expect(block).toBeVisible({ timeout: 30_000 })
    // Fremde Notiz erscheint, aber nur lesend (kein «+ Neue Notiz», kein Löschen).
    await expect(block.getByText(notizA, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    await expect(block.locator('.pw-btn-neue-notiz')).toHaveCount(0)
    await expect(block.locator('.pw-btn-loeschen')).toHaveCount(0)

    // Entkoppeln entfernt den Block.
    await block.getByRole('button', { name: 'Entkoppeln' }).click()
    await expect(karteB.locator('.pw-verknuepfte-sitzungen')).toHaveCount(0, { timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ===========================================================================
// Sitzungsnotiz-Feature (Kern des neuen Features)
// ===========================================================================
test.describe('Sitzungsnotiz: haftet am Geschäft', () => {
  // Breites Viewport erzwingt die Traktanden-Tabellendarstellung (siehe oben).
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Traktandum ohne Geschäft nutzt dieselbe NotizenListe wie überall (einheitlich, F39)', async ({ page }) => {
    const stamp = Date.now()
    const name = `E2E-Inline-Typ ${stamp}`
    const titel = `E2E-Inline-Sitzung ${stamp}`
    const trTitel = `E2E-Inline-Traktandum ${stamp}`
    const notiz = `E2E-Traktandum-Notiz ${stamp}`
    await login(page, USER)
    const typId = await createSitzungstyp(page, { name })
    const s = await createSitzung(page, { typId, datum: inEinerWoche(), titel, traktanden: [{ titel: trTitel, beschreibung: '' }] })
    await gotoView(page, 'Sitzungen')
    const karte = await oeffneSitzung(page, s.id)

    // Vereinheitlicht: geschäftsloses Traktandum nutzt dieselbe geteilte NotizenListe
    // wie überall (Hinweis «Notiz zum Traktandum», «+ Neue Notiz»), NICHT mehr einen
    // eigenen, daueroffenen Inline-Editor.
    const notizZelle = karte.locator('table.pw-tabelle-intern tr.pw-traktandum-notizen-zeile').first()
    await expect(notizZelle.locator('.pw-sitzungsnotiz-hinweis', { hasText: 'Notiz zum Traktandum' })).toBeVisible({ timeout: 15_000 })
    await expect(notizZelle.locator('.pw-neue-notiz .ProseMirror'), 'der alte Inline-Editor darf nicht mehr da sein').toHaveCount(0)
    const nl = notizZelle.locator('.pw-notizen-liste')
    await expect(nl, 'geteilte NotizenListe fehlt am geschäftslosen Traktandum').toBeVisible()

    // Notiz über die geteilte Liste schreiben → erscheint und persistiert am Traktandum.
    await notizenListeSchreiben(page, nl, notiz)
    await expect(nl.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    await gotoView(page, 'Sitzungen')
    const karte2 = await oeffneSitzung(page, s.id)
    const nl2 = karte2.locator('table.pw-tabelle-intern tr.pw-traktandum-notizen-zeile').first().locator('.pw-notizen-liste')
    await expect(nl2.getByText(notiz, { exact: false }).first(), 'Traktandum-Notiz nicht persistiert').toBeVisible({ timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Traktandum mit Geschäft: NotizenListe «Sitzungsnotiz zum Geschäft», persistiert, erscheint im Geschäft (ausklappbar) und nicht in den normalen Notizen; folgt dem Geschäft', async ({ page }) => {
    const notiz = `E2E-Sitzungsnotiz ${Date.now()}`
    await login(page, USER)
    // Reale Parlamentssitzung mit Geschäfts-Traktandum (interne Traktanden können
    // keine geschaeftId tragen — siehe Kopf-Kommentar). Bevorzugt ein Geschäft an >=2 Sitzungen.
    const treffer = await findeParlamentsSitzungMitGeschaeft(page)
    expect(treffer, 'Keine Parlamentssitzung mit Geschäfts-Traktandum in den Daten').not.toBeNull()

    expect(treffer.geschaeftTitel, 'Geschäftstitel für die Zeilen-Auswahl fehlt').not.toBe('')

    await gotoView(page, 'Sitzungen')
    await zeigeAlleSitzungen(page)
    const karte = await oeffneSitzung(page, treffer.sitzungId)
    const tabelle = karte.locator('.pw-table-desktop .pw-tabelle-traktanden')
    await expect(tabelle).toBeVisible({ timeout: 30_000 })
    // GEZIELT das (an >=2 Sitzungen hängende) Geschäft über seinen Titel treffen —
    // die «Notiz folgt dem Geschäft»-Prüfung braucht dasselbe Geschäft an beiden Sitzungen.
    const datenRow = tabelle.locator('tr.pw-table-row-clickable', { hasText: treffer.geschaeftTitel }).first()
    await datenRow.scrollIntoViewIfNeeded()
    const notizZeile = datenRow.locator('xpath=following-sibling::tr[1]')
    await expect(notizZeile.locator('.pw-sitzungsnotiz-hinweis')).toHaveText('Sitzungsnotiz zum Geschäft')

    // Sitzungsnotiz über die geteilte NotizenListe schreiben.
    const nl = notizZeile.locator('.pw-notizen-liste')
    await notizenListeSchreiben(page, nl, notiz)
    await expect(nl.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })

    // Neu laden → Notiz bleibt erhalten (am Geschäft persistiert).
    await gotoView(page, 'Sitzungen')
    await zeigeAlleSitzungen(page)
    const karte2 = await oeffneSitzung(page, treffer.sitzungId)
    const datenRow2 = karte2.locator('.pw-table-desktop .pw-tabelle-traktanden tr.pw-table-row-clickable', { hasText: treffer.geschaeftTitel }).first()
    await datenRow2.scrollIntoViewIfNeeded()
    const nl2 = datenRow2.locator('xpath=following-sibling::tr[1]').locator('.pw-notizen-liste')
    await expect(nl2.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })

    // Geschäft öffnen: die Notiz steht unter dem ausklappbaren <details>, NICHT in den normalen Notizen.
    await datenRow2.locator('.pw-col-titel').click()
    const modal = page.locator('.pw-modal', { has: page.locator('.pw-sitzungsnotizen-details') })
    await expect(modal).toBeVisible({ timeout: 30_000 })
    const details = modal.locator('.pw-sitzungsnotizen-details')
    // Standardmässig eingeklappt → aufklappen.
    await expect(details).not.toHaveJSProperty('open', true)
    await details.locator('summary').click()
    await expect(details.locator('.pw-notizen-liste').getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    // Nicht in der normalen (ersten) NotizenListe.
    await expect(modal.locator('.pw-notizen-liste').first().getByText(notiz, { exact: false })).toHaveCount(0)
    await modal.locator('.pw-btn-schliessen').click()

    // Folgt dem Geschäft: an der zweiten Parlamentssitzung mit demselben Geschäft
    // erscheint dieselbe Sitzungsnotiz (seed: Geschäft hängt an zwei Sitzungen).
    if (treffer.zweiteSitzungId) {
      const karte3 = await oeffneSitzung(page, treffer.zweiteSitzungId)
      const datenRow3 = karte3.locator('.pw-table-desktop .pw-tabelle-traktanden tr.pw-table-row-clickable', { hasText: treffer.geschaeftTitel }).first()
      await datenRow3.scrollIntoViewIfNeeded()
      const nl3 = datenRow3.locator('xpath=following-sibling::tr[1]').locator('.pw-notizen-liste')
      await expect(nl3.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    }
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Sitzungsnotiz löschen (Soft-Delete) und wiederherstellen', async ({ page }) => {
    const notiz = `E2E-Loesch-Sitzungsnotiz ${Date.now()}`
    await login(page, USER)
    const treffer = await findeParlamentsSitzungMitGeschaeft(page)
    expect(treffer, 'Keine Parlamentssitzung mit Geschäfts-Traktandum in den Daten').not.toBeNull()

    await gotoView(page, 'Sitzungen')
    await zeigeAlleSitzungen(page)
    const karte = await oeffneSitzung(page, treffer.sitzungId)
    const zelle = karte.locator('.pw-table-desktop .pw-tabelle-traktanden tr.pw-traktandum-notizen-zeile')
      .filter({ has: page.locator('.pw-sitzungsnotiz-hinweis') }).first()
    const nl = zelle.locator('.pw-notizen-liste')
    await notizenListeSchreiben(page, nl, notiz)
    const eintrag = nl.locator('.pw-notiz-eintrag', { hasText: notiz }).first()
    await expect(eintrag).toBeVisible({ timeout: 15_000 })

    // Soft-Delete → Lösch-Vermerk in der geteilten Aktionszeitleiste der
    // Traktandenzeile (dieselbe Komponente wie im Geschäft/Vorstoss), nicht mehr
    // in der Notizenliste.
    const zeitleiste = zelle.locator('.pw-detail-abschnitt', { hasText: 'Aktionszeitleiste' })
    await eintrag.locator('.pw-btn-loeschen').click()
    await expect(zeitleiste.getByText('hat seine Notiz gelöscht', { exact: false }).first(), 'Gelöschte Sitzungsnotiz fehlt in der Aktionszeitleiste').toBeVisible({ timeout: 15_000 })
    await expect(nl.locator('.pw-notiz-eintrag', { hasText: notiz }), 'Gelöschte Sitzungsnotiz steht noch in der Notizenliste').toHaveCount(0)

    // Wiederherstellen (↺) über die Aktionszeitleiste → Text kommt in die Liste zurück.
    await zeitleiste.locator('button[title="Löschen rückgängig machen"]').first().click()
    await expect(nl.getByText(notiz, { exact: false }).first()).toBeVisible({ timeout: 15_000 })
    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
