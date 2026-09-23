import { test, expect } from '@playwright/test'

/**
 * Feld-Inventar der zuletzt noch ungeprüften Masken: der Verwaltungsbereich für
 * Administratoren und die geteilten Bausteine, die in mehreren Masken stecken
 * (Notizenliste, Dokumente, formatierter Textbereich) sowie der Traktanden- und
 * Teilnehmerbereich des Sitzungsformulars.
 *
 * Ergänzt tests/e2e/neu-dialoge-vollstaendig.spec.js («+ Neu»-Masken) und
 * tests/e2e/feld-inventar-filter-und-dialoge.spec.js (Filterbereiche). Zweck ist
 * überall derselbe: Die erwarteten Bedienelemente stehen als Konstante im Test.
 * Verschwindet eines, wird der Test rot und benennt es namentlich. Zusätzlich
 * prüft jeder Test mindestens eine echte Funktion — Anwesenheit allein sagt
 * nichts darüber aus, ob ein Element noch etwas bewirkt.
 *
 * Bewusst NICHT geprüft wird hier der Start einer echten Synchronisation: das
 * verändert globalen Zustand und ist in tests/e2e/admin-sync-echtzeit.spec.js
 * abgedeckt. Dieser Test lässt den Verwaltungsbereich so zurück, wie er ihn
 * vorgefunden hat.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP = `${BASE_URL}/index.php/apps/parlwin`
const ADMIN_URL = `${BASE_URL}/index.php/settings/admin/parlwin`

const ADMIN = { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' }
const U1 = { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' }

const stamp = Date.now()

// ===========================================================================
// Verbindliches Inventar — Verwaltungsbereich (/settings/admin/parlwin)
// ===========================================================================

/** Karte «Synchronisation» (Seitenleiste): Beschriftung → Selektor. */
const KARTE_SYNCHRONISATION = [
  ['Knopf «Jetzt synchronisieren»', '#pw-btn-sync'],
  ['Knopf «Synchronisierung abbrechen»', '#pw-btn-sync-cancel'],
  ['Statustext der Synchronisation', '#pw-sync-status'],
  ['Fortschrittsbalken', '#pw-sync-progress'],
  ['Prozentanzeige', '#pw-sync-percent'],
  ['Detailzeile mit dem Live-Fortschritt', '#pw-sync-details'],
  ['Autosave-Status', '#pw-admin-autosave'],
]

/** Karte «Fraktionskonfiguration». */
const KARTE_FRAKTIONSKONFIGURATION = [
  ['Beschriftung der Fraktions-Auswahl', 'label[for="pw-fraktion"]'],
  ['Auswahlliste Fraktion', '#pw-fraktion'],
  ['Beschriftung des Gruppen-Textfelds', 'label[for="pw-nextcloud-gruppe"]'],
  ['Textfeld Nextcloud-Gruppe', '#pw-nextcloud-gruppe'],
  ['Vorschlagsliste der bestehenden Nextcloud-Gruppen', '#pw-nextcloud-gruppen'],
  ['Zustandstext zur gewählten Gruppe', '#pw-nextcloud-gruppe-state'],
]

/** Karte «Fraktionsmitglieder ↔ Nextcloud-Benutzer». */
const KARTE_MITGLIEDER = [
  ['Knopf «Ausgewählte abgleichen»', '#pw-btn-members-provision'],
  ['Statustext der Mitglieder-Zuordnung', '#pw-members-status'],
  ['Hinweis, solange keine Fraktion gewählt ist', '#pw-members-empty'],
  ['Tabelle der Mitglieder-Zuordnung', '#pw-members-table'],
  ['Kopf-Auswahlbox «Alle wählen»', '#pw-members-select-all'],
  ['Tabellenrumpf mit den Mitglieder-Zeilen', '#pw-members-body'],
]

/** Karte «Fraktionsmitglieder ↔ Nextcloud-Benutzer»: Spalten der Tabelle. */
const MITGLIEDER_SPALTEN = ['Mitglied', 'E-Mail', 'Benutzername', 'Gruppen']

/** Karte «Automatische Synchronisation». */
const KARTE_ZEITPLAN = [
  ['Liste der Zeitplan-Einträge', '#pw-zeitplan-liste'],
  ['Knopf «+ Zeit hinzufügen»', '#pw-zeitplan-hinzufuegen'],
  ['Statustext des Zeitplans', '#pw-zeitplan-status'],
]

/** Eine Zeitplan-Zeile: sieben Wochentage in genau dieser Reihenfolge. */
const WOCHENTAGE = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

/** Karte «Kürzel». */
const KARTE_KUERZEL = [
  ['Liste der Kürzel-Einträge', '#pw-kuerzel-liste'],
  ['Vorschlagsliste für den Suchtext', '#pw-status-kuerzel-liste'],
  ['Knopf «+ Eintrag hinzufügen»', '#pw-kuerzel-hinzufuegen'],
  ['Statustext der Kürzel', '#pw-kuerzel-status'],
]

/** Karte «E-Mail-Einladungen». */
const KARTE_EMAIL = [
  ['Beschriftung «Absender-E-Mail»', 'label[for="pw-absender-email"]'],
  ['Feld Absender-E-Mail', '#pw-absender-email'],
  ['Beschriftung «Absendername»', 'label[for="pw-absender-name"]'],
  ['Feld Absendername', '#pw-absender-name'],
]

// ===========================================================================
// Verbindliches Inventar — geteilte Bausteine
// ===========================================================================

/** Menü «+ Neues Dokument»: alle Vorlagen in genau dieser Reihenfolge. */
const DOKUMENT_VORLAGEN = [
  'Word-Dokument (docx)',
  'Excel-Tabelle (xlsx)',
  'PowerPoint (pptx)',
  'OpenDocument-Text (odt)',
  'OpenDocument-Tabelle (ods)',
  'OpenDocument-Präsentation (odp)',
  'Markdown (md)',
  'Textdatei (txt)',
]

/**
 * Werkzeugleiste des formatierten Textbereichs: jeder Knopf wird über seinen
 * Hinweistext (title) angesprochen — das ist zugleich die Beschriftung, die der
 * Nutzer beim Darüberfahren sieht. Reihenfolge wie in der Leiste.
 */
const WYSIWYG_KNOEPFE = [
  'Fett (Ctrl+B)',
  'Kursiv (Ctrl+I)',
  'Unterstrichen (Ctrl+U)',
  'Durchgestrichen',
  'Absatz',
  'Überschrift 2',
  'Überschrift 3',
  'Aufzählung',
  'Nummerierte Liste',
  'Zitat',
  'Code (verbatim)',
  'Code-Block (verbatim)',
  'Link einfügen / bearbeiten',
  'Link entfernen',
  'Rückgängig (Ctrl+Z)',
  'Wiederholen (Ctrl+Shift+Z)',
  'Formatierung entfernen',
]

