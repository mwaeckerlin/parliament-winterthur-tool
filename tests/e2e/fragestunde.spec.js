import { test, expect } from '@playwright/test'

/**
 * E2E der FRAGESTUNDE (F114) im echten Browser gegen das reale System: Eine
 * Fragestunde anlegen, Fragen eintragen, in der Fraktionssitzung anpassen und zum
 * Einreichen zuteilen.
 *
 * Geprüft wird der ganze Weg, den die Fraktion geht — nicht ein einzelner Aufruf:
 * anlegen, eintragen, zuteilen, wiederfinden. Dazu die zwei Regeln des Parlaments
 * (Frist am Donnerstag davor, höchstens 1'000 Zeichen) und die Regel des Rats,
 * dass jedes Mitglied nur eine Frage einreicht.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP_URL = `${BASE_URL}/index.php/apps/parlwin/`

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
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

async function gotoFragestunde(page) {
  await page.goto(APP_URL)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
  await page.getByRole('link', { name: 'Fragestunde', exact: true }).click()
  await page.waitForSelector('.pw-fragestunde', { timeout: 30_000 })
}

function fehlerWaechter(page) {
  const arr = []
  page.on('pageerror', (e) => arr.push(e.message))
  return arr
}

/** Ein Datum, das noch keine Fragestunde hat — jeder Lauf legt seine eigene an. */
function eigenesDatum() {
  const tag = new Date(Date.now() + (Math.floor(Math.random() * 900) + 30) * 86_400_000)
  return tag.toISOString().slice(0, 10)
}

