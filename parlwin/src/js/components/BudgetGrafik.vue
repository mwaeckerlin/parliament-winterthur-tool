<template>
  <section class="pw-budget-grafik">
    <nav class="pw-grafik-pfad" aria-label="Weg in der Grafik">
      <button
        v-for="(name, i) in pfadNamen"
        :key="i"
        type="button"
        class="pw-grafik-pfad-schritt"
        :aria-current="i === pfadNamen.length - 1 ? 'true' : null"
        @click="zuEbene(i)"
      >{{ name }}</button>
    </nav>

    <div class="pw-grafik-seiten">
      <figure
        v-for="seite in seiten"
        :key="seite.name"
        class="pw-grafik-seite"
        :data-seite="seite.name"
      >
        <figcaption class="pw-grafik-seite-kopf">
          <span class="pw-grafik-seite-titel">{{ seite.name }}</span>
          <span v-if="seite.fokus" class="pw-grafik-seite-betrag">{{ fr(seite.fokus.data.betrag) }}</span>
        </figcaption>
        <p v-if="!seite.fokus" class="pw-hinweis">Keine {{ seite.name }} für diesen Ausschnitt.</p>
        <svg
          v-else
          class="pw-grafik-flaeche"
          viewBox="0 0 1000 1000"
          tabindex="0"
          role="application"
          :aria-label="seite.name + ' als geschachtelte Kreise'"
          @keydown.esc="zurueck"
          @click.self="zurueck"
        >
          <g
            v-for="k in seite.kreise"
            :key="k.schluessel"
            class="pw-bubble"
            :class="[
              'pw-bubble-e' + Math.min(k.ebene, 4),
              { 'pw-bubble-blatt': !k.knoten.children, 'pw-bubble-wach': schwebt === k.schluessel },
            ]"
            :style="{
              '--pw-bubble-farbton': k.farbton,
              transform: `translate(${k.x}px, ${k.y}px) scale(${k.r})`,
            }"
            @mouseenter="schwebt = k.schluessel"
            @mouseleave="schwebt = schwebt === k.schluessel ? '' : schwebt"
          >
            <circle
              r="1"
              vector-effect="non-scaling-stroke"
              :data-name="k.knoten.data.name"
              :data-ebene="k.ebene"
              :data-r="k.r"
              @click.stop="oeffne(k.knoten)"
            >
              <title>{{ beschreibung(k.knoten) }}</title>
            </circle>
          </g>
          <text
            v-for="k in seite.beschriftet"
            :key="'t' + k.schluessel"
            class="pw-bubble-titel"
            :class="{ 'pw-bubble-titel-kind': k.ebene > 1 }"
            :data-name="k.knoten.data.name"
            :data-ebene="k.ebene"
            :style="{ transform: `translate(${k.x}px, ${k.textY}px)` }"
            :font-size="k.schrift"
          >
            <tspan
              v-for="(zeile, zi) in k.zeilen"
              :key="zi"
              x="0"
              :dy="zi === 0 ? '0' : '1.15em'"
            >{{ zeile }}</tspan>
            <tspan v-if="k.ebene === 1" x="0" dy="1.15em" class="pw-bubble-betrag">{{ fr(k.knoten.data.betrag) }}</tspan>
          </text>
        </svg>
      </figure>
    </div>
    <p v-if="!seiten.length" class="pw-hinweis">Keine Budgetzahlen für die Grafik vorhanden.</p>
  </section>
</template>

<script>
import { forceCollide, forceSimulation, forceX, forceY } from 'd3-force'
import { hierarchy, pack } from 'd3-hierarchy'
import { budgetHierarchie } from '../budget-hierarchie'
import { frankenFormat } from '../utils'

