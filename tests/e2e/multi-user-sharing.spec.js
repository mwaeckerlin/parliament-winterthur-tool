import { test, expect, request as pwRequest } from '@playwright/test'

/**
 * Multi-User-E2E-Test: drei gleichzeitige Nutzer in drei Browser-Kontexten.
 *
 * Geprüft wird die echte gemeinsame Fraktionsarbeit:
 *  - Alle drei sehen den geteilten Fraktionsordner (Files-App, Browser) und den
 *    Fraktionskalender (CalDAV – die Kalender-Web-App ist im Test-Image nicht
 *    installiert, das CalDAV-Backend hingegen schon).
 *  - Ein Termin wird per CalDAV angelegt, von einem zweiten Nutzer bearbeitet und
 *    von allen gesehen.
 *  - Ein Dokument wird im geteilten Ordner per WebDAV angelegt, bearbeitet und von
 *    allen gelesen; im Browser ist der geteilte Ordner bei jedem Nutzer sichtbar.
 *  - Beschluss- und Notiz-Änderungen an einem Geschäft erscheinen bei den anderen
 *    Nutzern sofort ohne Reload (WebSocket-Echtzeit, parlwin-Detailansicht).
 *
 * Voraussetzungen (von run-compose-e2e.sh hergestellt): drei Nutzer in der
 * Fraktionsgruppe, Fraktionsraum (Ordner + Kalender + Gruppen-Shares) eingerichtet,
 * ein pendentes Geschäft vorhanden.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
  u2: { name: process.env.PW_U2 || 'parlwin_protokoll', pass: process.env.PW_P2 || '' },
  u3: { name: process.env.PW_U3 || 'parlwin_mitglied', pass: process.env.PW_P3 || '' },
}

const CAL_URI = 'parlwin-fraktion-kalender'
const FOLDER = 'Fraktion'

const stamp = Date.now()
const EVENT_TITLE = `E2E Sitzung ${stamp}`
const EVENT_TITLE_EDIT = `${EVENT_TITLE} bearbeitet`
const EVENT_UID = `parlwin-e2e-${stamp}@winterthur`
const DOC_NAME = `E2E-Dokument-${stamp}.md`
const DOC_TEXT = `Erste Fassung ${stamp}`
const DOC_TEXT_EDIT = `Bearbeitete Fassung ${stamp}`

/** Meldet einen Nutzer über das Nextcloud-Login-Formular an. */
async function login(page, user) {
  await page.goto(`${BASE_URL}/index.php/login`)
  // Auf das interaktive Login-Formular warten: in Firefox wird der Submit-Handler
  // (Vue) teils verzögert gebunden, ein zu früher Klick löst dann keine Navigation
  // aus und der Login bleibt hängen.
  await page.waitForSelector('input[name="user"]', { state: 'visible', timeout: 30_000 })
  await page.fill('input[name="user"]', user.name)
  await page.fill('input[name="password"]', user.pass)
  // Bis zu drei Versuche: Klick auslösen (Fehler bewusst verwerfen, da der Klick
  // selbst die Navigation startet) und auf den Seitenwechsel warten. waitUntil
  // 'commit' statt des Defaults 'load' – sonst wartet Playwright in Firefox auf das
  // vollständige Laden des Dashboards (viele Requests) und läuft in einen Timeout,
  // obwohl die URL längst nicht mehr auf /login zeigt.
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

/** Öffnet die Files-App und wartet bis die Dateiliste geladen ist. */
async function openFiles(page) {
  await page.goto(`${BASE_URL}/index.php/apps/files/`)
  await page.waitForLoadState('networkidle')
}

/** Öffnet die parlwin-App in der Geschäfte-Ansicht. */
async function openParlwin(page) {
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForSelector('.pw-tabelle-geschaefte tbody tr', { timeout: 30_000 })
}

/** Authentisierter Request-Kontext (Basic-Auth) für WebDAV/CalDAV. */
async function davContext(user) {
  return pwRequest.newContext({
    baseURL: BASE_URL,
    httpCredentials: { username: user.name, password: user.pass },
    extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
  })
}

/** Findet den URI-Pfad des geteilten Fraktionskalenders im Kalender-Home des Nutzers. */
async function findSharedCalendarHref(ctx, user) {
  const res = await ctx.fetch(`${BASE_URL}/remote.php/dav/calendars/${user.name}/`, {
    method: 'PROPFIND',
    headers: { Depth: '1', 'Content-Type': 'application/xml' },
    data: '<d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
  })
  const body = await res.text()
  const hrefs = [...body.matchAll(/<d:href>([^<]*)<\/d:href>/gi)].map((m) => m[1])
  const match = hrefs.find((h) => h.includes(CAL_URI))
  return match ? (match.endsWith('/') ? match : `${match}/`) : null
}

/** Liest den Inhalt des Termins (.ics) aus dem geteilten Kalender des Nutzers. */
async function readEvent(ctx, user) {
  const href = await findSharedCalendarHref(ctx, user)
  if (!href) return null
  const res = await ctx.fetch(`${BASE_URL}${href}${EVENT_UID}.ics`)
  if (res.status() >= 300) return null
  return res.text()
}

/**
 * Weist per WebDAV nach, dass der geteilte Fraktionsordner beim Nutzer wirklich
 * gemountet ist: ein Unterordner der Admin-Struktur (00_Allgemein) muss
 * erreichbar sein. Ein nicht akzeptierter Gruppen-Share (STATUS_PENDING) wird
 * von Nextcloud nicht gemountet – dann liefert PROPFIND 404. Ein blosser
 * getByText('Fraktion') im Browser würde auch Navigations-/Breadcrumb-Treffer
 * zählen und den fehlenden Mount nicht entdecken.
 */
async function fraktionsordnerGemountet(ctx, user) {
  const res = await ctx.fetch(`${BASE_URL}/remote.php/dav/files/${user.name}/${FOLDER}/00_Allgemein`, {
    method: 'PROPFIND',
    headers: { Depth: '0', 'Content-Type': 'application/xml' },
    data: '<d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>',
  })
  return res.status() === 207
}

test.describe('Fraktion: drei Nutzer arbeiten gleichzeitig zusammen', () => {
  let ctx1, ctx2, ctx3, page1, page2, page3, dav1, dav2, dav3

  test.beforeAll(async ({ browser }) => {
    for (const u of Object.values(USERS)) {
      expect(u.pass, `Passwort für ${u.name} fehlt`).not.toBe('')
    }
    ctx1 = await browser.newContext()
    ctx2 = await browser.newContext()
    ctx3 = await browser.newContext()
    page1 = await ctx1.newPage()
    page2 = await ctx2.newPage()
    page3 = await ctx3.newPage()
    await login(page1, USERS.u1)
    await login(page2, USERS.u2)
    await login(page3, USERS.u3)
    dav1 = await davContext(USERS.u1)
    dav2 = await davContext(USERS.u2)
    dav3 = await davContext(USERS.u3)
  })

  test.afterAll(async () => {
    await Promise.all([
      ctx1?.close(), ctx2?.close(), ctx3?.close(),
      dav1?.dispose(), dav2?.dispose(), dav3?.dispose(),
    ])
  })

  // 0: Regression «Keine Geschäfte gefunden» – die synchronisierten Geschäfte
  // müssen in der Liste erscheinen, auch wenn sie alle erledigt sind. Wird auf der
  // echten parlwin-Seite im gerenderten HTML geprüft.
  test('0: Geschäfteliste zeigt die synchronisierten Geschäfte', async () => {
    await page1.goto(`${BASE_URL}/index.php/apps/parlwin/`)
    await page1.waitForLoadState('networkidle')
    await expect(
      page1.locator('.pw-tabelle-geschaefte tbody tr').first(),
      'Geschäfteliste ist leer – synchronisierte Geschäfte werden nicht angezeigt',
    ).toBeVisible({ timeout: 30_000 })
    await expect(
      page1.getByText('Keine Geschäfte gefunden'),
      'Leermeldung trotz vorhandener Geschäfte',
    ).toHaveCount(0)
  })

  // 0b: Regression (Nextcloud 34) – die Übersetzungsdatei l10n/<lang>.js der App
  // nutzt OC.L10N.register und darf nicht vor dem Nextcloud-Core laden, sonst
  // «Uncaught ReferenceError: OC is not defined» (real auf der Admin-Seite
  // beobachtet). Wir laden die Admin-Einstellungsseite (lädt admin.js + l10n) als
  // Admin mit einem Fehler-Wächter und prüfen, dass kein solcher Fehler auftritt.
  test('0b: Admin-Seite lädt ohne JavaScript-Fehler (OC is not defined)', async ({ browser }) => {
    const adminPass = process.env.PW_ADMIN_PASS || ''
    expect(adminPass, 'Admin-Passwort (PW_ADMIN_PASS) nicht gesetzt').not.toBe('')

    // Deutsch erzwingen: Die Übersetzungsdatei l10n/de.js (OC.L10N.register) wird
    // nur bei deutscher Sprache geladen – genau sie löst «OC is not defined» aus,
    // wenn sie vor dem Nextcloud-Core lädt. Auf Englisch gibt es keine solche Datei,
    // der Fehler bliebe unentdeckt.
    const ctx = await browser.newContext({ locale: 'de-DE' })
    const page = await ctx.newPage()
    const jsFehler = []
    page.on('pageerror', (e) => jsFehler.push(e.message))

    await login(page, { name: 'admin', pass: adminPass })
    await page.goto(`${BASE_URL}/index.php/settings/admin/parlwin`)
    await page.waitForLoadState('networkidle')
    await ctx.close()

    const ocFehler = jsFehler.filter((m) => /OC is not defined|OC is undefined/.test(m))
    expect(
      ocFehler,
      `JavaScript-Ladefehler auf der Admin-Seite: ${jsFehler.join(' | ') || '(keine)'}`,
    ).toEqual([])
  })

  // 1–3: Alle drei sehen den Fraktionsordner (Browser) und den Fraktionskalender (CalDAV).
  test('1–3: Alle drei Nutzer sehen Fraktionsordner und Fraktionskalender', async () => {
    for (const [page, dav, user] of [[page1, dav1, USERS.u1], [page2, dav2, USERS.u2], [page3, dav3, USERS.u3]]) {
      // Echter Mount-Nachweis: der geteilte Ordner ist per WebDAV erreichbar.
      // (parlwin_mitglied akzeptiert Freigaben nicht automatisch – der Ordner
      // erscheint nur, wenn der Fraktionsraum-Service den Share aktiv akzeptiert.)
      expect(
        await fraktionsordnerGemountet(dav, user),
        `${user.name} hat den geteilten Fraktionsordner nicht gemountet (Gruppen-Share nicht akzeptiert?)`,
      ).toBe(true)

      await openFiles(page)
      await expect(
        page.getByText(FOLDER, { exact: false }).first(),
        `${user.name} sieht den Fraktionsordner nicht`,
      ).toBeVisible({ timeout: 30_000 })

      const href = await findSharedCalendarHref(dav, user)
      expect(href, `${user.name} sieht den Fraktionskalender nicht (CalDAV)`).toBeTruthy()
    }
  })

  // 4: User1 legt per CalDAV einen Termin im geteilten Kalender an.
  test('4: User1 legt einen Termin im geteilten Kalender an (CalDAV)', async () => {
    const href = await findSharedCalendarHref(dav1, USERS.u1)
    expect(href, 'Kalender für User1 nicht gefunden').toBeTruthy()
    const dt = new Date(Date.now() + 24 * 3600 * 1000)
    const ymd = dt.toISOString().slice(0, 10).replace(/-/g, '')
    const ics = [
      'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//parlwin//e2e//DE',
      'BEGIN:VEVENT', `UID:${EVENT_UID}`, `DTSTAMP:${ymd}T090000Z`,
      `DTSTART:${ymd}T090000Z`, `DTEND:${ymd}T100000Z`, `SUMMARY:${EVENT_TITLE}`,
      'END:VEVENT', 'END:VCALENDAR',
    ].join('\r\n')
    const res = await dav1.fetch(`${BASE_URL}${href}${EVENT_UID}.ics`, {
      method: 'PUT', headers: { 'Content-Type': 'text/calendar; charset=utf-8' }, data: ics,
    })
    expect(res.status(), 'CalDAV-PUT des Termins fehlgeschlagen').toBeLessThan(300)
  })

  // 5: User2 sieht den Termin und bearbeitet ihn (CalDAV – keine Kalender-Web-App).
  test('5: User2 sieht den Termin und bearbeitet ihn', async () => {
    const inhalt = await readEvent(dav2, USERS.u2)
    expect(inhalt, 'User2 sieht den neuen Termin nicht').toContain(EVENT_TITLE)

    const href = await findSharedCalendarHref(dav2, USERS.u2)
    const ymd = new Date(Date.now() + 24 * 3600 * 1000).toISOString().slice(0, 10).replace(/-/g, '')
    const ics = [
      'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//parlwin//e2e//DE',
      'BEGIN:VEVENT', `UID:${EVENT_UID}`, `DTSTAMP:${ymd}T090000Z`,
      `DTSTART:${ymd}T090000Z`, `DTEND:${ymd}T100000Z`, `SUMMARY:${EVENT_TITLE_EDIT}`,
      'END:VEVENT', 'END:VCALENDAR',
    ].join('\r\n')
    const res = await dav2.fetch(`${BASE_URL}${href}${EVENT_UID}.ics`, {
      method: 'PUT', headers: { 'Content-Type': 'text/calendar; charset=utf-8' }, data: ics,
    })
    expect(res.status(), 'User2 konnte den Termin nicht bearbeiten').toBeLessThan(300)
  })

  // 6 & 7: User3 und User1 sehen den bearbeiteten Termin.
  test('6+7: User3 und User1 sehen den bearbeiteten Termin', async () => {
    for (const [dav, user] of [[dav3, USERS.u3], [dav1, USERS.u1]]) {
      const inhalt = await readEvent(dav, user)
      expect(inhalt, `${user.name} sieht den bearbeiteten Termin nicht`).toContain(EVENT_TITLE_EDIT)
    }
  })

  // 8: User1 legt ein Dokument im geteilten Ordner an (WebDAV).
  test('8: User1 legt ein Dokument im geteilten Fraktionsordner an', async () => {
    const res = await dav1.fetch(
      `${BASE_URL}/remote.php/dav/files/${USERS.u1.name}/${FOLDER}/${DOC_NAME}`,
      { method: 'PUT', headers: { 'Content-Type': 'text/markdown' }, data: DOC_TEXT },
    )
    expect(res.status(), 'WebDAV-PUT des Dokuments fehlgeschlagen').toBeLessThan(300)
  })

  // 9: User2 sieht den geteilten Ordner (Browser) und bearbeitet das Dokument (WebDAV).
  test('9: User2 sieht den geteilten Ordner und bearbeitet das Dokument', async () => {
    await openFiles(page2)
    await expect(
      page2.getByText(FOLDER, { exact: false }).first(),
      'User2 sieht den geteilten Ordner nicht',
    ).toBeVisible({ timeout: 30_000 })

    // Inhalt vor der Bearbeitung sichtbar (geteilt)?
    const vorher = await dav2.fetch(`${BASE_URL}/remote.php/dav/files/${USERS.u2.name}/${FOLDER}/${DOC_NAME}`)
    expect(await vorher.text(), 'User2 sieht den Dokumentinhalt nicht').toContain(DOC_TEXT)

    const res = await dav2.fetch(
      `${BASE_URL}/remote.php/dav/files/${USERS.u2.name}/${FOLDER}/${DOC_NAME}`,
      { method: 'PUT', headers: { 'Content-Type': 'text/markdown' }, data: DOC_TEXT_EDIT },
    )
    expect(res.status(), 'User2 konnte das Dokument nicht bearbeiten').toBeLessThan(300)
  })

  // 10 & 11: User3 und User1 sehen den geteilten Ordner (Browser) und den bearbeiteten Inhalt (WebDAV).
  test('10+11: User3 und User1 sehen den bearbeiteten Dokumentinhalt', async () => {
    for (const [dav, page, user] of [[dav3, page3, USERS.u3], [dav1, page1, USERS.u1]]) {
      await openFiles(page)
      await expect(
        page.getByText(FOLDER, { exact: false }).first(),
        `${user.name} sieht den geteilten Ordner nicht`,
      ).toBeVisible({ timeout: 30_000 })

      const res = await dav.fetch(`${BASE_URL}/remote.php/dav/files/${user.name}/${FOLDER}/${DOC_NAME}`)
      expect(await res.text(), `${user.name} sieht den bearbeiteten Inhalt nicht`).toContain(DOC_TEXT_EDIT)
    }
  })

  // 12 & 13: User1 setzt einen Beschluss → User2 und User3 sehen ihn sofort ohne Reload.
  test('12+13: Beschluss-Änderung erscheint bei allen sofort (Echtzeit)', async () => {
    // Erstes (pendentes) Geschäft bestimmen.
    const liste = await page1.request.get(`${BASE_URL}/index.php/apps/parlwin/geschaefte?limit=1&show_erledigt=1`, {
      headers: { 'OCS-APIRequest': 'true' },
    })
    const gId = (await liste.json())[0].id

    // Alle drei öffnen dasselbe Geschäft im Detail.
    for (const page of [page1, page2, page3]) {
      await openParlwin(page)
      await page.locator('.pw-tabelle-geschaefte tbody tr').first().click()
      await page.waitForSelector('.pw-geschaeft-detail, .pw-modal, .pw-notiz-eingabe', { timeout: 30_000 })
    }

    const detail = await page1.request.get(`${BASE_URL}/index.php/apps/parlwin/geschaefte/${gId}`, {
      headers: { 'OCS-APIRequest': 'true' },
    })
    const code = (await detail.json()).erlaubteBeschluesse[0].code
    const beschlussText = `E2E-Echtzeit-Beschluss ${stamp}`
    const token = await page1.evaluate(() => window.OC?.requestToken || '')
    const post = await page1.request.post(`${BASE_URL}/index.php/apps/parlwin/geschaefte/${gId}/beschluesse`, {
      headers: { 'OCS-APIRequest': 'true', requesttoken: token, 'Content-Type': 'application/x-www-form-urlencoded' },
      form: { code, text: beschlussText },
    })
    expect(post.ok(), 'Beschluss konnte nicht gesetzt werden').toBeTruthy()

    // Ohne Reload: WebSocket-Event aktualisiert die Detailansicht der Beobachter.
    for (const [page, user] of [[page2, USERS.u2], [page3, USERS.u3]]) {
      await expect(
        page.getByText(beschlussText, { exact: false }).first(),
        `${user.name} sieht den Beschluss nicht in Echtzeit`,
      ).toBeVisible({ timeout: 30_000 })
    }
  })

  // 14: User1 schreibt eine Notiz → User2 und User3 sehen sie sofort ohne Reload.
  test('14: Notiz erscheint bei allen sofort (Echtzeit)', async () => {
    // Die Notiz-Eingabe ist ein WYSIWYG-Editor (Tiptap/contenteditable), kein
    // <textarea>: Tiptap legt den Platzhalter als data-placeholder ab, nicht als
    // HTML-placeholder. Das Feld wird daher über das ProseMirror-Element der
    // «Notiz hinzufügen»-Zeile angesprochen, Eingabe per Klick + Tastatur.
    const notizEditor = (page) =>
      page.locator('.pw-form-zeile', { hasText: 'Notiz hinzufügen' }).locator('.ProseMirror').first()
    for (const page of [page1, page2, page3]) {
      await openParlwin(page)
      await page.locator('.pw-tabelle-geschaefte tbody tr .pw-col-titel').first().click()
      await notizEditor(page).waitFor({ state: 'visible', timeout: 30_000 })
    }

    const notizText = `E2E-Echtzeit-Notiz ${stamp}`
    const eingabe = notizEditor(page1)
    await eingabe.click()
    await page1.keyboard.type(notizText)
    await eingabe.blur() // speichert die Notiz (@blur) → löst Echtzeit-Event aus

    for (const [page, user] of [[page2, USERS.u2], [page3, USERS.u3]]) {
      await expect(
        page.getByText(notizText, { exact: false }).first(),
        `${user.name} sieht die Notiz nicht in Echtzeit`,
      ).toBeVisible({ timeout: 30_000 })
    }
  })

  // Regression: konfigurierte Status-Kürzel werden in der Admin-Verwaltung
  // angezeigt (nicht nur in der Geschäftsliste angewandt).
  test('Status-Kürzel werden in der Admin-Verwaltung angezeigt', async ({ browser }) => {
    const ctx = await browser.newContext()
    const page = await ctx.newPage()
    try {
      await login(page, { name: 'admin', pass: process.env.PW_ADMIN_PASS || '' })
      await page.goto(`${BASE_URL}/index.php/settings/admin/parlwin`)
      await page.waitForLoadState('networkidle')

      // Ein Status-Kürzel konfigurieren (wie über den Speichern-Knopf der UI).
      const token = await page.evaluate(() => window.OC?.requestToken || '')
      const res = await page.request.post(`${BASE_URL}/index.php/apps/parlwin/settings/status-kuerzel`, {
        headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
        data: JSON.stringify({ status_kuerzel: [{ suche: 'Beim Stadtrat pendent', kuerzel: 'Pendent: Stadtrat' }] }),
      })
      expect(res.ok(), 'Status-Kürzel speichern fehlgeschlagen').toBeTruthy()

      await page.reload()
      await page.waitForLoadState('networkidle')

      // Genug Zeit, damit ein evtl. konkurrierendes Skript die Liste leeren könnte
      // (der ursprüngliche Firefox-Bug trat erst ~400ms nach dem Rendern auf).
      await page.waitForTimeout(3000)

      const zeile = page.locator('#pw-kuerzel-liste .pw-kuerzel-row').first()
      await expect(
        zeile,
        'Konfiguriertes Status-Kürzel wird in der Admin-Verwaltung nicht angezeigt',
      ).toBeVisible({ timeout: 15_000 })
      await expect(zeile.locator('.pw-kuerzel-suchtext')).toHaveValue('Beim Stadtrat pendent')
      await expect(zeile.locator('.pw-kuerzel-wert')).toHaveValue('Pendent: Stadtrat')
    } finally {
      await ctx.close()
    }
  })
})
