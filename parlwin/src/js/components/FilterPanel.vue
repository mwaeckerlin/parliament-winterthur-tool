<template>
  <!-- Gemeinsames Filter-Widget für alle Listen: ein flexibler Teil (die
       view-eigenen Filterfelder im Slot) und ein fester Teil unten, der IMMER den
       «Filter zurücksetzen»-Knopf trägt. So hat jede Ansicht denselben Reset,
       ohne ihn einzeln einzubauen. -->
  <div class="pw-filter-body">
    <slot />
    <div class="pw-filter-fix">
      <NcButton type="tertiary" wide @click="$emit('reset')">Filter zurücksetzen</NcButton>
    </div>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
  name: 'FilterPanel',
  components: { NcButton },
  emits: ['reset'],
}
</script>
