<template>
  <NcSelect
    v-bind="$attrs"
    :model-value="modelValue"
    :options="verfuegbareOptionen"
    multiple
    :close-on-select="false"
    @update:model-value="$emit('update:model-value', $event)"
  />
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

/**
 * Generisches Multi-Select-Widget.
 *
 * Verhalten: Bereits ausgewählte Einträge werden grundsätzlich aus der
 * Auswahlliste entfernt – sie sind nur noch als Chip sichtbar.
 *
 * Übernimmt alle übrigen Props/Slots/Events von NcSelect.
 *
 * Vergleich der Optionen: bei Objekten via `value`-Feld, ansonsten
 * Identität (===). Damit funktioniert es sowohl für String-Listen als
 * auch für Objekte mit `{ label, value }`.
 */
export default {
  name: 'PwMultiSelect',
  components: { NcSelect },
  inheritAttrs: false,
  props: {
    modelValue: {
      type: Array,
      default: () => [],
    },
    options: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['update:model-value'],
  computed: {
    verfuegbareOptionen() {
      const gewaehlt = Array.isArray(this.modelValue) ? this.modelValue : []
      const ident = (x) => {
        if (x === null || typeof x !== 'object') return x
        if ('value' in x) return x.value
        if ('key' in x) return x.key
        if ('id' in x) return x.id
        return x
      }
      const gewaehltIds = new Set(gewaehlt.map(ident))
      return this.options.filter((o) => !gewaehltIds.has(ident(o)))
    },
  },
}
</script>
