import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei,
// weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * Die Ansicht «Stadtratsbeschlüsse» im echten Browser gegen den laufenden
 * Stack (F144 bis F146): der Abgleich holt die Beschlüsse von der Gegenstelle,
 * die Ansicht steht in der Navigation zwischen «Kommissionen» und «Vorstösse»,
 * und die Karten tragen Nummer, Titel, Datum, Departement und die Dokumente.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const USER = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

const norm = (s) => (s || '').replace(/\s+/g, ' ').trim()

async function apiGet(page, pfad) {
  const token = await page.evaluate(() => window.OC?.requestToken || '')
  const res = await page.request.get(`${BASE_URL}/index.php/apps/parlwin${pfad}`, {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
  })
  if (!res.ok()) throw new Error(`API ${pfad} → ${res.status()}`)
  return res.json()
}

async function ansichtOeffnen(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/#stadtratsbeschluesse`)
  await page.waitForSelector('.pw-stadtratsbeschluesse', { timeout: 60_000 })
  await expect(page.locator('.pw-view-title')).toHaveText('Stadtratsbeschlüsse')
}

test.describe('Stadtratsbeschlüsse', () => {
  test('der Abgleich hat Beschlüsse von der Quelle gelesen', async ({ page }) => {
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    const beschluesse = await apiGet(page, '/stadtratsbeschluesse')

    expect(Array.isArray(beschluesse), 'die Schnittstelle liefert keine Liste').toBe(true)
    expect(beschluesse.length, 'kein Beschluss eingelesen').toBeGreaterThan(0)

    const erster = beschluesse[0]
    expect(erster.nummer, `die Nummer fehlt: ${JSON.stringify(erster)}`).toMatch(/^\d{4}\/\d+$/)
    expect(erster.datum).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(String(erster.titel).length).toBeGreaterThan(3)
    expect(String(erster.departement).length).toBeGreaterThan(2)
    expect(erster.url).toContain('/stadtratsbeschluesse/')
  })

  test('die Ansicht steht in der Navigation zwischen Kommissionen und Vorstössen', async ({ page }) => {
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.app-navigation li a', { timeout: 60_000 })

    const eintraege = await page.locator('.app-navigation li a').allInnerTexts()
    const sauber = eintraege.map((t) => t.replace(/\s+/g, ' ').trim()).filter(Boolean)
    const kommissionen = sauber.findIndex((t) => t.includes('Kommissionen'))
    const beschluesse = sauber.findIndex((t) => t.includes('Stadtratsbeschlüsse'))
    const vorstoesse = sauber.findIndex((t) => t.includes('Vorstösse'))

    expect(beschluesse, `der Eintrag fehlt in: ${sauber.join(' | ')}`).toBeGreaterThan(-1)
    expect(beschluesse).toBe(kommissionen + 1)
    expect(vorstoesse).toBe(beschluesse + 1)
  })

  test('jede Karte zeigt Nummer, Titel, Datum und Departement', async ({ page }) => {
    await login(page, USER)
    await ansichtOeffnen(page)
    const beschluesse = await apiGet(page, '/stadtratsbeschluesse')
    expect(beschluesse.length, 'kein Beschluss eingelesen').toBeGreaterThan(0)

    // Die Ansicht zeigt die Beschlüsse in der Reihenfolge der Schnittstelle,
    // also gehört die Karte an Stelle i zum Beschluss an Stelle i.
    const karten = page.locator('.pw-srb-karte')
    await expect(karten).toHaveCount(beschluesse.length)

    for (let i = 0; i < beschluesse.length; i++) {
      const b = beschluesse[i]
      const karte = karten.nth(i)
      expect(b.nummer, `Beschluss ${i} ohne Nummer: ${JSON.stringify(b)}`).toMatch(/^\d{4}\/\d+$/)
      expect(b.datum, `Beschluss ${i} ohne Datum: ${JSON.stringify(b)}`).toMatch(/^\d{4}-\d{2}-\d{2}$/)
      expect(norm(await karte.locator('.pw-nr').textContent()), `Karte ${i}: die Nummer`).toBe(b.nummer)
      expect(norm(await karte.locator('h3').textContent()), `Karte ${i}: der Titel`).toBe(norm(b.titel))
      // Gemessen wird der Wert der Angabe, nicht ihre Beschriftung: mit leerem
      // Departement stünde die Beschriftung da und die Karte wäre leer.
      // Die Beschriftung steht über das Stylesheet in Grossbuchstaben da, und
      // `innerText` liefert sie so; gelesen wird darum der Text im Dokument.
      const paare = await karte.locator('.pw-data-pair').all()
      const angaben = {}
      for (const paar of paare) {
        angaben[norm(await paar.locator('span').textContent())] = norm(await paar.locator('strong').textContent())
      }
      expect(Object.keys(angaben), `Karte ${i} (${b.nummer}) ohne Departement`).toContain('Departement')
      expect(angaben.Departement.length, `Karte ${i} (${b.nummer}): das Departement ist leer`).toBeGreaterThan(2)
      expect(angaben.Departement, `Karte ${i} (${b.nummer}): das Departement`).toBe(norm(b.departement))
      const [jahr, monat, tag] = b.datum.split('-')
      expect(angaben.Beschlossen, `Karte ${i} (${b.nummer}): das Datum`).toBe(`${tag}.${monat}.${jahr}`)
    }
  })

  test('ein Dokument des Beschlusses lässt sich abrufen', async ({ page }) => {
    await login(page, USER)
    await ansichtOeffnen(page)

    const verweis = page.locator('.pw-srb-dokumente a').first()
    await expect(verweis, 'kein Dokument an den Beschlüssen').toBeVisible()
    const adresse = await verweis.getAttribute('href')
    expect(adresse).toContain('/download/resolution-document/')
    expect(await verweis.getAttribute('target')).toBe('_blank')

    const antwort = await page.request.get(adresse)
    expect(antwort.status(), `das Dokument antwortet nicht: ${adresse}`).toBe(200)
  })

  // F144: Ein Dokument ist ein Verweis und sieht aus wie die E-Mail in «Mitglieder».
  test('die Dokumente stehen in der Farbe der Verweise und nicht in der des Textes', async ({ page }) => {
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/#mitglieder`)
    const email = page.locator('.pw-mitglied-email').first()
    await email.waitFor({ state: 'visible', timeout: 60_000 })
    const verweisFarbe = await email.evaluate((el) => getComputedStyle(el).color)

    await ansichtOeffnen(page)
    const karte = page.locator('.pw-srb-karte', { has: page.locator('.pw-srb-dokumente a') }).first()
    await expect(karte, 'kein Dokument an den Beschlüssen').toBeVisible()
    const dokumentFarbe = await karte.locator('.pw-srb-dokumente a').first().evaluate((el) => getComputedStyle(el).color)
    const textFarbe = await karte.locator('h3').evaluate((el) => getComputedStyle(el).color)
    expect(dokumentFarbe, 'das Dokument hat nicht die Farbe der E-Mail in «Mitglieder»').toBe(verweisFarbe)
    expect(dokumentFarbe, 'das Dokument steht in der Farbe des Textes').not.toBe(textFarbe)
  })

  test('die Suche grenzt die Liste auf einen Beschluss ein', async ({ page }) => {
    await login(page, USER)
    await ansichtOeffnen(page)

    const vorher = await page.locator('.pw-srb-karte').count()
    expect(vorher).toBeGreaterThan(1)

    const nummer = (await page.locator('.pw-srb-karte .pw-nr').first().innerText()).trim()
    await page.fill('.pw-nav-search input', nummer)
    await expect(page.locator('.pw-srb-karte')).toHaveCount(1)
    await expect(page.locator('.pw-srb-karte .pw-nr')).toHaveText(nummer)

    await page.fill('.pw-nav-search input', 'Schneefall im August')
    await expect(page.getByText('Kein Beschluss passt zur Suche')).toBeVisible()
  })

  test('Departement und Jahr grenzen ein, «Filter zurücksetzen» stellt alles wieder her', async ({ page }) => {
    await login(page, USER)
    await ansichtOeffnen(page)
    const beschluesse = await apiGet(page, '/stadtratsbeschluesse')
    const karten = page.locator('.pw-srb-karte')
    await expect(karten).toHaveCount(beschluesse.length)

    const filter = page.locator('#pw-filter-slot')
    // Die Wurzel einer Auswahl trägt die Klasse `select` und ihr Etikett
    // `label.select__label`; die Liste hängt am Ende des Dokuments.
    const auswahl = (name) => filter.locator('.select').filter({ has: page.locator('label.select__label', { hasText: name }) })
    // Geöffnet wird über die Tastatur, wie bei den Filtern der Mitglieder: In der
    // Seitenleiste liegen die Einträge der Navigation über den Feldern und
    // fangen einen Klick ab.
    const oeffnen = async (name) => {
      await auswahl(name).locator('.vs__search').focus()
      await page.keyboard.press('ArrowDown')
      await expect(auswahl(name)).toHaveClass(/vs--open/)
    }
    const optionen = page.locator('.vs__dropdown-menu li.vs__dropdown-option')
    // Ohne Wahl steht die neutrale Option in der Auswahl, nicht ein leeres Feld.
    await expect(auswahl('Departement').locator('.vs__selected')).toHaveText('Alle Departemente')
    await expect(auswahl('Jahr').locator('.vs__selected')).toHaveText('Alle Jahre')

    // Die Liste führt genau die Departemente, die in den Beschlüssen stehen.
    const departemente = [...new Set(beschluesse.map((b) => b.departement).filter(Boolean))]
      .sort((a, b) => a.localeCompare(b, 'de-CH'))
    // Eine Option teilt ihren Namen für die Kürzung in der Mitte auf zwei
    // Elemente, und `innerText` setzt dazwischen einen Umbruch; gelesen wird
    // darum der Text im Dokument.
    await oeffnen('Departement')
    expect((await optionen.allTextContents()).map(norm)).toEqual(['Alle Departemente', ...departemente])
    const gewaehlt = departemente[departemente.length - 1]
    await optionen.filter({ hasText: gewaehlt }).first().click()
    await expect(auswahl('Departement').locator('.vs__selected')).toHaveText(gewaehlt)
    await expect(karten).toHaveCount(beschluesse.filter((b) => b.departement === gewaehlt).length)
    for (const wert of await karten.locator('.pw-data-pair', { hasText: 'Departement' }).locator('strong').allInnerTexts()) {
      expect(norm(wert), 'eine Karte eines anderen Departements steht noch da').toBe(gewaehlt)
    }

    // Das Jahr, das Departement bleibt stehen.
    const jahr = String(beschluesse.find((b) => b.departement === gewaehlt).datum).slice(0, 4)
    await oeffnen('Jahr')
    expect((await optionen.allTextContents()).map(norm), 'das Jahr fehlt in der Auswahl').toContain(jahr)
    await optionen.filter({ hasText: jahr }).first().click()
    await expect(auswahl('Jahr').locator('.vs__selected')).toHaveText(jahr)
    await expect(karten).toHaveCount(beschluesse
      .filter((b) => b.departement === gewaehlt && String(b.datum).startsWith(jahr)).length)

    await filter.getByRole('button', { name: /Filter zurücksetzen/ }).click()
    await expect(karten).toHaveCount(beschluesse.length)
    await expect(auswahl('Departement').locator('.vs__selected')).toHaveText('Alle Departemente')
    await expect(auswahl('Jahr').locator('.vs__selected')).toHaveText('Alle Jahre')
  })

  test('eine Notiz am Beschluss bleibt nach dem Neuladen stehen', async ({ page }) => {
    await login(page, USER)
    await ansichtOeffnen(page)

    const karte = page.locator('.pw-srb-karte').first()
    // Der Kopf der Notizen ist ein Schalter wie die Mitglieder an der
    // Kommission, ohne den Hintergrund, den Nextcloud jedem Knopf gibt.
    const kopf = karte.locator('.pw-srb-notizen-kopf')
    expect(await kopf.evaluate((el) => getComputedStyle(el).backgroundColor),
      'der Kopf der Notizen steht als gefüllter Knopf da').toBe('rgba(0, 0, 0, 0)')
    await kopf.click()

    const text = `Notiz aus dem Browsertest ${Date.now()}`
    await karte.locator('.pw-notizen-liste .pw-btn-neue-notiz').click()
    const editor = karte.locator('.pw-notizen-liste .ProseMirror').first()
    await editor.click()
    // Getippt wird erst, wenn der Editor den Fokus hat; sonst gehen die ersten
    // Zeichen verloren.
    await expect(editor).toBeFocused()
    await editor.pressSequentially(text, { delay: 25 })
    // Gespeichert wird nur über das Häkchen, das «Speichern» als Titel trägt.
    await karte.locator('.pw-notiz-bearbeiten-aktionen button[title="Speichern"]').first().click()

    await expect(karte.getByText(text)).toBeVisible()

    await page.reload()
    await page.waitForSelector('.pw-srb-karte', { timeout: 60_000 })
    const wieder = page.locator('.pw-srb-karte').first()
    await wieder.locator('.pw-srb-notizen-kopf').click()
    await expect(wieder.getByText(text), 'die Notiz hat das Neuladen nicht überlebt').toBeVisible()
  })
})
