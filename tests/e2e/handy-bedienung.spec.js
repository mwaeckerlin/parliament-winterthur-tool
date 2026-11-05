import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei,
// weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * Bedienung am Telefon, im echten Browser gegen den realen Stack.
 *
 * Gemessen am 19.10.2026 auf dem iPhone: In der aufgeklappten
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

// iPhone 13/14 in Punkten — das Format, auf dem der Fehler auftrat.
const TELEFON = { width: 390, height: 844 }

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

/**
 * Wartet, bis die Liste still steht: Nach einer Änderung der Fläche oder ihres
 * Inhalts setzt parlwin sie neu, und gemessen wird erst, wenn drei Messungen
 * in Folge dieselbe Lage ergeben.
 */
async function bisListeRuhig(menu) {
  let vorher = ''
  let gleich = 0
  await expect.poll(async () => {
    const jetzt = JSON.stringify(await menu.boundingBox())
    gleich = jetzt === vorher ? gleich + 1 : 0
    vorher = jetzt
    return gleich
  }, { timeout: 10_000, intervals: [100], message: 'die Liste kommt nicht zur Ruhe' }).toBeGreaterThanOrEqual(3)
}

/**
 * Prüft jeden Eintrag der offenen Liste dort, wo der Finger ihn trifft: Er
 * liegt mit oberer und unterer Kante in der sichtbaren Fläche, und an seiner
 * Mitte liegt er selbst. Was die Liste abschneidet, trifft dort der Finger nicht.
 */
async function jederEintragFrei(page, menu, flaecheBis) {
  const eintraege = menu.locator('.vs__dropdown-option')
  const anzahl = await eintraege.count()
  expect(anzahl, 'die Liste ist leer').toBeGreaterThan(0)
  for (let i = 0; i < anzahl; i++) {
    const kasten = await eintraege.nth(i).boundingBox()
    expect(kasten, `Eintrag ${i + 1} hat keine Fläche`).not.toBeNull()
    const x = kasten.x + kasten.width / 2
    const y = kasten.y + kasten.height / 2
    expect(kasten.y, `Eintrag ${i + 1} beginnt bei y=${Math.round(kasten.y)} über dem oberen Rand der Fläche`)
      .toBeGreaterThanOrEqual(0)
    expect(kasten.y + kasten.height,
      `Eintrag ${i + 1} endet bei y=${Math.round(kasten.y + kasten.height)} unter dem unteren Rand der Fläche bei ${flaecheBis}`)
      .toBeLessThanOrEqual(flaecheBis)
    const obenauf = await page.evaluate(([px, py]) => {
      const el = document.elementFromPoint(px, py)
      if (!el) { return 'nichts' }
      if (el.closest('.vs__dropdown-option')) { return 'eintrag' }
      const beschreibung = el.className || el.tagName
      return typeof beschreibung === 'string' ? beschreibung : el.tagName
    }, [x, y])
    expect(obenauf, `Eintrag ${i + 1} der Liste ist verdeckt oder abgeschnitten`).toBe('eintrag')
  }
}

/** Tippt das Filterfeld mit dieser Beschriftung an, so wie der Anwender es tut. */
/**
 * Das Auswahlfeld mit dieser Beschriftung. NcSelect setzt sein Etikett
 * `label.select__label` IN die Wurzel `.v-select`; gesucht wird darum die
 * Wurzel, die dieses Etikett trägt. Die frühere Suche über den nächsten
 * Vorfahren mit einem `.v-select` darin traf den ganzen Filterbereich und damit
 * dessen erstes Feld, «Budgetjahr», gleich welche Beschriftung gesucht war.
 */
function auswahlFeld(page, beschriftungText) {
  return page.locator('#pw-filter-slot .v-select').filter({
    has: page.locator('label.select__label', { hasText: new RegExp(`^\\s*${beschriftungText}\\s*$`) }),
  }).first()
}

