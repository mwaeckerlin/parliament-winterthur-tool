import { test, expect } from '@playwright/test'

/**
 * E2E-Datenfluss des BUDGET-Bereichs im echten Browser gegen das reale System
 * (Nextcloud + DB + API + der aus den Büchern erzeugten reference/budget-Daten).
 *
 * Deckt F74–F93 ab: Budget-Ansicht mit vier Tabs, Filterleiste (Budgetjahr,
 * Departement/Kommission, Kostensteigerung), sticky Summenzeile, Globalbudget-
 * Anträge auf Produktegruppen + Live-Entscheid, anteilige Verteilung/Automatik,
 * Personalbestand-Anträge, Investitionsrechnung je Projekt, Steuerfuss-Automatik,
 * Import vergangener Jahre («+ Neu») und Anträge-PDF.
 *
 * Jeder Test ist self-contained; das Budgetjahr wird idempotent sichergestellt.
 */

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const APP_URL = `${BASE_URL}/index.php/apps/parlwin/`

const USERS = {
  u1: { name: process.env.PW_U1 || 'parlwin_praesidium', pass: process.env.PW_P1 || '' },
}

const JAHR = 2026

function eindeutig(prefix) {
  return `E2E-${prefix} ${Date.now()}-${Math.floor(performance.now())}`
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

async function reqToken(page) {
  return page.evaluate(() => (window.OC && window.OC.requestToken) || '')
}

async function api(page, method, pfad, form, timeout) {
  const token = await reqToken(page)
  const opts = {
    headers: { requesttoken: token, 'OCS-APIRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded' },
  }
  if (form) opts.form = form
  if (timeout) opts.timeout = timeout
  return page.request[method](`${BASE_URL}/index.php/apps/parlwin${pfad}`, opts)
}

/** Stellt sicher, dass ein Budgetjahr importiert ist (idempotent, aus den Büchern). */
async function sorgeFuerBudgetjahr(page, jahr) {
  const res = await api(page, 'get', '/budget/jahre')
  const jahre = res.ok() ? await res.json() : []
  if (!jahre.some((j) => Number(j.jahr) === jahr)) {
    // Der Import holt sein Budgetbuch über die synchronisierte Weisung. Fehlt die,
    // antwortet er mit 500 — und jeder Budget-Test scheitert an einer Meldung, die
    // die Ursache nicht nennt. Darum zuerst nachsehen, ob das Jahr überhaupt eine
    // Quelle hat (2026-08-28: ein Sync-Limit hatte die Weisung weggeschnitten).
    const quellen = await api(page, 'get', '/budget/verfuegbar')
    const verfuegbar = quellen.ok() ? await quellen.json() : {}
    expect(
      (verfuegbar.verfuegbar || []).map(Number).includes(jahr),
      `Für ${jahr} ist keine Budget-Weisung synchronisiert — ohne sie gibt es kein Budgetbuch `
      + `(verfügbar: ${JSON.stringify(verfuegbar.verfuegbar || [])}). Prüfe das Sync-Limit für Geschäfte.`,
    ).toBeTruthy()
    // Der Import lädt zwei Budgetbücher herunter und parst sie Seite für Seite;
    // das dauert länger als die 20 Sekunden, die Playwright einer Anfrage per
    // Default gibt. Lief er in dieses Limit, fehlten dem ganzen Budget-Bereich
    // die Daten — und jeder folgende Test scheiterte an einem leeren Panel statt
    // an seiner eigenen Sache.
    const imp = await api(page, 'post', `/budget/${jahr}/import`, {}, 180_000)
    expect(imp.ok(), `Budget-Import ${jahr} fehlgeschlagen (${imp.status()})`).toBeTruthy()
  }
}

/** Öffnet die parlwin-App und wechselt in die Budget-Ansicht. */
async function gotoBudget(page) {
  await page.goto(APP_URL)
  await page.waitForSelector('.pw-view-content', { timeout: 30_000 })
  await page.getByRole('link', { name: 'Budget', exact: true }).click()
  await page.waitForSelector('.pw-budget', { timeout: 30_000 })
}

function fehlerWaechter(page) {
  const arr = []
  page.on('pageerror', (e) => arr.push(e.message))
  return arr
}

async function tabOeffnen(page, label) {
  // Vor dem Klick nach oben scrollen: der sticky Summen-Balken darf den Tab nicht
  // überlagern (sonst wird der Klick abgefangen).
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.locator('.pw-budget-tabs .pw-budget-tab', { hasText: label }).click()
}

test.beforeAll(() => {
  expect(USERS.u1.pass, `Passwort für ${USERS.u1.name} fehlt`).not.toBe('')
})

test.use({ viewport: { width: 1600, height: 1000 } })

// ---------------------------------------------------------------------------
test.describe('Budget: Import eines alten Jahrgangs (F89, F91)', () => {
  /**
   * Der älteste Jahrgang, den die Stadt veröffentlicht hat, geht denselben Weg
   * wie jeder andere: Budget-Geschäft → Beilagen «Teil A»/«Teil B» → Datenbank →
   * Ansicht. Sein Buch ist anders gesetzt als die heutigen (anderes
   * Rechnungsmodell, Leerzeichen statt Apostroph als Tausendertrenner,
   * Buchhaltungszeichen hinter den Beträgen) — bis zum 31.08.2026 kamen dabei ein
   * Gesamtaufwand von 33 Millionen und ein Steuerertrag von null heraus.
   */
  test('Das Budget 2017 lässt sich importieren und zeigt seine Produktegruppen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, 2017)

    // Die Kopfzahlen kommen aus Teil A und müssen die Grössenordnung einer Stadt
    // mit 110'000 Einwohnern haben — nicht Millionen statt Milliarden.
    const res = await api(page, 'get', '/budget/2017')
    expect(res.ok(), `Budget 2017 nicht abrufbar (${res.status()})`).toBeTruthy()
    const ansicht = await res.json()
    expect(ansicht.produktegruppen.length, 'Zu wenige Produktegruppen im Budget 2017').toBeGreaterThan(20)
    expect(Number(ansicht.jahr.steuerfuss), 'Steuerfuss 2017').toBeGreaterThan(100)
    expect(Number(ansicht.jahr.steuerertrag), 'Steuerertrag 2017').toBeGreaterThan(300_000_000)
    expect(Number(ansicht.jahr.totalAufwand), 'Total Aufwand 2017').toBeGreaterThan(1_000_000_000)
    expect(Number(ansicht.jahr.totalErtrag), 'Total Ertrag 2017').toBeGreaterThan(1_000_000_000)

    // Und die Ansicht zeigt es: Jahr wählbar, Produktegruppen sichtbar.
    await gotoBudget(page)
    await page.locator('.pw-budget-tabpanel .pw-data-card').first().waitFor({ state: 'visible', timeout: 30_000 })

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Ansicht, Tabs, Filter, Summen (F74, F75, F76, F77, F79)', () => {
  test('Budget-Ansicht mit vier Tabs, Filterleiste im Nav-Slot und Summenzeile', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // F74: die vier Tabs.
    for (const label of ['Globalbudgets', 'Personalbestand', 'Investitionsrechnung', 'Steuerfuss']) {
      await expect(page.locator('.pw-budget-tabs .pw-budget-tab', { hasText: label }), `Tab "${label}" fehlt`).toBeVisible()
    }

    // F75/F76/F77: Filter liegen im gemeinsamen #pw-filter-slot (kein eigenes Band).
    const filter = page.locator('#pw-filter-slot .pw-filter-body')
    await expect(filter, 'Budget-Filter nicht im gemeinsamen Filter-Slot').toBeVisible()
    await expect(filter.getByLabel('Anstieg ab %'), 'Kostensteigerungs-Filter (%) fehlt').toBeVisible()
    await expect(filter.getByLabel('Anstieg ab CHF'), 'Kostensteigerungs-Filter (CHF) fehlt').toBeVisible()
    await expect(page.locator('.pw-budget .pw-budget-filter'), 'Es darf kein eigenes Budget-Filterband auf der Seite geben').toHaveCount(0)

    // F79: Summenzeile mit allen Kennzahlen (nach dem Laden der Ansicht).
    await page.locator('.pw-budget-tabpanel .pw-data-card').first().waitFor({ state: 'visible', timeout: 30_000 })
    const summen = page.locator('.pw-budget-summen')
    await expect(summen).toBeVisible()
    for (const label of ['Stellen', 'Ausgaben', 'Einnahmen', 'Steuerfuss']) {
      await expect(summen.getByText(label, { exact: true }), `Summe "${label}" fehlt`).toBeVisible()
    }
    await expect(summen.getByText(/Defizit|Ertrag/)).toBeVisible()

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Kostensteigerungs-Filter grenzt die Produktegruppen ein (F77)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const gruppen = page.locator('.pw-budget-tabpanel:visible .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) })
    await gruppen.first().waitFor({ state: 'visible', timeout: 30_000 })
    const vorher = await gruppen.count()
    expect(vorher, 'Keine Produktegruppen sichtbar').toBeGreaterThan(0)

    // Sehr hohe Schwelle → weniger (oder keine) Gruppen.
    await page.locator('#pw-filter-slot').getByLabel('Anstieg ab CHF').fill('999999999')
    await page.waitForLoadState('networkidle')
    await expect
      .poll(async () => gruppen.count(), { timeout: 15_000 })
      .toBeLessThan(vorher)
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Globalbudgets — Anträge und Live-Entscheid (F80, F81, F82, F83, F84, F93)', () => {
  test('Antrag auf eine Produktegruppe stellen und den Entscheid live setzen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // F80: eine Produktegruppen-Karte mit Globalkredit.
    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(karte.locator('.pw-budget-betrag')).toBeVisible()

    // F81/F82: einen Kürzungsantrag stellen.
    const begruendung = eindeutig('Kürzung')
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-50000')
    await karte.locator('.pw-antrag-form').getByLabel('Begründung').fill(begruendung)
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')

    const antrag = karte.locator('.pw-budget-antraege li', { hasText: begruendung }).first()
    await expect(antrag, 'Antrag erscheint nicht an der Produktegruppe').toBeVisible({ timeout: 15_000 })
    // Der gestellte Betrag steht am Antrag.
    await expect(antrag, 'Betrag des Antrags fehlt').toContainText('50')
    // Der Beschluss (angenommen/abgelehnt) gehört seit F103 in den Sitzungsmodus
    // und wird dort am Sitzungsantrag geprüft — in der Vorbereitung gibt es ihn
    // bewusst nicht, weil eine Fraktion über ihre eigenen Anträge nicht beschliesst.
    await expect(antrag.locator('.pw-entscheid-knoepfe'), 'Beschluss-Knöpfe ausserhalb des Sitzungsmodus').toHaveCount(0)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  // F116/F117: Der Kopf der Karte rechnet unsere Anträge mit, und ein Antrag
  // lässt sich durch einen Klick in seine Zeile bearbeiten.
  test('Der Kopf der Karte zeigt Stadtrat und Fraktion; ein Klick macht den Antrag bearbeitbar', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Gewählt wird eine Produktegruppe, deren Kopf noch einen einzigen Wert
    // trägt: nur an ihr beweist der Test, dass die zweite Zeile durch UNSEREN
    // Antrag entsteht. Beide Browser arbeiten auf derselben Nextcloud, also
    // darf der Test nicht auf eine feste Karte setzen — die kann der andere
    // Browser bereits mit einem Antrag versehen haben.
    const karten = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) })
    await karten.first().waitFor({ state: 'visible', timeout: 30_000 })
    const ohneAntrag = karten.filter({ hasNot: page.locator('.pw-budget-wert-label') }).first()
    await expect(ohneAntrag, 'keine Produktegruppe ohne wirksamen Antrag gefunden (F116)').toBeVisible({ timeout: 20_000 })
    // Die gewählte Karte wird über ihre Produktegruppe festgehalten. Ein Filter
    // «ohne zweiten Wert» träfe nach dem Antrag eine andere Karte, weil diese
    // dann genau das Merkmal trägt, nach dem gesucht wurde.
    const kicker = await ohneAntrag.locator('.pw-data-card-kicker').innerText()
    const karte = karten.filter({ has: page.locator('.pw-data-card-kicker', { hasText: kicker }) }).first()

    // Ohne wirksamen Antrag steht genau ein Wert im Kopf (F116).
    const werte = karte.locator('.pw-budget-wert')
    const vorher = await werte.count()

    const begruendung = eindeutig('Kopfwert')
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-70000')
    await karte.locator('.pw-antrag-form').getByLabel('Begründung').fill(begruendung)
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')

    // Jetzt stehen zwei Werte da: Stadtrat und Fraktion (F116).
    await expect(werte, 'Kopf zeigt nach dem Antrag nicht zwei Werte (F116)').toHaveCount(2, { timeout: 20_000 })
    await expect(werte.nth(0), 'Beschriftung «Stadtrat» fehlt').toContainText('Stadtrat')
    await expect(werte.nth(1), 'Beschriftung «Fraktion» fehlt').toContainText('Fraktion')
    expect(vorher, 'vor dem Antrag stand mehr als ein Wert im Kopf').toBe(1)

    // Beide Werte tragen ihre Differenz zum Vorjahr, und die Zahl der Fraktion
    // ist um den beantragten Betrag kleiner als die des Stadtrats.
    const zahl = async (i) => Number((await werte.nth(i).locator('.pw-budget-wert-zahl').innerText()).replace(/[^\d-]/g, ''))
    expect(await zahl(0) - await zahl(1), 'die Fraktion liegt nicht um den Antrag tiefer').toBe(70000)
    await expect(werte.nth(1).locator('.pw-summe-diff'), 'Differenz zum Vorjahr der Fraktion fehlt').toBeVisible()

    // F117: Ein Klick in die Antragszeile öffnet das Formular mit den Werten des
    // Antrags; der geänderte Betrag steht danach an der Zeile.
    // Die Zeile wird über ihre Stelle in der Liste festgehalten, nicht über
    // ihren Text: sobald das Formular offen ist, steht die Begründung nur noch
    // im Feld, und ein Textfilter fände die Zeile nicht mehr wieder.
    const zeilen = karte.locator('.pw-budget-antraege li')
    const stelle = await zeilen.filter({ hasText: begruendung }).first().evaluate(
      (el) => Array.from(el.parentElement.children).indexOf(el),
    )
    const antrag = zeilen.nth(stelle)
    await antrag.click()
    const formular = antrag.locator('.pw-antrag-form')
    await expect(formular, 'Klick öffnet kein Formular (F117)').toBeVisible({ timeout: 15_000 })
    await expect(formular.locator('input[type="number"]').first(), 'Betrag nicht vorbelegt (F117)').toHaveValue('70000')

    await formular.locator('input[type="number"]').first().fill('90000')
    await formular.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')

    // Gemessen wird die Betragszelle der Zeile, nicht ihr ganzer Text: die
    // Begründung trägt einen Zeitstempel, in dem jede Ziffernfolge vorkommt, und
    // eine Suche nach «90» im Zeilentext bestätigte sich darum selbst.
    const geaendert = zeilen.nth(stelle)
    await expect(geaendert.locator('.pw-antrag-betrag'), 'geänderter Betrag steht nicht am Antrag (F117)')
      .toHaveText(/90['’]000/, { timeout: 20_000 })
    expect(await zahl(0) - await zahl(1), 'der Kopf folgt dem geänderten Antrag nicht').toBe(90000)

    // F116: Die Werte stehen rechts an der Karte — auch dann, wenn der Titel
    // ihnen die Zeile nimmt und sie umbrechen. Geprüft wird bei einer Breite,
    // bei der der Umbruch wirklich eintritt: nur so misst der Test die Kante,
    // die auf breiten Ansichten ohnehin stimmt.
    await page.setViewportSize({ width: 900, height: 900 })
    const betrag = karte.locator('.pw-budget-betrag').first()
    const kopf = karte.locator('.pw-budget-kopf').first()
    const kastenBetrag = await betrag.boundingBox()
    const kastenKopf = await kopf.boundingBox()
    const abstandRechts = (kastenKopf.x + kastenKopf.width) - (kastenBetrag.x + kastenBetrag.width)
    expect(abstandRechts, 'die Werte stehen nach dem Umbruch nicht an der rechten Kante der Karte (F116)').toBeLessThan(2)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  test('Produkte und Auftrag erscheinen als Information an der Produktegruppe (F80)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Produkte und Auftrag stehen seit F110 im Vollbild-Detail der Produktegruppe,
    // das der Knopf «Details» an der Karte öffnet — die Karte selbst bleibt
    // übersichtlich. Geprüft wird also der Weg, den auch ein Mensch geht.
    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', {
      has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }),
    }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    await karte.locator('.pw-pg-detail-knopf').click()

    const detail = page.locator('.pw-pg-detail')
    await detail.waitFor({ state: 'visible', timeout: 20_000 })
    await expect(detail.getByRole('heading', { name: 'Produkte' }), 'Keine Produkte im Detail (F80)').toBeVisible()
    await expect(detail.locator('.pw-produkt-karte').first(), 'Kein Produkt als Karte gelistet (F80)').toBeVisible()
    // Der Auftragstext der Produktegruppe steht als eigener Abschnitt.
    await expect(detail.getByRole('heading', { name: 'Auftrag' }), 'Kein Auftragstext im Detail (F80)').toBeVisible()
  })

  test('Automatik-Schalter und Zielmodus der anteiligen Verteilung sind bedienbar (F83, F84)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Die anteilige Verteilung steckt seit F100/F104 im Pauschalantrag selbst:
    // Der Kasten startet leer, «+ Pauschalantrag» legt einen an, und dessen
    // Ziel-Typ bestimmt, was verteilt wird (Einsparung, schwarze Null, fester
    // Ertrag, festes Defizit). Der frühere eigene Automatik-Block ist entfallen.
    const kasten = page.locator('.pw-pauschalantraege')
    await kasten.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(kasten.locator('.pw-pauschal-automatik'), 'Der alte Automatik-Block ist zurück (F84)').toHaveCount(0)

    const vorher = await kasten.locator('.pw-pauschal-form').count()
    await kasten.getByRole('button', { name: '+ Pauschalantrag' }).click()
    await expect(kasten.locator('.pw-pauschal-form'), 'Pauschalantrag wurde nicht angelegt (F84)').toHaveCount(vorher + 1, { timeout: 20_000 })
    const form = kasten.locator('.pw-pauschal-form').last()

    // Ziel-Typ ist die erste Auswahl; bei «Einsparungen» steht das Betragsfeld da,
    // über das der anteilig zu verteilende Betrag gesetzt wird (F83).
    await expect(form.locator('.v-select').first(), 'Ziel-Auswahl fehlt (F84)').toBeVisible()
    await expect(form.getByLabel('Einsparung CHF'), 'Betragsfeld der Einsparung fehlt (F83)').toBeVisible()
    await expect(form.getByLabel('oder in Prozent'), 'Prozentfeld der Einsparung fehlt (F83)').toBeVisible()

    // Aufräumen: der Testantrag verschwindet wieder.
    await form.locator('.pw-loeschen-ecke').click()
    await expect(kasten.locator('.pw-pauschal-form'), 'Test-Pauschalantrag nicht gelöscht').toHaveCount(vorher, { timeout: 20_000 })
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Personalbestand und Investitionen (F86, F87)', () => {
  test('Personalbestand zeigt Stellen und nimmt einen Personalantrag an (F86)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Personalbestand')

    const karte = page.locator('.pw-budget-tabpanel:visible .pw-data-card').first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    await expect(karte.locator('.pw-budget-betrag', { hasText: 'Stellen' })).toBeVisible()

    // Personalkürzung: Stellen − und Betrag (Vorgabe-Betrag ist vorbelegt).
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-1')
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    await expect(karte.locator('.pw-budget-antraege li').first(), 'Personalantrag erscheint nicht').toBeVisible({ timeout: 15_000 })
  })

  test('Investitionsrechnung listet Projekte mit Budgetwert (F87)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Investitionsrechnung')

    const panel = page.locator('.pw-budget-tabpanel:visible')
    await expect(panel.locator('.pw-budget-dep-titel').first(), 'Kein Departement in der Investitionsrechnung').toBeVisible({ timeout: 30_000 })
    const projekt = panel.locator('.pw-data-card').first()
    await expect(projekt.locator('h3'), 'Kein Projekt sichtbar').toBeVisible()
    await expect(projekt.locator('.pw-budget-betrag')).toBeVisible()

    // Der Filter «nur mit Betrag im Budgetjahr» steht bei den anderen Filtern in
    // der Seitenleiste und blendet die Projekte aus, die erst in einem Planjahr
    // beginnen. Auf den anderen Tabs wirkt er nicht und steht dort auch nicht.
    // Gefunden wird der Schalter an seiner Beschriftung: Ein Attribut an
    // NcCheckboxRadioSwitch landet am rohen Input, der versteckt ist, und dessen
    // Klickfläche liegt darüber.
    const filter = page.locator('#pw-filter-slot .checkbox-radio-switch', { hasText: 'Nur mit Betrag im Budgetjahr' })
    await expect(filter, 'Der Investitionsfilter fehlt in der Seitenleiste (F87)').toBeVisible()
    const vorher = await panel.locator('.pw-data-card').count()
    await filter.locator('.checkbox-radio-switch__content').first().click()
    await expect
      .poll(async () => panel.locator('.pw-data-card').count(), {
        message: 'Der Filter blendet keine Projekte aus (F87)',
      })
      .toBeLessThan(vorher)
    await filter.locator('.checkbox-radio-switch__content').first().click()

    await tabOeffnen(page, 'Globalbudgets')
    await expect(filter, 'Der Investitionsfilter steht auch auf anderen Tabs (F87)').toHaveCount(0)
    await tabOeffnen(page, 'Investitionsrechnung')

    // Gesamtkosten und bereits Getätigtes kommen aus dem Buch. Nicht jedes
    // Projekt trägt jeden Wert — wer erst in einem Planjahr beginnt, hat im
    // Budgetjahr nichts, und Gesamtkosten führt nur, wer einen bewilligten Kredit
    // hat. Eine Karte ganz ohne Zahl ist dagegen leer, und davon gibt es im Buch
    // wenige: über den ganzen Tab muss die grosse Mehrheit der Werte stehen.
    // «Nicht alle sind null» genügt hier nicht — Marc sah fast überall Nullen,
    // und diese Prüfung liess das durch (F87).
    const werte = await panel.locator('.pw-data-card .pw-data-pair strong').allTextContents()
    expect(werte.length, 'Keine Wertfelder in der Investitionsrechnung').toBeGreaterThan(20)
    const gefuellt = werte.filter(t => /[1-9]/.test(t)).length
    expect(
      gefuellt,
      `Nur ${gefuellt} von ${werte.length} Wertfeldern der Investitionsrechnung tragen eine Zahl (F87)`,
    ).toBeGreaterThan(werte.length / 3)

    // Ein Klick auf «Details» öffnet die Angaben, die auf der Karte keinen Platz
    // haben: Jahresreihe, Gesamtkosten und die bewilligten Kredite je Konto.
    await projekt.getByRole('button', { name: /^Details/ }).click()
    const detail = page.locator('.pw-modal-vollbild .pw-pg-detail')
    await expect(detail, 'Detail eines Investitionsprojekts öffnet nicht (F87)').toBeVisible()
    await expect(detail.getByText('Investitionen über die Jahre')).toBeVisible()
    await page.locator('.pw-modal-vollbild .pw-btn-schliessen').click()
    await expect(detail).toHaveCount(0)

    // Einen Investitionsantrag stellen.
    await projekt.getByRole('button', { name: '+ Antrag' }).click()
    await projekt.locator('.pw-antrag-form input[type="number"]').first().fill('-10000')
    await projekt.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
  })
})

