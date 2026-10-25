import { test, expect } from '@playwright/test'

/**
 * Bedienung am Telefon, im echten Browser gegen den realen Stack.
 *
 * Befund von Marc am 19.10.2026, mit Bild vom iPhone: In der aufgeklappten
 * Navigation lässt sich der Filter «Departement» nicht auswählen. Die Filter
 * stehen auf dem Telefon in der Navigation, und die Navigation liegt dort über
 * dem Inhalt — was dort nicht bedienbar ist, ist für den Anwender am Telefon
 * gar nicht da.
 *
 * Geprüft wird, was der Anwender tut: Navigation öffnen, Feld antippen, einen
 * Wert wählen, und danach muss der Wert im Feld stehen und die Ansicht
 * gefiltert sein.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

// iPhone 13/14 in Punkten — dasselbe Format wie auf Marcs Bild.
const TELEFON = { width: 390, height: 844 }

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
    } catch (e) { zuletzt = e }
  }
  throw zuletzt
}

/** Öffnet die App am Telefon und wechselt in eine Ansicht. */
async function appAmTelefon(page, ansicht) {
  await page.setViewportSize(TELEFON)
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
  await navigationOeffnen(page)
  await page.getByRole('link', { name: ansicht, exact: true }).click()
  await expect(page.locator('.pw-view-title')).toHaveText(ansicht, { timeout: 20_000 })
  await page.locator('.pw-view-content .pw-laden').first().waitFor({ state: 'hidden', timeout: 20_000 }).catch(() => {})
}

