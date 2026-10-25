// Nimmt die Ansichten von parlwin über alle Formate auf, für die Beurteilung
// am Bild (Skill `design-test`).
//
// Warum nicht der webdesign-scanner: Dessen Ablauf kommt an der Anmeldung von
// Nextcloud nicht vorbei — gemessen am 2026-10-16 über sieben Läufe. Das Konto
// gilt (an der Schnittstelle der Instanz geprüft), das Kennwort kommt im Feld
// Zeichen für Zeichen richtig an (im sichtbaren Feld gelesen), und die Seite
// antwortet trotzdem «Wrong login or password». Die Browsertests des Projekts
// melden sich in derselben Instanz zuverlässig an, darum nehmen sie die Bilder
// auf; der Skill lässt das ausdrücklich zu.
//
// Jedes Format bekommt einen eigenen Kontext und eine eigene Seite: Über eine
// einzige Seite brach der Lauf am 2026-10-16 nach 51 Bildern mit «Target page,
// context or browser has been closed» ab, und die Telefonformate und das
// Papier fehlten. Die Anmeldung läuft einmal und wird als Sitzungsstand
// weitergereicht.
//
// Diese Datei gehört NICHT in die Suite: Sie läuft in einem eigenen Image,
// gebaut aus dem Playwright-Image des Projekts (.claude/design-scan.sh).

import { test, expect } from '@playwright/test'
import { mkdirSync } from 'node:fs'

const BASE_URL = process.env.PARLWIN_BASE_URL || 'http://nextcloud-nginx:8080'
const AUS = process.env.PW_DESIGN_OUT || '/work/bilder'
const NUTZER = process.env.PW_DESIGN_USER || 'design-review'
const KENNWORT = process.env.PW_DESIGN_PASS || ''
const SITZUNG = `${AUS}/sitzung.json`

// Der Katalog des webdesign-scanner, dazu die Teilbreiten, an denen ein
// Layout zuerst bricht.
const FORMATE = [
  ['desktop-4-3', 1024, 768],
  ['hd', 1280, 720],
  ['desktop-16-10', 1440, 900],
  ['desktop-16-9', 1600, 900],
  ['full-hd', 1920, 1080],
  ['full-hd-half', 960, 1080],
  ['desktop-21-9-fhd', 2560, 1080],
  ['desktop-21-9', 3440, 1440],
  ['desktop-21-9-third', 1146, 1440],
  ['desktop-21-9-half', 1720, 1440],
  ['uhd', 3840, 2160],
  ['tablet-portrait', 768, 1024],
  ['tablet-landscape', 1024, 768],
  ['phone-small', 360, 640],
  ['phone-medium', 390, 844],
  ['phone-large', 430, 932],
  ['phone-landscape', 844, 390],
]

