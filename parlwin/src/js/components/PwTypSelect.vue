<template>
  <PwSelect
    v-bind="$attrs"
    :model-value="modelValue || null"
    :options="options"
    :clearable="false"
    placeholder="Typ wählen …"
    @update:model-value="$emit('update:model-value', $event || '')"
  />
</template>

<script>
import PwSelect from './PwSelect.vue'

/**
 * Das EINE Typ-Auswahlwidget für eigene Geschäfte. Die auswählbaren Typen
 * pflegt die Administration; sie kommen als `options` herein.
 */
export default {
  name: 'PwTypSelect',
  components: { PwSelect },
  inheritAttrs: false,
  props: {
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
  },
  emits: ['update:model-value'],
}
</script>