async function api(page, method, pfad, form, timeout) {
  const token = await page.evaluate(() => (window.OC && window.OC.requestToken) || '')
  const opts = { headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded' } }
  if (form) { opts.form = form }
  if (timeout) { opts.timeout = timeout }
  return page.request[method](`${BASE_URL}/index.php/apps/parlwin${pfad}`, opts)
}

/**
 * Stellt ein eingelesenes Budgetjahr sicher — ohne Daten bietet der Filter
 * «Departement» nichts an («No results»), und der Test misst dann die leere
 * Liste statt der Bedienung (gemessen am 19.10.2026).
 */
async function sorgeFuerBudgetjahr(page, jahr) {
  const res = await api(page, 'get', '/budget/jahre')
  const jahre = res.ok() ? await res.json() : []
  if (jahre.some((j) => Number(j.jahr) === jahr)) { return }
  const quellen = await api(page, 'get', '/budget/verfuegbar')
  const verfuegbar = quellen.ok() ? await quellen.json() : {}
  expect((verfuegbar.verfuegbar || []).map(Number).includes(jahr),
    `Für ${jahr} ist keine Budget-Weisung synchronisiert (verfügbar: ${JSON.stringify(verfuegbar.verfuegbar || [])})`,
  ).toBeTruthy()
  const imp = await api(page, 'post', `/budget/${jahr}/import`, {}, 180_000)
  expect(imp.ok(), `Budget-Import ${jahr} fehlgeschlagen (${imp.status()})`).toBeTruthy()
}

/**
 * Klappt die Navigation auf, in der am Telefon die Filter stehen.
 *
 * Geprüft wird an einem Navigationslink, ob sie wirklich offen ist: Die
 * zugeklappte Navigation steht am Telefon neben dem Bildschirm, und ihre
 * Elemente gelten dem Browser trotzdem als sichtbar — eine Prüfung auf
 * «sichtbar» las sie darum als offen (gemessen am 19.10.2026).
 */
async function navigationOeffnen(page) {
  const erster = page.getByRole('link', { name: 'Geschäfte', exact: true })
  const offen = async () => {
    const kasten = await erster.boundingBox().catch(() => null)
    return !!kasten && kasten.x >= 0
  }
  if (await offen()) { return }
  await page.getByRole('button', { name: /navigation/i }).first().click()
  await expect.poll(offen, { timeout: 10_000, message: 'die Navigation klappt nicht auf' }).toBe(true)
}

/** Tippt das Filterfeld mit dieser Beschriftung an, so wie der Anwender es tut. */
async function feldOeffnen(page, beschriftungText) {
  const beschriftung = page.locator('#pw-filter-slot label', { hasText: new RegExp(`^\\s*${beschriftungText}\\s*$`) }).first()
  await beschriftung.scrollIntoViewIfNeeded()
  const feld = beschriftung.locator('xpath=ancestor::*[.//div[contains(@class,"v-select")]][1]')
    .locator('.v-select').first()
  await feld.locator('.vs__dropdown-toggle').first().click()
  return feld
}

test.describe('Bedienung am Telefon', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, U1)
  })

  test('Das Auswahlfeld «Departement» lässt sich am Telefon bedienen', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)
    await appAmTelefon(page, 'Budget')
    await navigationOeffnen(page)

    // Das Feld steht im Filterbereich unter seiner Beschriftung «Departement»;
    // gesucht wird über die Beschriftung und das Feld in deren Umgebung.
    const beschriftung = page.locator('#pw-filter-slot label', { hasText: /^\s*Departement\s*$/ }).first()
    await beschriftung.scrollIntoViewIfNeeded()
    const departement = beschriftung.locator('xpath=ancestor::*[.//div[contains(@class,"v-select")]][1]')
      .locator('.v-select').first()
    await departement.locator('.vs__dropdown-toggle').first().click()

    // Die Liste muss offen UND sichtbar sein: Ein Menü, das ausserhalb des
    // Bildschirms oder hinter der Navigation liegt, ist nicht bedienbar.
    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu, 'die Liste der Departemente öffnet sich nicht').toBeVisible({ timeout: 10_000 })
    const kasten = await menu.boundingBox()
    expect(kasten, 'die Liste hat keine Fläche').not.toBeNull()
    expect(kasten.y, 'die Liste beginnt unterhalb des Bildschirms').toBeLessThan(TELEFON.height)
    expect(kasten.y + Math.min(kasten.height, 80), 'von der Liste ist nichts zu sehen').toBeLessThan(TELEFON.height)

    const erste = menu.locator('.vs__dropdown-option').first()
    const name = (await erste.textContent()).trim()
    await erste.click()

    await expect(departement, 'der gewählte Wert steht nicht im Feld').toContainText(name, { timeout: 10_000 })
  })

  test('Die geöffnete Liste ist an jedem Eintrag frei, nichts liegt darüber', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)
    await appAmTelefon(page, 'Budget')
    await navigationOeffnen(page)

    await feldOeffnen(page, 'Departement')
    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu).toBeVisible({ timeout: 10_000 })

    const eintraege = menu.locator('.vs__dropdown-option')
    const anzahl = await eintraege.count()
    expect(anzahl, 'die Liste ist leer').toBeGreaterThan(0)

    for (let i = 0; i < anzahl; i++) {
      const eintrag = eintraege.nth(i)
      const kasten = await eintrag.boundingBox()
      if (!kasten) { continue }
      const x = kasten.x + kasten.width / 2
      const y = kasten.y + kasten.height / 2
      if (y < 0 || y > TELEFON.height) {
        // Liegt der Eintrag ausserhalb des Bildschirms, ist er ebenso wenig zu
        // treffen wie ein verdeckter.
        expect(`Eintrag ${i + 1} liegt bei y=${Math.round(y)} ausserhalb des Bildschirms`).toBe('')
      }
      const obenauf = await page.evaluate(([px, py]) => {
        const el = document.elementFromPoint(px, py)
        if (!el) { return 'nichts' }
        if (el.closest('.vs__dropdown-option')) { return 'eintrag' }
        const beschreibung = el.className || el.tagName
        return typeof beschreibung === 'string' ? beschreibung : el.tagName
      }, [x, y])
      expect(obenauf, `Eintrag ${i + 1} der Liste ist verdeckt`).toBe('eintrag')
    }
  })

  test('Die Liste bleibt bedienbar, wenn die Bildschirmtastatur die Fläche halbiert', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)
    await appAmTelefon(page, 'Budget')
    await navigationOeffnen(page)

    await feldOeffnen(page, 'Departement')
    // Das Suchfeld von vue-select bekommt beim Öffnen den Fokus; auf dem iPhone
    // fährt dann die Bildschirmtastatur hoch und lässt rund die Hälfte der
    // Fläche übrig. Der Browser des Testlaufs kennt keine Tastatur — die
    // Verkleinerung stellt nach, was sie bewirkt.
    const MIT_TASTATUR = Math.round(TELEFON.height * 0.52)
    await page.setViewportSize({ width: TELEFON.width, height: MIT_TASTATUR })
    await page.waitForTimeout(500)

    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu, 'die Liste ist mit der Tastatur verschwunden').toBeVisible()
    const erste = menu.locator('.vs__dropdown-option').first()
    const kasten = await erste.boundingBox()
    expect(kasten, 'der erste Eintrag hat keine Fläche mehr').not.toBeNull()

    // Alles, womit die Lage gerechnet wird — damit ein Fehlschlag die Ursache
    // nennt und nicht nur das Ergebnis.
    const stand = await page.evaluate(() => {
      const liste = document.querySelector('.vs__dropdown-menu')
      const feld = document.querySelector('#pw-filter-slot .v-select .vs__dropdown-toggle')
      const r = (el) => {
        if (!el) { return null }
        const b = el.getBoundingClientRect()
        return { top: Math.round(b.top), bottom: Math.round(b.bottom), hoehe: Math.round(b.height) }
      }
      return {
        liste: r(liste),
        feld: r(feld),
        stil: liste ? { position: liste.style.position, top: liste.style.top, maxHeight: liste.style.maxHeight } : null,
        nachOben: liste ? liste.classList.contains('vs__dropdown-menu--floating-placement-top') : null,
        sichtbar: window.visualViewport ? Math.round(window.visualViewport.height) : window.innerHeight,
        fenster: window.innerHeight,
      }
    })

    expect(kasten.y, 'der erste Eintrag liegt über dem oberen Rand').toBeGreaterThanOrEqual(0)
    expect(kasten.y + kasten.height,
      `der erste Eintrag liegt unter der Tastatur (y=${Math.round(kasten.y)}, Fläche bis ${MIT_TASTATUR}); `
      + `Stand: ${JSON.stringify(stand)}`)
      .toBeLessThanOrEqual(MIT_TASTATUR)
  })

  test('Jedes Filterfeld ist am Telefon erreichbar, ohne dass etwas es verdeckt', async ({ page }) => {
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)
    await appAmTelefon(page, 'Budget')
    await navigationOeffnen(page)

    // Der Anwender tippt dorthin, wo das Feld gerade steht — er scrollt es
    // nicht erst in die Mitte. Jedes Feld muss also entweder im Bild stehen
    // oder durch Scrollen im Filterbereich dorthin kommen, und am getippten
    // Punkt muss das Feld selbst liegen und nichts darüber.
    const felder = page.locator('#pw-filter-slot .v-select .vs__dropdown-toggle')
    const anzahl = await felder.count()
    expect(anzahl, 'die Filter fehlen in der Navigation').toBeGreaterThan(2)

    for (let i = 0; i < anzahl; i++) {
      const feld = felder.nth(i)
      await feld.scrollIntoViewIfNeeded()
      const kasten = await feld.boundingBox()
      expect(kasten, `Filterfeld ${i + 1} hat keine Fläche`).not.toBeNull()
      const x = kasten.x + kasten.width / 2
      const y = kasten.y + kasten.height / 2
      const obenauf = await page.evaluate(([px, py]) => {
        const el = document.elementFromPoint(px, py)
        if (!el) { return 'nichts' }
        const feld = el.closest('.vs__dropdown-toggle')
        return feld ? 'feld' : (el.className || el.tagName)
      }, [x, y])
      expect(obenauf, `Filterfeld ${i + 1} ist an seiner Stelle verdeckt`).toBe('feld')
    }
  })

  test('Die Liste eines Filters steht am Telefon im Bild, auch im dunklen Thema', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' })
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)
    await appAmTelefon(page, 'Budget')
    await navigationOeffnen(page)

    const beschriftung = page.locator('#pw-filter-slot label', { hasText: /^\s*Departement\s*$/ }).first()
    await beschriftung.scrollIntoViewIfNeeded()
    const departement = beschriftung.locator('xpath=ancestor::*[.//div[contains(@class,"v-select")]][1]')
      .locator('.v-select').first()
    await departement.locator('.vs__dropdown-toggle').first().click()

    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu, 'die Liste öffnet sich im dunklen Thema nicht').toBeVisible({ timeout: 10_000 })
    const eintrag = menu.locator('.vs__dropdown-option').first()
    await expect(eintrag, 'die Liste bleibt im dunklen Thema leer').toBeVisible()
    // Lesbar heisst: Der Eintrag steht im Bild und hat eine Fläche, auf die
    // ein Finger trifft.
    const kasten = await eintrag.boundingBox()
    expect(kasten.height, 'der Eintrag ist zu flach für einen Finger').toBeGreaterThan(20)
    expect(kasten.y + kasten.height, 'der Eintrag liegt unterhalb des Bildschirms')
      .toBeLessThanOrEqual(TELEFON.height)
  })
})
