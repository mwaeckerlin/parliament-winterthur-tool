<template>
  <!-- Vollbild-Detail einer Produktegruppe (F110): oben die Zielvorgaben, darin die
       Produkte als Karten, dann die Erläuterungen mit erhaltener Formatierung.
       Rein informativ — Anträge stellt man im Antragsformular der Karte. -->
  <div class="pw-pg-detail">
    <header class="pw-pg-detail-kopf">
      <p class="pw-data-card-kicker">Produktegruppe {{ gruppe.code }}</p>
      <h2 class="pw-pg-detail-titel">{{ gruppe.name }}</h2>
      <p class="pw-pg-detail-kredit">
        Globalkredit <strong>{{ fr(gruppe.globalkredit.soll) }}</strong>
        <span class="pw-summe-diff" :class="diffKlasse(gruppe.globalkredit.soll - gruppe.globalkredit.sollVorjahr)">{{ diff(gruppe.globalkredit.soll - gruppe.globalkredit.sollVorjahr) }}</span>
      </p>
    </header>

    <section v-if="gruppe.zielvorgaben && gruppe.zielvorgaben.length" class="pw-pg-abschnitt">
      <h3>Parlamentarische Zielvorgaben</h3>
      <div v-for="ziel in zieleGruppiert" :key="ziel.nummer" class="pw-zielvorgabe">
        <h4>{{ ziel.titel }}</h4>
        <!-- Breite 7-Spalten-Tabelle scrollt auf schmalen Screens in ihrem eigenen
             Container, statt die Seite waagrecht zu sprengen oder zu quetschen. -->
        <div class="pw-zv-scroll">
          <table class="pw-zielvorgabe-tabelle">
            <thead>
              <tr>
                <th class="pw-zv-mg">Messgrösse</th>
                <th v-for="(sp, i) in spalten" :key="i" :class="{ 'pw-zv-soll': i === 2 }">{{ sp }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mg, i) in ziel.messgroessen" :key="i">
                <td class="pw-zv-mg">{{ mg.messgroesse }}</td>
                <td v-for="(w, j) in mg.werte" :key="j" :class="{ 'pw-zv-soll': j === 2 }">{{ w }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section v-if="gruppe.produkte && gruppe.produkte.length" class="pw-pg-abschnitt">
      <h3>Produkte</h3>
      <div class="pw-card-grid">
        <article v-for="(p, i) in gruppe.produkte" :key="i" class="pw-data-card pw-produkt-karte">
          <div class="pw-data-card-header">
            <div>
              <p class="pw-data-card-kicker">Produkt {{ p.nummer }}</p>
              <h4>{{ p.name }}</h4>
            </div>
            <span v-if="p.nettokosten && p.nettokosten.soll" class="pw-budget-betrag">{{ fr(p.nettokosten.soll) }}</span>
          </div>
          <!-- Kostentabelle des Produkts (Budgetzahlen): Kosten, Erlös, Nettokosten,
               Kostendeckungsgrad über Ist / Soll Vorjahr / Soll aktuell. -->
          <div v-if="p.kostentabelle && p.kostentabelle.length" class="pw-zv-scroll">
            <table class="pw-produkt-kosten">
              <thead>
                <tr>
                  <th></th>
                  <th>Ist {{ jahr - 2 }}</th>
                  <th>Soll {{ jahr - 1 }}</th>
                  <th class="pw-kosten-soll">Soll {{ jahr }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(z, k) in p.kostentabelle" :key="k">
                  <td>{{ z.label }}</td>
                  <td>{{ fr(z.werte[0]) }}</td>
                  <td>{{ fr(z.werte[1]) }}</td>
                  <td class="pw-kosten-soll">{{ fr(z.werte[2]) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <ul v-if="p.leistungen && p.leistungen.length" class="pw-produkt-leistungen">
            <li v-for="(l, j) in p.leistungen" :key="j">{{ l }}</li>
          </ul>
        </article>
      </div>
    </section>

    <section v-for="abschnitt in erlaeuterungen" :key="abschnitt.titel" class="pw-pg-abschnitt">
      <h3>{{ abschnitt.titel }}</h3>
      <template v-for="(block, i) in abschnitt.bloecke" :key="i">
        <ul v-if="block.liste" class="pw-erlaeuterung-liste">
          <li v-for="(z, j) in block.zeilen" :key="j">{{ z }}</li>
        </ul>
        <p v-else>{{ block.zeilen[0] }}</p>
      </template>
    </section>
  </div>
</template>

<script>
export default {
  name: 'BudgetPgDetail',
  props: {
    gruppe: { type: Object, required: true },
    jahr: { type: Number, default: 0 },
  },
  computed: {
    // Spaltentitel der Zielvorgaben relativ zum Budgetjahr (Ist Vorjahr, Soll Vorjahr,
    // Soll aktuell = entscheidbar, drei Planjahre).
    spalten() {
      const j = this.jahr
      return [`Ist ${j - 2}`, `Soll ${j - 1}`, `Soll ${j}`, `Plan ${j + 1}`, `Plan ${j + 2}`, `Plan ${j + 3}`]
    },
    // Zielvorgaben nach Ziel gruppieren (ein Ziel kann mehrere Messgrössen tragen).
    zieleGruppiert() {
      const map = new Map()
      for (const zv of this.gruppe.zielvorgaben || []) {
        if (!map.has(zv.zielNummer)) { map.set(zv.zielNummer, { nummer: zv.zielNummer, titel: zv.zielTitel, messgroessen: [] }) }
        map.get(zv.zielNummer).messgroessen.push({ messgroesse: zv.messgroesse, werte: zv.werte })
      }
      return Array.from(map.values())
    },
    // Erläuterungen als Abschnitte mit Blöcken (Absätze und Aufzählungen), die
    // erhaltene Formatierung (Zeilenumbrüche, Aufzählungspunkte) wird gerendert.
    erlaeuterungen() {
      const felder = [
        { titel: 'Auftrag', text: this.gruppe.auftrag },
        { titel: 'Begründung Abweichung', text: this.gruppe.begruendungAbweichung },
        { titel: 'Erläuterungen zum Stellenplan', text: this.gruppe.erlaeuterungStellen },
        { titel: 'Begründung Finanzplan', text: this.gruppe.begruendungFap },
        { titel: 'Wesentliche Massnahmen und Projekte', text: this.gruppe.massnahmen },
      ]
      return felder
        .filter(f => f.text && String(f.text).trim())
        .map(f => ({ titel: f.titel, bloecke: this.bloecke(String(f.text)) }))
    },
  },
  methods: {
    fr(n) {
      const v = Math.round(Number(n) || 0)
      const ziffern = Math.abs(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '’')
      return (v < 0 ? '−' : '') + ziffern
    },
    diff(n) {
      const v = Math.round(Number(n) || 0)
      return (v > 0 ? '+' : (v < 0 ? '−' : '±')) + this.fr(Math.abs(v))
    },
    diffKlasse(n) {
      return n > 0 ? 'pw-diff-plus' : (n < 0 ? 'pw-diff-minus' : '')
    },
    // Teilt einen Erläuterungstext in Blöcke: aufeinanderfolgende Aufzählungszeilen
    // («- …») werden zu einer Liste, alles andere zu Absätzen.
    bloecke(text) {
      const out = []
      for (const roh of text.split('\n')) {
        const z = roh.trim()
        if (!z) { continue }
        const bullet = /^[-–•]/.test(z)
        const inhalt = bullet ? z.replace(/^[-–•]\s*/, '') : z
        const letzter = out[out.length - 1]
        if (bullet && letzter && letzter.liste) { letzter.zeilen.push(inhalt) } else { out.push({ liste: bullet, zeilen: [inhalt] }) }
      }
      return out
    },
  },
}
</script>