// ---------------------------------------------------------------------------
test.describe('Budget: Steuerfuss, Import und PDF (F88, F89, F91, F92)', () => {
  test('Steuerfuss-Tab zeigt geltenden Fuss und Wert je Prozent, Automatik schaltbar (F88)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)
    await tabOeffnen(page, 'Steuerfuss')

    const panel = page.locator('.pw-budget-tabpanel:visible .pw-steuerfuss')
    await panel.waitFor({ state: 'visible', timeout: 30_000 })
    // Der Stadtratsantrag steht immer da; der geltende Fuss des Vorjahres nur,
    // wenn dessen Buch auch eingelesen ist — sonst nennt das Panel die Differenz
    // von ±0%.
    await expect(panel.getByText(/Stadtratsantrag Steuerfuss/)).toBeVisible()
    await expect(panel.getByText(/Geltender Steuerfuss|Differenz zum Vorjahr/)).toBeVisible()
    await expect(panel.getByText('1 Steuerprozent')).toBeVisible()
    await expect(panel.getByText('Steuerfuss bei Überschuss automatisch senken')).toBeVisible()

    // Automatik aus (auf das Schalter-Label klicken) → das Feld für den eigenen
    // Fuss erscheint samt dem Schalter «Antrag stellen» (seit F96 ein Toggle,
    // kein Knopf: der Antrag entsteht und verschwindet mit dem Schalter).
    await panel.locator('.checkbox-radio-switch__content').first().click()
    await expect(panel.getByText('Antrag stellen'), 'Schalter «Antrag stellen» fehlt (F88)').toBeVisible({ timeout: 15_000 })
  })

  test('«+ Neu» öffnet den Import-Dialog mit vorhandenen Jahren (F89, F91)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    await page.getByRole('button', { name: '+ Neu' }).click()
    const modal = page.locator('.pw-modal', { hasText: 'Vergangenes Budgetjahr importieren' })
    await expect(modal, 'Import-Dialog nicht geöffnet').toBeVisible({ timeout: 15_000 })
    // Es gibt ein Jahr-Auswahlfeld ODER den Hinweis, dass keine weiteren Jahre da sind.
    const hatAuswahl = await modal.locator('.v-select').count()
    const hatHinweis = await modal.getByText('Keine weiteren Jahre').count()
    expect(hatAuswahl + hatHinweis, 'Weder Jahr-Auswahl noch Leerhinweis im Import-Dialog').toBeGreaterThan(0)
    await modal.getByRole('button', { name: 'Abbrechen' }).click()
    await expect(modal).toHaveCount(0)
  })

  test('Sitzungsmodus trennt Fraktions- von Sitzungsanträgen (F93)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // In der Vorbereitung einen Fraktionsantrag stellen.
    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    const fraktionText = eindeutig('Fraktion')
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    await karte.locator('.pw-antrag-form input[type="number"]').first().fill('-11000')
    await karte.locator('.pw-antrag-form').getByLabel('Begründung').fill(fraktionText)
    await karte.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    await expect(karte.locator('.pw-budget-antraege li', { hasText: fraktionText })).toBeVisible({ timeout: 15_000 })

    // Sitzungsmodus einschalten (Schalter per Label-Text) → der Fraktionsantrag
    // verschwindet, die Automatik-Karte auch.
    await page.locator('#pw-filter-slot .checkbox-radio-switch__content').first().click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-pauschalantraege'), 'Pauschalanträge-Kasten im Sitzungsmodus weiterhin sichtbar').toHaveCount(0)
    await expect(
      page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: fraktionText }),
      'Fraktionsantrag im Sitzungsmodus weiterhin sichtbar',
    ).toHaveCount(0)

    // Im Sitzungsmodus einen offiziellen Sitzungsantrag erfassen.
    const karteS = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karteS.waitFor({ state: 'visible', timeout: 30_000 })
    const sitzungText = eindeutig('Sitzung')
    await karteS.getByRole('button', { name: '+ Antrag' }).click()
    // Das Feld zeigt den Betrag OHNE Vorzeichen; die Richtung kommt vom
    // Umschalter «Reduktion», der auf Reduktion steht.
    //
    // Getippt statt gefüllt: Nach dem Moduswechsel ist das Formular frisch
    // aufgebaut, und ein fill() setzt dort zwar den Text, löst aber nicht
    // zuverlässig das Eingabe-Ereignis aus, an dem die Bindung hängt — dann
    // bleibt der Betrag leer und der Antrag wird gar nicht gesendet. Ein
    // Mensch klickt ins Feld und tippt; genau das tut der Test hier.
    const betragFeld = karteS.locator('.pw-antrag-form input[type="number"]').first()
    await betragFeld.click()
    await betragFeld.pressSequentially('12000', { delay: 30 })
    await expect(betragFeld, 'Der Betrag erreicht das Formular nicht').toHaveValue('12000')
    await karteS.locator('.pw-antrag-form').getByLabel('Begründung').fill(sitzungText)
    await karteS.getByRole('button', { name: 'Antrag', exact: true }).click()
    await page.waitForLoadState('networkidle')
    const sitzungsantrag = page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: sitzungText }).first()
    await expect(sitzungsantrag, 'Sitzungsantrag nicht sichtbar').toBeVisible({ timeout: 15_000 })

    // F93: Der Beschluss wird im Sitzungsmodus am Sitzungsantrag gesetzt (✓) —
    // die Zeile zeigt ihn danach als «angenommen».
    await sitzungsantrag.locator('.pw-entscheid-knoepfe button', { hasText: '✓' }).click()
    await page.waitForLoadState('networkidle')
    await expect(
      page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: sitzungText }).locator('.pw-entscheid-angenommen'),
      'Beschluss «angenommen» nicht sichtbar (F93)',
    ).toBeVisible({ timeout: 15_000 })

    // Zurück in die Vorbereitung → der Fraktionsantrag ist wieder da, der Sitzungsantrag weg.
    await page.locator('#pw-filter-slot .checkbox-radio-switch__content').first().click()
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: fraktionText })).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('.pw-budget-tabpanel .pw-budget-antraege li', { hasText: sitzungText })).toHaveCount(0)
  })

  test('«Anträge als PDF» öffnet die Druckansicht (F92)', async ({ page }) => {
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Der PDF-Knopf steht seit F107 im Tab «N Anträge», wo alle Anträge
    // zusammengefasst sind — der Tab heisst nach ihrer Zahl.
    await page.evaluate(() => window.scrollTo(0, 0))
    await page.locator('.pw-budget-tabs .pw-budget-tab', { hasText: /^\d+ Anträge$/ }).click()

    const popupPromise = page.waitForEvent('popup', { timeout: 15_000 }).catch(() => null)
    await page.getByRole('button', { name: 'Anträge als PDF' }).click()
    const popup = await popupPromise
    expect(popup, 'PDF-Druckansicht wurde nicht geöffnet').not.toBeNull()
    if (popup) {
      await popup.waitForLoadState('domcontentloaded').catch(() => {})
      await popup.close().catch(() => {})
    }
  })
})