/** Teilnehmerzeile des Sitzungsformulars: alle wählbaren Arten. */
const TEILNEHMER_ARTEN = [
  'Einzelnes Mitglied',
  'Ganze Fraktion',
  'Eigene Fraktion',
  'Ganze Kommission',
  'Fraktions-Rolle',
  'Nextcloud-Gruppe',
  'Nextcloud-Benutzer',
]

// ===========================================================================
// Basis-Helfer (identisch zu den übrigen Specs)
// ===========================================================================

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

/** Öffnet die Verwaltungsseite und wartet, bis ihre Wurzel gerendert ist. */
async function openAdmin(page) {
  await page.goto(ADMIN_URL)
  await page.waitForSelector('#parlwin-admin-settings', { state: 'visible', timeout: 30_000 })
  await page.waitForLoadState('networkidle').catch(() => {})
}

/** GET auf einen App-Endpunkt (nutzt die Session-Cookies der Seite). */
const apiGet = (page, path) =>
  page.request.get(`${APP}${path}`, { headers: { 'OCS-APIRequest': 'true' } })

async function oeffneAnsicht(page, name, knopf) {
  await page.goto(`${APP}/`)
  await page.waitForLoadState('networkidle')
  await page.getByText(name, { exact: true }).first().click()
  await page.getByRole('button', { name: knopf }).waitFor({ state: 'visible', timeout: 30_000 })
}

/**
 * Legt über die Oberfläche einen Vorstoss an und gibt die offene Bearbeitungs-
 * maske zurück. Die Neu-Maske sammelt nur die Eingaben — erst «Speichern» legt
 * an. Dokumente, Notizen und die Geschäfts-Verknüpfung hängen an dieser ID und
 * sind deshalb erst danach zu prüfen.
 */