test.describe('Fragestunde: Fragen sammeln, besprechen und zuteilen (F114)', () => {
  test('Der ganze Weg der Fraktion: sammeln, wiederfinden, zuteilen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoFragestunde(page)

    // 1. Eine Frage eintragen, OHNE dass eine Fragestunde angesetzt ist. Der
    // Urheber ist vorbelegt, die Fragestunde bleibt leer.
    // «exact», sonst trifft der Name auch «+ Neue Fragestunde».
    await page.getByRole('button', { name: '+ Neue Frage', exact: true }).click()
    const maske = page.locator('.pw-modal').filter({ hasText: 'Neue Frage' })
    const text = `E2E-Frage ${Date.now()}: Wann wird der Veloweg an der Seenerstrasse markiert?`
    await maske.locator('textarea').first().fill(text)
    await maske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()

    const frage = page.locator('.pw-frage').filter({ hasText: text })
    await expect(frage, 'Die eingetragene Frage steht nicht in der Liste').toBeVisible({ timeout: 15_000 })
    await expect(frage, 'Ohne Fragestunde fehlt der Hinweis').toContainText('noch keiner zugeteilt')
    await expect(frage, 'Ohne Einreicher fehlt der Hinweis').toContainText('noch nicht zugeteilt')

    // 2. Die Frage bleibt nach dem Neuladen — sie liegt auf dem Server.
    await gotoFragestunde(page)
    await expect(page.locator('.pw-frage').filter({ hasText: text }), 'Die Frage überlebt das Neuladen nicht')
      .toBeVisible({ timeout: 15_000 })

    // 3. Später kommt die Fragestunde dazu: Titel und Frist entstehen aus dem Datum.
    const datum = eigenesDatum()
    await page.getByRole('button', { name: '+ Neue Fragestunde' }).click()
    await page.locator('.pw-modal input[type="date"]').fill(datum)
    await page.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()

    const karte = page.locator('.pw-fragestunde-karte').filter({ hasText: 'Fragestunde vom' }).first()
    await expect(karte, 'Die angelegte Fragestunde steht nicht auf der Seite').toBeVisible({ timeout: 15_000 })
    await expect(karte, 'Die Frist des Parlamentsdienstes fehlt').toContainText('Einzureichen bis')
    await expect(karte, 'Die Grenze von 1000 Zeichen fehlt').toContainText('1000 Zeichen')

    // 4. In der Fraktionssitzung: die gesammelte Frage einer Fragestunde und
    // einem Mitglied zum Einreichen zuteilen. Die erste Auswahl ist «Eingebracht
    // von», dann folgen «Fragestunde», «Einzureichen von» und «Status».
    await page.locator('.pw-frage').filter({ hasText: text }).click()
    const bearbeiten = page.locator('.pw-modal').filter({ hasText: 'Frage bearbeiten' })
    await expect(bearbeiten).toBeVisible({ timeout: 15_000 })
    await bearbeiten.locator('.v-select').nth(1).click()
    await page.locator('.vs__dropdown-option').first().click()
    await bearbeiten.locator('.v-select').nth(2).click()
    await page.locator('.vs__dropdown-option').first().click()
    await bearbeiten.getByRole('button', { name: 'Dialog schliessen' }).click()

    const zugeteilt = page.locator('.pw-frage').filter({ hasText: text })
    await expect(zugeteilt, 'Die Zuteilung an die Fragestunde wurde nicht gespeichert')
      .not.toContainText('noch keiner zugeteilt', { timeout: 15_000 })
    await expect(zugeteilt, 'Der Einreicher wurde nicht gespeichert')
      .not.toContainText('noch nicht zugeteilt', { timeout: 15_000 })
    await expect(karte, 'Die Fragestunde zählt die zugeteilte Frage nicht').toContainText('zugeteilt')

    // 5. Eine zweite Frage, derselben Person und derselben Fragestunde zugeteilt:
    // Die Karte meldet es, denn jedes Mitglied reicht nur eine Frage ein.
    // «exact», sonst trifft der Name auch «+ Neue Fragestunde».
    await page.getByRole('button', { name: '+ Neue Frage', exact: true }).click()
    const zweite = `E2E-Frage ${Date.now()}: Wie viele Bäume pflanzt die Stadt im nächsten Jahr?`
    const zweiteMaske = page.locator('.pw-modal').filter({ hasText: 'Neue Frage' })
    await zweiteMaske.locator('textarea').first().fill(zweite)
    await zweiteMaske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
    const zweiteFrage = page.locator('.pw-frage').filter({ hasText: zweite })
    await expect(zweiteFrage, 'Die zweite Frage steht nicht in der Liste').toBeVisible({ timeout: 15_000 })

    await zweiteFrage.click()
    const zweitesBearbeiten = page.locator('.pw-modal').filter({ hasText: 'Frage bearbeiten' })
    await expect(zweitesBearbeiten).toBeVisible({ timeout: 15_000 })
    await zweitesBearbeiten.locator('.v-select').nth(1).click()
    await page.locator('.vs__dropdown-option').first().click()
    await zweitesBearbeiten.locator('.v-select').nth(2).click()
    await page.locator('.vs__dropdown-option').first().click()
    await zweitesBearbeiten.getByRole('button', { name: 'Dialog schliessen' }).click()

    await expect(
      page.locator('.pw-fragestunde-karte').filter({ hasText: 'Fragestunde vom' }).first()
        .locator('[data-mehrfach]'),
      'Zwei Fragen an dieselbe Person, und die Seite meldet es nicht',
    ).toBeVisible({ timeout: 15_000 })

    // Ein Bild der fertigen Seite zum Ansehen — über das Aussehen urteilt das Auge.
    await page.locator('.pw-fragestunde').screenshot({ path: 'test-results/f114-fragestunde.png' }).catch(() => {})

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Eine Frage über 1000 Zeichen lässt sich nicht speichern (Art. 103 Abs. 2)', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoFragestunde(page)

    // «exact», sonst trifft der Name auch «+ Neue Fragestunde».
    await page.getByRole('button', { name: '+ Neue Frage', exact: true }).click()

    const maske = page.locator('.pw-modal').filter({ hasText: 'Neue Frage' })
    await maske.locator('textarea').first().fill('a'.repeat(1001))
    const speichern = maske.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' })
    await expect(maske, 'Der Hinweis zum Kürzen fehlt').toContainText('bitte kürzen')
    await expect(speichern, 'Über der Grenze ist Speichern nicht gesperrt').toBeDisabled()

    // Genau 1000 Zeichen gehen durch.
    await maske.locator('textarea').first().fill('b'.repeat(1000))
    await expect(speichern, 'Genau 1000 Zeichen sind zulässig').toBeEnabled()

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
