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

// Für eine gezielte Messung an einem einzelnen Test: `PW_GREP="Synchronisation abbrechen"`.
// Ohne die Variable läuft die ganze Suite — der volle Lauf braucht gut eine
// Stunde, und eine Ursache an einem einzelnen Test zu suchen kostete jedes Mal
// diese Stunde.
const GREP = process.env.PW_GREP || ''

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  ...(GREP ? { grep: new RegExp(GREP) } : {}),
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
    // Die beiden Desktop-Browser prüfen den Datenfluss am hohen Schirm. Die
    // Fenstergrösse, die Playwright mitbringt, ist 1280×720 — und bei dieser
    // Höhe gelten seit dem 24.10.2026 die Kompromisse des flachen Schirms
    // (F131 und der Auftrag «Nimm bei kleiner Höhe den grünen Rand unten weg»):
    // Der Budgetkopf zeigt nur die Differenz, die Pauschalanträge starten
    // zugeklappt. Die Tests erwarten den geräumigen Zustand, darum steht das
    // Fenster hier höher als 56rem. Den flachen Schirm prüfen der
    // Komponententest `budget-flacher-schirm.test.js`, das Telefon-Projekt
    // unten und der Design-Lauf über alle Formate.
    {
      name: 'chromium',
      testIgnore: '**/handy-bedienung.spec.js',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 1024 } },
    },
    // Firefox-Abdeckung: browser-spezifische Fehler (z.B. Timing/Rendering in der
    // Admin-Verwaltung) traten nur in Firefox auf und blieben mit Chromium unentdeckt.
    {
      name: 'firefox',
      testIgnore: '**/handy-bedienung.spec.js',
      use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 1024 } },
    },
    // Das Telefon ist eine eigene Maschine: Marcs Befund vom 19.10.2026 kam vom
    // iPhone, also von Safari mit Berührung statt Maus. Dieselbe Bedienung lief
    // in Chromium und Firefox bei derselben Fenstergrösse fehlerfrei. Nur die
    // Telefon-Tests laufen hier, damit der Lauf nicht ein drittes Mal die ganze
    // Suite abarbeitet.
    {
      name: 'iphone',
      testMatch: '**/handy-bedienung.spec.js',
      use: {
        ...devices['iPhone 13'],
        // Mit der Kennung, die das WebKit des Testabbilds mitbringt, hält
        // Nextcloud den Browser für zu alt und leitet auf
        // `/index.php/unsupported` um — die App war gar nicht erreichbar
        // (gemessen am 19.10.2026). Hier steht die Kennung des Safari, den ein
        // heutiges iPhone mitbringt; die Maschine darunter bleibt WebKit.
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) '
          + 'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      },
    },
  ],
})