async function oeffneVorstossMaske(page, name) {
  await oeffneAnsicht(page, 'Vorstösse', /Neuer Vorstoss/)
  await page.getByRole('button', { name: /Neuer Vorstoss/ }).click()
  const modal = page.locator('.pw-modal').first()
  await modal.waitFor({ state: 'visible', timeout: 30_000 })
  await modal.locator('input.pw-input').first().fill(`E2E Inventar ${name} ${stamp}`)
  await modal.locator('.pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()
  await expect(modal.locator('h3')).toHaveText('Vorstoss bearbeiten', { timeout: 30_000 })
  return modal
}

/** Das PwField zu einer Beschriftung — exakter Textvergleich. */
const feld = (wurzel, label) =>
  wurzel.locator(`.pw-field:has(> .pw-field-label:text-is(${JSON.stringify(label)}))`).first()

/**
 * Prüft ein ganzes Inventar auf Anwesenheit. Bewusst `toBeAttached` statt
 * `toBeVisible`: Statuszeilen und Vorschlagslisten sind im Ruhezustand leer bzw.
 * unsichtbar, müssen aber existieren — ihr Fehlen ist der Regressionsfall.
 */
async function pruefeInventar(wurzel, inventar) {
  for (const [name, selektor] of inventar) {
    await expect(wurzel.locator(selektor), `Element «${name}» fehlt`).toBeAttached()
  }
}

// ===========================================================================
// Verwaltungsbereich (nur für Administratoren)
// ===========================================================================

test.describe('Feld-Inventar: Verwaltungsbereich', () => {
  // Die Verwaltung blendet Tabellen unterhalb der Desktop-Breite anders ein.
  test.use({ viewport: { width: 1600, height: 1000 } })

  test.beforeAll(() => {
    expect(ADMIN.pass, 'Admin-Passwort (PW_ADMIN_PASS) fehlt').not.toBe('')
    expect(U1.pass, `Passwort für ${U1.name} fehlt`).not.toBe('')
  })

  test('Karte Synchronisation: alle Bedienelemente vorhanden und an den laufenden Stand gekoppelt', async ({ page }) => {
    await login(page, ADMIN)
    await openAdmin(page)

    await pruefeInventar(page, KARTE_SYNCHRONISATION)

    // Beschriftungen wörtlich — ein umbenannter Knopf ist für den Nutzer ein
    // anderer Knopf.
    await expect(page.locator('#pw-btn-sync')).toHaveText('Jetzt synchronisieren')
    await expect(page.locator('#pw-btn-sync-cancel')).toHaveText('Synchronisierung abbrechen')
    await expect(page.locator('#pw-admin-autosave')).toHaveText('Bereit für automatische Speicherung')
    // Die Prozentanzeige zeigt den zuletzt erreichten Stand — nach einem
    // vorangegangenen Lauf ist das nicht zwingend «0%». Geprüft wird darum das
    // Format (ganze Prozentzahl), nicht ein fixer Wert.
    await expect(page.locator('#pw-sync-percent')).toHaveText(/^\d+%$/)
    await expect(page.locator('#pw-sync-progress')).toHaveAttribute('max', '100')

    // Funktion: die beiden Knöpfe spiegeln den echten Lauf-Zustand des Servers.
    // Läuft nichts, ist Starten möglich und Abbrechen gesperrt — sonst umgekehrt.
    const status = await (await apiGet(page, '/sync/status')).json()
    if (status.running === true) {
      await expect(page.locator('#pw-btn-sync'), 'Start ist trotz laufender Synchronisation freigegeben').toBeDisabled()
      await expect(page.locator('#pw-btn-sync-cancel'), 'Abbrechen ist trotz laufender Synchronisation gesperrt').toBeEnabled()
    } else {
      await expect(page.locator('#pw-btn-sync'), 'Start ist gesperrt, obwohl keine Synchronisation läuft').toBeEnabled({ timeout: 60_000 })
      await expect(page.locator('#pw-btn-sync-cancel'), 'Abbrechen ist freigegeben, obwohl nichts läuft').toBeDisabled()
    }
  })

  test('Karte Fraktionskonfiguration: Auswahlliste, Gruppenfeld mit Vorschlägen und Zustandstext wirken', async ({ page }) => {
    await login(page, ADMIN)
    await openAdmin(page)

    const karte = page.locator('.pw-admin-card', { has: page.locator('h3', { hasText: 'Fraktionskonfiguration' }) })
    await expect(karte, 'Karte «Fraktionskonfiguration» fehlt').toBeVisible()
    await pruefeInventar(karte, KARTE_FRAKTIONSKONFIGURATION)

    await expect(karte.locator('label[for="pw-fraktion"]'))
      .toHaveText('Fraktion (aus synchronisierten Fraktionen)')
    await expect(karte.locator('label[for="pw-nextcloud-gruppe"]'))
      .toHaveText('Nextcloud-Gruppe (bestehend wählen oder neu erstellen)')
    await expect(karte.locator('#pw-fraktion option').first(), 'Leerauswahl der Fraktionsliste fehlt')
      .toHaveText('Bitte Fraktion wählen')

    // Funktion: Das Gruppenfeld meldet zurück, ob die eingetippte Gruppe schon
    // existiert oder neu angelegt würde — sonst wäre nicht erkennbar, dass ein
    // Tippfehler eine zweite Gruppe erzeugt.
    const gruppe = page.locator('#pw-nextcloud-gruppe')
    const zustand = page.locator('#pw-nextcloud-gruppe-state')
    const original = await gruppe.inputValue()

    await gruppe.fill(`pw-inventar-neu-${stamp}`)
    await expect(zustand, 'Zustandstext meldet keine neue Gruppe').toHaveText('Neue Gruppe wird angelegt', { timeout: 10_000 })

    const bekannte = await page.locator('#pw-nextcloud-gruppen option').evaluateAll((os) => os.map((o) => o.value).filter(Boolean))
    expect(bekannte.length, 'Vorschlagsliste der Nextcloud-Gruppen ist leer').toBeGreaterThan(0)
    await gruppe.fill(String(bekannte[0]))
    await expect(zustand, 'Zustandstext erkennt eine bestehende Gruppe nicht').toHaveText('Bestehende Gruppe', { timeout: 10_000 })

    // Ausgangszustand wiederherstellen und den Autosave abwarten.
    await gruppe.fill(original)
    await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 15_000 })
    await page.reload()
    await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
    await expect(page.locator('#pw-nextcloud-gruppe'), 'ursprüngliche Gruppe wurde nicht wiederhergestellt').toHaveValue(original)
  })

  test('Karte Mitglieder-Zuordnung: Knopf, Statustext, alle fünf Spalten und die Kopf-Auswahlbox wirken', async ({ page }) => {
    await login(page, ADMIN)
    await openAdmin(page)

    const karte = page.locator('.pw-admin-card', { has: page.locator('h3', { hasText: 'Fraktionsmitglieder' }) })
    await expect(karte, 'Karte «Fraktionsmitglieder ↔ Nextcloud-Benutzer» fehlt').toBeVisible()
    await pruefeInventar(karte, KARTE_MITGLIEDER)

    await expect(karte.locator('#pw-btn-members-provision')).toHaveText('Ausgewählte abgleichen')

    // Fünf Spalten: die Auswahlspalte plus die vier benannten.
    const kopfzellen = karte.locator('#pw-members-table thead th')
    await expect(kopfzellen, 'die Tabelle hat nicht mehr fünf Spalten').toHaveCount(5)
    await expect(kopfzellen.first().locator('#pw-members-select-all'), 'Kopf-Auswahlbox steht nicht in der ersten Spalte').toBeAttached()
    const spalten = (await kopfzellen.allTextContents()).map((s) => s.trim())
    for (const s of MITGLIEDER_SPALTEN) {
      expect(spalten, `Spalte «${s}» fehlt in der Mitglieder-Tabelle`).toContain(s)
    }

    // Die Zeilen erscheinen nur zu einer gewählten Fraktion. Ist noch keine
    // konfiguriert, wird die erste verfügbare Fraktion über das Select gewählt.
    // Der e2e-Stack synchronisiert echte Fraktionsdaten — es MUSS also welche
    // zur Auswahl geben; fehlen sie, ist das ein echter Fehler, kein Grund zum
    // Überspringen.
    const fraktionSelect = page.locator('#pw-fraktion')
    if (!(await fraktionSelect.inputValue()).trim()) {
      const optionen = await fraktionSelect.locator('option').evaluateAll((os) => os.map((o) => o.value).filter(Boolean))
      expect(optionen.length, 'Keine synchronisierten Fraktionen wählbar — die Synchronisation hat keine geliefert')
        .toBeGreaterThan(0)
      await fraktionSelect.selectOption(optionen[0])
    }
    await expect(page.locator('#pw-members-status'), 'Mitglieder der Fraktion wurden nicht geladen')
      .toHaveText(/Mitglieder geladen/i, { timeout: 30_000 })
    const zeilen = karte.locator('#pw-members-body tr')
    await expect(zeilen.first(), 'keine Mitglieder-Zeilen zur gewählten Fraktion').toBeVisible({ timeout: 30_000 })

    // Funktion 1: Die Kopf-Auswahlbox setzt und löscht die Auswahl aller Zeilen.
    const auswahlBoxen = karte.locator('#pw-members-body .pw-member-select, #pw-members-body .pw-orphan-select')
    const selectAll = karte.locator('#pw-members-select-all')
    await selectAll.uncheck()
    expect(
      await auswahlBoxen.evaluateAll((bs) => bs.every((b) => !b.checked)),
      'Abwählen im Tabellenkopf wählt nicht alle Zeilen ab',
    ).toBe(true)

    // Funktion 2: Der Abgleich-Knopf reagiert wirklich — ohne ausgewählte Zeile
    // legt er nichts an, sondern meldet den Grund. Bewusst dieser Weg: ein echter
    // Abgleich würde Nextcloud-Benutzer anlegen und den Stack verändern.
    await karte.locator('#pw-btn-members-provision').click()
    await expect(karte.locator('#pw-members-status'), 'der Abgleich-Knopf meldet nichts zurück')
      .toHaveText('Bitte mindestens einen Eintrag auswählen.', { timeout: 10_000 })

    // Ausgangszustand: alle wieder ausgewählt (so rendert die Maske frisch).
    await selectAll.check()
    expect(
      await auswahlBoxen.evaluateAll((bs) => bs.every((b) => b.checked)),
      'Anwählen im Tabellenkopf wählt nicht alle Zeilen an',
    ).toBe(true)
  })

  test('Karte Automatische Synchronisation: Zeile mit sieben Wochentagen, Zeitfeld und Löschknopf speichert', async ({ page }) => {
    test.setTimeout(150_000)
    await login(page, ADMIN)
    await openAdmin(page)

    const karte = page.locator('.pw-admin-card', { has: page.locator('h3', { hasText: 'Automatische Synchronisation' }) })
    await expect(karte, 'Karte «Automatische Synchronisation» fehlt').toBeVisible()
    await pruefeInventar(karte, KARTE_ZEITPLAN)
    await expect(karte.locator('#pw-zeitplan-hinzufuegen')).toHaveText('+ Zeit hinzufügen')

    const zeilen = page.locator('#pw-zeitplan-liste .pw-zeitplan-row')
    const status = page.locator('#pw-zeitplan-status')
    const vorher = await zeilen.count()

    await page.locator('#pw-zeitplan-hinzufuegen').click()
    await expect(zeilen, '«+ Zeit hinzufügen» erzeugt keine neue Zeile').toHaveCount(vorher + 1)

    // Inventar EINER Zeile: sieben benannte Wochentage, Zeitfeld, Löschknopf.
    const neu = zeilen.last()
    const tage = neu.locator('input[type="checkbox"][data-tag]')
    await expect(tage, 'eine Zeitplan-Zeile hat nicht sieben Wochentage').toHaveCount(7)
    const tagNamen = (await neu.locator('.pw-zeitplan-tag').allTextContents()).map((s) => s.trim())
    expect(tagNamen, 'die Wochentage stimmen nicht (Reihenfolge Mo–So)').toEqual(WOCHENTAGE)
    await expect(neu.locator('.pw-zeitplan-zeit'), 'Zeitfeld fehlt in der Zeitplan-Zeile').toBeVisible()
    await expect(neu.locator('.pw-zeitplan-delete'), 'Löschknopf fehlt in der Zeitplan-Zeile').toBeVisible()
    await expect(neu.locator('.pw-zeitplan-delete')).toHaveAttribute('title', 'Löschen')

    // Funktion: Eintrag setzen. Gespeichert wird erst fünf Sekunden nach der
    // letzten Eingabe — die grosszügige Wartezeit deckt genau diese Verzögerung ab.
    await neu.locator('.pw-zeitplan-zeit').fill('03:33')
    await neu.locator('.pw-zeitplan-zeit').press('Tab')
    await neu.locator('input[type="checkbox"][data-tag="3"]').check()
    await expect(status, 'der Zeitplan wurde nicht gespeichert').toHaveText(/Gespeichert/i, { timeout: 20_000 })

    await page.reload()
    await page.waitForSelector('#pw-zeitplan-liste .pw-zeitplan-row', { timeout: 15_000 })
    const gespeichert = () =>
      zeilen.evaluateAll((rs) =>
        rs.map((r) => ({
          zeit: r.querySelector('.pw-zeitplan-zeit')?.value || '',
          tage: [...r.querySelectorAll('input[type="checkbox"][data-tag]')].filter((b) => b.checked).map((b) => Number(b.dataset.tag)),
        })))
    const eintrag = (await gespeichert()).find((e) => e.zeit === '03:33')
    expect(eintrag, 'der neue Zeitplan-Eintrag wurde nicht gespeichert').toBeTruthy()
    expect(eintrag.tage, 'der gewählte Wochentag wurde nicht gespeichert').toEqual([3])

    // Aufräumen über den Löschknopf — der damit zugleich als wirksam belegt ist.
    const idx = (await gespeichert()).findIndex((e) => e.zeit === '03:33')
    await zeilen.nth(idx).locator('.pw-zeitplan-delete').click()
    await expect(status).toHaveText(/Gespeichert/i, { timeout: 20_000 })
    await page.reload()
    await page.waitForSelector('#pw-zeitplan-liste', { timeout: 15_000 })
    await expect
      .poll(async () => (await gespeichert()).map((e) => e.zeit), { timeout: 15_000 })
      .not.toContain('03:33')
  })

  test('Karte Kürzel: Zeile mit Suchtext, Kürzel und Löschknopf speichert und lässt sich entfernen', async ({ page }) => {
    test.setTimeout(150_000)
    await login(page, ADMIN)
    await openAdmin(page)

    const karte = page.locator('.pw-admin-card', { has: page.locator('h3', { hasText: 'Kürzel' }) }).first()
    await expect(karte, 'Karte «Kürzel» fehlt').toBeVisible()
    await pruefeInventar(karte, KARTE_KUERZEL)
    await expect(karte.locator('#pw-kuerzel-hinzufuegen')).toHaveText('+ Eintrag hinzufügen')

    const zeilen = page.locator('#pw-kuerzel-liste .pw-kuerzel-row')
    const status = page.locator('#pw-kuerzel-status')
    const vorher = await zeilen.count()

    await page.locator('#pw-kuerzel-hinzufuegen').click()
    await expect(zeilen, '«+ Eintrag hinzufügen» erzeugt keine neue Zeile').toHaveCount(vorher + 1)

    // Inventar EINER Zeile.
    const neu = zeilen.last()
    await expect(neu.locator('.pw-kuerzel-suchtext'), 'Suchtext-Feld fehlt in der Kürzel-Zeile').toBeVisible()
    await expect(neu.locator('.pw-kuerzel-suchtext')).toHaveAttribute('placeholder', 'Suchtext')
    await expect(neu.locator('.pw-kuerzel-suchtext'), 'Suchtext-Feld ohne Vorschlagsliste')
      .toHaveAttribute('list', 'pw-status-kuerzel-liste')
    await expect(neu.locator('.pw-kuerzel-wert'), 'Kürzel-Feld fehlt in der Kürzel-Zeile').toBeVisible()
    await expect(neu.locator('.pw-kuerzel-wert')).toHaveAttribute('placeholder', 'Kürzel')
    await expect(neu.locator('.pw-kuerzel-delete'), 'Löschknopf fehlt in der Kürzel-Zeile').toBeVisible()
    await expect(neu.locator('.pw-kuerzel-delete')).toHaveAttribute('title', 'Löschen')

    // Funktion: Eintrag erfassen. Auch hier greift der Speicher erst fünf
    // Sekunden nach der letzten Eingabe.
    const suche = `E2E-Inventar-Suchtext ${stamp}`
    const wert = `E2E-Inventar-Kuerzel ${stamp}`
    await neu.locator('.pw-kuerzel-suchtext').fill(suche)
    await neu.locator('.pw-kuerzel-wert').fill(wert)
    await neu.locator('.pw-kuerzel-wert').blur()
    await expect(status, 'das Kürzel wurde nicht gespeichert').toHaveText(/Gespeichert/i, { timeout: 20_000 })

    await page.reload()
    await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
    const gespeichert = () =>
      zeilen.evaluateAll((rs) =>
        rs.map((r) => ({
          suche: r.querySelector('.pw-kuerzel-suchtext')?.value || '',
          wert: r.querySelector('.pw-kuerzel-wert')?.value || '',
        })))
    await expect
      .poll(async () => (await gespeichert()).some((r) => r.suche === suche && r.wert === wert), { timeout: 15_000 })
      .toBe(true)

    // Aufräumen über den Löschknopf.
    const idx = (await gespeichert()).findIndex((r) => r.suche === suche)
    expect(idx, 'gespeicherte Kürzel-Zeile nicht wiedergefunden').toBeGreaterThanOrEqual(0)
    await zeilen.nth(idx).locator('.pw-kuerzel-delete').click()
    await expect(status).toHaveText(/Gespeichert/i, { timeout: 20_000 })
    await page.reload()
    await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
    await expect
      .poll(async () => (await gespeichert()).map((r) => r.suche), { timeout: 15_000 })
      .not.toContain(suche)
  })

  test('Karte E-Mail-Einladungen: beide Felder vorhanden und speichern automatisch', async ({ page }) => {
    await login(page, ADMIN)
    await openAdmin(page)

    const karte = page.locator('.pw-admin-card', { has: page.locator('h3', { hasText: 'E-Mail-Einladungen' }) })
    await expect(karte, 'Karte «E-Mail-Einladungen» fehlt').toBeVisible()
    await pruefeInventar(karte, KARTE_EMAIL)

    await expect(karte.locator('label[for="pw-absender-email"]')).toHaveText('Absender-E-Mail')
    await expect(karte.locator('label[for="pw-absender-name"]')).toHaveText('Absendername')
    await expect(karte.locator('#pw-absender-email'), 'das Absenderfeld nimmt keine E-Mail-Adresse entgegen')
      .toHaveAttribute('type', 'email')

    // Funktion: Eine Änderung wird ohne Speichern-Knopf übernommen und überlebt
    // das Neuladen.
    const nameFeld = page.locator('#pw-absender-name')
    const original = await nameFeld.inputValue()
    const testName = `E2E Absender ${stamp}`
    await nameFeld.fill(testName)
    await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 15_000 })

    await page.reload()
    await page.waitForSelector('#parlwin-admin-settings', { timeout: 30_000 })
    await expect(page.locator('#pw-absender-name'), 'der Absendername wurde nicht gespeichert').toHaveValue(testName)

    // Ausgangszustand wiederherstellen.
    await page.locator('#pw-absender-name').fill(original)
    await expect(page.locator('#pw-admin-autosave')).toHaveText(/gespeichert/i, { timeout: 15_000 })
  })
})

