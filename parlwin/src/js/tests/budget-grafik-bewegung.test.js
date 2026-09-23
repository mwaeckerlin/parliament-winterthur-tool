import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetGrafik from '../components/BudgetGrafik.vue'

/**
 * Wie die Kreise zusammenfinden — die Bewegung selbst, nicht ihr Ergebnis.
 *
 * Marcs Anforderung (2026-08-31): «Ca. 2-5s wären gut, und alles kommt von weit
 * zusammen.» Gemessen wird deshalb beides: die Strecke, die ein Kreis vom
 * Startpunkt bis an seinen Platz zurücklegt, und wie lange er dafür braucht.
 * Ein Tick der Simulation ist ein Bild; bei 60 Bildern je Sekunde sind 60 Ticks
 * eine Sekunde.
 */
const BILDER_JE_SEKUNDE = 60

const GRUPPEN = [
  { code: '110', name: 'Stadtkanzlei', departement: 'Präsidiales', aufwand: { soll: 12_000_000 }, ertrag: { soll: 2_000_000 }, produkte: [] },
  { code: '121', name: 'Personalamt', departement: 'Präsidiales', aufwand: { soll: 7_400_000 }, ertrag: { soll: 2_500_000 }, produkte: [] },
  { code: '210', name: 'Steueramt', departement: 'Finanzen', aufwand: { soll: 9_000_000 }, ertrag: { soll: 40_000_000 }, produkte: [] },
  { code: '322', name: 'Tiefbau', departement: 'Bau', aufwand: { soll: 30_000_000 }, ertrag: { soll: 6_000_000 }, produkte: [] },
  { code: '420', name: 'Stadtpolizei', departement: 'Sicherheit', aufwand: { soll: 22_000_000 }, ertrag: { soll: 3_000_000 }, produkte: [] },
  { code: '510', name: 'Schulen', departement: 'Schule und Sport', aufwand: { soll: 55_000_000 }, ertrag: { soll: 4_000_000 }, produkte: [] },
  { code: '610', name: 'Soziales', departement: 'Soziales', aufwand: { soll: 18_000_000 }, ertrag: { soll: 5_000_000 }, produkte: [] },
  { code: '720', name: 'Beleuchtung', departement: 'Technische Betriebe', aufwand: { soll: 4_000_000 }, ertrag: { soll: 1_000_000 }, produkte: [] },
  { code: '770', name: 'Stadtgrün', departement: 'Technische Betriebe', aufwand: { soll: 14_000_000 }, ertrag: { soll: 2_000_000 }, produkte: [] },
  { code: '810', name: 'Parlamentsdienste', departement: 'Behörden', aufwand: { soll: 6_000_000 }, ertrag: { soll: 1_000_000 }, produkte: [] },
]

/** Die Kreise der aktuellen Ebene einer Seite, mit Position und Ziel. */
function kreise(w, seite = 'Ausgaben') {
  const flaeche = w.vm.seiten.find(s => s.name === seite)
  return flaeche.kreise.filter(k => k.ebene === 1)
}