// Die Ansichten, um die es in den Befunden geht: der Name des Eintrags in der
// Seitenleiste und ein Element, das nur diese Ansicht hat.
const ANSICHTEN = [
  ['geschaefte', 'Geschäfte', '.pw-geschaefte'],
  ['vorstoesse', 'Vorstösse', '.pw-vorstoesse'],
  ['kommissionen', 'Kommissionen', '.pw-kommissionen'],
  ['mitglieder', 'Mitglieder', '.pw-mitglieder'],
  // F123: die Grafik in beiden Massen. Der vierte Eintrag sagt, was nach dem
  // Öffnen der Ansicht noch zu tun ist.
  ['budget-grafik', 'Budget', '.pw-budget-grafik', async (page) => {
    await page.getByRole('tab', { name: 'Grafik' }).click()
    await page.waitForTimeout(2500)
  }],
  ['budget-stellen', 'Budget', '.pw-budget-grafik', async (page) => {
    await page.getByRole('tab', { name: 'Grafik' }).click()
    await page.locator('.pw-grafik-mass button', { hasText: 'Stellen' }).click()
    await page.waitForTimeout(2500)
  }],
  // F138: der Tab «Anträge» mit seiner Kopfzeile — der PDF-Knopf und die
  // beiden Wege der Tabelle («Excel in die Dateien», «Excel herunterladen»).
  // Der Tab heisst nach der Zahl der Anträge, darum über das Muster.
  ['budget-antraege', 'Budget', '.pw-antraege-kopf', async (page) => {
    await page.getByRole('tab', { name: /Anträge$/ }).click()
    await page.waitForTimeout(1200)
  }],
  // F87: die Karten der Investitionsrechnung mit «Summe der Jahre» und
  // «Bewilligter Kredit», und das Detail eines Vorhabens mit der Jahresreihe
  // und der Kontotabelle samt Total.
  // Die Tabs liegen alle im Dokument und werden nur ein- und ausgeblendet, also
  // trifft «.pw-data-card» 669 Karten, davon die erste unsichtbar. Gewartet wird
  // auf eine Karte DIESES Tabs.
  ['budget-investition', 'Budget', '.pw-budget-investitionen .pw-data-card', async (page) => {
    await page.getByRole('tab', { name: 'Investitionsrechnung' }).click()
    await page.waitForTimeout(1200)
  }],
  // Befund von Marc am 24.10.2026: Das Antragsformular läuft rechts aus dem
  // Bild, «Betrag CHF» ist abgeschnitten. Ein Zustand, der sich öffnen lässt,
  // wird nur gemessen, wenn der Lauf ihn öffnet — die Messung vom 20.10.2026
  // fand nichts, weil sie das Formular nie aufmachte.
  ['budget-antragsform', 'Budget', '.pw-antrag-form', async (page) => {
    await page.locator('.pw-budget-card-aktionen button', { hasText: '+ Antrag' }).first().click()
    await page.waitForTimeout(800)
  }],
  ['budget-antragsform-ziele', 'Budget', '.pw-antrag-ziele[open]', async (page) => {
    await page.locator('.pw-budget-card-aktionen button', { hasText: '+ Antrag' }).first().click()
    await page.waitForTimeout(800)
    await page.locator('.pw-antrag-ziele > summary').first().click()
    await page.waitForTimeout(500)
  }],
  ['budget-investition-detail', 'Budget', '.pw-pg-detail', async (page) => {
    await page.getByRole('tab', { name: 'Investitionsrechnung' }).click()
    await page.waitForTimeout(1200)
    await page.locator('.pw-budget-investitionen .pw-pg-detail-knopf').first().click()
    await page.waitForTimeout(800)
  }],
]

const PAPIERE = [['A4', false], ['A4-quer', true], ['A3', false], ['A5', false], ['Letter', false]]

async function anmelden(page) {
  await page.goto(`${BASE_URL}/index.php/login`)
  await page.waitForSelector('input[name="user"]', { state: 'visible', timeout: 30_000 })
  await page.fill('input[name="user"]', NUTZER)
  await page.fill('input[name="password"]', KENNWORT)
  for (let versuch = 0; versuch < 3; versuch++) {
    void page.click('button[type="submit"], input[type="submit"]', { noWaitAfter: true }).catch(() => {})
    try {
      await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 12_000, waitUntil: 'commit' })
      await page.waitForLoadState('domcontentloaded').catch(() => {})
      return
    } catch { /* nächster Versuch */ }
  }
  throw new Error('Anmeldung nicht möglich')
}

/**
 * Klappt die Navigationsleiste zu, solange sie über dem Inhalt liegt.
 *
 * Nextcloud legt sie auf schmalen Fenstern über den Inhalt und lässt ihn
 * durchscheinen; wer die Ansicht gewählt hat, sieht dann beides übereinander.
 * Gemessen wird die Überdeckung selbst, damit kein Grenzwert geraten wird.
 * Der Griff heisst «Open navigation», also klein geschrieben.
 */