// ===========================================================================
// Dialog «Mit Geschäft verknüpfen»
// ===========================================================================

test.describe('Feld-Inventar: Dialog «Mit Geschäft verknüpfen»', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Suchfeld, Hinweistext, Trefferliste und Leermeldung sind vorhanden und wirken', async ({ page }) => {
    await login(page, U1)
    const modal = await oeffneVorstossMaske(page, 'Verknuepfen')

    // Der Knopf steckt in einem PwField-<label>; sein zugänglicher Name ist die
    // Beschriftung «Geschäft», nicht sein Text — deshalb über den Text ansprechen.
    await modal.locator('button', { hasText: 'Mit Geschäft verknüpfen' }).click()

    // Der Verknüpfen-Dialog liegt neben der Vorstoss-Maske im Seiten-Körper;
    // eindeutig über seine eigene Überschrift ansprechen.
    const dialog = page.locator('.pw-modal:has(> .pw-modal-kopf > h3:text-is("Mit Geschäft verknüpfen"))')
    await dialog.waitFor({ state: 'visible', timeout: 30_000 })

    await expect(dialog.locator('.input-field__label'), 'Beschriftung des Suchfelds fehlt').toHaveText('Suche')
    const suchfeld = dialog.locator('input[placeholder="Nr. oder Titel"]')
    await expect(suchfeld, 'Suchfeld fehlt im Verknüpfen-Dialog').toBeVisible()
    // `.first()`: unter der Trefferliste kann zusätzlich die Leermeldung stehen,
    // die dieselbe Hinweis-Klasse trägt.
    await expect(dialog.locator('.pw-hinweis').first(), 'Hinweistext zur Sortierung fehlt')
      .toHaveText('Ähnlichste zum Titel zuerst, sonst neueste.')
    await expect(dialog.locator('.pw-verknuepfen-liste'), 'Trefferliste fehlt').toBeAttached()
    await expect(dialog.locator('.pw-btn-schliessen'), 'Schliessen-Knopf fehlt').toBeVisible()

    // Funktion: Die Suche grenzt die Trefferliste ein; ohne Treffer erscheint
    // die Leermeldung statt einer stumm leeren Liste.
    const treffer = dialog.locator('.pw-verknuepfen-liste .pw-verknuepfen-eintrag')
    expect(await treffer.count(), 'keine Geschäfte zur Verknüpfung angeboten').toBeGreaterThan(0)
    await suchfeld.fill(`zzz-kein-treffer-${stamp}`)
    await expect(treffer, 'die Suche grenzt die Trefferliste nicht ein').toHaveCount(0)
    await expect(dialog.getByText('Keine Geschäfte gefunden.'), 'Leermeldung fehlt bei null Treffern').toBeVisible()
    await suchfeld.fill('')
    await expect(treffer.first(), 'nach dem Leeren der Suche kehren die Treffer nicht zurück').toBeVisible()

    await dialog.locator('.pw-btn-schliessen').click()
    await expect(dialog).toHaveCount(0)

    // Beim Bearbeiten ist alles gespeichert — das ✕ schliesst nur.
    await modal.locator('.pw-btn-schliessen').click()
  })
})