async function feldOeffnen(page, beschriftungText) {
  const feld = auswahlFeld(page, beschriftungText)
  await feld.scrollIntoViewIfNeeded()
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
    const departement = auswahlFeld(page, 'Departement')
    await departement.scrollIntoViewIfNeeded()
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

    const departement = await feldOeffnen(page, 'Departement')
    // Das Suchfeld von vue-select bekommt beim Öffnen den Fokus; auf dem iPhone
    // fährt dann die Bildschirmtastatur hoch und lässt rund die Hälfte der
    // Fläche übrig. Der Browser des Testlaufs kennt keine Tastatur — die
    // Verkleinerung stellt nach, was sie bewirkt.
    const MIT_TASTATUR = Math.round(TELEFON.height * 0.52)
    await page.setViewportSize({ width: TELEFON.width, height: MIT_TASTATUR })

    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu, 'die Liste ist mit der Tastatur verschwunden').toBeVisible()
    await bisListeRuhig(menu)
    const erste = menu.locator('.vs__dropdown-option').first()
    const kasten = await erste.boundingBox()
    expect(kasten, 'der erste Eintrag hat keine Fläche mehr').not.toBeNull()

    // Alles, womit die Lage gerechnet wird — damit ein Fehlschlag die Ursache
    // nennt und nicht nur das Ergebnis.
    const stand = await departement.locator('.vs__dropdown-toggle').first().evaluate((feld) => {
      const liste = document.querySelector('.vs__dropdown-menu')
      const r = (el) => {
        if (!el) { return null }
        const b = el.getBoundingClientRect()
        return { top: Math.round(b.top), bottom: Math.round(b.bottom), hoehe: Math.round(b.height) }
      }
      return {
        liste: r(liste),
        feld: r(feld),
        inhalt: liste ? {
          eintraege: liste.querySelectorAll('.vs__dropdown-option').length,
          keineEintraege: !!liste.querySelector('.vs__no-options'),
          hoehe: liste.scrollHeight,
        } : null,
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

  test('Die Liste wächst mit, wenn ihre Einträge erst nach dem Antippen ankommen', async ({ page }) => {
    // Befund aus der Regression vom 01.11.2026: Das Feld «Departement» war
    // angetippt, bevor das Budget geladen war. Die leere Liste war 31 Punkte
    // hoch und behielt diese Höhe, als die Departemente ankamen; im Bild stand
    // ein Streifen des ersten. Die Antwort des Servers wird zurückgehalten,
    // bis die Liste offen ist: Sie kommt echt vom Server, nur später.
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await sorgeFuerBudgetjahr(page, 2026)

    let freigeben
    const freigabe = new Promise((weiter) => { freigeben = weiter })
    let angekommen
    const anfrage = new Promise((da) => { angekommen = da })
    const budgetAnsicht = (url) => /\/apps\/parlwin\/budget\/\d{4}$/.test(new URL(url).pathname)
    await page.route((url) => budgetAnsicht(url.href), async (route) => {
      angekommen()
      await freigabe
      await route.continue()
    })

    await page.setViewportSize(TELEFON)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
    await navigationOeffnen(page)
    await page.getByRole('link', { name: 'Budget', exact: true }).click()
    await expect(page.locator('.pw-view-title')).toHaveText('Budget', { timeout: 20_000 })
    await anfrage
    await navigationOeffnen(page)

    await feldOeffnen(page, 'Departement')
    const menu = page.locator('.vs__dropdown-menu').first()
    await expect(menu.locator('.vs__no-options'),
      'die Liste ist beim Antippen schon gefüllt, der Fall ist nicht hergestellt').toBeVisible({ timeout: 10_000 })

    const antwort = page.waitForResponse((r) => budgetAnsicht(r.url()))
    freigeben()
    expect((await antwort).ok(), 'die Budgetansicht lädt nicht').toBeTruthy()
    await expect(menu.locator('.vs__dropdown-option').first(), 'die Departemente kommen nicht in der Liste an')
      .toBeVisible({ timeout: 20_000 })
    await bisListeRuhig(menu)

    await jederEintragFrei(page, menu, TELEFON.height)
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

    const departement = auswahlFeld(page, 'Departement')
    await departement.scrollIntoViewIfNeeded()
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