async function navigationSchliessen(page) {
  const ueberdeckung = () => page.evaluate(() => {
    const nav = document.querySelector('#app-navigation, #app-navigation-vue, .app-navigation')
    const inhalt = document.querySelector('#app-content, #app-content-vue, .app-content')
    if (!nav || !inhalt) return false
    const n = nav.getBoundingClientRect()
    const i = inhalt.getBoundingClientRect()
    return n.width > 0 && n.right > i.left + 1
  })
  // Der Klick geht direkt an den Knopf: Playwright wartet sonst auf
  // Sichtbarkeit und Ruhe, und die Leiste bewegt sich gerade — drei Versuche
  // mal fünf Sekunden Wartezeit je Ansicht brachten die Aufnahme über ihre
  // Zeit (gemessen am 24.10.2026).
  for (let versuch = 0; versuch < 3; versuch++) {
    if (!(await ueberdeckung())) { return }
    await page.evaluate(() => {
      const knopf = [...document.querySelectorAll('button')]
        .find((b) => /navigation/i.test(b.getAttribute('aria-label') || ''))
      if (knopf) { knopf.click() }
    })
    await page.waitForTimeout(400)
  }
}

async function ansichtOeffnen(page, eintrag, warten, danach) {
  // Die Anwendung wechselt die Ansicht über die Seitenleiste; eine Adresse mit
  // Anker ist kein Weg dorthin. Am 2026-10-16 zeigten darum alle 51 Bilder
  // eines Laufs die Geschäfte, auch die unter dem Namen «kommissionen». Die
  // Browsertests des Projekts klicken den Eintrag, und das tut dieser Lauf
  // auch. Auf schmalen Formaten liegt die Seitenleiste hinter ihrem Knopf.
  await page.goto(`${BASE_URL}/index.php/apps/parlwin/`)
  await page.waitForLoadState('networkidle').catch(() => {})
  const link = page.getByRole('link', { name: eintrag, exact: true })
  // Derselbe Griff, den die Browsertests des Projekts nehmen: Nextcloud nennt
  // ihn «Open navigation» — klein geschrieben, darum ohne Rücksicht auf Gross
  // und Klein. Mit dem alten Treffer auf «Navigation» traf der Klick nichts,
  // die Leiste blieb über dem Inhalt, und die Aufnahme lief in ihre Zeit
  // (gemessen am 24.10.2026).
  const schalter = page.getByRole('button', { name: /navigation/i }).first()
  if (!(await link.isVisible().catch(() => false))) {
    await schalter.click().catch(() => {})
  }
  await link.click()
  await navigationSchliessen(page)
  if (danach) {
    await danach(page)
  }
  // Ohne Auffangen: Ein Bild der falschen Ansicht ist schlimmer als ein
  // abgebrochener Lauf, weil es wie ein Befund aussieht.
  await page.waitForSelector(warten, { state: 'visible', timeout: 30_000 })
  await page.waitForLoadState('networkidle').catch(() => {})
  await page.waitForTimeout(600)
}

test.describe.configure({ mode: 'serial' })

test('anmelden', async ({ browser }) => {
  test.setTimeout(120_000)
  mkdirSync(AUS, { recursive: true })
  const kontext = await browser.newContext()
  const page = await kontext.newPage()
  await anmelden(page)
  await kontext.storageState({ path: SITZUNG })
  await kontext.close()
})

// Die Ansicht wird breit geöffnet und erst danach auf das Format verkleinert.
// Auf schmalen Formaten liegt die Navigationsleiste von Nextcloud über dem
// Inhalt; jeder Klick darin ging an die Leiste, und die Aufnahme lief in ihre
// Zeit, statt Bilder zu liefern (gemessen am 24.10.2026). Fotografiert wird
// derselbe Zustand: Die Seite rechnet ihre Aufteilung beim Verkleinern neu,
// und die Leiste steht dann so, wie sie der Anwender nach seiner Wahl sieht.
const BREIT = { width: 1920, height: 1080 }

