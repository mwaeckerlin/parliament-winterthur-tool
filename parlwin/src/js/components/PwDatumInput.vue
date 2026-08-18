<template>
  <input
    v-bind="$attrs"
    :value="modelValue || ''"
    type="date"
    @change="$emit('update:model-value', $event.target.value)"
  />
</template>

<script>
/**
 * Das EINE Datums-Eingabewidget. Kapselt das native `type="date"`-Feld und
 * dessen `@change → update:model-value`-Verhalten (Rohwert ISO `YYYY-MM-DD`).
 * Die Layout-Klasse gibt der Aufrufer per Attribut mit (z.B. `pw-input` im
 * Formular, `pw-form-feld` in der Sitzungsmaske), damit derselbe Widget-Code
 * überall passt, ohne bestehende Layouts zu überschreiben.
 */
export default {
  name: 'PwDatumInput',
  inheritAttrs: false,
  props: {
    modelValue: { type: String, default: '' },
  },
  emits: ['update:model-value'],
}
</script>
