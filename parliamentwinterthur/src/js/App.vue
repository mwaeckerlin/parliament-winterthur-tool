<template>
  <div class="pw-app">
    <!-- Navigationsleiste -->
    <div class="pw-nav">
      <button
        v-for="ansicht in ansichten"
        :key="ansicht.key"
        :class="['pw-nav-btn', { aktiv: aktiveAnsicht === ansicht.key }]"
        @click="aktiveAnsicht = ansicht.key"
      >
        {{ ansicht.bezeichnung }}
      </button>
    </div>

    <!-- Synchronisationsstatus -->
    <div v-if="syncMeldung" class="pw-sync-meldung" :class="syncFehler ? 'fehler' : 'erfolg'">
      {{ syncMeldung }}
    </div>

    <!-- Hauptinhalt -->
    <Geschaeftsliste
      v-if="aktiveAnsicht === 'geschaefte'"
      :mitglieder="mitglieder"
      @aktualisiert="ladeGeschaefte"
    />
    <Sitzungsliste
      v-else-if="aktiveAnsicht === 'sitzungen'"
    />
    <Mitgliederliste
      v-else-if="aktiveAnsicht === 'mitglieder'"
      :mitglieder="mitglieder"
    />
    <Kommissionsliste
      v-else-if="aktiveAnsicht === 'kommissionen'"
    />
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Geschaeftsliste from './components/Geschaeftsliste.vue'
import Sitzungsliste from './components/Sitzungsliste.vue'
import Mitgliederliste from './components/Mitgliederliste.vue'
import Kommissionsliste from './components/Kommissionsliste.vue'

export default {
  name: 'ParliamentWinterthurApp',
  components: {
    Geschaeftsliste,
    Sitzungsliste,
    Mitgliederliste,
    Kommissionsliste,
  },
  data() {
    return {
      aktiveAnsicht: 'geschaefte',
      mitglieder: [],
      syncMeldung: '',
      syncFehler: false,
      ansichten: [
        { key: 'geschaefte',  bezeichnung: 'Geschäfte' },
        { key: 'sitzungen',   bezeichnung: 'Sitzungen' },
        { key: 'mitglieder',  bezeichnung: 'Mitglieder' },
        { key: 'kommissionen', bezeichnung: 'Kommissionen' },
      ],
    }
  },
  mounted() {
    this.ladeMitglieder()
  },
  methods: {
    async ladeMitglieder() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parliamentwinterthur/mitglieder') + '?aktiv=1')
        this.mitglieder = data
      } catch (fehler) {
        console.error('Fehler beim Laden der Mitglieder:', fehler)
      }
    },
    async ladeGeschaefte() {
      // Wird durch Geschaeftsliste selbst geladen; hier nur als Hook
    },
  },
}
</script>