for (const [format, breite, hoehe] of FORMATE) {
  test(`Format ${format}`, async ({ browser }) => {
    // Neun Ansichten, jede einmal breit geöffnet und einmal verkleinert: Mit
    // drei Minuten je Format lief die Aufnahme am 24.10.2026 in ihre Zeit.
    test.setTimeout(300_000)
    const kontext = await browser.newContext({ storageState: SITZUNG, viewport: BREIT })
    const page = await kontext.newPage()
    for (const [name, eintrag, warten, danach] of ANSICHTEN) {
      await page.setViewportSize(BREIT)
      await ansichtOeffnen(page, eintrag, warten, danach)
      await page.setViewportSize({ width: breite, height: hoehe })
      await page.waitForTimeout(800)
      // Beim Verkleinern legt Nextcloud die Leiste über den Inhalt, ohne sie
      // zuzuklappen: Das Bild zeigte beides übereinander (24.10.2026).
      await navigationSchliessen(page)
      await page.screenshot({ path: `${AUS}/${format}--${name}.png`, fullPage: true })
    }
    await kontext.close()
  })
}

/**
 * Die Kästen der Seitenaufteilung, Format für Format: Wo steht die
 * Navigationsleiste, wo der Inhalt, wie breit ist jeder. Am 24.10.2026 war der
 * Inhalt bei 1024×768 ein Drittel breit und lag unter der Navigationsleiste;
 * am Bild war die Ursache nicht zu sehen, an diesen Zahlen schon.
 */
test('Kästen messen', async ({ browser }) => {
  test.setTimeout(600_000)
  const WAHL = ['desktop-4-3', 'hd', 'full-hd', 'tablet-landscape']
  for (const [format, breite, hoehe] of FORMATE.filter(f => WAHL.includes(f[0]))) {
    const kontext = await browser.newContext({ storageState: SITZUNG, viewport: { width: breite, height: hoehe } })
    const page = await kontext.newPage()
    await ansichtOeffnen(page, ...ANSICHTEN[0].slice(1))
    const kaesten = await page.evaluate(() => {
      const wahl = ['#header', '#content-vue', '#content', '#app-navigation-vue', '#app-navigation',
        '#app-content-vue', '#app-content', '.pw-view', '.pw-geschaefte', '.pw-table-wrap',
        // Der Griff von Nextcloud und unser Vollbild-Knopf daneben: Beide
        // müssen in Grösse, Fläche und Lage dasselbe sein (Marc, 24.10.2026).
        '.app-navigation-toggle', 'button[aria-label="Open navigation"]',
        '.pw-vollbild-knopf', '.pw-view-header']
      const aus = []
      for (const auswahl of wahl) {
        const el = document.querySelector(auswahl)
        if (!el) { aus.push(`${auswahl}: fehlt`); continue }
        const r = el.getBoundingClientRect()
        const s = getComputedStyle(el)
        aus.push(`${auswahl}: x=${Math.round(r.x)} y=${Math.round(r.y)} b=${Math.round(r.width)} h=${Math.round(r.height)}`
          + ` display=${s.display} position=${s.position} margin=${s.margin} width=${s.width}`)
      }
      aus.push('--body-container-margin=' + getComputedStyle(document.body).getPropertyValue('--body-container-margin'))
      return aus
    })
    process.stderr.write(`[kasten] ===== ${format} ${breite}×${hoehe} =====\n`)
    for (const zeile of kaesten) { process.stderr.write(`[kasten] ${zeile}\n`) }
    await kontext.close()
  }
  expect(true).toBe(true)
})