// Koordinatensystem einer Seite; die Grösse auf dem Bildschirm macht das
// Stylesheet. Alle Werte hier sind viewBox-Einheiten und skalieren mit.
const SEITE = 1000
const MITTE = SEITE / 2
// Der grösste Kreis füllt die Fläche bis auf diesen Rand.
const AUSSEN = 470
// Wie hoch über der Kreismitte der Text sitzt, als Anteil des Radius. In der
// Mitte liegen die grössten Kindkreise; weiter oben ist der Kreis noch breit
// genug für den Namen und der Text steht frei.
const BESCHRIFTUNG_HOCH = 0.45
// Schriftgrössen in viewBox-Einheiten: unter der kleinsten ist nichts mehr zu
// lesen, über der grössten sprengt der Text kleine Kreise.
const SCHRIFT_MIN = 13
const SCHRIFT_MAX = 40
// Mittlere Zeichenbreite im Verhältnis zur Schriftgrösse — daraus folgt, wie
// viele Zeichen in einen Kreis passen.
const ZEICHENBREITE = 0.55
// Die Kräfte, mit denen die Kreise ihren Platz finden: Der Zug zum Ziel bringt
// sie dorthin, die Kollision hält sie auseinander. Der Zug muss KRÄFTIGER sein
// als die Kollision, sonst schiebt diese die Kreise nach aussen und sie bleiben
// verstreut liegen, statt zusammenzufinden.
//
// Die Stärke des Zugs bestimmt die DAUER der Reise, und sie WÄCHST über die
// Reise: Anfangs zieht es sanft, damit die Kreise mehrere Sekunden unterwegs
// sind, einander beiseiteschieben und sich einschwingen; gegen Ende zieht es
// kräftig, damit jeder wirklich auf seinem Platz ankommt. Ein durchgehend
// kräftiger Zug (0.4) setzte jeden Kreis in einem halben Dutzend Bildern ab, und
// vom Zusammenfinden war nichts zu sehen; ein durchgehend sanfter liess die
// kleinen Kreise draussen liegen, weil die Kollision stärker drückt als er zieht.
const ZUG_ANFANG = 0.012
const ZUG_ENDE = 0.45
// Über wie viele Bilder der Zug von seinem Anfangs- auf seinen Endwert wächst.
const ZUG_DAUER = 260
// Die Kollision umgekehrt: Auf der Reise schiebt sie die Kreise kräftig
// auseinander, am Ziel lässt sie nach. Anders als der Zug hängt ihre Kraft nicht
// an der abklingenden Energie der Simulation — bliebe sie stark, drückte sie
// einen kleinen Kreis dauerhaft von seinem Platz weg, und er bliebe draussen
// liegen. Auf den gepackten Plätzen überlappt ohnehin keiner den anderen.
const STOSS_ANFANG = 0.9
const STOSS_ENDE = 0.15
// Reibung: Wie stark ein Kreis bei jedem Bild abgebremst wird (d3 nennt es
// velocityDecay). Weniger Reibung als die üblichen 0.4 lässt ihn gleiten, statt
// zu stocken.
const REIBUNG = 0.28
// Wie schnell die Simulation ihre Energie verliert (d3s alphaDecay). Der übliche
// Wert erschöpft sie in gut fünf Sekunden — zusammen mit dem sanfteren Zug wäre
// die Reise dann zu Ende, bevor die Kreise angekommen sind.
const ENERGIE_ZERFALL = 0.008
// Damit das Bild nie ganz erstarrt, bleibt ein Rest Energie im System: die
// Kreise justieren sich weiter, ohne dass es unruhig wird.
const REST_ENERGIE = 0.006
// Wo die Kreise beim Aufbau starten: auf einem Ring um die Mitte, im Abstand
// dieses Anteils der Zeichenfläche. Sie kommen damit von weit aussen und aus
// allen Richtungen herein — das Bild, das gemeint ist. Vorher starteten sie
// dicht neben ihrem Platz und waren in einem Wimpernschlag dort.
const START_RING = 0.52
const START_RING_STREUUNG = 0.12

/**
 * Der Betrag, aus dem die Fläche eines Kreises entsteht. Bei Blättern steht er
 * in den Daten, bei Eltern ist es die Summe ihrer Blätter — d3 rechnet sie in
 * `value`. Beide Wege führen zum selben Wert, weil die Produkte einer Gruppe auf
 * deren Budget normiert sind; wo ein Zweig gar nichts beisteuert, gilt der
 * eigene Betrag.
 */
const betragVon = (knoten) => Math.max(
  Number(knoten?.value) || 0,
  Math.max(0, Number(knoten?.data?.flaeche) || 0),
)

