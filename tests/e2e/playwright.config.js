import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright-Konfiguration für den Multi-User-E2E-Test.
 *
 * Läuft im `playwright`-Service innerhalb des E2E-Compose-Netzwerks und
 * spricht Nextcloud über den internen Hostnamen `nextcloud-nginx:8080` an.
 * Der Test wird über tests/e2e/run-compose-e2e.sh gestartet, nachdem der
 * Stack hochgefahren, der Sync gelaufen und der Fraktionsraum (Ordner +
 * Kalender + Gruppen-Shares) eingerichtet ist.
 */
const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  // Echte Gleichzeitigkeit mehrerer Browser passiert innerhalb eines Tests
  // über mehrere Browser-Kontexte — die Tests selbst laufen seriell, damit
  // die geteilten Ressourcen deterministisch bleiben.
  fullyParallel: false,
  workers: 1,
  forbidOnly: true,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 20_000 },
  // outputFile wird relativ zum Verzeichnis dieser Config aufgelöst (tests/e2e/).
  reporter: [['list'], ['junit', { outputFile: '.junit/e2e.xml' }]],
  use: {
    baseURL: BASE_URL,
    ignoreHTTPSErrors: true,
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    // Firefox-Abdeckung: browser-spezifische Fehler (z.B. Timing/Rendering in der
    // Admin-Verwaltung) traten nur in Firefox auf und blieben mit Chromium unentdeckt.
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
  ],
})
