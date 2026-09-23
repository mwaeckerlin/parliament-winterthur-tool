import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetGrafik from '../components/BudgetGrafik.vue'

// F111: die grafische Übersicht — geschachtelte Kreise, deren Fläche dem Betrag
// entspricht: Einnahmen/Ausgaben → Departement → Produktegruppe → Produkt.
const gruppen = [
  {
    code: '121', name: 'Personalamt', departement: 'Präsidiales',
    aufwand: { soll: 6000000 }, ertrag: { soll: 1000000 },
    produkte: [
      { nummer: '1', name: 'Personaldienstleistungen', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 4000000] }] },
      { nummer: '2', name: 'Lohnadministration', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 2000000] }] },
    ],
  },
  { code: '160', name: 'Bibliotheken', departement: 'Präsidiales', aufwand: { soll: 8000000 }, ertrag: { soll: 0 }, produkte: [] },
  { code: '221', name: 'Finanzamt', departement: 'Finanzen', aufwand: { soll: 2000000 }, ertrag: { soll: 500000 }, produkte: [] },
  // Ein Departement, das nur Ausgaben hat — auf der Einnahmenseite gibt es es nicht.
  {
    code: '300', name: 'Feuerwehr', departement: 'Sicherheit', aufwand: { soll: 4000000 }, ertrag: { soll: 0 },
    produkte: [
      { nummer: '1', name: 'Einsatz', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 3000000] }] },
      { nummer: '2', name: 'Prävention', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 1000000] }] },
    ],
  },
]

const montiert = (produktegruppen = gruppen) => mount(BudgetGrafik, { props: { produktegruppen, jahr: 2026 } })
const kreisNamen = (w) => w.findAll('circle').map(c => c.attributes('data-name'))