// ===========================================================================
// Geteilter Baustein: Notizenliste (geprüft im Geschäft)
// ===========================================================================

test.describe('Feld-Inventar: Notizenliste im Geschäft', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('«+ Neue Notiz», Editor mit Bestätigen/Abbrechen, Kopfzeile und Löschknopf der eigenen Notiz', async ({ page }) => {
    await login(page, U1)
    await oeffneAnsicht(page, 'Geschäfte', /Eigenes Geschäft/)
    await page.getByRole('button', { name: /Eigenes Geschäft/ }).click()

    const detail = page.locator('.pw-geschaeft-detail')
    await detail.waitFor({ state: 'visible', timeout: 30_000 })

    // Notizen hängen an einer bestehenden ID: erst Titel erfassen und speichern,
    // danach steht die Notizenliste bereit.
    await detail.getByLabel('Titel').fill(`E2E Inventar Notizen ${stamp}`)
    await page.locator('.pw-modal .pw-modal-footer').getByRole('button', { name: 'Speichern' }).click()

    // Die reguläre Notizenliste (nicht die eingeklappten Sitzungsnotizen).
    const liste = detail.locator('.pw-form-zeile:has(> label:text-is("Notizen")) > .pw-notizen-liste')
    await expect(liste, 'Notizenliste fehlt im Geschäft').toBeVisible({ timeout: 30_000 })

    const neuKnopf = liste.locator('.pw-btn-neue-notiz')
    await expect(neuKnopf, 'Knopf «+ Neue Notiz» fehlt').toBeVisible()
    await expect(neuKnopf).toHaveText('+ Neue Notiz')

    // Abbrechen an einem leeren Editor: schliesst ihn, ohne etwas anzulegen.
    await neuKnopf.click()
    const editor = liste.locator('.pw-notiz-bearbeiten-zeile')
    await expect(editor, 'der Notiz-Editor öffnet nicht').toBeVisible({ timeout: 30_000 })
    await expect(editor.locator('.pw-wysiwyg'), 'im Notiz-Editor fehlt der formatierte Textbereich').toBeVisible()
    const bestaetigen = editor.locator('.pw-btn-mini[title="Speichern"]')
    const abbrechen = editor.locator('.pw-btn-mini[title="Abbrechen"]')
    await expect(bestaetigen, 'Bestätigen-Knopf fehlt im Notiz-Editor').toBeVisible()
    await expect(abbrechen, 'Abbrechen-Knopf fehlt im Notiz-Editor').toBeVisible()
    await abbrechen.click()
    await expect(editor, 'Abbrechen schliesst den Notiz-Editor nicht').toHaveCount(0)
    await expect(liste.locator('.pw-notiz-eintrag'), 'Abbrechen hat trotzdem eine Notiz angelegt').toHaveCount(0)

    // Funktion: Eine Notiz erfassen und über den Bestätigen-Knopf abschliessen.
    const text = `E2E Inventar-Notiz ${stamp}`
    await neuKnopf.click()
    const prose = liste.locator('.ProseMirror').first()
    await prose.waitFor({ state: 'visible', timeout: 30_000 })
    await prose.click()
    await page.waitForTimeout(300)
    await prose.pressSequentially(text, { delay: 25 })
    await liste.locator('.pw-btn-mini[title="Speichern"]').click()

    const eintrag = liste.locator('.pw-notiz-eintrag', { hasText: text }).first()
    await expect(eintrag, 'die bestätigte Notiz erscheint nicht in der Liste').toBeVisible({ timeout: 30_000 })

    // Kopfzeile: Verfasser und Zeitpunkt.
    await expect(eintrag.locator('.pw-notiz-autor'), 'Verfasser fehlt in der Kopfzeile der Notiz').not.toBeEmpty()
    await expect(eintrag.locator('.pw-notiz-datum'), 'Zeitpunkt fehlt in der Kopfzeile der Notiz')
      .toHaveText(/\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{2}/)

    // Löschknopf: bei der eigenen Notiz vorhanden …
    await expect(eintrag.locator('.pw-notiz-loeschen'), 'Löschknopf fehlt bei der eigenen Notiz').toBeVisible()
    await expect(eintrag.locator('.pw-notiz-loeschen')).toHaveAttribute('title', 'Notiz löschen')

    // … und bei fremden Notizen NICHT. Nur der Verfasser darf löschen. Der eigene
    // Anzeigename kommt aus der soeben selbst verfassten Notiz — verlässlicher als
    // eine zweite Quelle, die von der Anzeige abweichen könnte.
    const eigenerName = (await eintrag.locator('.pw-notiz-autor').innerText()).trim()
    const fremde = await liste.locator('.pw-notiz-eintrag').evaluateAll(
      (eintraege, name) => eintraege
        .filter((e) => (e.querySelector('.pw-notiz-autor')?.textContent || '').trim() !== name)
        .map((e) => ({
          autor: (e.querySelector('.pw-notiz-autor')?.textContent || '').trim(),
          loeschbar: !!e.querySelector('.pw-notiz-loeschen'),
        })),
      eigenerName,
    )
    for (const f of fremde) {
      expect(f.loeschbar, `fremde Notiz von «${f.autor}» zeigt einen Löschknopf`).toBe(false)
    }

    // Der titellose Entwurf wird beim Schliessen samt Notiz wieder entfernt.
    await page.locator('.pw-modal .pw-btn-schliessen').first().click()
    await page.waitForLoadState('networkidle')
  })
})

