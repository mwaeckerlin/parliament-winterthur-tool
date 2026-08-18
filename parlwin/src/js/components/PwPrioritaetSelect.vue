<template>
  <PwSelect
    v-bind="$attrs"
    :model-value="modelValue || null"
    :options="optionen"
    :clearable="true"
    placeholder="—"
    @update:model-value="$emit('update:model-value', $event || '')"
  />
</template>

<script>
import PwSelect from './PwSelect.vue'
import { PRIORITAETEN } from '../utils'

/**
 * Das EINE Prioritäts-Auswahlwidget für Geschäfte UND Vorstösse. Kapselt die
 * Prioritätsstufen (PRIORITAETEN) an einem Ort — kein Aufrufer baut die
 * Optionsliste oder die Wert↔Option-Umrechnung selbst.
 */
export default {
  name: 'PwPrioritaetSelect',
  components: { PwSelect },
  inheritAttrs: false,
  props: {
    modelValue: { type: String, default: '' },
  },
  emits: ['update:model-value'],
  computed: {
    optionen() {
      return PRIORITAETEN
    },
  },
}
</script>