/**
 * F111: Das Budget als geschachtelte Kreise. Einnahmen und Ausgaben haben je
 * ihre eigene Zeichenfläche — nebeneinander füllen sie die Breite, auf schmalen
 * Seiten stehen sie untereinander. Beide zeigen IMMER denselben Ausschnitt: ein
 * Klick auf ein Departement zoomt beide Seiten dorthin, so lassen sich Aufwand
 * und Ertrag derselben Einheit vergleichen. Die Grössenrelation zwischen den
 * Seiten bleibt dabei erhalten (die kleinere Summe bekommt den kleineren Kreis).
 */
export default {
  name: 'BudgetGrafik',
  props: {
    // Die Produktegruppen der Budgetansicht, bereits gefiltert (F76).
    produktegruppen: { type: Array, default: () => [] },
    jahr: { type: Number, default: 0 },
  },
  data() {
    // `schwebt`: der Kreis unter dem Zeiger. Er wächst leicht an, und die Namen
    // seiner Kinder erscheinen — so sieht man vor dem Klick, was drin steckt.
    // `lagen`: wo die Kreise der aktuellen Ebene GERADE stehen. Die Packung sagt,
    // wohin sie gehören; dorthin schiebt sie die Kraftsimulation, und dabei
    // stossen sie aneinander.
    // `simulationen`: je Seite eine Kraftsimulation. Sie sind kein Anzeigezustand
    // und stehen deshalb ausserhalb der Reaktivität.
    this.simulationen = []
    // Wie viele Bilder jede Simulation gelaufen ist — daraus folgt, wie fest der
    // Zug zum Zielplatz gerade anzieht.
    this.tickZaehler = new Map()
    return { pfadNamen: ['Budget'], schwebt: '', lagen: {} }
  },
  mounted() {
    this.simulationNeu()
    // Die Grafik ist ein Tab unter mehreren und steht von Anfang an im DOM, nur
    // ausgeblendet. Ohne dieses Signal war die Simulation längst ausgelaufen,
    // wenn jemand den Tab öffnete, und alle Kreise standen schon still. Der
    // IntersectionObserver sagt, wann die Fläche zu sehen ist; dann finden die
    // Kreise ihre Plätze vor den Augen des Betrachters.
    if (typeof IntersectionObserver === 'function') {
      this.sichtbarkeit = new IntersectionObserver(([eintrag]) => {
        if (eintrag && eintrag.isIntersecting) { this.neuAnordnen() }
      }, { threshold: 0.05 })
      this.sichtbarkeit.observe(this.$el)
    }
  },
  beforeUnmount() {
    this.simulationEnde()
    if (this.sichtbarkeit) { this.sichtbarkeit.disconnect() }
  },
  watch: {
    // Ein neuer Ausschnitt oder neue Daten: die Kreise suchen ihre Plätze neu.
    // Sie starten dort, wo sie stehen, und wandern an die neuen Stellen.
    // Der Auslöser sind die Daten selbst, nie das gerechnete Bild — sonst setzte
    // jeder Schritt der Simulation sie wieder auf Anfang.
    pfadNamen() {
      this.simulationNeu()
    },
    produktegruppen() {
      this.simulationNeu()
    },
  },
  computed: {
    baum() {
      return budgetHierarchie(this.produktegruppen)
    },
    /** Je Seite der gepackte Kreisbaum (Einnahmen zuerst, dann Ausgaben). */
    baeume() {
      return (this.baum.children || []).map(daten => {
        const knoten = hierarchy(daten)
          .sum(d => (d.children && d.children.length ? 0 : Math.max(0, Number(d.flaeche) || 0)))
          .sort((a, b) => (b.value || 0) - (a.value || 0))
        return { name: daten.name, wurzel: pack().size([SEITE, SEITE]).padding(SEITE / 150)(knoten) }
      })
    },
    /**
     * Wohin die Kreise gehören: die reine Packung, ohne die Bewegung. Die
     * Simulation zieht sie von hier aus zusammen; `seiten` legt das Ergebnis
     * darüber.
     */
    layout() {
      const fokusse = this.baeume.map(b => ({ ...b, fokus: this.fokusVon(b.wurzel) }))
      // Der grösste Ausschnitt füllt seine Fläche; die andere Seite wird im
      // Verhältnis der Wurzeln dazu gezeichnet — sonst wirkten 1 Mio Einnahmen
      // gleich gross wie 14 Mio Ausgaben.
      const groesster = Math.max(...fokusse.map(f => (f.fokus ? betragVon(f.fokus) : 0)), 1)
      return fokusse.map(f => {
        if (!f.fokus) {
          return { name: f.name, fokus: null, kreise: [], beschriftet: [] }
        }
        const radius = AUSSEN * Math.sqrt(betragVon(f.fokus) / groesster)
        // Die aktuelle Ebene wird EIGENS gepackt, mit ihren Einheiten als
        // Blätter: Nur so folgt jeder Radius der Wurzel seines Betrags. Ein
        // pack() über den ganzen Baum macht einen Elternkreis so gross, wie
        // seine Kinder samt Abständen es verlangen — ein Departement mit vielen
        // kleinen Gruppen wurde dadurch grösser gezeichnet als eines mit mehr
        // Geld (Soziales 615 Mio erschien kleiner als Schule und Sport 465 Mio).
        const platzierung = pack().size([2 * radius, 2 * radius]).padding(radius / 40)(
          hierarchy({ children: (f.fokus.children || []).map(k => ({ knoten: k })) })
            .sum(d => (d.knoten ? betragVon(d.knoten) : 0))
            .sort((a, b) => (b.value || 0) - (a.value || 0)),
        )
        // Jede Einheit bringt ihren eigenen Inhalt mit: Die inneren Kreise
        // behalten ihre Packung und werden auf den neuen Radius skaliert.
        const knotenListe = []
        for (const platz of platzierung.children || []) {
          const wurzel = platz.data.knoten
          const s = platz.r / wurzel.r
          const dx = MITTE - radius + platz.x - wurzel.x * s
          const dy = MITTE - radius + platz.y - wurzel.y * s
          // Wo diese Einheit gerade steht: die Simulation schiebt sie von ihrem
          // Platz aus zusammen; ihr Inhalt wandert um denselben Betrag mit.
          const einheit = wurzel.data.name
          const zielX = MITTE - radius + platz.x
          const zielY = MITTE - radius + platz.y
          for (const n of wurzel.descendants()) {
            knotenListe.push({
              n,
              x: dx + n.x * s,
              y: dy + n.y * s,
              r: n.r * s,
              ebene: n.depth - wurzel.depth + 1,
              elter: n.parent ? n.parent.data.name : '',
              einheit,
              zielX,
              zielY,
            })
          }
        }
        const kreise = knotenListe.map((eintrag, i) => {
          const knoten = eintrag.n
          const r = eintrag.r
          const ebene = eintrag.ebene
          const name = knoten.data.name
          // Die Schrift folgt dem Kreis, bleibt aber lesbar. Beschriftet wird auf
          // der Höhe BESCHRIFTUNG_HOCH über der Mitte; dort ist der Kreis noch
          // fast so breit wie in der Mitte, aber die grossen Kindkreise liegen
          // weiter unten. Der Name wird auf die dortige Breite gekürzt.
          const schrift = Math.min(SCHRIFT_MAX, r / 3.6)
          const halbbreite = r * Math.sqrt(1 - BESCHRIFTUNG_HOCH * BESCHRIFTUNG_HOCH)
          const platz = Math.floor((halbbreite * 1.9) / (schrift * ZEICHENBREITE))
          const zeilen = this.namensZeilen(name, platz)
          return {
            knoten,
            ebene,
            schluessel: f.name + '|' + name + '|' + knoten.depth + '|' + i,
            x: eintrag.x,
            y: eintrag.y,
            r,
            farbton: this.farbtoene.get(this.departementVon(knoten)) ?? 210,
            zeilen,
            schrift,
            // Der Text sitzt über der Mitte: In der Mitte liegen die Kindkreise,
            // und zwei Beschriftungen übereinander sind keine. Mehrere Zeilen
            // wachsen nach unten, also rückt der Block um seine halbe Höhe hoch
            // und bleibt um dieselbe Stelle zentriert wie eine einzelne Zeile.
            textY: eintrag.y - r * BESCHRIFTUNG_HOCH - ((zeilen.length - 1) * 1.15 * schrift) / 2,
            platz,
            // Der Kreis, in dem dieser hier liegt — daran hängt, wessen Kinder
            // beim Überfahren ihre Namen zeigen.
            elter: eintrag.elter,
            // Die Einheit der aktuellen Ebene, mit der dieser Kreis mitwandert,
            // und der Platz, den die Packung ihr zugewiesen hat.
            einheit: eintrag.einheit,
            zielX: eintrag.zielX,
            zielY: eintrag.zielY,
          }
        })
        return { name: f.name, fokus: f.fokus, kreise }
      })
    },
    /**
     * Was gezeichnet wird: die Packung, verschoben um das, was die Simulation
     * aus ihr gemacht hat. Der Inhalt einer Einheit wandert mit ihr.
     */
    seiten() {
      return this.layout.map(seite => {
        const kreise = seite.kreise.map(k => {
          const lage = this.lagen[seite.name + '§' + k.einheit]
          if (!lage) { return k }
          const vx = lage.x - k.zielX
          const vy = lage.y - k.zielY
          return { ...k, x: k.x + vx, y: k.y + vy, textY: k.textY + vy }
        })
        return {
          ...seite,
          kreise,
          // NUR die aktuelle Ebene wird beschriftet. Beschriftet man auch die
          // tieferen, liegen die Texte übereinander — ein Kind sitzt immer IN
          // seinem Elternkreis, und beide Beschriftungen treffen sich in der
          // Mitte. Was tiefer liegt, sagt der Titel beim Überfahren, und ein
          // Klick holt die Ebene nach vorn.
          beschriftet: this.beschriftungen(kreise),
        }
      })
    },
    /**
     * Ein Farbton je Departement, gleichmässig über den Kreis verteilt — so bleibt
     * beim Hineinzoomen erkennbar, in welchem Departement man ist, und dieselbe
     * Einheit hat auf beiden Seiten dieselbe Farbe.
     */
    farbtoene() {
      const namen = []
      for (const gruppe of this.produktegruppen || []) {
        const dep = String(gruppe?.departement || 'Ohne Departement').trim()
        if (!namen.includes(dep)) { namen.push(dep) }
      }
      return new Map(namen.map((dep, i) => [dep, Math.round((i * 360) / Math.max(1, namen.length))]))
    },
  },
  methods: {
    fr: frankenFormat,
    /**
     * Die Kreise finden ihren Platz: d3s Kraftsimulation zieht jede Einheit an
     * die Stelle, die ihr die Packung zuweist (`forceX`/`forceY`), und hält sie
     * dabei auseinander (`forceCollide`). Das ergibt das Bild, das gemeint ist —
     * die Kugeln schieben einander beiseite und rücken zusammen, bis alles passt.
     *
     * Simuliert wird die aktuelle Ebene; ihr Inhalt wandert mit seiner Einheit.
     * Die Simulation kommt nie ganz zur Ruhe (`alphaTarget`), damit das Bild
     * lebt, statt nach ein paar Sekunden zu erstarren.
     */
    simulationNeu() {
      this.simulationEnde()
      // JE SEITE eine eigene Simulation: Beide Zeichenflächen haben dasselbe
      // Koordinatensystem, und in einer gemeinsamen Simulation stiessen sich die
      // Kreise der Einnahmen und der Ausgaben gegenseitig weg — sie klebten
      // verstreut in den Ecken, statt zusammenzufinden.
      this.simulationen = this.layout.map(seite => {
        const eigene = seite.kreise.filter(k => k.ebene === 1)
        const knoten = eigene.map((k, i) => {
          const schluessel = seite.name + '§' + k.einheit
          const alt = this.lagen[schluessel]
          // Beim Aufbau kommen sie von aussen: gleichmässig über den Ring
          // verteilt, damit sie aus allen Richtungen hereinwandern und nicht als
          // Pulk. Danach starten sie dort, wo sie gerade stehen.
          const winkel = (i / Math.max(1, eigene.length)) * 2 * Math.PI + Math.random() * 0.6
          const weite = SEITE * (START_RING + Math.random() * START_RING_STREUUNG)
          return {
            schluessel,
            r: k.r,
            zielX: k.zielX,
            zielY: k.zielY,
            x: alt ? alt.x : MITTE + Math.cos(winkel) * weite,
            y: alt ? alt.y : MITTE + Math.sin(winkel) * weite,
          }
        })
        if (!knoten.length) { return null }
        const sim = forceSimulation(knoten)
          .force('x', forceX(d => d.zielX).strength(ZUG_ANFANG))
          .force('y', forceY(d => d.zielY).strength(ZUG_ANFANG))
          .force('stoss', forceCollide(d => d.r).strength(STOSS_ANFANG))
          .velocityDecay(REIBUNG)
          .alphaDecay(ENERGIE_ZERFALL)
          .alpha(1)
          .alphaTarget(REST_ENERGIE)
        this.tickZaehler.set(sim, 0)
        sim.on('tick', () => {
          this.zugNachziehen(sim)
          this.lagenUebernehmen()
        })
        return sim
      }).filter(Boolean)
    },
    /**
     * Alles von vorn: Die Kreise vergessen, wo sie standen, starten versetzt und
     * suchen ihre Plätze neu. So sieht das Zusammenfinden, wer die Fläche gerade
     * zu Gesicht bekommt.
     */
    neuAnordnen() {
      this.lagen = {}
      this.simulationNeu()
    },
    /**
     * Zieht den Zug zum Zielplatz mit jedem Bild ein Stück fester an. Quadratisch,
     * damit der Anfang der Reise sanft bleibt und das Ankommen bestimmt ist.
     */
    zugNachziehen(sim) {
      const bilder = (this.tickZaehler.get(sim) || 0) + 1
      this.tickZaehler.set(sim, bilder)
      const anteil = Math.min(1, bilder / ZUG_DAUER)
      sim.force('x').strength(ZUG_ANFANG + (ZUG_ENDE - ZUG_ANFANG) * anteil * anteil)
      sim.force('y').strength(ZUG_ANFANG + (ZUG_ENDE - ZUG_ANFANG) * anteil * anteil)
      sim.force('stoss').strength(STOSS_ANFANG + (STOSS_ENDE - STOSS_ANFANG) * anteil * anteil)
    },
    /** Die Positionen aller Simulationen in den Zustand übernehmen. */
    lagenUebernehmen() {
      const lagen = {}
      for (const sim of this.simulationen) {
        for (const d of sim.nodes()) {
          lagen[d.schluessel] = { x: d.x, y: d.y }
        }
      }
      this.lagen = lagen
    },
    simulationEnde() {
      for (const sim of this.simulationen) {
        sim.stop()
      }
      this.simulationen = []
      this.tickZaehler.clear()
    },
    /**
     * Rechnet die Simulationen um n Bilder weiter — für Tests ohne Zeitgeber.
     * Gerechnet wird Bild für Bild, weil der Zug mit jedem Bild fester anzieht;
     * d3s eigener Sprung über mehrere Bilder löst keine Ereignisse aus.
     */
    simulationSchritte(n) {
      if (!this.simulationen.length) { this.simulationNeu() }
      for (const sim of this.simulationen) {
        for (let i = 0; i < n; i++) {
          this.zugNachziehen(sim)
          sim.tick(1)
        }
      }
      this.lagenUebernehmen()
    },
    /**
     * Welche Kreise ihren Namen tragen. Immer die aktuelle Ebene, soweit der Name
     * lesbar hineinpasst. Zusätzlich die Kinder des Kreises unter dem Zeiger: So
     * sieht man, was in einem Departement steckt, bevor man es öffnet.
     *
     * @param {Array<object>} kreise
     */
    beschriftungen(kreise) {
      const lesbar = k => k.schrift >= SCHRIFT_MIN && k.platz >= 5
      const oben = kreise.filter(k => k.ebene === 1 && lesbar(k))
      const zeiger = kreise.find(k => k.schluessel === this.schwebt)
      if (!zeiger) {
        return oben
      }
      // Die Kinder des angezeigten Kreises dürfen kleiner beschriftet werden als
      // die aktuelle Ebene — sie erscheinen nur, solange der Zeiger dort steht.
      const kinder = kreise.filter(k => k.ebene === zeiger.ebene + 1
        && k.elter === zeiger.knoten.data.name
        && k.platz >= 4)
      return [...oben, ...kinder]
    },
    /** Der Knoten einer Seite, auf den der gemeinsame Weg zeigt (null: gibt es dort nicht). */
    fokusVon(wurzel) {
      let knoten = wurzel
      for (const name of this.pfadNamen.slice(1)) {
        const kind = (knoten.children || []).find(k => k.data.name === name)
        if (!kind) { return null }
        knoten = kind
      }
      return knoten.children ? knoten : null
    },
    /** Das Departement, unter dem ein Kreis hängt (zweite Ebene unter der Seite). */
    departementVon(knoten) {
      const weg = knoten.ancestors().reverse()
      return weg.length > 1 ? weg[1].data.name : ''
    },
    /**
     * Der Name auf mehrere Zeilen verteilt, umgebrochen an seinen Leerzeichen.
     * Gekürzt wird nie: Vier Produkte «Bewirtschaftung …» standen als viermal
     * «Bewirtscha…» nebeneinander und waren nicht mehr zu unterscheiden. Ein
     * einzelnes Wort, das breiter ist als der Kreis, bleibt ganz — es ragt dann
     * über den Rand, was immer noch lesbar ist.
     *
     * @param {string} name
     * @param {number} platz Zeichen, die in eine Zeile passen
     * @returns {string[]}
     */
    namensZeilen(name, platz) {
      const breite = Math.max(1, platz)
      const zeilen = []
      for (const wort of String(name).split(/\s+/).filter(Boolean)) {
        const letzte = zeilen.length - 1
        if (letzte >= 0 && (zeilen[letzte] + ' ' + wort).length <= breite) {
          zeilen[letzte] += ' ' + wort
        } else {
          zeilen.push(wort)
        }
      }
      return zeilen.length ? zeilen : [String(name)]
    },
    beschreibung(knoten) {
      const teile = [knoten.data.name, this.fr(knoten.data.betrag) + ' CHF']
      const eltern = knoten.parent
      if (eltern && eltern.value > 0) {
        const anteil = ((knoten.value / eltern.value) * 100).toFixed(1).replace('.', ',')
        teile.push(anteil + '% von ' + eltern.data.name)
      }
      // Die Produkte im Budgetbuch erklären das Budget ihrer Gruppe nicht immer
      // vollständig. Weil die Kreisflächen der Produkte auf die Gruppe normiert
      // sind, wäre die Lücke sonst unsichtbar — hier steht sie.
      const deckung = knoten.data.produktDeckung
      if (typeof deckung === 'number' && deckung < 99.5) {
        teile.push('Produkte weisen ' + Math.round(deckung) + '% aus')
      }
      return teile.join(' · ')
    },
    oeffne(knoten) {
      // Ein Kreis mit Kindern öffnet sich selbst. Ein Blatt hat nichts zu
      // öffnen — und es liegt ÜBER den grossen Kreisen, die es umschliessen, und
      // fängt deren Klicks ab. Ein Departement wäre sonst nur dort zu treffen,
      // wo zufällig kein Produkt liegt. Ein Klick auf ein Blatt gilt deshalb dem
      // nächsten Schritt hinein: dem Kreis eine Ebene unter dem Ausschnitt.
      const ziel = knoten.children
        ? knoten
        : (knoten.ancestors().reverse()[this.pfadNamen.length] || knoten)
      if (!ziel.children) { return }
      // Der Weg gilt für BEIDE Seiten — die Namen ab der Seitenebene.
      this.pfadNamen = ['Budget', ...ziel.ancestors().reverse().slice(1).map(k => k.data.name)]
    },
    zurueck() {
      if (this.pfadNamen.length > 1) { this.pfadNamen = this.pfadNamen.slice(0, -1) }
    },
    zuEbene(index) {
      this.pfadNamen = this.pfadNamen.slice(0, index + 1)
    },
  },
}
</script>