// ===========================================================================
// Geteilter Baustein: Dokumente
// ===========================================================================

test.describe('Feld-Inventar: Dokumente', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Überschrift, Pfadhinweis, alle acht Vorlagen, Hochladen und der Dialog «Neues Dokument»', async ({ page }) => {
    await login(page, U1)
    const modal = await oeffneVorstossMaske(page, 'Dokumente')

    const dokumente = feld(modal, 'Dokument').locator('.pw-dokumente')
    await expect(dokumente, 'Dokumente-Baustein fehlt').toBeVisible()
    await expect(dokumente.locator('.pw-dokumente-kopf h4'), 'Überschrift «Dokumente» fehlt').toHaveText('Dokumente')
    await expect(dokumente.locator('.pw-dokumente-kopf code'), 'Pfadhinweis fehlt oder zeigt den falschen Ordner')
      .toHaveText(/^Fraktion\/40_Vorstösse\/10_Eigene\/\d{4}\/\*$/)

    // Der NcActions-Auslöser ist das erste beschriftbare Element im PwField-<label>
    // und erbt darum dessen zugänglichen Namen «Dokument» — über den Text ansprechen.
    const neuKnopf = dokumente.locator('button', { hasText: '+ Neues Dokument' })
    await expect(neuKnopf, 'Menü «+ Neues Dokument» fehlt').toBeVisible()
    // Exakter Name: der Menü-Auslöser erbt vom PwField-<label> einen langen
    // zugänglichen Namen, der den Text «⤒ Hochladen» mit enthält — ohne
    // «exact» träfe die Rollen-Suche beide Knöpfe.
    await expect(
      dokumente.getByRole('button', { name: '⤒ Hochladen', exact: true }),
      'Knopf «⤒ Hochladen» fehlt',
    ).toBeVisible()

    // Alle acht Vorlagen, in genau dieser Reihenfolge.
    await neuKnopf.click()
    const menu = page.locator('.v-popper__popper .action-item__menu, .v-popper__popper ul').first()
    await menu.waitFor({ state: 'visible', timeout: 15_000 })
    const eintraege = (await menu.locator('li').allTextContents()).map((s) => s.trim())
    for (const v of DOKUMENT_VORLAGEN) {
      expect(eintraege, `Vorlage «${v}» fehlt im Menü «+ Neues Dokument»`).toContain(v)
    }
    expect(eintraege.filter((e) => DOKUMENT_VORLAGEN.includes(e)), 'die Vorlagen stehen in einer anderen Reihenfolge')
      .toEqual(DOKUMENT_VORLAGEN)

    // Funktion: Eine Vorlage öffnet den Erstellen-Dialog mit Präfix und Endung.
    await menu.locator('li', { hasText: DOKUMENT_VORLAGEN[0] }).first().click()
    const dialog = page.locator('.pw-modal-dokument')
    await dialog.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(dialog.locator('h3'), 'der Dialog nennt die gewählte Vorlage nicht')
      .toHaveText(`Neues Dokument: ${DOKUMENT_VORLAGEN[0]}`)
    await expect(dialog.locator('label'), 'Beschriftung des Namensfelds fehlt')
      .toContainText('Dateiname (ohne Endung)')
    await expect(dialog.locator('.pw-dokument-praefix'), 'Der interne V-Präfix darf im Vorstoss-Dokumentnamen nicht mehr erscheinen').toHaveCount(0)
    await expect(dialog.locator('.pw-dokument-suffix'), 'Endung fehlt in der Namensvorschau').toHaveText('.docx')
    const nameFeld = dialog.locator('input.pw-input')
    await expect(nameFeld, 'Namensfeld fehlt').toBeVisible()
    await expect(nameFeld).toHaveAttribute('placeholder', 'z. B. Überweisung Rede')

    const erstellen = dialog.getByRole('button', { name: 'Erstellen' })
    const abbrechen = dialog.getByRole('button', { name: 'Abbrechen' })
    await expect(erstellen, 'Knopf «Erstellen» fehlt').toBeVisible()
    await expect(abbrechen, 'Knopf «Abbrechen» fehlt').toBeVisible()

    // Funktion: Ohne Namen lässt sich nichts erstellen; mit Namen schon.
    await nameFeld.fill('')
    await expect(erstellen, 'ohne Dateinamen darf «Erstellen» nicht freigegeben sein').toBeDisabled()
    await nameFeld.fill(`Inventar ${stamp}`)
    await expect(erstellen, '«Erstellen» bleibt trotz Dateiname gesperrt').toBeEnabled()

    // Abbrechen schliesst den Dialog, ohne eine Datei anzulegen.
    await abbrechen.click()
    await expect(dialog, '«Abbrechen» schliesst den Dialog nicht').toHaveCount(0)
    await expect(dokumente.locator('.pw-dokument-eintrag'), '«Abbrechen» hat trotzdem ein Dokument erzeugt').toHaveCount(0)

    await modal.locator('.pw-btn-schliessen').click()
  })
})

