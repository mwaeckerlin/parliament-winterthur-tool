<template>
  <div class="pw-erfassen">
    <!-- Was bei allen Anträgen gleich ist, steht einmal hier und gilt für jede
         neue Zeile. -->
    <div class="pw-erfassen-vorgaben">
      <NcSelect
        :model-value="herkunftOption"
        :options="herkunftOptionen"
        :clearable="false"
        input-label="Herkunft"
        @update:model-value="opt => vorgaben.herkunft = opt ? opt.value : 'eigene'"
      />
      <NcSelect
        v-model="antragstellerOption"
        :options="antragstellerOptionen"
        input-label="Antragsteller"
        placeholder="Person"
      />
      <PwMultiSelect
        :model-value="unterstuetzerOptionen"
        :options="fraktionOptionen"
        input-label="Unterstützende Fraktionen"
        @update:model-value="liste => vorgaben.unterstuetzer = Array.isArray(liste) ? liste : []"
      />
    </div>

    <table class="pw-erfassen-tabelle">
      <thead>
        <tr>
          <th>Position</th>
          <th>Richtung</th>
          <th>Betrag CHF</th>
          <th>Betrag %</th>
          <th>Begründung</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(z, i) in zeilen" :key="i" class="pw-erfassen-zeile">
          <td data-label="Position">
            <NcSelect
              :model-value="positionOption(z)"
              :options="positionOptionen"
              input-label="Position"
              placeholder="Nummer oder Name"
              @update:model-value="opt => z.position = opt ? opt.value : ''"
            />
          </td>
          <td data-label="Richtung">
            <NcCheckboxRadioSwitch :model-value="z.mehrausgabe" type="switch" @update:model-value="w => z.mehrausgabe = w">
              {{ z.mehrausgabe ? 'Mehrausgabe' : 'Reduktion' }}
            </NcCheckboxRadioSwitch>
          </td>
          <td data-label="Betrag CHF">
            <NcTextField :model-value="z.betrag" type="number" label="CHF" @update:model-value="w => betragGesetzt(z, w)" @keydown.enter="zeileSpeichern(i)" />
          </td>
          <td data-label="Betrag %">
            <NcTextField :model-value="z.prozent" type="number" label="%" @update:model-value="w => prozentGesetzt(z, w)" @keydown.enter="zeileSpeichern(i)" />
          </td>
          <!-- Dieselbe mehrzeilige, formatierbare Begründung wie im
               Antragsformular (Auftrag von Marc am 24.10.2026). -->
          <td data-label="Begründung">
            <PwWysiwyg
              :model-value="z.begruendung"
              aria-label="Begründung"
              placeholder="Warum dieser Antrag?"
              @update:model-value="w => z.begruendung = w"
            />
          </td>
          <td>
            <NcButton type="tertiary" @click="zeileEntfernen(i)">✕</NcButton>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="pw-erfassen-aktionen">
      <NcButton type="secondary" @click="zeileAnhaengen">+ Zeile</NcButton>
      <NcButton type="primary" :disabled="!speicherbar" @click="alleSpeichern">{{ speicherbareZahl }} Anträge anlegen</NcButton>
    </div>
    <p v-if="meldung" class="pw-erfassen-meldung">{{ meldung }}</p>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PwMultiSelect from './PwMultiSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'

const HERKUNFT = [
  { value: 'eigene', label: 'Eigen' },
  { value: 'fremde', label: 'Fremd' },
]

/**
 * Erfassungstabelle für viele Anträge (F126).
 *
 * Marc am 24.10.2026: «Die Eingabe von vielen Anträgen, irgendwie 50 Anträge
 * oder so, ist sehr mühsam. Wie kann man das vereinfachen?» Je Antrag brauchte
 * es bisher das Suchen seiner Produktegruppe in der Kachelwand, das Aufklappen
 * ihres Formulars und sieben Felder, von denen zwei den Antrag ausmachen.
 *
 * Hier steht eine Zeile je Antrag; Herkunft, Antragsteller und unterstützende
 * Fraktionen stehen einmal darüber und gelten für alle. Die Eingabetaste
 * speichert die Zeile und öffnet die nächste.
 */