describe('BudgetGrafik — die Reise der Kreise', () => {
  const montiere = () => mount(BudgetGrafik, { props: { produktegruppen: GRUPPEN } })

  it('startet weit weg und braucht mehrere Sekunden bis zur Ruhe', () => {
    const w = montiere()
    w.vm.neuAnordnen()
    w.vm.simulationSchritte(0)

    const start = kreise(w).map(k => ({ name: k.knoten.data.name, x: k.x, y: k.y, zielX: k.zielX, zielY: k.zielY, r: k.r }))
    const seite = 1000 // die Zeichenfläche der Grafik, in ihren eigenen Einheiten

    // «Alles kommt von weit zusammen»: Jeder Kreis startet mindestens eine
    // Viertelfläche von seinem Platz entfernt, im Mittel deutlich mehr.
    const entfernungen = start.map(k => Math.hypot(k.x - k.zielX, k.y - k.zielY))
    const mittel = entfernungen.reduce((a, b) => a + b, 0) / entfernungen.length
    expect(mittel, `Die Kreise starten im Mittel nur ${mittel.toFixed(0)} von ${seite} entfernt`)
      .toBeGreaterThan(seite * 0.25)

    // Und sie brauchen dafür Zeit: Nach einer Sekunde ist die Reise noch im
    // Gang, nach fünf Sekunden ist sie vorbei.
    const amPlatz = () => kreise(w).every(k => Math.hypot(k.x - k.zielX, k.y - k.zielY) < k.r * 0.5)
    w.vm.simulationSchritte(BILDER_JE_SEKUNDE)
    expect(amPlatz(), 'Nach einer Sekunde stehen schon alle Kreise an ihrem Platz').toBe(false)

    w.vm.simulationSchritte(BILDER_JE_SEKUNDE * 4)
    expect(amPlatz(), 'Nach fünf Sekunden stehen die Kreise noch nicht an ihrem Platz').toBe(true)
  })

  it('bewegt sich zwei bis fünf Sekunden lang sichtbar', () => {
    const w = montiere()
    w.vm.neuAnordnen()
    w.vm.simulationSchritte(0)
    const seite = 1000 // die Zeichenfläche der Grafik, in ihren eigenen Einheiten

    // Gemessen wird, was das Auge sieht: die letzte Sekunde, in der sich ein
    // Kreis noch um mehr als ein Hundertstel der Fläche verschiebt. Das Kriterium
    // «steht auf seinem Platz» taugt dafür nicht — bei einem grossen Kreis ist
    // sein halber Radius eine weite Strecke, und er gilt als angekommen, während
    // er noch sichtbar wandert.
    let vorher = kreise(w).map(k => ({ x: k.x, y: k.y }))
    let letzteBewegteSekunde = 0
    for (let sekunde = 1; sekunde <= 15; sekunde++) {
      w.vm.simulationSchritte(BILDER_JE_SEKUNDE)
      const jetzt = kreise(w).map(k => ({ x: k.x, y: k.y }))
      const weg = Math.max(...jetzt.map((k, i) => Math.hypot(k.x - vorher[i].x, k.y - vorher[i].y)))
      if (weg > seite * 0.01) { letzteBewegteSekunde = sekunde }
      vorher = jetzt
    }
    expect(letzteBewegteSekunde, `Die Bewegung dauert ${letzteBewegteSekunde}s`).toBeGreaterThanOrEqual(2)
    expect(letzteBewegteSekunde, `Die Bewegung dauert ${letzteBewegteSekunde}s`).toBeLessThanOrEqual(5)
  })

  // Bug 2026-08-31: Beim Überfahren nannte das Popup mal den Kreis unter dem
  // Zeiger, mal den grossen darüber. Ursache war eine Regel, die den kleinen
  // Kreisen den Zeiger entzog, damit der Klick den grossen trifft — sie nahm ihnen
  // damit auch ihren Namen. Gerade die kleinen tragen keine Beschriftung mehr,
  // und ihr Name steht nur noch im Popup.
  it('gibt jedem Kreis seinen eigenen Namen und Betrag beim Überfahren', () => {
    const w = montiere()
    const quelle = readFileSync('parlwin/src/js/components/BudgetGrafik.vue', 'utf8')
    // Gemeint sind die Kreise selbst (`.pw-bubble`, `.pw-bubble circle`), nicht
    // die Beschriftung `.pw-bubble-titel`: Die liegt über den Kreisen und muss
    // den Zeiger durchlassen, sonst verdeckt der Text sie.
    expect(quelle, 'eine Regel entzieht Kreisen den Zeiger und damit ihr Popup')
      .not.toMatch(/\.pw-bubble(?![-\w])[^{]*\{[^}]*pointer-events:\s*none/)

    // Jeder Kreis trägt seinen eigenen Namen und Betrag, auch die tiefen.
    const tiefe = w.findAll('.pw-bubble').filter(g => Number(g.find('circle').attributes('data-ebene')) > 1)
    expect(tiefe.length, 'keine tieferen Kreise im Bild').toBeGreaterThan(0)
    for (const g of tiefe.slice(0, 5)) {
      const name = g.find('circle').attributes('data-name')
      const titel = g.find('title').text()
      expect(titel, `«${name}» nennt im Popup nicht sich selbst`).toContain(name)
      expect(titel, `«${name}» nennt im Popup keinen Betrag`).toMatch(/CHF/)
    }
  })

  // Die Summe der Seite ist die Zahl, um die es geht.
  it('hebt die Gesamtsumme jeder Seite hervor', () => {
    const w = montiere()
    const quelle = readFileSync('parlwin/src/js/components/BudgetGrafik.vue', 'utf8')
    const block = quelle.split('.pw-grafik-seite-betrag')[1] || ''
    const regel = block.slice(0, block.indexOf('}'))
    expect(regel, 'die Gesamtsumme ist nicht hervorgehoben').toMatch(/font-weight:\s*(700|800|900|bold)/)
    expect(regel, 'die Gesamtsumme ist nicht grösser gesetzt').toMatch(/font-size:\s*[\d.]+\s*(rem|em)/)
    expect(w.findAll('.pw-grafik-seite-betrag').length, 'keine Summe im Kopf').toBeGreaterThan(0)
  })

  it('lässt am Ende jeden Kreis auf seinem Platz stehen', () => {
    const w = montiere()
    w.vm.neuAnordnen()
    w.vm.simulationSchritte(BILDER_JE_SEKUNDE * 10)
    for (const k of kreise(w)) {
      const weit = Math.hypot(k.x - k.zielX, k.y - k.zielY)
      expect(weit, `«${k.knoten.data.name}» steht ${weit.toFixed(0)} weit von seinem Platz`)
        .toBeLessThan(k.r * 0.5)
    }
  })

  it('bewegt sich in jeder der ersten zwei Sekunden sichtbar', () => {
    const w = montiere()
    w.vm.neuAnordnen()
    w.vm.simulationSchritte(0)
    const seite = 1000 // die Zeichenfläche der Grafik, in ihren eigenen Einheiten

    let vorher = kreise(w).map(k => ({ x: k.x, y: k.y }))
    for (const sekunde of [1, 2]) {
      w.vm.simulationSchritte(BILDER_JE_SEKUNDE)
      const jetzt = kreise(w).map(k => ({ x: k.x, y: k.y }))
      const weg = Math.max(...jetzt.map((k, i) => Math.hypot(k.x - vorher[i].x, k.y - vorher[i].y)))
      // Sichtbar heisst: mehr als ein Hundertstel der Fläche in dieser Sekunde.
      expect(weg, `In Sekunde ${sekunde} bewegt sich kein Kreis mehr als ${weg.toFixed(1)}`)
        .toBeGreaterThan(seite * 0.01)
      vorher = jetzt
    }
  })
})
