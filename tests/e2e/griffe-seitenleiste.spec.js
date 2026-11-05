import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei,
// weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * Die beiden Griffe am Kopf der Seitenleiste bilden ein Paar: der Griff, der
 * die Leiste ein- und ausklappt, und der Knopf fürs Vollbild (F131).
 *
 * Die Anforderung: Offen steht das Paar rechtsbündig am Rand der Leiste.
 * Zugeklappt rückt es nach links, bis der linke Knopf am linken Rand des
 * Inhalts steht, die Breite beider Knöpfe zählt also mit. Der Vollbild-Knopf
 * bleibt bei jeder Schirmgrösse sichtbar.
 *
 * Gemessen wird die Lage im Browser, nicht der Text des Stylesheets: Vorher
 * stand das Paar 42 Pixel rechts über den Rand der Leiste hinaus im
 * Inhaltsbereich und zugeklappt 26 Pixel links ausserhalb des Bildes.
 */
const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const USER = {
  name: process.env.PW_U1 || 'parlwin_praesidium',
  pass: process.env.PW_P1 || '',
}

/** Die Kanten von Leiste, Behälter und den beiden Knöpfen. */
async function kanten(page) {
  return page.evaluate(() => {
    const r = (wahl) => {
      const el = document.querySelector(wahl)
      if (!el) { return null }
      const k = el.getBoundingClientRect()
      return { links: Math.round(k.x), rechts: Math.round(k.right), breite: Math.round(k.width) }
    }
    const nav = document.querySelector('.app-navigation, #app-navigation, #app-navigation-vue')
    const knoepfe = [...document.querySelectorAll('.app-navigation-toggle-wrapper > *')]
      .map((el) => {
        const k = el.getBoundingClientRect()
        return { links: Math.round(k.x), rechts: Math.round(k.right) }
      })
    return {
      zugeklappt: !!(nav && nav.className.includes('app-navigation--closed')),
      leiste: r('.app-navigation, #app-navigation, #app-navigation-vue'),
      behaelter: r('.app-navigation-toggle-wrapper'),
      knoepfe,
    }
  })
}

/**
 * Wartet, bis die Kanten des Behälters über drei Messungen gleich bleiben.
 *
 * Nextcloud schiebt das Paar mit einer Bewegung an seinen Platz; eine feste
 * Wartezeit wäre auf der leeren Maschine grün und unter Last rot.
 */
async function bisRuhig(page) {
  let vorher = ''
  let gleich = 0
  await expect.poll(async () => {
    const jetzt = JSON.stringify((await kanten(page)).behaelter)
    gleich = jetzt === vorher ? gleich + 1 : 0
    vorher = jetzt
    return gleich
  }, { timeout: 15_000, intervals: [100] }).toBeGreaterThanOrEqual(3)
}

async function leisteUmschalten(page) {
  // Über die Rolle geklickt, nicht über das DOM: so ist mitgeprüft, dass der
  // Griff für einen Finger erreichbar ist und nichts darüber liegt.
  await page.getByRole('button', { name: /navigation/i }).first().click()
  await bisRuhig(page)
}

test.describe('Die beiden Griffe am Kopf der Seitenleiste', () => {
  test('stehen offen rechtsbündig in der Leiste und zugeklappt am linken Bildrand', async ({ page }) => {
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.app-navigation-toggle-wrapper', { timeout: 30_000 })
    await bisRuhig(page)

    const offen = await kanten(page)
    expect(offen.zugeklappt, 'die Leiste ist schon zugeklappt').toBe(false)
    expect(offen.knoepfe.length, 'es stehen nicht beide Knöpfe im Behälter').toBe(2)
    // Beide ganz in der Leiste, rechtsbündig an deren Rand.
    expect(Math.abs(offen.behaelter.rechts - offen.leiste.rechts),
      `der Behälter endet bei ${offen.behaelter.rechts}, die Leiste bei ${offen.leiste.rechts}`)
      .toBeLessThanOrEqual(1)
    expect(offen.behaelter.links, 'der Behälter ragt links aus der Leiste')
      .toBeGreaterThanOrEqual(offen.leiste.links)

    await leisteUmschalten(page)
    const zu = await kanten(page)
    expect(zu.zugeklappt, 'die Leiste klappte nicht zu').toBe(true)
    expect(zu.knoepfe.length, 'zugeklappt stehen nicht beide Knöpfe im Behälter').toBe(2)
    // Der linke der beiden Knöpfe steht am linken Rand des Inhalts; die Breite
    // beider zählt, der Behälter steht also vollständig rechts davon.
    const inhaltLinks = await page.evaluate(() => {
      const el = document.querySelector('#app-content, #app-content-vue, .app-content')
      return el ? Math.round(el.getBoundingClientRect().x) : 0
    })
    expect(Math.abs(zu.knoepfe[0].links - inhaltLinks),
      `der linke Knopf steht bei ${zu.knoepfe[0].links}, der Rand bei ${inhaltLinks}`)
      .toBeLessThanOrEqual(1)
    expect(zu.behaelter.rechts, 'der Behälter liegt nicht rechts seines linken Randes')
      .toBeGreaterThan(zu.knoepfe[0].links)
    // Der Titel der Ansicht beginnt rechts des Paares: Er stand mit seinem
    // ersten Buchstaben unter dem Vollbild-Knopf (Design-Lauf vom 03.11.2026).
    const titelLinks = await page.locator('.pw-view-title').first().evaluate((el) => Math.round(el.getBoundingClientRect().x))
    expect(titelLinks, `der Titel beginnt bei ${titelLinks}, das Paar endet bei ${zu.behaelter.rechts}`)
      .toBeGreaterThanOrEqual(zu.behaelter.rechts)
  })

  test('der Vollbild-Knopf steht auch auf hohem Schirm und schaltet das Vollbild ein und aus', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 })
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.app-navigation-toggle-wrapper', { timeout: 30_000 })
    const knopf = page.locator('.pw-vollbild-knopf')
    await expect(knopf, 'der Vollbild-Knopf fehlt auf hohem Schirm').toBeVisible({ timeout: 15_000 })
    await expect(knopf).toHaveAttribute('aria-label', 'Vollbild')

    // Bedient über die Rolle, wie ein Benutzer ihn findet; die Wirkung misst
    // der Browser selbst.
    await page.getByRole('button', { name: 'Vollbild', exact: true }).click()
    await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { message: 'der Klick schaltet kein Vollbild ein' })
      .toBe(true)
    await expect(knopf, 'der Knopf zeigt nach dem Einschalten nicht «Vollbild verlassen»').toHaveAttribute('aria-label', 'Vollbild verlassen')

    await page.getByRole('button', { name: 'Vollbild verlassen' }).click()
    await expect.poll(() => page.evaluate(() => !!document.fullscreenElement), { message: 'der zweite Klick verlässt das Vollbild nicht' })
      .toBe(false)
    await expect(knopf).toHaveAttribute('aria-label', 'Vollbild')
  })
})
