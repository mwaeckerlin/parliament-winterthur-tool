<template>
  <section class="pw-view-content pw-protokoll">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Protokoll</h2>
      <span class="pw-view-count">{{ ereignisse.length }}</span>
    </header>

    <p v-if="!ereignisse.length" class="pw-protokoll-leer">Noch keine Ereignisse protokolliert.</p>

    <!-- Ein Protokoll ist eine chronologische Liste (neueste oben), keine Kachel-
         wand: einspaltig, damit die Zeitfolge oben nach unten liest, mit einem
         Status-Randakzent (grün/rot) plus Zeichen (✓/✕), nicht Farbe allein. -->
    <ul v-else class="pw-protokoll-liste">
      <li
        v-for="e in ereignisse"
        :key="e.id"
        class="pw-data-card pw-protokoll-eintrag"
        :class="e.erfolg ? 'pw-protokoll-ok' : 'pw-protokoll-fehler'"
      >
        <p class="pw-protokoll-kopf">
          <span class="pw-protokoll-status" :class="e.erfolg ? 'pw-positiv' : 'pw-negativ'">{{ e.erfolg ? '✓' : '✕' }}</span>
          <span class="pw-protokoll-meta">{{ artLabel(e.art) }} · {{ formatZeit(e.zeitpunkt) }} · {{ e.ausgeloestVon }}</span>
        </p>
        <h3 class="pw-protokoll-titel">{{ e.titel }}</h3>
        <p v-if="e.meldung" class="pw-protokoll-meldung">{{ e.meldung }}</p>
      </li>
    </ul>
  </section>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const ART_LABEL = {
  sync: 'Synchronisation',
  budget_import: 'Budget-Import',
  budget_reimport: 'Budget neu eingelesen',
  novemberbrief: 'Novemberbrief',
  sitzungsantraege: 'Sitzungsanträge',
  fehler: 'Fehler',
}

// Protokoll-Ansicht (F105): die Historie der Synchronisationen und Budget-Importe,
// neueste zuerst — und der Ort, an dem Parsing-Probleme (Fehler-Ereignisse) sichtbar
// werden. Nutzt exakt dieselbe Seiten-/Card-Struktur wie alle anderen Ansichten.
export default {
  name: 'Protokoll',
  data() {
    return {
      ereignisse: [],
    }
  },
  mounted() {
    this.laden()
  },
  methods: {
    async laden() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/protokoll'))
        this.ereignisse = (data && data.ereignisse) || []
      } catch (f) {
        console.error('Protokoll laden fehlgeschlagen', f)
      }
    },
    artLabel(art) {
      return ART_LABEL[art] || art
    },
    formatZeit(ts) {
      return new Date((ts || 0) * 1000).toLocaleString('de-CH')
    },
  },
}
</script>
