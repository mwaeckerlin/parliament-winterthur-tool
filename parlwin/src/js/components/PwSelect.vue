<template>
  <NcSelect
    v-bind="$attrs"
    :model-value="gewaehlt"
    :options="aufbereitet"
    @update:model-value="onUpdate"
  />
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

/**
 * Einheitliches Einzel-Auswahl-Widget — die gemeinsame Basis für ALLE
 * konkreten Datentyp-Selects (Kommission, Priorität, Typ, …). Kein Aufrufer
 * benutzt NcSelect für einen Wert direkt; jeder Datentyp hat genau ein davon
 * abgeleitetes Widget.
 *
 * Ein-/Ausgabe ist immer der ROHWERT (String), nie das Option-Objekt. Die
 * Anzeige läuft über einen optionalen `format`-Formatierer (z.B. Kürzel),
 * gespeichert wird trotzdem der Originalwert.
 *
 * `options` akzeptiert eine String-Liste oder `{ label, value }`-Objekte.
 * Übrige Props/Attribute (clearable, placeholder, aria-label …) reicht es an
 * NcSelect durch.
 */
export default {
  name: 'PwSelect',
  components: { NcSelect },
  inheritAttrs: false,
  props: {
    modelValue: { default: null },
    options: { type: Array, default: () => [] },
    // Formatiert NUR die Anzeige (Label); der gespeicherte Wert bleibt roh.
    format: { type: Function, default: null },
  },
  emits: ['update:model-value'],
  computed: {
    aufbereitet() {
      return this.options.map((o) => this.eintrag(o))
    },
    gewaehlt() {
      if (this.modelValue === null || this.modelValue === undefined || this.modelValue === '') return null
      return this.aufbereitet.find((o) => o.value === this.modelValue) || this.eintrag(this.modelValue)
    },
  },
  methods: {
    eintrag(o) {
      const wert = (o !== null && typeof o === 'object') ? o.value : o
      const roh = (o !== null && typeof o === 'object') ? (o.label ?? o.value) : o
      return { value: wert, label: this.format ? this.format(roh) : String(roh) }
    },
    onUpdate(option) {
      const wert = (option !== null && typeof option === 'object') ? option.value : option
      this.$emit('update:model-value', wert === undefined ? null : wert)
    },
  },
}
</script>
