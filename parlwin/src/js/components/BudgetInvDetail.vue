<template>
  <!-- Vollbild-Detail eines Investitionsprojekts (F87): oben die Jahresreihe vom
       bereits Investierten bis in die Planjahre, darunter die einzelnen Konten mit
       ihrem bewilligten Kredit und dem Datum der Bewilligung. Rein informativ —
       Anträge stellt man im Antragsformular der Karte. -->
  <div class="pw-pg-detail">
    <header class="pw-pg-detail-kopf">
      <p class="pw-data-card-kicker">{{ projekt.departement }}<template v-if="projekt.cluster"> · {{ projekt.cluster }}</template></p>
      <h2 class="pw-pg-detail-titel">{{ projekt.projekt }}</h2>
      <p class="pw-pg-detail-kredit">
        Budget {{ jahr }}: <strong>{{ fr(projekt.bu) }}</strong>
      </p>
    </header>

    <section class="pw-pg-abschnitt">
      <h3>Investitionen über die Jahre</h3>
      <div class="pw-zv-scroll">
        <table class="pw-produkt-kosten pw-inv-jahre">
          <thead>
            <tr>
              <th>bis {{ jahr - 1 }}</th>
              <th class="pw-kosten-soll">Budget {{ jahr }}</th>
              <th>Plan {{ jahr + 1 }}</th>
              <th>Plan {{ jahr + 2 }}</th>
              <th>Plan {{ jahr + 3 }}</th>
              <th>Summe der Jahre</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ fr(projekt.bereitsGetaetigt) }}</td>
              <td class="pw-kosten-soll">{{ fr(projekt.bu) }}</td>
              <td>{{ fr(projekt.fap1) }}</td>
              <td>{{ fr(projekt.fap2) }}</td>
              <td>{{ fr(projekt.fap3) }}</td>
              <td>{{ fr(summeDerJahre) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="projekt.planungskosten" class="pw-inv-planung">
        Davon Planung: <strong>{{ fr(projekt.planungskosten) }}</strong>
      </p>
      <!-- Zwei verschiedene Zahlen, zwei verschiedene Namen: Die Summe der
           Jahre sagt, was das Vorhaben bis zum letzten Planjahr kostet; der
           bewilligte Kredit sagt, was das Parlament dafür freigegeben hat. Ein
           Fraktionsmitglied fand am 19.10.2026 beide unter dem Namen
           «Gesamtkosten» und hielt sie für widersprüchlich. -->
      <p v-if="projekt.gesamtkosten" class="pw-inv-kredit">
        Bewilligter Kredit: <strong>{{ fr(projekt.gesamtkosten) }}</strong>
      </p>
    </section>

    <section v-if="konten.length" class="pw-pg-abschnitt">
      <h3>Bewilligte Kredite je Konto</h3>
      <div class="pw-zv-scroll">
        <table class="pw-produkt-kosten">
          <thead>
            <tr>
              <th>Konto</th>
              <th>Bezeichnung</th>
              <th class="pw-kosten-soll">Budget {{ jahr }}</th>
              <th>Kredit</th>
              <th>bewilligt am</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(k, i) in konten" :key="i">
              <td>{{ k.konto }}</td>
              <td class="pw-inv-konto-name">{{ k.name }}</td>
              <td class="pw-kosten-soll">{{ fr(k.betrag) }}</td>
              <td>{{ fr(k.kredit) }}</td>
              <td>{{ k.bewilligt || '—' }}</td>
            </tr>
          </tbody>
          <!-- Die Summe macht den bewilligten Kredit nachvollziehbar: Er ist
               die Summe dieser Konten. Ohne sie war der Überblick nur im PDF
               des Budgetbuchs zu holen (Rückmeldung eines Fraktionsmitglieds
               vom 19.10.2026). -->
          <tfoot>
            <tr class="pw-inv-konten-summe">
              <td />
              <td class="pw-inv-konto-name">Total</td>
              <td class="pw-kosten-soll">{{ fr(summeBetrag) }}</td>
              <td>{{ fr(summeKredit) }}</td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
    <p v-else class="pw-hinweis">Für dieses Projekt führt das Budgetbuch keine einzelnen Kredite auf.</p>
  </div>
</template>

<script>
import { frankenFormat } from '../utils'

/**
 * Detail eines Investitionsprojekts (F87). Zeigt, was vor dem Budgetjahr
 * investiert wurde, was in den Planjahren folgt, was das Projekt insgesamt
 * kostet, und die einzelnen Konten aus dem Anhang «Kontrolle der
 * Investitionskredite» mit ihrem Bewilligungsdatum.
 */
export default {
  name: 'BudgetInvDetail',
  props: {
    projekt: { type: Object, required: true },
    jahr: { type: Number, default: 0 },
  },
  computed: {
    konten() {
      return Array.isArray(this.projekt.konten) ? this.projekt.konten : []
    },
    /** Was das Vorhaben über alle ausgewiesenen Jahre kostet. */
    summeDerJahre() {
      const p = this.projekt
      return ['bereitsGetaetigt', 'bu', 'fap1', 'fap2', 'fap3']
        .reduce((summe, feld) => summe + (Number(p[feld]) || 0), 0)
    },
    /** Was die Konten im Budgetjahr tragen. */
    summeBetrag() {
      return this.konten.reduce((summe, k) => summe + (Number(k.betrag) || 0), 0)
    },
    /** Was an diesen Konten bewilligt ist — der bewilligte Kredit des Projekts. */
    summeKredit() {
      return this.konten.reduce((summe, k) => summe + (Number(k.kredit) || 0), 0)
    },
  },
  methods: {
    fr: frankenFormat,
  },
}
</script>

<style scoped lang="scss">
/* Die Bezeichnung eines Kontos ist die einzige Textspalte — sie bekommt den
   Platz, die Zahlenspalten bleiben schmal und rechtsbündig. */
.pw-inv-konto-name { text-align: start; }
.pw-inv-planung { margin-block-start: 0.5rem; }
/* Die Summe steht als Abschluss der Tabelle und trägt deshalb die obere Linie
   und das Gewicht; die Spalten bleiben die der Zeilen darüber. */
.pw-inv-konten-summe td {
  border-block-start: 2px solid var(--color-border-dark);
  font-weight: bold;
}
</style>
