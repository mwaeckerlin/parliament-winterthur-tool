<template>
  <div class="pw-pauschal-form pw-data-card">
    <div class="pw-antrag-form-zeile">
      <NcTextField v-model="betragMag" type="number" label="Einsparung CHF" />
      <NcTextField v-model="prozentMag" type="number" label="oder in Prozent" />
      <NcCheckboxRadioSwitch :model-value="einreichen" type="switch" @update:model-value="v => einreichen = v">
        {{ einreichen ? 'Einreichen' : 'Nicht einreichen' }}
      </NcCheckboxRadioSwitch>
    </div>
    <div class="pw-antrag-form-zeile">
      <PwMultiSelect
        :model-value="ausnahmenGewaehlt"
        :options="gruppenOptionen"
        input-label="Ausnahmen (Produktegruppen)"
        @update:model-value="ausnahmenSetzen"
      />
      <NcTextField v-model="begruendung" label="Begründung" />
    </div>
    <div class="pw-antrag-form-zeile">
      <NcButton type="secondary" @click="speichern">Übernehmen</NcButton>
      <NcButton type="tertiary" @click="$emit('delete')">Löschen</NcButton>
    </div>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PwMultiSelect from './PwMultiSelect.vue'

/**
 * Editor für einen einzelnen (festen) Pauschalantrag (F100): Einsparung in CHF
 * oder in Prozent des ursprünglichen Aufwands, Einreichen-Entscheid, ausgenommene
 * Produktegruppen (F101) und Begründung. «Übernehmen» meldet die Felder an die
 * Elternkomponente.
 */
export default {
  name: 'BudgetPauschalForm',
  components: { NcButton, NcTextField, NcCheckboxRadioSwitch, PwMultiSelect },
  props: {
    pauschal: { type: Object, required: true },
    produktegruppen: { type: Array, default: () => [] },
  },
  emits: ['save', 'delete'],
  data() {
    return {
      betragMag: this.pauschal.betrag ? String(Math.abs(this.pauschal.betrag)) : '',
      prozentMag: this.pauschal.prozent ? String(Math.abs(this.pauschal.prozent)) : '',
      einreichen: (this.pauschal.haltung || 'einreichen') !== 'nicht_einreichen',
      begruendung: this.pauschal.begruendung || '',
      ausnahmen: Array.isArray(this.pauschal.ausnahmen) ? [...this.pauschal.ausnahmen] : [],
    }
  },
  computed: {
    gruppenOptionen() {
      return this.produktegruppen.map(g => ({ value: g.code, label: g.code + ' ' + (g.name || '') }))
    },
    ausnahmenGewaehlt() {
      return this.gruppenOptionen.filter(o => this.ausnahmen.includes(o.value))
    },
  },
  methods: {
    ausnahmenSetzen(liste) {
      this.ausnahmen = (Array.isArray(liste) ? liste : []).map(o => (o && o.value) || o)
    },
    speichern() {
      // Einsparung ist immer eine Reduktion (negatives Vorzeichen). Prozent hat
      // Vorrang, wenn gesetzt.
      const felder = {
        haltung: this.einreichen ? 'einreichen' : 'nicht_einreichen',
        begruendung: this.begruendung,
        ausnahmen: this.ausnahmen,
      }
      if (this.prozentMag !== '') {
        felder.prozent = -Math.abs(Number(this.prozentMag))
        felder.betrag = 0
      } else {
        felder.betrag = -Math.abs(Math.round(Number(this.betragMag) || 0))
        felder.prozent = 0
      }
      this.$emit('save', felder)
    },
  },
}
</script>

<style scoped lang="scss">
.pw-pauschal-form { display: flex; flex-direction: column; gap: 0.4rem; margin-block-start: 0.4rem; }
.pw-pauschal-form .pw-antrag-form-zeile { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; }
.pw-pauschal-form .pw-antrag-form-zeile > * { flex: 1 1 10rem; }
</style>