test.describe('Budget: Grafische Übersicht (F111)', () => {
  test('Der letzte Tab zeigt das Budget als geschachtelte Kreise und lässt sich öffnen', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Die Grafik ist der letzte Tab.
    const tabs = page.locator('.pw-budget-tabs .pw-budget-tab')
    await expect(tabs.last(), 'Die Grafik ist nicht der letzte Tab (F111)').toHaveText('Grafik')
    await tabOeffnen(page, 'Grafik')

    const grafik = page.locator('.pw-budget-grafik')
    await expect(grafik, 'Grafik-Tab bleibt leer (F111)').toBeVisible({ timeout: 20_000 })
    // Zwei Zeichenflächen: Einnahmen und Ausgaben, jede über die volle Breite.
    const ausgaben = grafik.locator('[data-seite="Ausgaben"]')
    const einnahmen = grafik.locator('[data-seite="Einnahmen"]')
    await expect(ausgaben, 'Ausgaben-Fläche fehlt (F111)').toBeVisible()
    await expect(einnahmen, 'Einnahmen-Fläche fehlt (F111)').toBeVisible()

    // Die Kreise finden ihren Platz: d3s Kraftsimulation zieht sie an die Stelle,
    // die ihnen die Packung zuweist, und hält sie dabei auseinander. Gemessen wird
    // die Bewegung selbst, und zwar SOFORT nach dem Öffnen — dort läuft die
    // Simulation heiss. Eine gesetzte Animation beweist nichts: die frühere
    // Fassung skalierte um gut ein Promille und stand für das Auge still.
    const lagenJetzt = () => ausgaben.locator('.pw-bubble[class*="pw-bubble-e1"] circle').evaluateAll(
      (nodes) => nodes.map((n) => {
        const b = n.getBoundingClientRect()
        return { x: b.x, y: b.y, w: b.width }
      }),
    )
    const vorher = await lagenJetzt()
    await page.waitForTimeout(1000)
    const nachher = await lagenJetzt()
    // Gemessen wird der Kreis, der sich am weitesten bewegt hat: In einer
    // Kraftsimulation steht ein Kreis, der schon an seinem Platz ist, fast still,
    // und der erste der Liste ist zufällig irgendeiner davon.
    const weg = Math.max(...vorher.map((v, i) => (nachher[i]
      ? Math.hypot(nachher[i].x - v.x, nachher[i].y - v.y) + Math.abs(nachher[i].w - v.w)
      : 0)))
    expect(weg, `Die Kreise bewegen sich nicht sichtbar (${weg.toFixed(2)} px in einer Sekunde, F111)`)
      .toBeGreaterThan(1)

    // Departemente und Produktegruppen als Kreise, die aktuelle Ebene beschriftet.
    const departement = ausgaben.locator('circle[data-ebene="1"]').first()
    await expect(departement, 'Keine Departements-Kreise (F111)').toBeVisible()
    await expect(ausgaben.locator('circle[data-ebene="2"]').first(), 'Keine Produktegruppen-Kreise (F111)').toBeVisible()
    // Beschriftet ist NUR die aktuelle Ebene: Ein Kind sitzt immer im Kreis
    // seines Elternteils, zwei Beschriftungen träfen sich dort in der Mitte.
    const beschriftet = await ausgaben.locator('text[data-ebene="1"]').count()
    const departemente = await ausgaben.locator('circle[data-ebene="1"]').count()
    expect(beschriftet, 'Keine Beschriftung auf der aktuellen Ebene (F111)').toBeGreaterThan(0)
    expect(beschriftet, 'Mehr Beschriftungen als Kreise auf der aktuellen Ebene (F111)').toBeLessThanOrEqual(departemente)
    expect(
      await ausgaben.locator('text[data-ebene="2"]').count(),
      'Auch tiefere Kreise sind beschriftet — ihre Texte liegen über denen der aktuellen Ebene (F111)',
    ).toBe(0)

    // Ein Bild der Grafik zum Ansehen — Lesbarkeit beurteilt am Ende das Auge.
    await grafik.screenshot({ path: 'test-results/f111-grafik.png' }).catch(() => {})

    // Und die Beschriftungen sind LESBAR: keine zwei überlappen sich, und jede
    // steht in ihrem Kreis. Genau das war der Mangel — die Namen lagen quer
    // übereinander und ergaben Wortsalat.
    const kaesten = await ausgaben.locator('text[data-ebene="1"]').evaluateAll(
      (nodes) => nodes.map((n) => {
        const b = n.getBoundingClientRect()
        return { name: n.getAttribute('data-name'), x: b.x, y: b.y, w: b.width, h: b.height }
      }),
    )
    for (let i = 0; i < kaesten.length; i++) {
      for (let j = i + 1; j < kaesten.length; j++) {
        const a = kaesten[i]
        const b = kaesten[j]
        const ueberlappt = a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h
        expect(ueberlappt, `Beschriftungen «${a.name}» und «${b.name}» überlappen sich (F111)`).toBe(false)
      }
    }

    // Und sie stecken nicht ineinander: die Kollision hält sie auseinander.
    const lagen = await ausgaben.locator('.pw-bubble[class*="pw-bubble-e1"] circle').evaluateAll(
      (nodes) => nodes.map((n) => {
        const b = n.getBoundingClientRect()
        return { name: n.getAttribute('data-name'), x: b.x + b.width / 2, y: b.y + b.height / 2, r: b.width / 2 }
      }),
    )
    for (let i = 0; i < lagen.length; i++) {
      for (let j = i + 1; j < lagen.length; j++) {
        const a = lagen[i]
        const b = lagen[j]
        const abstand = Math.hypot(a.x - b.x, a.y - b.y)
        expect(abstand, `«${a.name}» und «${b.name}» stecken ineinander (F111)`)
          .toBeGreaterThan((a.r + b.r) * 0.8)
      }
    }

    // Die Namen stehen vollständig da, an den Leerzeichen umgebrochen — vier
    // Produkte «Bewirtschaftung …» wären als «Bewirtscha…» nicht zu unterscheiden.
    const beschriftungen = await ausgaben.locator('text[data-ebene="1"]').allTextContents()
    for (const t of beschriftungen) {
      expect(t, `Beschriftung «${t}» ist gekürzt (F111)`).not.toContain('…')
    }

    // Beim Überfahren tritt der Kreis unter dem Zeiger hervor: er wächst und
    // bekommt eine kräftigere Kante. Getroffen wird der innerste Kreis an dieser
    // Stelle — welcher das ist, entscheidet die Anordnung, deshalb prüft der Test
    // die Wirkung und nicht einen bestimmten Kreis.
    // Der Zeiger geht knapp unter den oberen Rand des Kreises: in der Mitte liegen
    // die Kindkreise, dort träfe er ein Kind statt des Departements.
    // Erst warten, bis die Kreise stehen: Die Kraftsimulation bewegt sie zwei bis
    // fünf Sekunden lang (F111), und ein bewegtes Ziel ist nicht anzufahren.
    let vorherLage = await lagenJetzt()
    for (let versuch = 0; versuch < 20; versuch++) {
      await page.waitForTimeout(500)
      const jetzt = await lagenJetzt()
      const bewegung = Math.max(...vorherLage.map((v, i) => (jetzt[i]
        ? Math.hypot(jetzt[i].x - v.x, jetzt[i].y - v.y)
        : 0)))
      vorherLage = jetzt
      if (bewegung < 0.5) { break }
    }
    const oben = ausgaben.locator('.pw-bubble[class*="pw-bubble-e1"]').first()
    const kasten = await oben.boundingBox()
    // Angefahren wird die STELLE, nicht das Element: Dort liegt der innerste Kreis
    // obenauf, und Playwright würde einen Klick auf das Departement als «vom Kind
    // verdeckt» verweigern. Geprüft ist die Wirkung — genau ein Kreis tritt hervor.
    await page.mouse.move(kasten.x + kasten.width / 2, kasten.y + kasten.height * 0.08)
    const wach = ausgaben.locator('.pw-bubble-wach')
    await expect(wach, 'Kein Kreis tritt unter dem Zeiger hervor (F111)').toHaveCount(1)
    expect(
      await wach.locator('circle').evaluate((n) => getComputedStyle(n).strokeWidth),
      'Der Kreis unter dem Zeiger tritt nicht hervor (F111)',
    ).not.toBe(await ausgaben.locator('.pw-bubble:not(.pw-bubble-wach) circle').first()
      .evaluate((n) => getComputedStyle(n).strokeWidth))
    // Und der überfahrene Kreis zeigt die Namen seiner Kinder.
    expect(
      await ausgaben.locator('text.pw-bubble-titel-kind').count(),
      'Beim Überfahren erscheinen die Namen der Kinder nicht (F111)',
    ).toBeGreaterThan(0)

    // Ein Klick auf ein Departement zoomt BEIDE Seiten dorthin. Geklickt wird der
    // Ring, den der Benutzer sieht: In der Mitte des Departements liegen seine
    // Produktegruppen und Produkte, dort öffnet ein Klick das Kind. Gesucht wird
    // die erste Stelle, an der der Departementskreis selbst zuoberst liegt.
    const name = await departement.getAttribute('data-name')
    // Erst den Zeiger vom Kreis nehmen: Der überfahrene Kreis wächst um ein
    // Zwanzigstel, und gemessen werden soll die Lage, die beim Klick gilt.
    await page.mouse.move(1, 1)
    await page.waitForTimeout(400)
    // Gemessen und geklickt wird in Fensterkoordinaten: Der Kreis muss im Bild sein.
    await departement.scrollIntoViewIfNeeded()
    const stelle = await departement.evaluate((kreis) => {
      const b = kreis.getBoundingClientRect()
      const mx = b.x + b.width / 2
      const my = b.y + b.height / 2
      const r = Math.min(b.width, b.height) / 2
      for (const anteil of [0.92, 0.85, 0.78, 0.7, 0.6]) {
        for (let grad = 0; grad < 360; grad += 15) {
          const x = mx + Math.cos((grad * Math.PI) / 180) * r * anteil
          const y = my + Math.sin((grad * Math.PI) / 180) * r * anteil
          if (document.elementFromPoint(x, y) === kreis) { return { x, y } }
        }
      }
      return null
    })
    expect(stelle, `Der Kreis «${name}» ist nirgends anklickbar — die Kinder decken ihn ganz zu (F111)`).not.toBeNull()
    await page.mouse.click(stelle.x, stelle.y)
    await expect(grafik.locator('.pw-grafik-pfad button'), 'Der Weg im Kopf wächst beim Öffnen nicht (F111)').toHaveCount(2)
    await expect(grafik.locator('.pw-grafik-pfad button').last()).toHaveText(name)
    await grafik.locator('.pw-grafik-pfad button').first().click()
    await expect(grafik.locator('.pw-grafik-pfad button')).toHaveCount(1)

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})

test.describe('Budget: Antragsmodell — Herkunft, Betrag, Haltung, Pauschal, Notizen (F94–F104)', () => {
  test('Antragsform und Pauschal-Bedienung zeigen die neuen Felder und sind bedienbar', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-data-card-kicker', { hasText: 'Produktegruppe' }) }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })

    // Das Antragsformular ist lazy — erst über «+ Antrag» einblenden.
    await karte.getByRole('button', { name: '+ Antrag' }).click()
    const form = karte.locator('.pw-antrag-form')
    await form.waitFor({ state: 'visible', timeout: 15_000 })

    // F94/F95/F97/F98: die neuen Formularfelder sind vorhanden.
    await expect(form.getByText('Herkunft', { exact: true }), 'Herkunft-Auswahl fehlt (F94)').toBeVisible()
    // Das Betragsfeld des Antrags steht in einer Formularzeile. «Betrag CHF»
    // heisst auch jedes Feld der Einsparungsverteilung (F109), die je Kostenzeile
    // und Produkt eines führt — die liegen in .pw-antrag-aufteil-zeile.
    await expect(form.locator('.pw-antrag-form-zeile').getByLabel('Betrag CHF'), 'CHF-Feld fehlt (F95)').toBeVisible()
    await expect(form.getByLabel('Betrag %'), 'Prozent-Feld fehlt (F95)').toBeVisible()
    await expect(form.getByText('Unterstützende Fraktionen', { exact: true }), 'Unterstützer-Auswahl fehlt (F98)').toBeVisible()

    // F97: Unsere Haltung wird an der Antragszeile selbst umgeschaltet, nicht im
    // Formular — ein eigener Antrag heisst dort «Antrag stellen», ein fremder
    // «Unterstützen». Der Schalter wird bedient und sein Zustand nachgemessen.
    //
    // Das ist zugleich der Nachweis zu F117: seit ein Klick in die Zeile sie
    // bearbeitbar macht, darf derselbe Klick auf ein Bedienelement genau das
    // NICHT tun. Gemessen wird im Browser, weil ein Klick dort ein Kindelement
    // der fremden Komponente trifft und ihr @click.stop nicht mehr greift.
    const zeile = karte.locator('.pw-budget-antraege li', { has: page.locator('.pw-antrag-toggle') }).first()
    await zeile.waitFor({ state: 'visible', timeout: 15_000 })
    const haltung = zeile.locator('.pw-antrag-toggle')
    await expect(haltung, 'Haltung-Schalter an der Antragszeile fehlt (F97)').toBeVisible()
    const eingang = await haltung.locator('input').first().isChecked()
    await haltung.locator('.checkbox-radio-switch__content').first().click()
    await expect
      .poll(async () => haltung.locator('input').first().isChecked(), {
        message: 'Der Haltung-Schalter ändert seinen Zustand nicht (F97)',
        timeout: 20_000,
      })
      .toBe(!eingang)
    await expect(zeile.locator('.pw-antrag-bearbeiten'),
      'der Klick auf den Haltung-Schalter hat die Zeile bearbeitbar gemacht (F117)').toHaveCount(0)

    // F100: alle Pauschalanträge stehen im gemeinsamen Kasten «Pauschalanträge»,
    // jeder mit seinem Einreichen-Schalter. Die Liste startet leer, darum wird für
    // die Prüfung einer angelegt und am Ende wieder gelöscht.
    const kasten = page.locator('.pw-pauschalantraege')
    await expect(kasten.getByText('Pauschalanträge', { exact: true }), 'Kasten «Pauschalanträge» fehlt (F100)').toBeVisible()
    const vorher = await kasten.locator('.pw-pauschal-form').count()
    await kasten.getByRole('button', { name: '+ Pauschalantrag' }).click()
    await expect(kasten.locator('.pw-pauschal-form')).toHaveCount(vorher + 1, { timeout: 20_000 })
    const pauschal = kasten.locator('.pw-pauschal-form').last()
    await expect(pauschal.getByText('Einreichen', { exact: false }), 'Einreichen-Schalter fehlt (F100)').toBeVisible()
    await expect(pauschal.getByText('Ausnahmen (Produktegruppen)'), 'Ausnahmen-Auswahl fehlt (F101)').toBeVisible()

    // F101: derselbe Pauschalantrag hat an jeder Produktegruppe seinen Schalter.
    await expect(karte.locator('.pw-pg-pauschale li').last(), 'Ausnahme-Schalter an der Produktegruppe fehlt (F101)').toBeVisible()

    await pauschal.locator('.pw-loeschen').click()
    await expect(kasten.locator('.pw-pauschal-form')).toHaveCount(vorher, { timeout: 20_000 })

    // F103: die Notizen lassen sich an einem (automatisch erzeugten) Antrag öffnen.
    const antrag = karte.locator('.pw-budget-antraege li').first()
    await antrag.waitFor({ state: 'visible', timeout: 15_000 })
    await antrag.locator('.pw-antrag-notizen summary').click()
    await expect(antrag.locator('.pw-antrag-notizen[open]'), 'Notiz-Bereich öffnet nicht (F103)').toBeVisible()

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })

  // Bug 2026-08-28 (F101): der Schalter an der Produktegruppe und die Ausnahmen-
  // Auswahl im Pauschalantrag zeigen dieselbe Liste. Sie glichen sich nur von oben
  // nach unten ab — eine unten aufgehobene Ausnahme blieb oben stehen und wurde
  // beim nächsten Speichern des Formulars wieder zurückgeschrieben.
  test('Ausnahmen gleichen sich in beide Richtungen ab: Produktegruppe und Pauschalantrag (F101)', async ({ page }) => {
    const jsFehler = fehlerWaechter(page)
    await login(page, USERS.u1)
    await gotoBudget(page)
    await sorgeFuerBudgetjahr(page, JAHR)
    await gotoBudget(page)

    // Eigener Pauschalantrag für diesen Test; am Ende wieder gelöscht.
    const kasten = page.locator('.pw-pauschalantraege')
    await kasten.waitFor({ state: 'visible', timeout: 30_000 })
    const vorher = await kasten.locator('.pw-pauschal-form').count()
    await kasten.getByRole('button', { name: '+ Pauschalantrag' }).click()
    await expect(kasten.locator('.pw-pauschal-form'), 'Pauschalantrag wurde nicht angelegt').toHaveCount(vorher + 1, { timeout: 20_000 })
    const form = kasten.locator('.pw-pauschal-form').last()
    // Das Formular führt drei Auswahlen: Ziel, Antragsteller und zuletzt die
    // Ausnahmen (Produktegruppen) — die ist hier gemeint.
    const auswahl = form.locator('.v-select').last()

    const karte = page.locator('.pw-budget-tabpanel .pw-data-card', { has: page.locator('.pw-pg-pauschale') }).first()
    await karte.waitFor({ state: 'visible', timeout: 30_000 })
    const code = (await karte.locator('.pw-data-card-kicker').first().innerText()).replace(/\D+/g, '')
    // Der Schalter des eben angelegten Pauschalantrags: beide Listen iterieren die
    // Pauschalanträge in derselben Reihenfolge, der neue steht also zuunterst.
    const schalter = karte.locator('.pw-pg-pauschale li').last()

    // Unten ausnehmen → oben erscheint die Produktegruppe in den Ausnahmen.
    await schalter.locator('.checkbox-radio-switch__content').click()
    await expect(schalter, 'Produktegruppe unten nicht als ausgenommen markiert (F101)').toContainText('ausgenommen', { timeout: 20_000 })
    await expect(auswahl.locator('.vs__selected').filter({ hasText: code }), 'Unten gesetzte Ausnahme erscheint oben nicht (F101)').toHaveCount(1, { timeout: 20_000 })

    // Unten wieder aufnehmen → oben verschwindet sie.
    await schalter.locator('.checkbox-radio-switch__content').click()
    await expect(schalter, 'Produktegruppe unten weiterhin ausgenommen (F101)').not.toContainText('ausgenommen', { timeout: 20_000 })
    await expect(auswahl.locator('.vs__selected').filter({ hasText: code }), 'Unten aufgehobene Ausnahme bleibt oben stehen (F101)').toHaveCount(0, { timeout: 20_000 })

    // Gegenrichtung: oben ausnehmen → unten steht «ausgenommen».
    await auswahl.locator('.vs__search').click()
    await page.locator('.vs__dropdown-menu li.vs__dropdown-option').filter({ hasText: code }).first().click()
    await expect(auswahl.locator('.vs__selected').filter({ hasText: code }), 'Ausnahme oben nicht übernommen (F101)').toHaveCount(1, { timeout: 20_000 })
    await expect(schalter, 'Oben gesetzte Ausnahme erscheint unten nicht (F101)').toContainText('ausgenommen', { timeout: 20_000 })

    // Aufräumen: der Testantrag verschwindet samt seiner Ausnahmen.
    await form.locator('.pw-loeschen').click()
    await expect(kasten.locator('.pw-pauschal-form'), 'Test-Pauschalantrag nicht gelöscht').toHaveCount(vorher, { timeout: 20_000 })

    expect(jsFehler, `JS-Fehler: ${jsFehler.join(' | ')}`).toEqual([])
  })
})