export default {
  name: 'BudgetAntraegeErfassen',
  components: { NcButton, NcSelect, NcTextField, NcCheckboxRadioSwitch, PwMultiSelect, PwWysiwyg },
  props: {
    /** Wählbare Positionen: `{ value, label, basis }` je Produktegruppe und Vorhaben. */
    positionOptionen: { type: Array, default: () => [] },
    antragstellerOptionen: { type: Array, default: () => [] },
    fraktionOptionen: { type: Array, default: () => [] },
    /** Vorbelegter Antragsteller (zuletzt verwendet). */
    letzterAntragsteller: { type: String, default: '' },
  },
  emits: ['anlegen'],
  data() {
    return {
      herkunftOptionen: HERKUNFT,
      vorgaben: { herkunft: 'eigene', antragsteller: this.letzterAntragsteller, unterstuetzer: [] },
      zeilen: [this.leereZeile()],
      meldung: '',
    }
  },
  watch: {
    // Der zuletzt verwendete Antragsteller kommt aus der Elternansicht und
    // ändert sich, sobald dort ein Antrag angelegt wurde. Ohne diesen Nachzug
    // bliebe die Tabelle beim Stand des ersten Rendern stehen.
    letzterAntragsteller(neu) {
      if (!this.vorgaben.antragsteller) { this.vorgaben.antragsteller = neu }
    },
  },
  computed: {
    herkunftOption() {
      return HERKUNFT.find(h => h.value === this.vorgaben.herkunft) || HERKUNFT[0]
    },
    antragstellerOption: {
      get() {
        const wert = this.vorgaben.antragsteller
        return this.antragstellerOptionen.find(o => o.value === wert)
          || (wert ? { value: wert, label: wert } : null)
      },
      set(opt) { this.vorgaben.antragsteller = opt ? opt.value : '' },
    },
    unterstuetzerOptionen() {
      return (this.vorgaben.unterstuetzer || []).map(u => (u && u.value) ? u : { value: u, label: u })
    },
    speicherbareZeilen() {
      return this.zeilen.filter(z => this.zeileVollstaendig(z))
    },
    speicherbareZahl() {
      return this.speicherbareZeilen.length
    },
    speicherbar() {
      return this.speicherbareZahl > 0
    },
  },
  methods: {
    leereZeile() {
      return { position: '', mehrausgabe: false, betrag: '', prozent: '', begruendung: '' }
    },
    zeileVollstaendig(z) {
      return !!z.position && String(z.betrag) !== '' && !isNaN(Number(z.betrag))
    },
    positionOption(z) {
      return this.positionOptionen.find(o => o.value === z.position) || null
    },
    basisVon(z) {
      const opt = this.positionOption(z)
      return opt && opt.basis ? Number(opt.basis) : 0
    },
    // CHF und Prozent rechnen sich wechselseitig um, wie im Einzelformular (F95).
    betragGesetzt(z, wert) {
      z.betrag = wert
      const basis = this.basisVon(z)
      if (basis > 0 && wert !== '' && wert !== null) {
        z.prozent = String(Math.round(Math.abs(Number(wert)) / basis * 10000) / 100)
      } else if (wert === '' || wert === null) {
        z.prozent = ''
      }
    },
    prozentGesetzt(z, wert) {
      z.prozent = wert
      const basis = this.basisVon(z)
      if (basis > 0 && wert !== '' && wert !== null) {
        z.betrag = String(Math.round(basis * Math.abs(Number(wert)) / 100))
      } else if (wert === '' || wert === null) {
        z.betrag = ''
      }
    },
    zeileAnhaengen() {
      this.zeilen.push(this.leereZeile())
    },
    zeileEntfernen(i) {
      this.zeilen.splice(i, 1)
      if (this.zeilen.length === 0) { this.zeilen.push(this.leereZeile()) }
    },
    // Die Eingabetaste schliesst die Zeile ab und öffnet die nächste: die Hand
    // bleibt auf der Tastatur.
    zeileSpeichern(i) {
      const z = this.zeilen[i]
      if (!this.zeileVollstaendig(z)) { return }
      if (i === this.zeilen.length - 1) { this.zeileAnhaengen() }
    },
    alleSpeichern() {
      const antraege = this.speicherbareZeilen.map(z => this.alsAntrag(z))
      if (antraege.length === 0) { return }
      this.$emit('anlegen', antraege)
      this.zeilen = [this.leereZeile()]
      this.meldung = antraege.length + ' Anträge angelegt.'
    },
    /** Eine Zeile als Antrag, wie ihn das Einzelformular abschickt. */
    alsAntrag(z) {
      const vorzeichen = z.mehrausgabe ? 1 : -1
      return {
        position: z.position,
        herkunft: this.vorgaben.herkunft,
        antragsteller: this.vorgaben.antragsteller,
        unterstuetzer: (this.vorgaben.unterstuetzer || []).map(u => (u && u.value) ? u.value : u),
        mehrausgabe: z.mehrausgabe,
        betragDelta: vorzeichen * Math.abs(Math.round(Number(z.betrag))),
        begruendung: z.begruendung,
      }
    },
  },
}
</script>

<style scoped lang="scss">
.pw-erfassen { display: flex; flex-direction: column; gap: var(--pw-gap-1); }
.pw-erfassen-vorgaben { display: flex; gap: var(--pw-gap-1); flex-wrap: wrap; }
.pw-erfassen-vorgaben > * { flex: 1 1 14rem; min-inline-size: 0; }
.pw-erfassen-tabelle { inline-size: 100%; border-collapse: collapse; }
.pw-erfassen-tabelle th { text-align: start; font-weight: 400; font-size: var(--pw-font-sm); color: var(--pw-muted); }
.pw-erfassen-tabelle td { vertical-align: top; padding-block: var(--pw-space-2xs); padding-inline-end: var(--pw-space-xs); min-inline-size: 0; }
.pw-erfassen-aktionen { display: flex; gap: var(--pw-gap-1); flex-wrap: wrap; }

/* Am Telefon steht jede Zeile als Block, jedes Feld mit seiner Beschriftung. */
@media (max-width: 54rem) {
  .pw-erfassen-tabelle thead { display: none; }
  .pw-erfassen-tabelle tr { display: block; margin-block-end: var(--pw-gap-1); }
  .pw-erfassen-tabelle td { display: block; padding-inline-end: 0; }
  .pw-erfassen-tabelle td::before { content: attr(data-label); display: block; font-size: var(--pw-font-sm); color: var(--pw-muted); }
}
</style>
