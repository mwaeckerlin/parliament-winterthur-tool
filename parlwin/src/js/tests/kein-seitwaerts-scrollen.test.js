import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

/**
 * Nichts geht über den Bildschirm hinaus.
 *
 * Marcs Regel vom 20.10.2026: «Alles was über den Bildschirm hinaus geht, muss
 * auf den Bildschirm limitiert, horizonal eingepasst (z.B. mit Zeilen-Umbruch)
 * und vertikal scrollbar gemacht werden. Für Handy braucht es halt
 * Kompromisse.»
 *
 * Also scrollt kein Behälter seitwärts, und keine Tabelle erzwingt eine
 * Breite, die ein Telefon nicht hat. Was nicht nebeneinander passt, bricht um
 * und macht die Seite höher — die Höhe hat der Bildschirm.
 *
 * Geprüft wird die Quelle des Stylesheets. Wie es am Gerät aussieht, misst der
 * Lauf in `.claude/design-capture.spec.js` («Überlauf messen») und beurteilt
 * der Skill `design-test` am Bild.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')
const FORMULAR = readFileSync('parlwin/src/js/components/BudgetAntragForm.vue', 'utf8')
const LISTE = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')

/** Die Zeilen ausserhalb von `@media print` — auf Papier gelten andere Regeln. */
function amSchirm(css) {
  const zeilen = []
  let tiefe = 0
  let imDruck = false
  let druckTiefe = 0
  for (const zeile of css.split('\n')) {
    if (zeile.includes('@media print')) { imDruck = true; druckTiefe = tiefe }
    if (!imDruck) { zeilen.push(zeile) }
    tiefe += (zeile.match(/{/g) || []).length
    tiefe -= (zeile.match(/}/g) || []).length
    if (imDruck && tiefe <= druckTiefe) { imDruck = false }
  }
  return zeilen.join('\n')
}