describe('BudgetGrafik (F111)', () => {
  it('führt Einnahmen und Ausgaben als die beiden Seiten, je mit ihrer Summe', () => {
    const w = montiert()
    const koepfe = w.findAll('.pw-grafik-seite-kopf').map(k => k.text())
    expect(koepfe[0]).toContain('Einnahmen')
    expect(koepfe[0]).toMatch(/1['’]500['’]000/)
    expect(koepfe[1]).toContain('Ausgaben')
    expect(koepfe[1]).toMatch(/20['’]000['’]000/)
  })

  // Jede Seite bekommt ihre eigene Zeichenfläche: nebeneinander füllen sie die
  // Breite, auf schmalen Seiten stehen sie untereinander (das entscheidet das
  // Stylesheet). Die Grössenrelation bleibt trotzdem sichtbar — die kleinere
  // Summe bekommt in ihrem Rahmen den kleineren Kreis.
  it('gibt jeder Seite eine eigene Zeichenfläche über die volle Breite', () => {
    const w = montiert()
    const flaechen = w.findAll('.pw-grafik-seite')
    expect(flaechen).toHaveLength(2)
    expect(flaechen.map(f => f.attributes('data-seite'))).toEqual(['Einnahmen', 'Ausgaben'])
    for (const f of flaechen) {
      expect(f.find('svg').attributes('viewBox')).toBe('0 0 1000 1000')
    }
  })

  // Jeder Kreis der aktuellen Ebene ist beschriftet — sonst weiss man nicht, was
  // man vor sich hat. Tiefere Ebenen bleiben dem Titel überlassen.
  it('beschriftet jeden Kreis der aktuellen Ebene', () => {
    const w = montiert()
    const ausgaben = w.find('[data-seite="Ausgaben"]')
    const kreiseEbene1 = ausgaben.findAll('circle[data-ebene="1"]').map(c => c.attributes('data-name'))
    const beschriftet = ausgaben.findAll('text[data-ebene="1"]').map(t => t.attributes('data-name'))
    expect(kreiseEbene1.length).toBeGreaterThan(0)
    expect(beschriftet.sort()).toEqual(kreiseEbene1.sort())
  })

  // Ein Klick auf ein Departement zoomt BEIDE Seiten dorthin — nur so lassen sich
  // Einnahmen und Ausgaben derselben Einheit nebeneinander vergleichen.
  it('zoomt beim Klick auf ein Departement beide Seiten dorthin', async () => {
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Präsidiales').trigger('click')
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget', 'Präsidiales'])
    // Beide Seiten zeigen jetzt die Produktegruppen von Präsidiales.
    const namenIn = (seite) => w.find(`[data-seite="${seite}"]`).findAll('circle').map(c => c.attributes('data-name'))
    expect(namenIn('Ausgaben')).toContain('Bibliotheken')
    expect(namenIn('Einnahmen')).toContain('Personalamt')
  })

  // Wo eine Seite den gewählten Kreis nicht kennt (keine Einnahmen), sagt sie das.
  it('meldet auf der Seite ohne Betrag, dass dort nichts vorliegt', async () => {
    const w = montiert()
    // Sicherheit hat keinen Ertrag — beim Zoomen dorthin bleibt die Einnahmenseite leer.
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Sicherheit').trigger('click')
    expect(w.find('[data-seite="Einnahmen"]').text()).toContain('Keine Einnahmen')
    expect(w.find('[data-seite="Ausgaben"]').findAll('circle').map(c => c.attributes('data-name'))).toContain('Feuerwehr')
  })

  it('zeichnet die Departemente und Produktegruppen darin', () => {
    const namen = kreisNamen(montiert())
    expect(namen).toContain('Präsidiales')
    expect(namen).toContain('Personalamt')
  })

  it('zeigt die Kreise beider Seiten in ihrer eigenen Fläche', () => {
    const w = montiert()
    const namenIn = (seite) => w.find(`[data-seite="${seite}"]`).findAll('circle').map(c => c.attributes('data-name'))
    // Bibliotheken haben keinen Ertrag — sie erscheinen nur bei den Ausgaben.
    expect(namenIn('Ausgaben')).toContain('Bibliotheken')
    expect(namenIn('Einnahmen')).not.toContain('Bibliotheken')
  })

  const radius = (w, seite, name) => Number(w.find(`[data-seite="${seite}"]`).findAll('circle')
    .find(c => c.attributes('data-name') === name).attributes('data-r'))

  it('gibt der grösseren Summe den grösseren Kreis', () => {
    const w = montiert()
    // Präsidiales: 14 Mio Ausgaben gegen 1 Mio Einnahmen — dieselbe Einheit,
    // beide Seiten im selben Massstab.
    expect(radius(w, 'Ausgaben', 'Präsidiales')).toBeGreaterThan(radius(w, 'Einnahmen', 'Präsidiales'))
  })

  it('behält die Grössenrelation auch nach dem Zoomen', async () => {
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Präsidiales').trigger('click')
    // Personalamt: 6 Mio Ausgaben gegen 1 Mio Einnahmen.
    expect(radius(w, 'Ausgaben', 'Personalamt')).toBeGreaterThan(radius(w, 'Einnahmen', 'Personalamt'))
  })

  it('öffnet einen Kreis per Klick und zeigt den Weg dorthin', async () => {
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Personalamt').trigger('click')
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget', 'Präsidiales', 'Personalamt'])
    // Im geöffneten Kreis sind die Produkte erreichbar.
    expect(kreisNamen(w)).toContain('Personaldienstleistungen')
  })

  it('öffnet auch dann eine Ebene, wenn ein Produkt den Klick abfängt', async () => {
    // Die kleinen Kreise liegen ÜBER den grossen: Ein Klick auf das Departement
    // trifft in dessen Mitte ein Produkt, und ein Produkt hat nichts zu öffnen.
    // Bis zum Fix passierte dann gar nichts, und das Departement liess sich nur
    // dort treffen, wo zufällig kein Produkt lag (e2e-Befund 2026-08-29).
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Personaldienstleistungen').trigger('click')
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget', 'Präsidiales'])
    // Und von dort weiter: derselbe Klick führt eine Ebene tiefer.
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Personaldienstleistungen').trigger('click')
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget', 'Präsidiales', 'Personalamt'])
  })

  it('geht über den Weg im Kopf wieder zurück', async () => {
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Präsidiales').trigger('click')
    await w.findAll('.pw-grafik-pfad button')[0].trigger('click')
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget'])
  })

  it('geht mit Escape eine Ebene zurück', async () => {
    const w = montiert()
    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Präsidiales').trigger('click')
    await w.findAll('svg')[0].trigger('keydown', { key: 'Escape' })
    expect(w.findAll('.pw-grafik-pfad button').map(b => b.text())).toEqual(['Budget'])
  })

  it('beschriftet jeden Kreis mit Name und Betrag, sobald er gross genug ist', () => {
    const w = montiert()
    const beschriftung = w.text()
    expect(beschriftung).toContain('Ausgaben')
    // Der Betrag steht in Schweizer Schreibweise am Kreis.
    expect(beschriftung).toMatch(/14['’]000['’]000/)
  })

  // Marc sah vier Kreise nebeneinander, alle mit «Bewirtscha…» beschriftet — der
  // Name war auf die Kreisbreite gekürzt und dadurch für alle vier derselbe. Ein
  // Name wird deshalb nie gekürzt: Er bricht an seinen Leerzeichen um.
  it('bricht einen langen Namen um, statt ihn zu kürzen', async () => {
    const lang = [
      'Bewirtschaftung Verwaltungsvermögen',
      'Bewirtschaftung Finanzvermögen',
      'Bewirtschaftung Baurechte',
      'Bewirtschaftung Landreserven',
    ]
    const w = montiert([
      {
        code: '410', name: 'Immobilien', departement: 'Finanzen',
        aufwand: { soll: 80_000_000 }, ertrag: { soll: 0 },
        produkte: lang.map((name, i) => ({
          nummer: String(i + 1), name,
          kostentabelle: [{ label: 'Kosten', werte: [0, 0, 20_000_000] }],
        })),
      },
    ])
    const seite = () => w.find('[data-seite="Ausgaben"]')
    // Bis auf die Ebene der Produkte: Departement, dann Produktegruppe.
    for (const name of ['Finanzen', 'Immobilien']) {
      await seite().findAll('circle').find(c => c.attributes('data-name') === name).trigger('click')
    }

    const texte = seite().findAll('text')
    expect(texte.length, 'keine Beschriftung auf der Produkteebene').toBeGreaterThan(0)
    for (const t of texte) {
      expect(t.text(), 'der Name «' + t.attributes('data-name') + '» ist gekürzt').not.toContain('…')
    }
    // Jeder der vier Namen steht vollständig da — sonst sind sie nicht
    // unterscheidbar, weil sie alle mit demselben Wort beginnen.
    const gezeigt = texte.map(t => t.findAll('tspan').map(s => s.text()).join(' '))
    for (const name of lang) {
      expect(gezeigt.some(g => g.includes(name)), 'fehlt: ' + name).toBe(true)
    }
  })

  // Die Kreise sollen sich sichtbar bewegen. Die erste Fassung skalierte um
  // 1,2 Promille — bei einem Kreis von 400 Pixeln ein halber Pixel, den niemand
  // sieht. Gemessen wird die Amplitude der Bewegung im Stylesheet: die
  // Skalierung um mindestens zwei Prozent, dazu ein Versatz, damit die Kreise
  // wandern statt nur zu pulsieren.
  it('lässt die Kreise ihre Position finden, mit Kollision', async () => {
    // Acht Departemente, die die Fläche füllen — wie im echten Budget. Bei nur
    // vier kleinen Kreisen kommen sie von selbst an; erst wenn es eng wird,
    // zeigt sich, ob der Zug zum Platz gegen die Kollision ankommt.
    // Beide Seiten tragen Beträge: Einnahmen UND Ausgaben zeichnen ihre Kreise,
    // jede in ihrer eigenen Fläche. Sie dürfen sich nicht gegenseitig wegstossen.
    const w = montiert([
      { code: '110', name: 'Finanzamt', departement: 'Finanzen', aufwand: { soll: 1_060_000_000 }, ertrag: { soll: 900_000_000 }, produkte: [] },
      { code: '210', name: 'Soziale Dienste', departement: 'Soziales', aufwand: { soll: 615_000_000 }, ertrag: { soll: 370_000_000 }, produkte: [] },
      { code: '310', name: 'Schulamt', departement: 'Schule und Sport', aufwand: { soll: 465_000_000 }, ertrag: { soll: 86_000_000 }, produkte: [] },
      { code: '410', name: 'Werke', departement: 'Technische Betriebe', aufwand: { soll: 436_000_000 }, ertrag: { soll: 393_000_000 }, produkte: [] },
      { code: '510', name: 'Umweltschutz', departement: 'Sicherheit und Umwelt', aufwand: { soll: 87_000_000 }, ertrag: { soll: 36_000_000 }, produkte: [] },
      { code: '610', name: 'Tiefbau', departement: 'Bau und Mobilität', aufwand: { soll: 71_000_000 }, ertrag: { soll: 71_000_000 }, produkte: [] },
      { code: '710', name: 'Stadtkanzlei', departement: 'Präsidiales', aufwand: { soll: 66_000_000 }, ertrag: { soll: 12_000_000 }, produkte: [] },
      { code: '810', name: 'Parlamentsdienste', departement: 'Behörden', aufwand: { soll: 15_000_000 }, ertrag: { soll: 5_000_000 }, produkte: [] },
    ])
    const lagenVon = (seite) => w.find(`[data-seite="${seite}"]`).findAll('g.pw-bubble')
      .filter(g => g.find('circle').attributes('data-ebene') === '1')
      .map(g => {
        const t = g.attributes('style') || ''
        const p = t.match(/translate\((-?[\d.]+)px,\s*(-?[\d.]+)px\)\s*scale\(([\d.]+)\)/)
        return p ? { name: g.find('circle').attributes('data-name'), x: +p[1], y: +p[2], r: +p[3] } : null
      })
      .filter(Boolean)
    const lagen = () => lagenVon('Ausgaben')

    // Vom ersten Aufbau an: die Kreise starten verstreut und finden zusammen.
    w.vm.lagen = {}
    w.vm.simulationNeu()
    w.vm.simulationSchritte(0)
    await w.vm.$nextTick()
    const start = lagen()
    expect(start.length, 'keine Kreise mit Position').toBeGreaterThan(1)

    // Die Simulation läuft: nach einigen Schritten stehen die Kreise woanders.
    w.vm.simulationSchritte(30)
    await w.vm.$nextTick()
    const nachher = lagen()
    const weg = Math.max(...nachher.map((k, i) => Math.hypot(k.x - start[i].x, k.y - start[i].y)))
    expect(weg, 'die Kreise bewegen sich nicht').toBeGreaterThan(0.5)

    // Und sie kommen an: nach dem Einschwingen steht jeder Kreis an dem Platz,
    // den ihm die Packung zuweist. Marc sah das Gegenteil — die Kreise klebten
    // verstreut in den Ecken, weil der Zug zu schwach war gegen die Kollision.
    // Lange laufen lassen: Marc sah das Bild nicht in der ersten Sekunde,
    // sondern im Dauerzustand — dort darf die Kollision die Kreise nicht nach
    // aussen schieben, während der Zug zum Platz zu schwach dagegen hält.
    w.vm.simulationSchritte(2000)
    await w.vm.$nextTick()
    // Auf BEIDEN Seiten: jeder Kreis steht an seinem Platz. Die beiden Flächen
    // liegen im selben Koordinatensystem; werden sie in einer gemeinsamen
    // Simulation gerechnet, stossen sich Einnahmen und Ausgaben gegenseitig weg
    // und alles klebt verstreut in den Ecken.
    for (const seite of ['Einnahmen', 'Ausgaben']) {
      const ziele = w.vm.layout.find(s => s.name === seite).kreise.filter(k => k.ebene === 1)
      for (const k of lagenVon(seite)) {
        const ziel = ziele.find(z => z.knoten.data.name === k.name)
        const weit = Math.hypot(k.x - ziel.zielX, k.y - ziel.zielY)
        expect(weit, `${seite}: «${k.name}» steht ${weit.toFixed(0)} weit von seinem Platz`)
          .toBeLessThan(ziel.r * 0.5)
      }
    }
    const ruhe = lagen()

    // Und sie stecken nicht ineinander: die Kollision hält sie auseinander.
    for (let i = 0; i < ruhe.length; i++) {
      for (let j = i + 1; j < ruhe.length; j++) {
        const a = ruhe[i]
        const b = ruhe[j]
        const abstand = Math.hypot(a.x - b.x, a.y - b.y)
        expect(abstand, `«${a.name}» und «${b.name}» stecken ineinander`)
          .toBeGreaterThan((a.r + b.r) * 0.9)
      }
    }
  })

  it('gibt dem grösseren Betrag den grösseren Kreis, auch bei vielen kleinen Kindern', () => {
    // Der Fehler, den Marc gesehen hat: Soziales (615 Mio) war kleiner
    // gezeichnet als Schule und Sport (465 Mio). Ursache war die Packung — ein
    // Elternkreis wird so gross, wie seine Kinder samt Abständen es verlangen,
    // und viele kleine Kinder packen schlechter als wenige grosse. Die aktuelle
    // Ebene wird deshalb eigens gelegt, mit den Einheiten als Blätter.
    const vieleKleine = Array.from({ length: 12 }, (_, i) => ({
      code: '90' + i, name: 'Klein ' + i, departement: 'Zersplittert',
      aufwand: { soll: 40_000_000 }, ertrag: { soll: 0 },
      produkte: [
        { nummer: '1', name: 'A' + i, kostentabelle: [{ label: 'Kosten', werte: [0, 0, 20_000_000] }] },
        { nummer: '2', name: 'B' + i, kostentabelle: [{ label: 'Kosten', werte: [0, 0, 20_000_000] }] },
      ],
    }))
    const w = montiert([
      // 600 Mio in einem Stück gegen 480 Mio in zwölf Teilen.
      { code: '800', name: 'Gross', departement: 'Gebündelt', aufwand: { soll: 600_000_000 }, ertrag: { soll: 0 }, produkte: [] },
      ...vieleKleine,
    ])
    const radius = (name) => Number(
      w.find('[data-seite="Ausgaben"]').findAll('circle')
        .find(c => c.attributes('data-name') === name).attributes('data-r'),
    )
    expect(radius('Gebündelt')).toBeGreaterThan(radius('Zersplittert'))
    // Und zwar im Verhältnis der Wurzeln: √(600/480) = 1,118.
    expect(radius('Gebündelt') / radius('Zersplittert')).toBeCloseTo(Math.sqrt(600 / 480), 2)
  })

  it('zeigt beim Überfahren die Namen der Kinder und hebt den Kreis hervor', async () => {
    const w = montiert()
    const seite = w.find('[data-seite="Ausgaben"]')
    const namen = () => seite.findAll('text').map(t => t.attributes('data-name'))
    expect(namen(), 'Produktegruppen sind ohne Zeiger beschriftet').not.toContain('Personalamt')

    const gruppe = seite.findAll('g.pw-bubble')
      .find(g => g.find('circle').attributes('data-name') === 'Präsidiales')
    await gruppe.trigger('mouseenter')
    expect(gruppe.classes(), 'Kreis unter dem Zeiger nicht hervorgehoben').toContain('pw-bubble-wach')
    expect(namen(), 'Kinder bleiben beim Überfahren namenlos').toContain('Personalamt')

    await gruppe.trigger('mouseleave')
    expect(namen(), 'Kindernamen bleiben nach dem Verlassen stehen').not.toContain('Personalamt')
  })

  // Nach einem Wechsel der Ebene suchen die Kreise ihre Plätze neu: sie starten
  // dort, wo sie stehen, und wandern an die neuen Stellen.
  it('sucht nach dem Öffnen einer Ebene die Plätze neu', async () => {
    const w = montiert()
    w.vm.simulationSchritte(400)
    await w.vm.$nextTick()
    const vorher = { ...w.vm.lagen }

    await w.find('[data-seite="Ausgaben"]').findAll('circle')
      .find(c => c.attributes('data-name') === 'Präsidiales').trigger('click')
    await w.vm.$nextTick()
    w.vm.simulationSchritte(5)
    await w.vm.$nextTick()

    expect(Object.keys(w.vm.lagen).length, 'nach dem Wechsel steht nichts').toBeGreaterThan(0)
    expect(Object.keys(w.vm.lagen).join(), 'dieselben Kreise wie vor dem Wechsel')
      .not.toBe(Object.keys(vorher).join())
  })

  it('beschriftet NUR die aktuelle Ebene und setzt den Text über die Kreismitte', () => {
    // Ein Kind sitzt immer im Kreis seines Elternteils. Beschriftet man beide,
    // treffen sich die Texte in der Mitte und ergeben Wortsalat — genau das war
    // in der Ansicht zu sehen. Deshalb trägt nur die aktuelle Ebene ihren Namen,
    // und der steht über der Mitte, wo die grossen Kindkreise nicht liegen.
    const w = montiert()
    const texte = w.findAll('text')
    expect(texte.length).toBeGreaterThan(0)
    expect(texte.every(t => t.attributes('data-ebene') === '1'), 'auch tiefere Ebenen beschriftet').toBe(true)

    const seite = w.find('[data-seite="Ausgaben"]')
    // Die Position des Kreises steht an seiner Gruppe (dort sitzt auch die
    // Skalierung), die des Textes am Text selbst.
    const kreis = seite.findAll('g.pw-bubble')
      .find(g => g.find('circle').attributes('data-name') === 'Präsidiales')
    const text = seite.findAll('text').find(t => t.attributes('data-name') === 'Präsidiales')
    const y = (el) => Number(/translate\([-\d.]+px,\s*([-\d.]+)px\)/.exec(el.attributes('style'))?.[1] ?? NaN)
    expect(y(text), 'Beschriftung sitzt nicht über der Kreismitte').toBeLessThan(y(kreis))
  })

  it('nennt beim Überfahren Name, Betrag und Anteil am übergeordneten Kreis', () => {
    const w = montiert()
    const titel = w.findAll('circle').find(c => c.attributes('data-name') === 'Bibliotheken').find('title')
    expect(titel.text()).toContain('Bibliotheken')
    expect(titel.text()).toMatch(/8['’]000['’]000/)
    // 8 von 14 Mio des Departements Präsidiales.
    expect(titel.text()).toContain('57')
  })

  // Wo die Produkte im Buch das Budget ihrer Gruppe nicht erklären, steht das am
  // Kreis — die Flächennormierung darf die Lücke nicht verstecken.
  it('nennt im Titel, welchen Anteil des Budgets die Produkte ausweisen', () => {
    const w = montiert([{
      code: '425', name: 'Parkieren', departement: 'Bau',
      aufwand: { soll: 5519084 }, ertrag: { soll: 0 },
      produkte: [{ nummer: '1', name: 'Parkhäuser', kostentabelle: [{ label: 'Kosten', werte: [0, 0, 2322060] }] }],
    }])
    const titel = w.findAll('circle').find(c => c.attributes('data-name') === 'Parkieren').find('title')
    expect(titel.text()).toContain('Produkte weisen 42% aus')
  })

  it('schweigt im Titel, wo die Produkte das Budget erklären', () => {
    const titel = montiert().findAll('circle').find(c => c.attributes('data-name') === 'Personalamt').find('title')
    expect(titel.text()).not.toContain('Produkte weisen')
  })

  it('zeigt einen Hinweis, wenn kein Budget darstellbar ist', () => {
    const w = montiert([])
    expect(w.text()).toContain('Keine Budgetzahlen')
    expect(w.findAll('circle')).toHaveLength(0)
  })
})
