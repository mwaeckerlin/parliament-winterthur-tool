import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Drucken ist gestaltet.
 *
 * Befund aus dem Design-Test vom 2026-09-11: Die Anwendung hatte kein einziges
 * `@media print`. Auf A4 druckte die Kopfleiste von Nextcloud übereinander,
 * «Weisung 2025.110» wurde abgeschnitten, der blaue Hintergrund kam als
 * Vollton, und nach dem ersten Pauschalantrag brach die Seite ab. Das betrifft
 * jede Ansicht, nicht nur das Budget.
 *
 * Geprüft wird die Quelle des Stylesheets: dass es den Druckteil gibt und was
 * er regelt. Wie der Druck aussieht, beurteilt der Skill `design-test` am
 * gerenderten Papierformat — kein Test kann das.
 *
 * vitest läuft im Projekt-Root.
 */
const CSS = readFileSync('parlwin/src/css/style.scss', 'utf8')

/** Der Inhalt aller `@media print`-Blöcke, mit Klammerzählung statt Regex. */
function druckteil(css) {
  let rest = css
  const teile = []
  for (;;) {
    const start = rest.indexOf('@media print')
    if (start < 0) { break }
    const auf = rest.indexOf('{', start)
    if (auf < 0) { break }
    let tiefe = 0
    let ende = auf
    for (let i = auf; i < rest.length; i += 1) {
      if (rest[i] === '{') { tiefe += 1 }
      if (rest[i] === '}') {
        tiefe -= 1
        if (tiefe === 0) { ende = i; break }
      }
    }
    teile.push(rest.slice(auf + 1, ende))
    rest = rest.slice(ende + 1)
  }
  return teile.join('\n')
}

