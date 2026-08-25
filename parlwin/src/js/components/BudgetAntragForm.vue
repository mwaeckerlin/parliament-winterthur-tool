<template>
  <div class="pw-antrag-form">
    <div class="pw-antrag-form-zeile">
      <NcSelect
        :model-value="herkunftOption"
        :options="herkunftOptionen"
        :clearable="false"
        input-label="Herkunft"
        @update:model-value="herkunftGewaehlt"
      />
      <NcSelect
        v-model="antragstellerOption"
        :options="antragstellerOptionen"
        input-label="Antragsteller"
        :placeholder="form.herkunft === 'fremde' ? 'Fraktion / Person' : 'Person'"
      />
    </div>
    <div v-if="mitStellen" class="pw-antrag-form-zeile">
      <NcTextField :model-value="form.stellen" type="number" label="Stellen (− kürzen)" @update:model-value="form.stellen = $event" />
      <NcTextField :model-value="form.betrag" type="number" label="Betrag CHF (optional)" @update:model-value="betragGeaendert" />
    </div>
    <div v-else class="pw-antrag-form-zeile">
      <NcCheckboxRadioSwitch :model-value="form.mehrausgabe" type="switch" @update:model-value="form.mehrausgabe = $event">
        {{ form.mehrausgabe ? 'Mehrausgabe' : 'Reduktion' }}
      </NcCheckboxRadioSwitch>
      <NcTextField :model-value="chfMagnitude" type="number" label="Betrag CHF" @update:model-value="betragGeaendert" />
      <NcTextField :model-value="prozentMagnitude" type="number" label="Betrag %" @update:model-value="prozentGeaendert" />
    </div>
    <div class="pw-antrag-form-zeile">
      <NcSelect
        :model-value="haltungOption"
        :options="haltungOptionen"
        :clearable="false"
        input-label="Unsere Haltung"
        @update:model-value="haltungGewaehlt"
      />
      <PwMultiSelect
        :model-value="unterstuetzerOptionen"
        :options="fraktionOptionen"
        input-label="Unterstützende Fraktionen"
        @update:model-value="unterstuetzerGewaehlt"
      />
    </div>
    <div class="pw-antrag-form-zeile">
      <NcTextField :model-value="form.begruendung" label="Begründung" @update:model-value="form.begruendung = $event" />
      <NcButton type="secondary" @click="$emit('save')">Antrag</NcButton>
    </div>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PwMultiSelect from './PwMultiSelect.vue'

const HERKUNFT = [
  { value: 'eigene', label: 'Eigen' },
  { value: 'fremde', label: 'Fremd' },
]

/**
 * Antragsformular (F94/F95/F97/F98). Bindet an ein Formularobjekt der
 * Elternkomponente (betrag/prozent/mehrausgabe/herkunft/antragsteller/haltung/
 * unterstuetzer/begruendung). Die Richtung ist das Vorzeichen (Reduktion −,
 * Mehrausgabe +); CHF und Prozent werden über die Bezugsbasis wechselseitig
 * berechnet (F95).
 */
export default {
  name: 'BudgetAntragForm',
  components: { NcButton, NcSelect, NcTextField, NcCheckboxRadioSwitch, PwMultiSelect },
  props: {
    form: { type: Object, required: true },
    antragstellerOptionen: { type: Array, default: () => [] },
    haltungOptionen: { type: Array, default: () => [] },
    fraktionOptionen: { type: Array, default: () => [] },
    mitStellen: { type: Boolean, default: false },
    // Budgetwert der Position als Basis für die %↔CHF-Rechnung (F95).
    basis: { type: Number, default: 0 },
  },
  emits: ['save', 'herkunft'],
  data() {
    return { herkunftOptionen: HERKUNFT }
  },
  computed: {
    herkunftOption() {
      return HERKUNFT.find(h => h.value === this.form.herkunft) || HERKUNFT[0]
    },
    antragstellerOption: {
      get() {
        return this.antragstellerOptionen.find(o => o.value === this.form.antragsteller)
          || (this.form.antragsteller ? { value: this.form.antragsteller, label: this.form.antragsteller } : null)
      },
      set(opt) { this.form.antragsteller = opt ? opt.value : '' },
    },
    haltungOption() {
      return this.haltungOptionen.find(o => o.value === this.form.haltung) || this.haltungOptionen[0]
    },
    unterstuetzerOptionen() {
      return (this.form.unterstuetzer || []).map(u => (u && u.value)
        ? u
        : { value: u, label: u })
    },
    chfMagnitude() {
      return this.form.betrag === '' || this.form.betrag === null ? '' : String(Math.abs(Number(this.form.betrag)))
    },
    prozentMagnitude() {
      return this.form.prozent === '' || this.form.prozent === null ? '' : String(Math.abs(Number(this.form.prozent)))
    },
  },
  methods: {
    herkunftGewaehlt(opt) {
      this.$emit('herkunft', opt ? opt.value : 'eigene')
    },
    haltungGewaehlt(opt) {
      this.form.haltung = opt ? opt.value : this.haltungOptionen[0]?.value
    },
    unterstuetzerGewaehlt(liste) {
      this.form.unterstuetzer = Array.isArray(liste) ? liste : []
    },
    // Eingabe im CHF-Feld → Prozent nachrechnen (F95).
    betragGeaendert(wert) {
      this.form.betrag = wert
      if (this.basis > 0 && wert !== '' && wert !== null) {
        this.form.prozent = String(Math.round(Math.abs(Number(wert)) / this.basis * 10000) / 100)
      } else if (wert === '' || wert === null) {
        this.form.prozent = ''
      }
    },
    // Eingabe im Prozent-Feld → CHF nachrechnen (F95).
    prozentGeaendert(wert) {
      this.form.prozent = wert
      if (this.basis > 0 && wert !== '' && wert !== null) {
        this.form.betrag = String(Math.round(this.basis * Math.abs(Number(wert)) / 100))
      } else if (wert === '' || wert === null) {
        this.form.betrag = ''
      }
    },
  },
}
</script>

<style scoped lang="scss">
.pw-antrag-form { display: flex; flex-direction: column; gap: 0.4rem; margin-block-start: 0.4rem; }
.pw-antrag-form-zeile { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; }
.pw-antrag-form-zeile > * { flex: 1 1 10rem; }
</style>
