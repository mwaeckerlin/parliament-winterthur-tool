<template>
  <NcButton type="tertiary" class="pw-loeschen" :aria-label="label" :title="label" @click.stop="$emit('click', $event)">✕</NcButton>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

/**
 * Einheitlicher Löschen-Knopf für die ganze App: überall dasselbe Aussehen (ein
 * tertiäres «✕», kein Papierkorb, kein Text) und — wo möglich — dieselbe Stelle
 * (oben rechts am Element bzw. am Ende einer Zeile). Einheitlichkeit im Layout ist
 * oberstes Gebot; darum gibt es genau EINEN Löschen-Knopf und nicht viele Varianten.
 */
export default {
  name: 'PwLoeschen',
  components: { NcButton },
  props: {
    // Barrierefreie Beschriftung, konkret benannt («Antrag löschen», «Notiz löschen»).
    label: { type: String, default: 'Löschen' },
  },
  emits: ['click'],
}
</script>