describe('Drucklayout', () => {
  const druck = druckteil(CSS)

  it('es gibt einen Druckteil', () => {
    expect(druck.trim(), 'Kein @media print im Stylesheet').not.toBe('')
  })

  it('die Bedienelemente der Anwendung stehen nicht auf dem Papier', () => {
    // Filter, Werkzeugleisten und Knöpfe sind auf Papier sinnlos und nehmen
    // den Platz des Inhalts.
    expect(druck).toMatch(/display:\s*none/)
    expect(druck).toMatch(/pw-filter|pw-werkzeug|pw-aktionen/)
  })

  it('Flächen drucken ohne Vollton-Hintergrund', () => {
    // Der blaue Hintergrund kam als Vollton aus dem Drucker: viel Farbe, und
    // der Text darauf schlecht lesbar.
    expect(druck).toMatch(/background(?:-color)?:\s*(?:transparent|none|var\(--color-main-background[^)]*\))/)
  })

  it('eine Tabelle und eine Karte brechen nicht mitten im Eintrag', () => {
    expect(druck).toMatch(/break-inside:\s*avoid/)
  })

  it('ein offenes Popup druckt als Inhalt, nicht als graues Blatt', () => {
    // Das Detail eines Investitionsvorhabens druckte auf A4 dreimal dasselbe
    // Blatt: die Abdeckung des Popups als dunkelgraue Fläche über die ganze
    // Seite, darin das Fenster. Was fest positioniert ist, steht auf jeder
    // Seite erneut (Bild vom 20.10.2026).
    const regel = druck.slice(druck.indexOf('.pw-modal-overlay'))
    expect(druck, 'der Druckteil kennt die Abdeckung des Popups nicht').toContain('.pw-modal-overlay')
    expect(regel.slice(0, 600), 'die Abdeckung bleibt fest positioniert').toMatch(/position:\s*static/)
    expect(regel.slice(0, 600), 'die Abdeckung druckt ihre Fläche').toMatch(/background:\s*(none|transparent)/)
  })

  it('bei offenem Popup druckt nur das Popup', () => {
    // Das A4 des geöffneten Details trug 190 Seiten: die ganze Liste der 567
    // Investitionsvorhaben, und das Detail irgendwo darin. Wer ein Detail
    // ansieht und druckt, will dieses Detail (Bild vom 20.10.2026).
    expect(druck, 'die Seite hinter dem Popup druckt mit')
      .toMatch(/:has\(\.pw-modal-overlay\)/)
  })

  it('eine Tabelle mit Mindestbreite passt auf das Papier', () => {
    // Am Schirm scrollt eine breite Tabelle in ihrem Behälter, und die
    // Mindestbreite hält die Spalten auseinander. Auf Papier scrollt nichts:
    // Auf A5 lief die Jahresreihe rechts aus dem Blatt und «Summe der Jahre»
    // war abgeschnitten (Bild vom 20.10.2026).
    const regel = druck.slice(druck.indexOf('.pw-zv-scroll'))
    expect(druck, 'der Druckteil kennt den Scroll-Behälter nicht').toContain('.pw-zv-scroll')
    expect(regel.slice(0, 400), 'die Mindestbreite gilt auch auf Papier').toMatch(/min-inline-size:\s*0/)
  })

  it('die Kachelanordnung wird auf Papier zur Spalte', () => {
    // Befund vom 16.10.2026 am gedruckten A4: Die Karte «Budget 2018» brach
    // mitten durch, obwohl `break-inside: avoid` auf ihr steht. In einem
    // Raster greift die Regel nicht; erst als Block hält sie.
    expect(druck, 'pw-card-grid druckt weiter als Raster').toMatch(/pw-card-grid[\s\S]{0,200}display:\s*block/)
  })

  it('leere Eingabefelder drucken keinen Rahmen', () => {
    // Auf dem Papier stand unter «Priorität» ein leerer Kasten: Am Schirm ist
    // das ein Auswahlfeld, auf Papier zählt nur sein Wert (Befund vom
    // 16.10.2026).
    expect(druck, 'keine Regel für input, select und textarea auf Papier')
      .toMatch(/input,[\s\S]{0,120}select,[\s\S]{0,120}textarea[\s\S]{0,200}border:\s*(?:0|none)/)
  })

  it('das Blatt ist weiss', () => {
    // Der blaue Hintergrund von Nextcloud lief rechts und unten über das
    // Blatt. Ihn auf durchsichtig zu setzen machte es schlimmer: Danach war
    // die ganze Seite blau, weil die weisse Fläche des Inhalts ihn vorher
    // verdeckt hatte. Auf Papier steht dort Weiss (Befund vom 16.10.2026).
    const regel = druck.slice(druck.indexOf('#body-user'))
    expect(druck, 'die Behälter von Nextcloud stehen nicht in der Regel').toMatch(/#body-user/)
    // Die Farbe kommt aus der Variablen von Nextcloud, nie als Farbcode
    // (css-farben-variablen.test.js).
    expect(regel.slice(0, 500), 'die Behälter drucken nicht weiss')
      .toMatch(/background:\s*var\(--color-main-background/)
  })

  it('der Behälter der Ansicht ist auf Papier Blockfluss', () => {
    // In einem Flex-Behälter übergeht Chromium `break-inside: avoid`, und
    // die Karte «Budget 2018» brach weiter mitten durch, obwohl die Regel auf
    // ihr stand und die Kachelanordnung schon Block war (Befund vom
    // 16.10.2026).
    expect(druck, 'pw-view-content druckt weiter als Flex')
      .toMatch(/pw-view-content[\s\S]{0,200}display:\s*block/)
  })

  it('nichts über dem Inhalt ist fest positioniert', () => {
    // Gemessen am 16.10.2026 im Druck: Über den Karten standen
    // `div.content` als `position: fixed` mit `overflow: hidden` und `body`
    // ebenso fest. Was fest positioniert ist, nimmt am Seitenumbruch nicht
    // teil, also konnte keine Karte auf die nächste Seite rücken, und
    // `break-inside: avoid` blieb wirkungslos.
    expect(druck, 'keine Regel setzt body und .content zurück in den Fluss')
      .toMatch(/\.app-parlwin[\s\S]{0,300}position:\s*static/)
  })

  it('eine Karte darf auf Papier überlaufen', () => {
    // `break-inside: avoid` greift nicht, solange die Karte ihren Inhalt
    // abschneidet: Die Karte «Budget 2018» brach trotzdem mitten durch
    // (Befund vom 16.10.2026).
    expect(druck, 'die Karten behalten ihr overflow vom Schirm')
      .toMatch(/pw-data-card[\s\S]{0,300}overflow:\s*visible/)
  })

  it('das Papierformat und seine Ränder stehen in einer Regel', () => {
    expect(CSS).toMatch(/@page\s*\{[^}]*margin/)
  })
})
