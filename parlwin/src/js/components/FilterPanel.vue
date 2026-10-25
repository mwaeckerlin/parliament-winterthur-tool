<template>
  <!-- Gemeinsames Filter-Widget für alle Listen: ein flexibler Teil (die
       view-eigenen Filterfelder im Slot) und ein fester Teil unten, der IMMER den
       «Filter zurücksetzen»-Knopf trägt. So hat jede Ansicht denselben Reset,
       ohne ihn einzeln einzubauen. -->
  <div class="pw-filter-body" @focusin="feldNachOben">
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
  methods: {
    /**
     * Ein angetipptes Auswahlfeld rückt an den Anfang des Filterbereichs.
     *
     * Am Telefon stehen die Filter in der Navigation, und ein Auswahlfeld
     * bekommt beim Antippen den Fokus in sein Suchfeld: Die Bildschirmtastatur
     * fährt hoch und lässt rund die Hälfte der Fläche übrig. Die Liste öffnet
     * sich nach unten und lag dadurch hinter der Tastatur — gemessen am
     * 19.10.2026 ihr erster Eintrag bei 481 Punkten, sichtbar waren 439. Oben
     * im Filterbereich ist darunter Platz für die Liste.
     */
    feldNachOben(ereignis) {
      const feld = ereignis.target && ereignis.target.closest
        ? ereignis.target.closest('.v-select')
        : null
      if (feld && typeof feld.scrollIntoView === 'function') {
        feld.scrollIntoView({ block: 'start', behavior: 'smooth' })
      }
    },
  },
}
</script>