describe('Nichts geht über den Bildschirm hinaus', () => {
  const schirm = amSchirm(CSS)

  it('kein Behälter scrollt seitwärts', () => {
    // `overflow-x: auto` sieht wie eine Lösung aus und ist der Fehler: Der
    // Inhalt steht weiterhin ausserhalb des Bildes, nur mit einem Balken
    // darunter. Am Telefon war die Jahresreihe eines Investitionsvorhabens so
    // nach «Plan 2027» abgeschnitten (Bild vom 20.10.2026).
    const treffer = schirm.split('\n').filter(z => /overflow-x:\s*(auto|scroll)/.test(z))
    expect(treffer, `seitwärts scrollende Behälter: ${treffer.join(' | ')}`).toEqual([])
  })

  it('ein hoher Behälter scrollt nur nach unten', () => {
    // Wo eine Höchsthöhe steht, gehört `overflow-y`: `overflow: auto` erlaubt
    // auch die Seitwärtsbewegung.
    const treffer = schirm.split('\n').filter(z => /^\s*overflow:\s*auto/.test(z))
    expect(treffer, `Behälter mit beidseitigem Scrollen: ${treffer.join(' | ')}`).toEqual([])
  })

  it('keine Tabelle erzwingt eine Breite, die ein Telefon nicht hat', () => {
    // 360 Pixel sind 22.5rem. Eine Mindestbreite darüber passt auf kein
    // Telefon, und die Tabelle schiebt sich aus dem Bild.
    const treffer = []
    for (const zeile of schirm.split('\n')) {
      const m = zeile.match(/min-inline-size:\s*([\d.]+)rem/)
      if (m && Number(m[1]) > 22.5) { treffer.push(zeile.trim()) }
    }
    expect(treffer, `Mindestbreiten über Telefonbreite: ${treffer.join(' | ')}`).toEqual([])
  })

  it('der Kopf einer Karte passt in die Karte', () => {
    // Gemessen am 20.10.2026 auf Full HD: Der Kopf einer Investitionskarte war
    // 406 Pixel breit in einer Karte von 380 — Titel und Betrag stehen in einer
    // Zeile, und keines von beiden durfte schmaler werden.
    const block = schirm.slice(schirm.indexOf('.pw-data-card-header,'))
    expect(block.slice(0, 1200), 'der Kopf bricht nicht um').toMatch(/flex-wrap:\s*wrap/)
    expect(block.slice(0, 1200), 'seine Teile können nicht schmaler werden').toMatch(/min-inline-size:\s*0/)
  })

  it('eine Karte bleibt in ihrer Spalte', () => {
    // Ein Rasterelement ist von sich aus so breit, wie sein Inhalt mindestens
    // braucht, und ragt darüber hinaus nach rechts. In der linken Spalte bleibt
    // das im Bild, in der rechten steht es ausserhalb: Marcs Bild vom
    // 24.10.2026 zeigt das offene Antragsformular der letzten Spalte mit
    // abgeschnittenem «Betrag CHF».
    const stelle = schirm.indexOf('.pw-card-grid > *')
    expect(stelle, 'die Rasterelemente dürfen nicht schmaler werden').toBeGreaterThan(-1)
    expect(schirm.slice(stelle, stelle + 200)).toMatch(/min-inline-size:\s*0/)
  })

  it('ein Feld des Antragsformulars darf schmaler werden', () => {
    // Ohne das hält die Mindestbreite des Eingabefelds die Zeile auseinander,
    // die Zeile die Karte und die Karte steht über ihre Spalte hinaus.
    const zeile = FORMULAR.slice(FORMULAR.indexOf('.pw-antrag-form-zeile > *'))
    expect(zeile.slice(0, 120), 'die Felder können nicht schmaler werden').toMatch(/min-inline-size:\s*0/)
  })

  it('wird ein Kopf zur Spalte, verliert die Breitenbasis ihre Wirkung', () => {
    // In Spaltenrichtung ist die Basis einer Flex-Kurzform eine HÖHE. Unter
    // 54rem steht der Kartenkopf als Spalte, und `flex: 1 1 12rem` am Titel gab
    // diesem 192 Pixel Höhe: zwischen «Personalamt» und dem Betrag stand eine
    // leere Fläche über die ganze Kartenbreite (Bild vom 24.10.2026, Tablet
    // hochkant und jedes Telefon).
    // Die Angabe steht in der Komponente und wird dort zurückgenommen: eine
    // Regel im globalen Stylesheet verliert gegen das scoped CSS, und die
    // Kurzform `flex` setzt die Basis ohnehin erneut (gemessen am Bild des
    // zweiten Laufs, in dem die leere Fläche unverändert stand).
    const spalte = schirm.indexOf('@media (max-width: 54rem)')
    expect(spalte, 'der Kopf wird nicht zur Spalte gestellt').toBeGreaterThan(-1)
    expect(schirm.slice(spalte), 'der Kopf steht unter 54rem nicht als Spalte').toMatch(/flex-direction:\s*column/)
    const stelle = LISTE.indexOf('@media (max-width: 54rem)')
    expect(stelle, 'die Breitenbasis wird in der Spalte nicht zurückgesetzt').toBeGreaterThan(-1)
    expect(LISTE.slice(stelle, stelle + 200)).toMatch(/\.pw-budget-kopf-titel\s*{\s*flex-basis:\s*auto/)
  })

  it('am Telefon steht jedes Feld des Antragsformulars auf einer eigenen Zeile', () => {
    // Zu zweit nebeneinander blieb vom Namen des Antragstellers «D⋯ Review»
    // (Bild vom 24.10.2026, phone-large).
    const stelle = FORMULAR.indexOf('@media (max-width: 32rem)')
    expect(stelle, 'das Formular bleibt am Telefon zweispaltig').toBeGreaterThan(-1)
    const block = FORMULAR.slice(stelle, stelle + 300)
    expect(block).toMatch(/flex-direction:\s*column/)
    expect(block, 'die Basis wirkt in der Spalte als Höhe').toMatch(/flex-basis:\s*auto/)
  })

  it('die Navigationsleiste scrollt, wenn ihr Inhalt nicht passt', () => {
    // Menüeinträge und Filter sind auf einem flachen Schirm zusammen höher als
    // die Leiste; ohne diese Regel stand alles ab «Budgetjahr» unerreichbar
    // unter dem unteren Rand (Bild von Marc vom 24.10.2026, Tablet quer).
    const stelle = schirm.indexOf('.app-navigation__list')
    expect(stelle, 'für die Navigationsliste gilt keine eigene Regel').toBeGreaterThan(-1)
    const block = schirm.slice(stelle, stelle + 200)
    expect(block, 'die Liste scrollt nicht nach unten').toMatch(/overflow-y:\s*auto/)
    expect(block, 'die Liste darf nicht schrumpfen, also scrollt sie nie').toMatch(/min-block-size:\s*0/)
    // Eine Flex-Kette gibt die Höhe nur frei, wenn jedes Glied schrumpfen darf.
    const kette = schirm.slice(schirm.indexOf('#app-navigation,'), stelle)
    expect(kette, 'die Leiste selbst darf nicht schrumpfen').toMatch(/min-block-size:\s*0/)
  })

  it('der Spaltentitel bricht um, die Zahl bleibt zusammen', () => {
    // Ohne Umbruch im Titel hilft die fehlende Mindestbreite nichts: «Summe der
    // Jahre» hält die Spalte dann selbst auseinander. Und eine Zahl, die bricht,
    // steht als «400'00 0» da (beides gemessen am 20.10.2026).
    const titel = schirm.slice(schirm.indexOf('.pw-produkt-kosten th {'))
    expect(titel.slice(0, 300), 'der Spaltentitel bricht nicht um').toMatch(/white-space:\s*normal/)
    expect(titel.slice(0, 300), 'der Titel bricht ohne Silbentrennung').toMatch(/hyphens:\s*auto/)
    expect(titel.slice(0, 300), 'der Titel darf mitten im Wort brechen').not.toMatch(/overflow-wrap:\s*break-word/)
    const wert = schirm.slice(schirm.indexOf('Eine Zahl bleibt zusammen'))
    expect(wert.slice(0, 250), 'die Zahl darf umbrechen').toMatch(/white-space:\s*nowrap/)
  })
})