// ===========================================================================
// Geteilter Baustein: formatierter Textbereich (Werkzeugleiste)
// ===========================================================================

test.describe('Feld-Inventar: formatierter Textbereich', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  test('Alle Knöpfe der Werkzeugleiste sind vorhanden und formatieren wirklich', async ({ page }) => {
    await login(page, U1)
    const modal = await oeffneVorstossMaske(page, 'Wysiwyg')

    const bereich = feld(modal, 'Inhalt').locator('.pw-wysiwyg')
    const leiste = bereich.locator('.pw-wysiwyg__toolbar')
    await leiste.waitFor({ state: 'visible', timeout: 30_000 })

    for (const knopf of WYSIWYG_KNOEPFE) {
      await expect(
        leiste.locator(`[title=${JSON.stringify(knopf)}]`),
        `Knopf «${knopf}» fehlt in der Werkzeugleiste`,
      ).toHaveCount(1)
    }

    // «Link entfernen» ist ohne aktiven Link bewusst gesperrt — der Zustand
    // hängt am Inhalt, nicht nur an der Anwesenheit des Knopfes.
    await expect(leiste.locator('[title="Link entfernen"]'), '«Link entfernen» ist ohne Link freigegeben').toBeDisabled()

    // Funktion: Text auszeichnen, Auszeichnung erkennen, Rückgängig wirkt.
    const prose = bereich.locator('.pw-wysiwyg__editor .ProseMirror').first()
    await prose.click()
    await page.waitForTimeout(300)
    await prose.pressSequentially('Formatierungsprobe', { delay: 25 })
    await expect(prose, 'Der Absatz wurde nicht geschrieben').toHaveText('Formatierungsprobe')

    // «Fett» ohne Auswahl zeichnet den ganzen Absatz aus.
    await leiste.locator('[title="Fett (Ctrl+B)"]').click()
    await expect(prose.locator('strong'), '«Fett» zeichnet den Text nicht aus').toHaveCount(1)

    // Der Knopf zeigt, was an der EINFÜGEMARKE gilt. Nach dem Auszeichnen steht
    // sie am Absatzanfang, also ausserhalb der Auszeichnung. Sie wird MITTEN in
    // das fette Wort gesetzt — mit der Tastatur, weil ein Mausklick je nach
    // Browser am Wortende landet und dort schon wieder ausserhalb der Marke
    // liegt (gemessen: Chromium Position 4, Firefox Position 18 von 18).
    await prose.click()
    await page.keyboard.press('Home')
    for (let i = 0; i < 5; i++) { await page.keyboard.press('ArrowRight') }
    await expect(leiste.locator('[title="Fett (Ctrl+B)"]'), '«Fett» zeigt den aktiven Zustand nicht an')
      .toHaveClass(/aktiv/, { timeout: 15_000 })

    await leiste.locator('[title="Rückgängig (Ctrl+Z)"]').click()
    await expect(prose.locator('strong'), '«Rückgängig» nimmt die Auszeichnung nicht zurück').toHaveCount(0)

    // Und eine Block-Auszeichnung: aus dem Absatz wird eine Aufzählung.
    await leiste.locator('[title="Aufzählung"]').click()
    await expect(prose.locator('ul li'), '«Aufzählung» erzeugt keine Liste').toHaveCount(1)
    await leiste.locator('[title="Formatierung entfernen"]').click()
    await expect(prose.locator('ul'), '«Formatierung entfernen» räumt die Liste nicht ab').toHaveCount(0)

    await modal.locator('.pw-btn-schliessen').click()
  })
})

// ===========================================================================
// Sitzungsformular: Traktanden und Teilnehmer
// ===========================================================================