<style scoped lang="scss">
.pw-budget-grafik { display: flex; flex-direction: column; gap: 0.5rem; }

/* Der Weg in den geöffneten Kreis: jeder Schritt führt dorthin zurück. */
.pw-grafik-pfad { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.25rem; }
.pw-grafik-pfad-schritt {
  border: none;
  background: none;
  padding: 0 0.15rem;
  font-size: 0.95rem;
  color: var(--color-primary-element);
  cursor: pointer;
}
.pw-grafik-pfad-schritt::after { content: '›'; padding-inline-start: 0.4rem; color: var(--color-text-maxcontrast); }
.pw-grafik-pfad-schritt:last-child { color: var(--color-main-text); cursor: default; }
.pw-grafik-pfad-schritt:last-child::after { content: none; }

/* Nebeneinander über die volle Breite; auf schmalen Seiten untereinander. */
.pw-grafik-seiten { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
.pw-grafik-seite { flex: 1 1 24rem; min-inline-size: 0; margin: 0; }
.pw-grafik-seite-kopf {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 0.5rem;
  padding-block-end: 0.25rem;
}
.pw-grafik-seite-titel { font-weight: bold; }
/* Die Summe der Seite ist die Zahl, um die es geht: Sie steht grösser und
   kräftiger als alles andere im Kopf. */
.pw-grafik-seite-betrag {
  font-variant-numeric: tabular-nums;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}

.pw-grafik-flaeche { inline-size: 100%; block-size: auto; display: block; }
.pw-grafik-flaeche:focus-visible { outline: 0.15rem solid var(--color-primary-element); }

/* Farbe: der Farbton kommt je Departement aus den Daten, Sättigung und Helligkeit
   stehen hier — je tiefer die Ebene, desto kräftiger der Kreis. */
/* Die Bewegung kommt aus der Kraftsimulation, nicht aus einem Übergang: jeder
   Schritt setzt die Position neu, und ein Übergang darüber würde sie verschmieren.
   Nur die GRÖSSE bekommt einen Übergang, für den Wechsel der Ebene. */
.pw-bubble {
  --pw-bubble-saettigung: 55%;
  --pw-bubble-helligkeit: 92%;
}
.pw-bubble-e2 { --pw-bubble-helligkeit: 84%; }
.pw-bubble-e3 { --pw-bubble-helligkeit: 74%; }
.pw-bubble-e4 { --pw-bubble-helligkeit: 64%; }

/* Jeder Kreis nimmt den Zeiger an, auch der kleinste: Beim Überfahren nennt er
   seinen Namen und seinen Betrag, und gerade die kleinen tragen keine
   Beschriftung mehr. Der Klick trifft trotzdem die richtige Ebene — darum
   kümmert sich `oeffne()`, nicht das Abschalten der Zeiger. */

.pw-bubble circle {
  fill: hsl(var(--pw-bubble-farbton) var(--pw-bubble-saettigung) var(--pw-bubble-helligkeit));
  stroke: var(--color-main-background);
  stroke-width: 0.1rem;
  /* Der Kreis hat den Radius 1 und wird von seiner Gruppe skaliert; ein eigener
     Übergang gilt deshalb relativ zu seiner Grösse — er trägt nur das Anwachsen
     unter dem Zeiger. */
  transform-box: fill-box;
  transform-origin: center;
  transition: transform 0.25s ease-out;
}
.pw-bubble:not(.pw-bubble-blatt) circle { cursor: pointer; }
.pw-bubble circle:hover { filter: brightness(0.94); }

/* Der Kreis unter dem Zeiger tritt hervor: Er wächst ein Stück und legt sich mit
   einer kräftigeren Kante über seine Nachbarn — so ist vor dem Klick zu sehen,
   was gemeint ist. */
.pw-bubble-wach > circle {
  transform: scale(1.05);
  stroke-width: 0.2rem;
  filter: brightness(0.97);
}

.pw-bubble-titel {
  text-anchor: middle;
  dominant-baseline: middle;
  pointer-events: none;
  fill: var(--color-main-text);
  font-weight: 600;
  /* Der Name steht über den Kindkreisen, die hinter ihm liegen. Ein Rand in der
     Hintergrundfarbe, VOR der Füllung gezeichnet, hält ihn auf jedem Untergrund
     lesbar — ohne einen Kasten, der die Kreise verdeckt. */
  stroke: var(--color-main-background);
  stroke-width: 0.28em;
  stroke-linejoin: round;
  paint-order: stroke fill;
  transition: transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
}
.pw-bubble-betrag { font-variant-numeric: tabular-nums; font-weight: 400; }

/* Die Namen der Kinder erscheinen nur, solange der Zeiger auf ihrem Kreis steht.
   Sie sind kleiner und blenden sanft ein, damit das Bild nicht springt. */
.pw-bubble-titel-kind {
  font-weight: 400;
  animation: pw-einblenden 0.25s ease-out;
}

@keyframes pw-einblenden {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Wer Bewegung abgeschaltet hat, bekommt kein Einblenden und keine Übergänge.
   Die Kreise finden ihren Platz trotzdem — das ist die Anordnung selbst, keine
   Zierde; die Simulation läuft dann bis zur Ruhe und bleibt dort stehen. */
@media (prefers-reduced-motion: reduce) {
  .pw-bubble-titel { transition: none; }
  .pw-bubble circle { transition: none; }
  .pw-bubble-titel-kind { animation: none; }
}
</style>
