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
        Budget {{ jahr }} <strong>{{ fr(projekt.bu) }}</strong>
      </p>
    </header>

    <section class="pw-pg-abschnitt">
      <h3>Investitionen über die Jahre</h3>
      <div class="pw-zv-scroll">
        <table class="pw-produkt-kosten">
          <thead>
            <tr>
              <th>bis {{ jahr - 1 }}</th>
              <th class="pw-kosten-soll">Budget {{ jahr }}</th>
              <th>Plan {{ jahr + 1 }}</th>
              <th>Plan {{ jahr + 2 }}</th>
              <th>Plan {{ jahr + 3 }}</th>
              <th>Gesamtkosten</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ fr(projekt.bereitsGetaetigt) }}</td>
              <td class="pw-kosten-soll">{{ fr(projekt.bu) }}</td>
              <td>{{ fr(projekt.fap1) }}</td>
              <td>{{ fr(projekt.fap2) }}</td>
              <td>{{ fr(projekt.fap3) }}</td>
              <td>{{ fr(projekt.gesamtkosten) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="projekt.planungskosten" class="pw-inv-planung">
        Davon Planung: <strong>{{ fr(projekt.planungskosten) }}</strong>
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
</style>