test('Überlauf messen', async ({ browser }) => {
  // Marcs Regel vom 20.10.2026: Nichts geht über den Bildschirm hinaus. Was
  // breiter ist als sein Platz, wird hier genannt — Element, Ansicht, Format
  // und die beiden Breiten, damit die Ursache ohne Raten zu finden ist.
  // Die schmalen Formate entscheiden es; ein Element, das auf 1920 hinausläuft,
  // läuft auf 360 erst recht hinaus. Der Lauf über alle 18 Formate brauchte
  // länger als seine zehn Minuten und gab darum gar nichts aus (2026-10-20).
  const ENG = new Set(['phone-small', 'phone-medium', 'phone-large', 'phone-landscape', 'tablet-portrait', 'tablet-landscape', 'desktop-4-3', 'full-hd-half', 'full-hd'])
  test.setTimeout(1_800_000)
  const befunde = []
  for (const [format, breite, hoehe] of FORMATE.filter(f => ENG.has(f[0]))) {
    const kontext = await browser.newContext({ storageState: SITZUNG, viewport: BREIT })
    const page = await kontext.newPage()
    for (const [name, eintrag, warten, danach] of ANSICHTEN) {
      // Breit öffnen, schmal messen: siehe den Kommentar bei den Formaten.
      await page.setViewportSize(BREIT)
      await ansichtOeffnen(page, eintrag, warten, danach)
      await page.setViewportSize({ width: breite, height: hoehe })
      await page.waitForTimeout(800)
      await navigationSchliessen(page)
      const treffer = await page.evaluate((sicht) => {
        const raus = []
        if (document.documentElement.scrollWidth > sicht + 1) {
          raus.push(`DOKUMENT scrollWidth=${document.documentElement.scrollWidth} sichtbar=${sicht}`)
        }
        for (const el of document.querySelectorAll('body *')) {
          const r = el.getBoundingClientRect()
          if (r.width === 0 || r.height === 0) { continue }
          // Was links neben dem Bild geparkt ist, läuft nicht hinaus: die
          // unsichtbaren Beschriftungen von Nextcloud und die zugeklappte
          // Navigation. Sie füllten die erste Messung (2026-10-20).
          if (r.right <= 0) { continue }
          // Ein seitwärts scrollender Behälter IST der Fehler, den Marc am
          // 20.10.2026 verboten hat — gleich ob er `auto` oder `scroll` sagt.
          const eigen = el.scrollWidth > el.clientWidth + 1
          const breiter = r.right > sicht + 1
          // Ein Element, das über SEINEN BEHÄLTER hinausragt, läuft ebenso
          // hinaus — im Kartenraster sieht man es aber nur in der rechten
          // Spalte am Bildrand. Marcs Bild vom 24.10.2026 zeigt genau das:
          // Die Karte mit dem offenen Antragsformular ist breiter als ihre
          // Spalte, und in der letzten Spalte ist «Betrag CHF» abgeschnitten.
          // Die Messung vom 20.10.2026 prüfte nur den Bildrand und fand
          // dieselbe Karte in der linken Spalte darum in Ordnung.
          const eltern = el.parentElement
          const pr = eltern ? eltern.getBoundingClientRect() : null
          const ueberEltern = pr !== null && pr.width > 0
            && (r.right > pr.right + 1 || r.left < pr.left - 1)
            && eltern.scrollWidth <= eltern.clientWidth + 1
          if (!eigen && !breiter && !ueberEltern) { continue }
          const kenn = `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ').slice(0, 2).join('.')}`
          const behaelter = pr !== null ? ` in [${Math.round(pr.left)}..${Math.round(pr.right)}]` : ''
          raus.push(`${kenn} [${Math.round(r.left)}..${Math.round(r.right)}]${behaelter} scroll=${el.scrollWidth}/${el.clientWidth}`)
        }
        return raus.slice(0, 20)
      }, breite)
      for (const t of treffer) { befunde.push(`${format} ${name}: ${t}`) }
      // Je Ansicht ausgeben: Bricht der Lauf ab, steht das Gemessene trotzdem da.
      // eslint-disable-next-line no-console
      console.log(`[ueberlauf] ${format} ${name}: ${treffer.length ? '\n  ' + treffer.join('\n  ') : 'nichts'}`)
    }
    await kontext.close()
  }
  // eslint-disable-next-line no-console
  console.log('[ueberlauf]\n' + (befunde.join('\n') || 'nichts läuft über'))
})