test.describe('Feld-Inventar: Traktanden und Teilnehmer im Sitzungsformular', () => {
  test.use({ viewport: { width: 1600, height: 1000 } })

  /** Öffnet das vollständige Sitzungsformular über den ersten Sitzungstyp. */
  async function oeffneSitzungsformular(page) {
    await oeffneAnsicht(page, 'Sitzungen', /Neue Sitzung/)
    await page.getByRole('button', { name: /Neue Sitzung/ }).click()
    const menu = page.locator('.v-popper__popper .action-item__menu, .v-popper__popper ul').first()
    await menu.waitFor({ state: 'visible', timeout: 15_000 })
    await menu.locator('button').first().click()
    const form = page.locator('.pw-neue-sitzung-form')
    await form.waitFor({ state: 'visible', timeout: 30_000 })
    return form
  }

  test('Traktandenzeile: Ziehgriff, Nummer, Titel, Beschreibung und Löschknopf wirken', async ({ page }) => {
    await login(page, U1)
    const form = await oeffneSitzungsformular(page)

    await expect(form.locator('.pw-form-traktanden .pw-form-traktanden-kopf'), 'Überschrift «Traktanden» fehlt')
      .toHaveText('Traktanden')
    const hinzufuegen = form.getByRole('button', { name: '+ Traktandum hinzufügen' })
    await expect(hinzufuegen, 'Knopf «+ Traktandum hinzufügen» fehlt').toBeVisible()

    const zeilen = form.locator('.pw-form-traktandum')
    const vorher = await zeilen.count()
    await hinzufuegen.click()
    await expect(zeilen, '«+ Traktandum hinzufügen» erzeugt keine Zeile').toHaveCount(vorher + 1)

    // Inventar EINER Zeile.
    const neu = zeilen.last()
    await expect(neu.locator('.pw-form-drag-handle'), 'Ziehgriff fehlt in der Traktandenzeile').toBeVisible()
    await expect(neu.locator('.pw-form-drag-handle'), 'der Ziehgriff lässt sich nicht ziehen')
      .toHaveAttribute('draggable', 'true')
    await expect(neu.locator('.pw-form-traktandum-nr'), 'Nummer fehlt in der Traktandenzeile')
      .toHaveText(`${vorher + 1}.`)
    await expect(neu.locator('input[placeholder="Titel"]'), 'Titelfeld fehlt in der Traktandenzeile').toBeVisible()
    await expect(neu.locator('input[placeholder="Beschreibung (optional)"]'), 'Beschreibungsfeld fehlt in der Traktandenzeile')
      .toBeVisible()
    const loeschen = neu.locator('.pw-form-del-btn')
    await expect(loeschen, 'Löschknopf fehlt in der Traktandenzeile').toBeVisible()
    await expect(loeschen, 'der Löschknopf nennt das Traktandum nicht')
      .toHaveAttribute('aria-label', `Traktandum ${vorher + 1} löschen`)

    // Funktion: Eingaben werden übernommen, der Löschknopf entfernt genau diese
    // Zeile und die Nummerierung schliesst die Lücke.
    await neu.locator('input[placeholder="Titel"]').fill(`Inventar-Traktandum ${stamp}`)
    await neu.locator('input[placeholder="Beschreibung (optional)"]').fill('Beschreibung zur Probe')
    await expect(neu.locator('input[placeholder="Titel"]')).toHaveValue(`Inventar-Traktandum ${stamp}`)

    await loeschen.click()
    await expect(zeilen, 'der Löschknopf entfernt die Traktandenzeile nicht').toHaveCount(vorher)
    const nummern = (await zeilen.locator('.pw-form-traktandum-nr').allTextContents()).map((s) => s.trim())
    expect(nummern, 'die Traktanden sind nach dem Löschen nicht lückenlos nummeriert')
      .toEqual(nummern.map((_, i) => `${i + 1}.`))

    // Nichts anlegen: das Formular wird verworfen.
    await form.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(form).toHaveCount(0)
  })

  test('Teilnehmerzeile: Art-Auswahl mit allen sieben Arten, Löschknopf und Hinzufügen wirken', async ({ page }) => {
    await login(page, U1)
    const form = await oeffneSitzungsformular(page)

    await expect(form.locator('.pw-form-teilnehmer .pw-form-traktanden-kopf'), 'Überschrift «Teilnehmer» fehlt')
      .toHaveText('Teilnehmer')
    const hinzufuegen = form.getByRole('button', { name: '+ Teilnehmer hinzufügen' })
    await expect(hinzufuegen, 'Knopf «+ Teilnehmer hinzufügen» fehlt').toBeVisible()

    const zeilen = form.locator('.pw-form-teilnehmer-zeile')
    const vorher = await zeilen.count()
    await hinzufuegen.click()
    await expect(zeilen, '«+ Teilnehmer hinzufügen» erzeugt keine Zeile').toHaveCount(vorher + 1)

    // Inventar EINER Zeile: die Art-Auswahl mit allen sieben Arten.
    const neu = zeilen.last()
    const artAuswahl = neu.locator('select').first()
    await expect(artAuswahl, 'Art-Auswahl fehlt in der Teilnehmerzeile').toBeVisible()
    const arten = (await artAuswahl.locator('option').allTextContents()).map((s) => s.trim())
    for (const a of TEILNEHMER_ARTEN) {
      expect(arten, `Teilnehmer-Art «${a}» fehlt`).toContain(a)
    }
    expect(arten, 'die Teilnehmer-Arten stehen in einer anderen Reihenfolge').toEqual(TEILNEHMER_ARTEN)
    await expect(neu.locator('.pw-form-del-btn'), 'Löschknopf fehlt in der Teilnehmerzeile').toBeVisible()

    // Funktion: Die gewählte Art bestimmt, was daneben zur Auswahl steht.
    // Vorbelegt ist «Eigene Fraktion» — dort steht nur der Hinweis auf die Gruppe.
    await expect(artAuswahl, 'eine neue Teilnehmerzeile ist nicht auf «Eigene Fraktion» vorbelegt')
      .toHaveValue('eigeneFraktion')
    await expect(neu.locator('.pw-form-teilnehmer-hinweis'), 'bei «Eigene Fraktion» fehlt der Hinweis auf die Gruppe')
      .toBeVisible()

    await artAuswahl.selectOption('mitglied')
    const zweiteAuswahl = neu.locator('select').nth(1)
    await expect(zweiteAuswahl, 'bei «Einzelnes Mitglied» erscheint keine Mitglieder-Auswahl').toBeVisible()
    await expect(zweiteAuswahl.locator('option').first()).toHaveText('— Mitglied wählen —')

    await artAuswahl.selectOption('kommission')
    await expect(neu.locator('select').nth(1).locator('option').first(), 'bei «Ganze Kommission» erscheint keine Kommissions-Auswahl')
      .toHaveText('— Kommission wählen —')

    // Funktion: Der Löschknopf entfernt genau diese Zeile.
    await neu.locator('.pw-form-del-btn').click()
    await expect(zeilen, 'der Löschknopf entfernt die Teilnehmerzeile nicht').toHaveCount(vorher)

    await form.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(form).toHaveCount(0)
  })
})
