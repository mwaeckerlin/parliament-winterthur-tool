import { test, expect, sperreVorAllem } from './fixtures.js'
import { login } from './anmeldung.js'

// Die Sperre aus sperre.js steht vor jedem anderen `beforeAll` dieser Datei,
// weil schon ein `beforeAll` globale Einstellungen schreiben kann.
test.beforeAll(sperreVorAllem)

/**
 * Layout-Konsistenz-E2E-Test.
 *
 * Prüft, dass jede parlwin-Ansicht exakt dieselbe gemeinsame Seitenstruktur
 * verwendet (pw-view-content mit pw-view-header und pw-view-title) – unabhängig
 * vom Inhalt. So ist sichergestellt, dass keine Seite ein abweichendes Layout
 * oder einen abweichenden Titel-Stil bekommt.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const USER = {
  name: process.env.PW_U1 || 'parlwin_praesidium',
  pass: process.env.PW_P1 || '',
}

// Reihenfolge wie in der Navigation (App.vue → ansichten).
const ANSICHTEN = [
  { tab: 'Geschäfte', titel: 'Geschäfte' },
  { tab: 'Sitzungen', titel: 'Sitzungen' },
  { tab: 'Mitglieder', titel: 'Mitglieder' },
  { tab: 'Kommissionen', titel: 'Kommissionen' },
  { tab: 'Vorstösse', titel: 'Vorstösse' },
  { tab: 'Sitzungstypen', titel: 'Sitzungstypen' },
  { tab: 'Änderungsverlauf', titel: 'Änderungsverlauf' },
]

test.describe('Layout-Konsistenz aller parlwin-Seiten', () => {
  test('jede Ansicht nutzt dieselbe pw-view-Struktur mit eigenem Titel', async ({ page }) => {
    await login(page, USER)
    await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page.waitForSelector('.pw-view-content', { timeout: 30_000 })

    for (const { tab, titel } of ANSICHTEN) {
      await page.getByRole('link', { name: tab, exact: true }).click()

      const content = page.locator('.pw-view-content')
      await expect(content, `Ansicht "${tab}" muss pw-view-content rendern`).toBeVisible()
      await expect(
        content.locator('.pw-view-header'),
        `Ansicht "${tab}" muss den gemeinsamen pw-view-header haben`,
      ).toBeVisible()
      await expect(
        content.locator('.pw-view-title'),
        `Ansicht "${tab}" muss den gemeinsamen pw-view-title "${titel}" haben`,
      ).toHaveText(titel)
    }
  })
})