test('Druck messen', async ({ browser }) => {
  // Was im Druck wirklich gilt, steht hier: Ohne diese Messung kostet jede
  // Vermutung über eine Regel einen ganzen Lauf.
  test.setTimeout(120_000)
  const kontext = await browser.newContext({ storageState: SITZUNG, viewport: { width: 1920, height: 1080 } })
  const page = await kontext.newPage()
  await ansichtOeffnen(page, 'Geschäfte', '.pw-geschaefte')
  await page.emulateMedia({ media: 'print' })
  const messung = await page.evaluate(() => {
    const lies = (el, felder) => {
      if (!el) return null
      const s = getComputedStyle(el)
      return Object.fromEntries(felder.map(f => [f, s[f]]))
    }
    const kasten = ['borderTopWidth', 'borderTopStyle', 'borderTopColor', 'backgroundColor', 'minHeight', 'boxShadow']
    const fluss = ['display', 'overflow', 'breakInside']
    return {
      toggle: lies(document.querySelector('.pw-card-prio .vs__dropdown-toggle'), kasten),
      vselect: lies(document.querySelector('.pw-card-prio .v-select'), kasten),
      kartePrio: lies(document.querySelector('.pw-card-prio'), kasten),
      karte: lies(document.querySelector('.pw-data-card'), fluss),
      raster: lies(document.querySelector('.pw-card-grid'), fluss),
      inhalt: lies(document.querySelector('.pw-view-content'), fluss),
      appContent: lies(document.querySelector('#app-content-vue, #app-content'), fluss),
      content: lies(document.querySelector('#content-vue, #content'), fluss),
      body: lies(document.body, fluss),
      // Wer zeichnet den Strich am rechten Blattrand? Alles, was im Druck
      // rechts aussen eine Farbe oder einen Rahmen hat, nennt sich hier.
      rechterRand: [...document.querySelectorAll('*')].filter((el) => {
        const r = el.getBoundingClientRect()
        if (r.right < window.innerWidth - 60 || r.width === 0) return false
        const s = getComputedStyle(el)
        const farbig = s.backgroundColor !== 'rgba(0, 0, 0, 0)' && s.backgroundColor !== 'transparent'
        const rahmen = parseFloat(s.borderRightWidth) > 0 || parseFloat(s.borderLeftWidth) > 0
        return farbig || rahmen || s.boxShadow !== 'none'
      }).slice(0, 12).map((el) => {
        const s = getComputedStyle(el)
        const r = el.getBoundingClientRect()
        return `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ').slice(0, 2).join('.')} [${Math.round(r.left)}..${Math.round(r.right)}] bg=${s.backgroundColor} br=${s.borderRightWidth} ${s.borderRightColor} shadow=${s.boxShadow.slice(0, 40)}`
      }),
      // Was die Lücke unter «PRIORITÄT» hoch macht.
      prioHoehe: (() => {
        const el = document.querySelector('.pw-card-prio')
        if (!el) return null
        return [...el.querySelectorAll('*')].slice(0, 8).map((k) => {
          const s = getComputedStyle(k)
          return `${k.tagName.toLowerCase()}.${(k.className || '').toString().split(' ')[0]} h=${Math.round(k.getBoundingClientRect().height)} minH=${s.minHeight} pad=${s.padding}`
        })
      })(),
      kartenhoehen: [...document.querySelectorAll('.pw-data-card')].slice(0, 4).map(k => Math.round(k.getBoundingClientRect().height)),
      vorfahren: (() => {
        const kette = []
        let el = document.querySelector('.pw-data-card')
        while (el && el !== document.documentElement) {
          const s = getComputedStyle(el)
          kette.push(`${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}: ${s.display}/${s.overflow}/${s.position}/${s.contain}`)
          el = el.parentElement
        }
        return kette
      })(),
    }
  })
  await page.emulateMedia({ media: null })
  // eslint-disable-next-line no-console
  console.log('[messung]', JSON.stringify(messung, null, 1))
  await kontext.close()
})

test('Papier', async ({ browser }) => {
  test.setTimeout(300_000)
  const kontext = await browser.newContext({
    storageState: SITZUNG,
    viewport: { width: 1920, height: 1080 },
  })
  const page = await kontext.newPage()
  for (const [name, eintrag, warten, danach] of ANSICHTEN) {
    await ansichtOeffnen(page, eintrag, warten, danach)
    for (const [papier, quer] of PAPIERE) {
      await page.pdf({
        path: `${AUS}/papier-${papier}--${name}.pdf`,
        format: papier.replace('-quer', ''),
        landscape: quer,
        printBackground: true,
        margin: { top: '10mm', bottom: '10mm', left: '10mm', right: '10mm' },
      })
    }
  }
  await kontext.close()
  expect(true).toBe(true)
})
