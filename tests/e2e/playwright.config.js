import fs from 'node:fs'
import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright-Konfiguration für den Multi-User-E2E-Test.
 *
 * Läuft im `playwright`-Service innerhalb des E2E-Compose-Netzwerks und
 * spricht Nextcloud über den internen Hostnamen `nextcloud-nginx:8080` an.
 * Gestartet wird sie im Container der Tests, nachdem der Stack hochgefahren,
 * der Abgleich gelaufen und der Fraktionsraum (Ordner + Kalender +
 * Gruppen-Shares) eingerichtet ist: einzeln von tests/e2e/run-compose-e2e.sh,
 * im Lauf über alle Gruppen (npm test) einmal je Phase und Stack über
 * `docker exec` in den laufenden Container.
 */
const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

// Für eine gezielte Messung an einem einzelnen Test: `PW_GREP="Synchronisation abbrechen"`.
const GREP = process.env.PW_GREP || ''

// Die Auswahl einer Phase oder einer Reparaturrunde: eine JSON-Datei mit den
// Titelpfaden der Tests, je Test `[datei, describe…, titel]`. Playwright prüft
// `grep` am Titel «<projekt> <datei> <describe…> <titel>»; jeder Eintrag wird
// zu einem Muster, das genau diesen Titel trägt.
const AUSWAHL = process.env.PW_AUSWAHL || ''

function muster(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function auswahlMuster() {
  if (!AUSWAHL) return null
  const eintraege = JSON.parse(fs.readFileSync(AUSWAHL, 'utf8'))
  return eintraege.map((pfad) => new RegExp(`(^| )${pfad.map(muster).join(' ')}( @|$)`))
}

const auswahl = auswahlMuster()

// Ein Worker für den ganzen Lauf, also jede Datei nacheinander. Mit zweien wartet der
// zweite Worker an der Sperre «allein» (tests/e2e/sperre.js), solange der erste
// eine Datei aus `ALLEIN` läuft, und sein `beforeAll` läuft nach 90s ab, bevor
// der Test beginnt. `PW_WORKERS=2` lässt Dateien mit eigenen Daten gleichzeitig
// laufen, für eine Messung.
const WORKERS = Number(process.env.PW_WORKERS || 1)

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  ...(auswahl ? { grep: auswahl } : GREP ? { grep: new RegExp(GREP) } : {}),
  fullyParallel: false,
  workers: WORKERS,
  forbidOnly: true,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 20_000 },
  globalSetup: './globale-anmeldung.js',
  // outputFile wird relativ zum Verzeichnis dieser Config aufgelöst (tests/e2e/).
  reporter: [['list'], ['junit', { outputFile: '.junit/e2e.xml' }], ['./journal-reporter.js']],
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
    // Höhe gelten die Kompromisse des flachen Schirms: Der grüne Rand unten
    // fällt weg, der Budgetkopf zeigt nur die Differenz, die Pauschalanträge
    // starten zugeklappt. Die Tests erwarten den geräumigen Zustand, darum steht das
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
    // Das Telefon ist eine eigene Maschine: Safari mit Berührung statt Maus
    // zeigt Fehler der Bedienung, die Chromium und Firefox bei derselben
    // Fenstergrösse nicht zeigen. Nur die
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
