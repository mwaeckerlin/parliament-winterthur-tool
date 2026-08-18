<template>
  <PwSelect
    v-bind="$attrs"
    :model-value="modelValue || null"
    :options="options"
    :format="kuerze"
    :clearable="true"
    placeholder="—"
    @update:model-value="$emit('update:model-value', $event || '')"
  />
</template>

<script>
import PwSelect from './PwSelect.vue'
import { kuerze } from '../utils'

/**
 * Das EINE Kommissions-Auswahlwidget. Zeigt die Kommissionsnamen gekürzt an
 * (gleiche Kürzel-Regeln wie überall sonst), speichert aber den vollen Namen.
 * Höchstens eine Kommission; keine ist ebenfalls gültig (clearable).
 */
export default {
  name: 'PwKommissionSelect',
  components: { PwSelect },
  inheritAttrs: false,
  props: {
    modelValue: { type: String, default: '' },
    // Aktive Kommissionsnamen (volle Namen).
    options: { type: Array, default: () => [] },
  },
  emits: ['update:model-value'],
  methods: { kuerze },
}
</script>
